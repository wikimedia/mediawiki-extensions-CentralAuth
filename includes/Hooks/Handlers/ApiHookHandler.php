<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use ApiBase;
use ApiMain;
use Config;
use MediaWiki\Api\Hook\APIGetAllowedParamsHook;
use Wikimedia\ParamValidator\ParamValidator;

class ApiHookHandler implements
	APIGetAllowedParamsHook
{
	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
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
		if ( !$this->config->get( 'CentralAuthCookies' ) ) {
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
