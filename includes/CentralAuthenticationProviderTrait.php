<?php
namespace MediaWiki\Extension\CentralAuth;

use Config;
use MediaWiki\Auth\PrimaryAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Assert\Assert;

/**
 * This is a trait with various utility methods that are used by
 * the CentralAuth authentication providers. It performs actions such as:
 *   - Determine if we're in SUL3 mode
 *   - Check if we're on a shared domain (the central login domain)
 *   - Getting the central login URL
 *   - Implements the testUserExistsInternal which is used by the provider's
 *     testUserExists() override.
 */
trait CentralAuthenticationProviderTrait {
	/**
	 * Simple helper for getting the SharedDomainUtils service.
	 *
	 * @return SharedDomainUtils
	 */
	private function getSharedDomainUtils(): SharedDomainUtils {
		return MediaWikiServices::getInstance()->getService(
			'CentralAuth.SharedDomainUtils'
		);
	}

	/**
	 * Detects if we're in SUL3 mode. Returns true if that is the case
	 * and false otherwise.
	 *
	 * @param Config $config
	 * @param WebRequest $request
	 *
	 * @return bool
	 */
	private function isSul3Enabled( Config $config, WebRequest $request ): bool {
		return $config->get( 'CentralAuthEnableSul3' ) && $request->getCheck( 'usesul3' );
	}

	/**
	 * Is this the login wiki? If yes, return true,
	 * otherwise return false
	 *
	 * @return bool
	 */
	private function isSharedDomain(): bool {
		return $this->getSharedDomainUtils()->isSharedDomain();
	}

	/**
	 * Get the login URL of the login wiki.
	 *
	 * @return string
	 */
	private function getCentralLoginUrl(): string {
		global $wgCentralAuthSsoUrlPrefix;
		$titleFactory = MediaWikiServices::getInstance()->getTitleFactory();

		$localUrl = $titleFactory->newFromText( 'Special:UserLogin' )->getLocalURL();
		$url = $wgCentralAuthSsoUrlPrefix . $localUrl;

		return wfAppendQuery( $url, [
			// At this point, we should just be leaving the local
			// wiki before hitting the loginwiki.
			'wikiid' => WikiMap::getCurrentWikiId(),
			// TODO: Fix T369467
			'returnto' => 'Main_Page',
			'usesul3' => '1'
		] );
	}

	/**
	 * Assert that the SUL3 mode is set.
	 *
	 * @param Config $config
	 * @param WebRequest $request
	 *
	 * @return void
	 */
	private function assertSul3Enabled( Config $config, WebRequest $request ) {
		Assert::precondition(
			$this->isSul3Enabled( $config, $request ),
			'SUL3 is not enabled. Set $wgCentralAuthEnableSul3 to boolean true.'
		);
	}

	/**
	 * Assert that we're on a shared domain (central login wiki).
	 * @return void
	 */
	private function assertIsSharedDomain() {
		Assert::precondition(
			$this->isSharedDomain(),
			'This action is not allowed because the domain is not a shared domain.'
		);
	}

	/**
	 * Assert that we're not on a shared domain (central login wiki).
	 *
	 * @return void
	 */
	private function assertIsNotSharedDomain() {
		Assert::precondition(
			!( $this->isSharedDomain() ),
			'This action is not allowed because the domain is not a shared domain.'
		);
	}

	/**
	 * @see PrimaryAuthenticationProvider::testUserExists()
	 *
	 * @param string $username
	 * @param UserNameUtils $userNameUtils
	 *
	 * @return bool
	 */
	private function testUserExistsInternal( $username, UserNameUtils $userNameUtils ): bool {
		$username = $userNameUtils->getCanonical( $username, UserNameUtils::RIGOR_USABLE );
		if ( $username === false ) {
			return false;
		}

		$centralUser = CentralAuthUser::getInstanceByName( $username );
		return $centralUser && $centralUser->exists();
	}
}
