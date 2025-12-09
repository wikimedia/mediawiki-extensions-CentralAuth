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
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use Wikimedia\ParamValidator\ParamValidator;

class ApiHookHandler implements APIGetAllowedParamsHook {

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
			];
		}
		return true;
	}
}
