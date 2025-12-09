<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 * @ingroup Auth
 */

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Auth\AuthenticationRequest;

/**
 * Holds a token which can be exchanged for information describing the
 * outcome of central authentication. Passed via the return URL when
 * redirecting back from the login domain to the local wiki.
 *
 * @see CentralAuthRedirectingPrimaryAuthenticationProvider
 */
class CentralAuthReturnRequest extends AuthenticationRequest {

	/**
	 * Token store key used to pass information from RedirectingLoginHookHandler to
	 * CentralAuthRedirectingPrimaryAuthenticationProvider.
	 */
	public string $centralauthLoginToken;

	/**
	 * @inheritDoc
	 */
	public function getFieldInfo() {
		return [
			'centralauthLoginToken' => [
				'type' => 'string',
				'label' => wfMessage( 'centralauth-return-username-token' ),
				'help' => wfMessage( 'centralauth-return-username-token-help' ),
			],
		];
	}
}
