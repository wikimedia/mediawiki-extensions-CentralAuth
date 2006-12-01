<?php

global $wgCentralAuthMessages;

$wgCentralAuthMessages = array();
$wgCentralAuthMessages['en'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Login unification status',
	'centralauth-merge-notlogged' =>
		'Please <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} log in]' .
		'</span> to check if your accounts have been fully merged.',
	
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
		":''[[meta:Help:Unified login|Read more about '''unified login''']]...''",
	
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

$wgCentralAuthMessages['sk'] = array(
       // When not logged in...
       'mergeaccount' =>
               'Stav zjednotenia prihlasovacích účtov',
       'centralauth-merge-notlogged' =>
               'Prosím, <span class="plainlinks">' .
               '[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} prihláste sa]' .
               '</span>, aby ste mohli skontrolovať, či sú vaše účty celkom zjednotené.',
       // Big text on completion
       'centralauth-complete' =>
               'Zjednotenie prihlasovacích účtov dokončené!',
       'centralauth-incomplete' =>
               'Zjednotenie prihlasovacích účtov nedokončené!',

       // Wheeee
       'centralauth-complete-text' =>
               'Teraz sa môžete prihlásiť na ľubovoľnú wiki nadácie Wikimedia bez toho, aby ste ' .
               'si museli vytvárať nový účet; rovnaké užívateľské meno a heslo bude fungovať na ' .
               'projektoch Wikipedia, Wiktionary, Wikibooks a ďalších sesterských projektoch ' .
               'vo všetkých jazykoch. ',
       'centralauth-incomplete-text' =>
               'Potom, ako budú vaše účty zjednotené sa budete môcť prihlásiť ' .
               'na ľubovoľnú wiki nadácie Wikimedia bez toho, aby ste si museli vytvárat ďalší účet; ' .
               'rovnaké užívateľské meno a heslo bude fungovať na ' .
               'projektoch Wikipedia, Wiktionary, Wikibooks a ďalších sesterských projektoch ' .
               'vo všetkých jazykoch. ',
       'centralauth-not-owner-text' =>
               'Užívateľské meno "$1" bolo automaticky priradené vlastníkovi ' .
               "účtu na projekte $2.\n" .
               "\n" .
               "Ak ste to vy, môžete dokončiť proces zjednotenia účtov " .
               "jednoducho napísaním hesla pre uvedený účet sem:",

       // Appended to various messages above
       'centralauth-readmore-text' =>
               ":''[[meta:Help:Unified login|Prečítajte si viac o '''zjednotení prihlasovacích účtov''']]...''",

       // For lists of wikis/accounts:
       'centralauth-list-merged' =>
               'Účty z názvom "$1" na nasledujúcich projektoch ' .
               'boli automaticaticky zjednotené:',
       'centralauth-list-unmerged' =>
               'Nebolo možné automaticky potvrdiť, že účet "$1" ' .
               'na nasledujúcich projektoch patrí vám; ' .
               'pravdepodobne má odlišné heslo ako váš ' .
               'primárny účet:',
       'centralauth-foreign-link' =>
               'Užívateľ $1 na $2',

       // When not complete, offer to finish...
       'centralauth-finish-title' =>
               'Dokončiť zjednotenie',
       'centralauth-finish-text' =>
               'Ak tieto účty naozaj patria vám, môžete skončiť ' .
               'proces zjednotenia jednoducho napísaním hesiel ' .
               'dotyčných účtov:',
       'centralauth-finish-password' =>
               'Heslo:',
       'centralauth-finish-login' =>
               'Prihlasovacie meno',
       'centralauth-finish-send-confirmation' =>
               'Zaslať heslo emailom',
       'centralauth-finish-problems' =>
               "Máte problém alebo nie ste vlastníkom týchto účtov? " .
               "[[meta:Help:Unified login problems|Ako hľadat pomoc]]...",

       'centralauth-merge-attempt' =>
               "'''Kontrolujem poskytnuté heslá voči zostávajúcim zatiaľ nezjednoteným účtom...'''",

);
?>