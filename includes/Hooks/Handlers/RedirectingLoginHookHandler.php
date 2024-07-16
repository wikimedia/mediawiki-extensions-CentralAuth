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

use ErrorPageError;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthenticationProviderTrait;
use MediaWiki\Hook\PostLoginRedirectHook;
use RuntimeException;

class RedirectingLoginHookHandler implements
	PostLoginRedirectHook,
	AuthPreserveQueryParamsHook
{
	use CentralAuthenticationProviderTrait;

	/** @inheritDoc */
	public function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ) {
		if ( !$this->isSharedDomain() ) {
			// We're not on the central login wiki, so do nothing.
			return;
		}

		$context = RequestContext::getMain();
		$request = $context->getRequest();
		$url = $request->getRawVal( 'returnurl' );

		if ( !$url ) {
			throw new ErrorPageError(
				'centralauth-error-badtoken', 'centralauth-error-badtoken'
			);
		}

		if ( !$context->getUser()->isRegistered() ) {
			throw new \LogicException( 'This account is not a registered user' );
		}

		if ( $this->isSul3Enabled( $context->getConfig(), $request ) ) {
			$url = wfAppendQuery( $url, [ 'token' => $context->getUser()->getName() ] );

			// We have a return URL from the login wiki, we're fine.
			$context->getOutput()->redirect( $url );
			$type = 'success';
		} else {
			// This should not happen as the authentication workflow should know where
			// to go back to in order to complete the login process.
			throw new RuntimeException( 'Invalid return URL, login aborted' );
		}

		return true;
	}

	public function onAuthPreserveQueryParams( array &$params, array $options ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();

		$params += [
			'returnurl' => $request->getRawVal( 'returnurl' ),
			'wikiid' => $request->getRawVal( 'wikiid' ),
			'usesul3' => $request->getRawVal( 'usesul3' ),
		];
	}
}
