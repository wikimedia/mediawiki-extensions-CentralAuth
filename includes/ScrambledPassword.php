<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Extension\CentralAuth\Maintenance\ScramblePassword;
use MediaWiki\Password\InvalidPassword;

/**
 * Represents a password which has been disabled in a reversible way, and the user must do a
 * password reset to recover their account.
 * Behaves identically to InvalidPassword except that the user cannot change their password back
 * to the unscrambled version (which is assumed to have been compromised), and the login error
 * message is different.
 *
 * The password hash structure is ':scrambled:<reason>:<original hash>'.
 *
 * @see ScramblePassword
 */
class ScrambledPassword extends InvalidPassword {
}
