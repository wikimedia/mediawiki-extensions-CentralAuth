<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\Hook\APIGetAllowedParamsHook;
use MediaWiki\Api\Hook\APIGetDescriptionMessagesHook;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Api\ApiCentralAuthToken;
use MediaWiki\Extension\CentralAuth\CentralAuthTokenManager;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class ApiHookHandler implements APIGetAllowedParamsHook, APIGetDescriptionMessagesHook {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Inject the "centralauthtoken" parameter into the API
	 * @param ApiBase $module API module
	 * @param array &$params Array of parameter specifications
	 * @param int $flags
	 * @return bool
	 */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		if ( !$this->config->get( CAMainConfigNames::CentralAuthCookies ) ) {
			return true;
		}

		if ( $module instanceof ApiMain ) {
			$params['centralauthtoken'] = [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_SENSITIVE => true,
				ApiBase::PARAM_HELP_MSG => MessageValue::new( 'apihelp-main-param-centralauthtoken' )
					->numParams( CentralAuthTokenManager::EXPIRY ),
			];
			if ( $this->config->get( MainConfigNames::UseSessionCookieJwt ) ) {
				$params['centralauthtoken'][ApiBase::PARAM_HELP_MSG_APPEND] = [
					'apihelp-main-param-centralauthtoken-jwt',
				];
			}
		}
		return true;
	}

	/**
	 * Inject additional description for the "centralauthtoken" API
	 * @param ApiBase $module
	 * @param Message[] &$msg
	 * @return bool
	 */
	public function onAPIGetDescriptionMessages( $module, &$msg ) {
		if (
			$module instanceof ApiCentralAuthToken
			&& $this->config->get( CAMainConfigNames::CentralAuthCookies )
			&& $this->config->get( MainConfigNames::UseSessionCookieJwt )
		) {
			$msg[] = $module->msg( 'apihelp-centralauthtoken-summary-jwt' );
		}
		return true;
	}
}
