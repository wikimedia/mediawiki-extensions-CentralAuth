<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Auth\ButtonAuthenticationRequest;

/**
 * Authentication request indicating that login should happen by redirecting
 * to a central login domain.
 */
class CentralAuthRedirectingAuthenticationRequest extends ButtonAuthenticationRequest {

	public function __construct() {
		parent::__construct(
			CentralAuthRedirectingPrimaryAuthenticationProvider::NON_LOGIN_WIKI_BUTTONREQUEST_NAME,
			wfMessage( 'centralauth-non-login-wiki-buttonlabel' ),
			wfMessage( 'centralauth-non-login-wiki-buttonlabel-help' ),
			true
		);
	}
}
