<?php

namespace MediaWiki\Extension\CentralAuth;

use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\Api\ApiMessage;
use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Context\RequestContext;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikimedia\IPUtils;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * PreAuthentication provider that checks if an IP address is known to ipoid
 * @see https://wikitech.wikimedia.org/wiki/Service/IPoid
 */
class CentralAuthIpReputationPreAuthenticationProvider extends AbstractPreAuthenticationProvider {

	private HttpRequestFactory $httpRequestFactory;
	private StatsdDataFactoryInterface $statsDataFactory;
	private WANObjectCache $cache;
	private FormatterFactory $formatterFactory;
	private PermissionManager $permissionManager;

	public function __construct(
		FormatterFactory $formatterFactory,
		HttpRequestFactory $httpRequestFactory,
		WANObjectCache $cache,
		StatsdDataFactoryInterface $statsDataFactory,
		PermissionManager $permissionManager
	) {
		$this->formatterFactory = $formatterFactory;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->cache = $cache;
		$this->statsDataFactory = $statsDataFactory;
		$this->permissionManager = $permissionManager;
	}

	/** @inheritDoc */
	public function testForAccountCreation( $user, $creator, array $reqs ) {
		// If feature flag is off, don't do any checks, let the user proceed.
		if ( !$this->config->get( 'CentralAuthIpoidCheckAtAccountCreation' ) ) {
			return StatusValue::newGood();
		}

		if (
			$this->permissionManager->userHasAnyRight(
				$creator,
				'ipblock-exempt',
			)
		) {
			return StatusValue::newGood();
		}

		$this->logger = LoggerFactory::getInstance( 'IpReputation' );

		$ip = $this->manager->getRequest()->getIP();

		$data = $this->getIpoidDataFor( $user, $ip );
		if ( !$data ) {
			// ipoid doesn't know anything about this IP, so let the authentication request proceed.
			return StatusValue::newGood();
		}

		$shouldLogOnly = $this->config->get( 'CentralAuthIpoidCheckAtAccountCreationLogOnly' );

		if ( !isset( $data['risks'] ) || !$data['risks'] ) {
			// 'risks' should always be set and populated, but if not set to 'UNKNOWN'.
			$data['risks'] = [ 'UNKNOWN' ];
		}

		if ( !isset( $data['tunnels'] ) ) {
			// 'tunnels' should always be set, but if not set to empty list.
			$data['tunnels'] = [];
		}

		$risksToBlock = $this->config->get( 'CentralAuthIpoidDenyAccountCreationRiskTypes' );
		$tunnelTypesToBlock = $this->config->get( 'CentralAuthIpoidDenyAccountCreationTunnelTypes' );

		$risks = $data['risks'];
		sort( $risks );

		$tunnels = $data['tunnels'];
		sort( $tunnels );

		// Allow for the possibility to exclude VPN users from having account
		// creation denied, if the only risk type known for the IP is that it's a VPN,
		// and if config is set up to allow VPN tunnel types.
		// That would be done with:
		// $wgCentralAuthIpoidDenyAccountCreationRiskTypes = [ 'TUNNEL', 'CALLBACK_PROXY', ... ];
		// $wgCentralAuthIpoidDenyAccountCreationTunnelTypes = [ 'PROXY', 'UNKNOWN' ];
		// If the only risk type is a TUNNEL...
		if (
			$risks === [ 'TUNNEL' ]
			// and there are tunnels listed for the IP
			&& count( $tunnels )
			// and we have configured TUNNEL as a risk type to block
			&& in_array( 'TUNNEL', $risksToBlock )
			// and the configured tunnel types to block are *not* present in the data
			&& !array_intersect( $tunnelTypesToBlock, $tunnels )
		) {
			$this->logger->debug(
				'Allowing account creation for user {user} as IP {ip} is known to iPoid '
				. 'with only non-blocked tunnels ({tunnelTypes})',
				[
					'user' => $user->getName(),
					'ip' => $ip,
					'tunnelTypes' => implode( ' ', $tunnels ),
					'ipoidData' => json_encode( $data ),
				]
			);
			return StatusValue::newGood();
		}

		// Otherwise, check for other risks.
		$blockedRisks = array_intersect( $risksToBlock, $risks );
		if ( $blockedRisks ) {
			$this->logger->notice(
				'Blocking account creation for user {user} as IP {ip} is known to iPoid '
				. 'with risks {riskTypes} (blocked due to {blockedRiskTypes})',
				[
					'user' => $user->getName(),
					'ip' => $ip,
					'riskTypes' => implode( ' ', $risks ),
					'blockedRiskTypes' => implode( ' ', $blockedRisks ),
					'ipoidData' => json_encode( $data ),
				]
			);

			$statsdKey = $shouldLogOnly ? 'DenyCreationLogOnly' : 'DenyCreation';
			$this->statsDataFactory->increment( "CentralAuth.IpReputation.$statsdKey." . implode( '_', $risks ) );

			if ( $shouldLogOnly ) {
				return StatusValue::newGood();
			}

			return StatusValue::newFatal( ApiMessage::create( 'centralauth-blocked-ip-reputation', 'autoblocked' ) );
		}

		return StatusValue::newGood();
	}

	/**
	 * @param UserIdentity $user
	 * @param string $ip
	 *
	 * @return array|null iPoid data for the specific address, or null if there is no data
	 */
	private function getIpoidDataFor( UserIdentity $user, string $ip ): ?array {
		$fname = __METHOD__;
		$data = $this->cache->getWithSetCallback(
			$this->cache->makeGlobalKey( 'centralauth-ipoid', $ip ),
			// ipoid data is refreshed every 24 hours and roughly 10% of its IPs drop out
			// of the database each 24-hour cycle. A one hour TTL seems reasonable to allow
			// no longer problematic IPs to get evicted from the cache relatively quickly,
			// and also means that IPs for e.g. residential proxies are updated in our cache
			// relatively quickly.
			$this->cache::TTL_HOUR,
			function () use ( $ip, $user, $fname ) {
				// If ipoid URL isn't configured, don't do any checks, let the user proceed.
				$baseUrl = $this->config->get( 'CentralAuthIpoidUrl' );
				if ( !$baseUrl ) {
					$this->logger->warning(
						'Configured to check IP reputation on signup, but no iPoid URL configured'
					);
					// Don't cache this.
					return false;
				}

				$timeout = $this->config->get( 'CentralAuthIpoidRequestTimeoutSeconds' );
				// Convert IPv6 to lowercase, to match ipoid storage format.
				$url = $baseUrl . '/feed/v1/ip/' . IPUtils::prettifyIP( $ip );
				$request = $this->httpRequestFactory->create( $url, [
					'method' => 'GET',
					'timeout' => $timeout,
					'connectTimeout' => 1,
				], $fname );
				$response = $request->execute();
				if ( !$response->isOK() ) {
					// Probably a 404, which means ipoid doesn't know about the IP.
					// If not a 404, log it, so we can figure out what happened.
					if ( $request->getStatus() !== 404 ) {
						$statusFormatter = $this->formatterFactory->getStatusFormatter( RequestContext::getMain() );
						[ $errorText, $context ] = $statusFormatter->getPsr3MessageAndContext( $response );
						$this->logger->error( $errorText, $context );
					}

					return null;
				}

				$data = json_decode( $request->getContent(), true );

				if ( !$data ) {
					// Malformed data.
					$this->logger->error(
						'Got invalid JSON data while checking user {user} with IP {ip}',
						[
							'ip' => $ip,
							'user' => $user->getName(),
							'response' => $request->getContent()
						]
					);
					return null;
				}

				// ipoid will return the IP in lower case format, and we are searching for the
				// indexed value in the returned array.
				if ( !isset( $data[IPUtils::prettifyIP( $ip )] ) ) {
					// IP should always be set in the data array, but just to be safe.
					$this->logger->error(
						'Got JSON data with no IP {ip} present while checking user {user}',
						[
							'ip' => $ip,
							'user' => $user->getName(),
							'response' => $request->getContent()
						]
					);
					return null;
				}

				// We have a match and valid data structure;
				// return the values for this IP for storage in the cache.
				return $data[$ip];
			}
		);

		// Unlike null, false tells cache not to cache something. Normalize both to null before returning.
		if ( $data === false ) {
			return null;
		}

		return $data;
	}
}
