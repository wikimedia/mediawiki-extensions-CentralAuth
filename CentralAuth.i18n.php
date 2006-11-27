<?php

global $wgCentralAuthMessages;

$wgCentralAuthMessages = array();
$wgCentralAuthMessages['en'] = array(
	// Big text on completion
	'centralauth-complete' =>
		'Login unification complete!',
	'centralauth-incomplete' =>
		'Login unification not complete!',
	
	// Wheeee
	'centralauth-complete-text' =>
		'You can now log in to any Wikimedia wiki site without creating ' .
		'a new account; the same username and password will work everywhere.',
	'centralauth-incomplete-text' =>
		'Once your login is unified, you will be able to log in ' .
		'to any Wikimedia wiki site without creating a new account; ' .
		'the same username and password will work everywhere.',
	'centralauth-not-owner-text' =>
		'The username "$1" was automatically assigned to the owner ' .
		"of the account on $2.\n" .
		"\n" .
		"If this is you, you can finish the login unification process " .
		"simply by typing the master password for that account here:",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		'(Read [[meta:Help:Unified login|more about unified login]]...)',
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'The accounts named "$1" on the following sites ' .
		'have been automatically merged:',
	'centralauth-list-unmerged' =>
		'Some accounts could not be automatically confirmed ' .
		'as belonging to you; most likely they have a different ' .
		'password from your primary account.',
	'centralauth-foreign-link' =>
		'User $1 on $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-text' =>
		'If these accounts do belong to you, you can finish ' .
		'the login unification process simply by typing the passwords ' .
		'for the other accounts here:',
	'centralauth-finish-password' =>
		'Password:',
	'centralauth-finish-login' =>
		'Login',
	'centralauth-finish-forgot' =>
		'Forgot the password?',
	'centralauth-finish-send-confirmation' =>
		'Send a confirmation mail',
	'centralauth-finish-notmine' =>
		'Not your accounts? [[How to deal with this bla bla]]',

);




?>