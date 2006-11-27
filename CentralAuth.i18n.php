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
		'a new account; the same username and password will work on ' .
		'Wikipedia, Wiktionary, Wikibooks, and their sister projects ' .
		'in all languages.',
	'centralauth-incomplete-text' =>
		'Once your login is unified, you will be able to log in ' .
		'to any Wikimedia wiki site without creating a new account; ' .
		'the same username and password will work on ' .
		'Wikipedia, Wiktionary, Wikibooks, and their sister projects ' .
		'in all languages.',
	'centralauth-not-owner-text' =>
		'The username "$1" was automatically assigned to the owner ' .
		"of the account on $2.\n" .
		"\n" .
		"If this is you, you can finish the login unification process " .
		"simply by typing the master password for that account here:",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Read more about '''unified login''']]...''\n" .
		"----\n",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'The accounts named "$1" on the following sites ' .
		'have been automatically merged:',
	'centralauth-list-unmerged' =>
		'The account "$1" could not be automatically confirmed ' .
		'as belonging to you on the following sites; ' .
		'most likely they have a different password from your ' .
		'primary account:',
	'centralauth-foreign-link' =>
		'User $1 on $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Finish merge',
	'centralauth-finish-text' =>
		'If these accounts do belong to you, you can finish ' .
		'the login unification process simply by typing the passwords ' .
		'for the other accounts here:',
	'centralauth-finish-password' =>
		'Password:',
	'centralauth-finish-login' =>
		'Login',
	'centralauth-finish-send-confirmation' =>
		'E-mail password',
	'centralauth-finish-problems' =>
		"Having trouble, or don't own these other accounts? " .
		"[[meta:Help:Unified login problems|How to find help]]...",
	
	'centralauth-merge-attempt' =>
		"'''Checking provided password against remaining unmerged accounts...'''",

);




?>