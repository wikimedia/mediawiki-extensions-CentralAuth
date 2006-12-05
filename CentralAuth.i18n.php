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
$wgCentralAuthMessages['it'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Processo di unificazione delle utenze - status',
	'centralauth-merge-notlogged' =>
		'Si prega di <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} effettuare il login]' .
		'</span> per verificare se il processo di unificazione delle proprie utenze è completo.',
	
	// Big text on completion
	'centralauth-complete' =>
		'Il processo di unificazione delle utenze è stato completato.',
	'centralauth-incomplete' =>
		'Il processo di unificazione delle utenze non è ancora stato completato.',
	
	// Wheeee
	'centralauth-complete-text' =>
		'È ora possibile accedere a tutti i siti Wikimedia senza dover ' .
		'creare nuovi account; questo nome utente e questa password sono ' .
		'attivi su tutte le edizioni di Wikipedia, Wiktionary, Wikibooks, ' .
		'ecc. nelle varie lingue e su tutti i progetti correlati.',
	'centralauth-incomplete-text' =>
		'Dopo aver unificato le proprie utenze, sarà possibile accedere ' .
		'a tutti i siti Wikimedia senza dover creare nuovi account; il ' .
		'nome utente e la password saranno attivi su tutte le edizioni di ' .
		'Wikipedia, Wiktionary, Wikibooks, ecc. nelle varie lingue e su ' .
		'tutti i progetti correlati.',
	'centralauth-not-owner-text' =>
		'Il nome utente "$1" è stato assegnato automaticamente al ' .
		"titolare dell'account con lo stesso nome sul progetto $2.\n" .
		"\n" .
		"Se si è il titolare dell'utenza, per terminare il processo di unificazione " .
		"è sufficiente inserire la password principale di quell'account qui di seguito:",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Per saperne di più sul '''login unico''']]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'Gli account con nome utente "$1" sui progetti elencati ' .
		'di seguito sono stati unificati automaticamente:',
	'centralauth-list-unmerged' =>
		'Non è stato possibile verificare automaticamente che gli ' .
		'account con nome utente "$1" sui progetti elencati di seguito ' .
		'appartengano allo stesso titolare; è probabile che sia stata ' .
		'usata una password diversa da quella dell\'account principale:',
	'centralauth-foreign-link' =>
		'Utente $1 su $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Completa il processo di unificazione',
	'centralauth-finish-text' =>
		'Se si è il titolare di queste utenze, per completare il processo ' .
		'di unificazione degli account è sufficiente inserire le password ' .
		'relative alle utenze stesse qui di seguito:',
	'centralauth-finish-password' =>
		'Password:',
	'centralauth-finish-login' =>
		'Esegui il login',
	'centralauth-finish-send-confirmation' =>
		'Invia password via e-mail',
	'centralauth-finish-problems' =>
		"Se non si è il titolare di queste utenze, o se si incontrano altri problemi, " .
		"si invita a consultare la [[meta:Help:Unified login problems|pagina di aiuto]]...",
	
	'centralauth-merge-attempt' =>
		"'''Verifica della password inserita sulle utenze non ancora unificate...'''",

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

$wgCentralAuthMessages['pt'] = array(
	// Big text on completion
	'centralauth-complete' =>
		'Unificação de logins completa!',
	'centralauth-incomplete' =>
		'Unificação de logins incompleta!',
	
	// Wheeee
	'centralauth-complete-text' =>
		'Agora você poderá se logar em quaisquer das wikis da Wikimedia sem ter de criar ' .
		'uma nova conta; o mesmo nome de utilizador e senha funcionarão' .
		'na Wikipedia, no Wikcionário, no Wikibooks e demais projetos, ' .
		'em todos os idiomas.',
	'centralauth-incomplete-text' =>
		'Uma vez estando com seu login unificado, você poderá se logar ' .
		'em qualquer wiki da Wikimedia sem ter de criar novo cadastro; ' .
		'o mesmo nome de utilizador e senha funcionarão' .
		'na Wikipedia, no Wikcionário, no Wikibooks e demais projetos, ' .
		'em todos os idiomas.',
	'centralauth-not-owner-text' =>
		'O nome de utilizador "$1" foi automaticamente relacionado ao proprietário ' .
		"da conta em $2.\n" .
		"\n" .
		"Se este for você, você poderá concluir o procedimento de unificação de login " .
		"simplesmente digitando a senha principal de tal conta aqui:",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Leia mais sobre o '''login unificado''']]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'A conta nomeada como "$1" nos seguintes sítios ' .
		'foram automaticamente fundidos:',
	'centralauth-list-unmerged' =>
		'A conta "$1" não pôde ser automaticamente confirmada ' .
		'como sendo tua nos seguintes sítios; ' .
		'provavelmente elas tenham uma senha diferente de sua ' .
		'conta principal:',
	'centralauth-foreign-link' =>
		'Utilizador $1 em $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Completar fusão',
	'centralauth-finish-text' =>
		'Se estas contas lhe pertencerem, você poderá concluir ' .
		'a unificação de logins simplesmente digitando as senhas ' .
		'das demais contas aqui:',
	'centralauth-finish-password' =>
		'Senha:',
	'centralauth-finish-login' =>
		'Login',
	'centralauth-finish-send-confirmation' =>
		'Enviar senha por e-mail',
	'centralauth-finish-problems' =>
		"Está com problemas ou estas outras contas não são suas? " .
		"[[meta:Help:Unified login problems|Como procurar por ajuda]]...",
	
	'centralauth-merge-attempt' =>
		"'''Verificando a senha fornecida para encontrar as demais contas ainda não fundidas...'''",
 
);

?>