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
	
	// Administrator's console
	'centralauth' => 'Unified login administration',
	'centralauth-admin-manage' =>
		'Manage user data',
	'centralauth-admin-username' =>
		'User name:',
	'centralauth-admin-lookup' =>
		'View or edit user data',
	'centralauth-admin-permission' =>
		"Only stewards may merge other people's accounts for them.",
	'centralauth-admin-unmerge' =>
		'Unmerge selected',
	'centralauth-admin-merge' =>
		'Merge selected',
	
	// Info panel in preferences
	'centralauth-globalid' =>
		'Unified account ID:',
);

$wgCentralAuthMessages['de'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Status der Benutzerkonten-Zusammenführung',
	'centralauth-merge-notlogged' =>
		'Bitte <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} melden Sie sich an, ]' .
		'</span> um zu prüfen, ob Ihre Benutzerkonten vollständig zusammengeführt wurden.',
	
	// Big text on completion
	'centralauth-complete' =>
		'Die Zusammenführung der Benutzerkonten ist vollständig.',
	'centralauth-incomplete' =>
		'Die Zusammenführung der Benutzerkonten ist unvollständig!',
	
	// Wheeee
	'centralauth-complete-text' =>
		'Sie können sich nun auf jeder Wikimedia-Webseite anmelden ' .
		'ohne ein neues Benutzerkonto anzulegen; ' .
		'derselbe Benutzername und dasselbe Passwort ist für Wikipedia, ' .
		'Wiktionary, Wikibooks und alle Schwesterprojekte '.
		'in allen Sprachen gültig.',

	'centralauth-incomplete-text' =>
		'Sobald Ihre Benutzerkonten zusammengeführt sind, ' .
		'können Sie sich auf jeder Wikimedia-Webseite anmelden ohne ein ' .
		'neues Benutzerkonto anzulegen; derselbe Benutzernamen ' .
		'und dasselbe Passwort ist für Wikipedia, Wiktionary, ' .
		'Wikibooks und alle Schwesterprojekte in allen Sprachen gültig.',

	'centralauth-not-owner-text' =>
		'Der Benutzername „$1“ wurde automatisch dem Eigentümer ' .
		"des Benutzerkontos auf $2 zugewiesen.\n" .
		"\n" .
		"Wenn dies Ihre Benutzername ist, können Sie die Zusammenführung " .
		"der Benutzerkonten durch Eingabe des Haupt-Passwortes".
		"für dieses Benutzerkonto vollenden:",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Informationen über die '''Zusammenführung der Benutzerkonten''']]…''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'Die Benutzerkonten mit dem Namen „$1“ auf den folgenden ' .
		'Projekten wurden automatisch zusammengeführt:',
 
	'centralauth-list-unmerged' =>
		'Das Benutzerkonto „$1“ konnte für die folgenden Projekte ' .
		'nicht automatisch als zu Ihnen gehörend bestätigt werden; ' .
		'vermutlich hat es ein anderes Passwort ' .
		'als Ihr primäres Benutzerkonto:',

	'centralauth-foreign-link' =>
		'Benutzer $1 auf $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Zusammenführung vollenden',
	'centralauth-finish-text' =>
		'Wenn diese Benutzerkonten Ihnen gehören, können Sie hier ' .
		'den Prozess der Benutzerkonten-Zusammenführung durch die ' .
		'Eingabe des Passwortes für die anderen Benutzerkonto vollenden:',

	'centralauth-finish-password' =>
		'Passwort:',
	'centralauth-finish-login' =>
		'Anmeldung',
	'centralauth-finish-send-confirmation' =>
		'Passwort per E-Mail zusenden',
	'centralauth-finish-problems' =>
		"Haben Sie Probleme oder gehören Ihnen diese anderen " .
		"Benutzerkonten nicht? [[meta:Help:Unified login problems|Hier finden Sie Hilfe]]…",
	
	'centralauth-merge-attempt' =>
		"'''Prüfe das eingegebene Passwort mit den restlichen Benutzerkonten…'''",
	
	// Administrator's console
	'centralauth' => 'Verwaltung der Benutzerkonten-Zusammenführung',
	'centralauth-admin-manage' =>
		'Benutzerdaten verwalten',
	'centralauth-admin-username' =>
		'Benutzername:',
	'centralauth-admin-lookup' =>
		'Benutzerdaten ansehen oder bearbeiten',
	'centralauth-admin-permission' =>
		"Die Zusammenführung von Benutzerkonten für andere Benutzer kann nur durch Stewards erfolgen.",
	'centralauth-admin-unmerge' =>
		'Ausgewählte Benutzerkonten trennen',
	'centralauth-admin-merge' =>
		'Ausgewählte Benutzerkonten zusammenführen',

	// Info panel in preferences
	'centralauth-globalid' =>
		'ID der zusammengeführten Benutzerkonten:',

);

// Based on r18928
// Ashar Voultoiz <hashar@altern.org>
$wgCentralAuthMessages['fr'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Statut d\'unification du compte utilisateur',
	'centralauth-merge-notlogged' =>
		'Merci de bien vouloir <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} vous connecter]' .
		'</span> pour vérifier que vos comptes ont bien été réunis.',
	
	// Big text on completion
	'centralauth-complete' =>
		'Unification du compte terminée!',
	'centralauth-incomplete' =>
		'Unification du compte non terminée!',
	
	// Wheeee
	'centralauth-complete-text' =>
		'Vous pouvez maintenant vous connecter sur n\'importe quel site ' .
		'Wikimedia sans avoir à créer un nouveau compte; le même nom '.
		'd\'utilisateur et mot de passe fonctionnent sur Wikipédia, '.
		'Wiktionary, Wikibooks et leurs projets soeurs, ceci pour tout '.
		'les langages.',
	'centralauth-incomplete-text' =>
		'Une fois votre compte unifier, vous pourrez vous connecter sur n\'importe quel site ' .
		'Wikimedia sans avoir à créer un nouveau compte; le même nom '.
		'd\'utilisateur et mot de passe fonctionneront sur Wikipédia, '.
		'Wiktionary, Wikibooks et leurs projets soeurs, ceci pour tout '.
		'les langages.',
	'centralauth-not-owner-text' =>
		'Le compte utilisateur "$1" a été automatiquement assigné au '.
		"propriétaire du compte sur $2.\n" .
		"\n" .
		'Si c\'est vous, vous pourrez terminer le process d\'unification de '.
		'compte en tapant le mot de passe maître pour ce compte sur :',
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Compte unifié|En savoir plus sur le '''compte unifié''']]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'Les comptes utilisateurs nommés "$1" ont été réunis pour les sites suivants :',
	'centralauth-list-unmerged' =>
		'Le compte utilisateur "$1 ne peut être confirmé automatiquement ' .
		'pour les sites qui suivent; ils ont probablement un mot de passe '.
		'différent de votre compte maître:',
	'centralauth-foreign-link' =>
		'Utilisateur $1 sur $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Compléter l\'unification',
	'centralauth-finish-text' =>
		'Si ces comptes vous appartiennent, vous pouvez terminer leur ' .
		'unification en tapant les mots de passe ci-dessous :',
	'centralauth-finish-password' =>
		'Mot de passe :',
	'centralauth-finish-login' =>
		'Compte utilisateur :',
	'centralauth-finish-send-confirmation' =>
		'E-mail password',
	'centralauth-finish-problems' =>
		'En cas de problème ou si vous ne possédez pas ces autres comptes, ' .
		'voyez la page [[meta:Help:Unified login problems|How to find help]]...',
	
	'centralauth-merge-attempt' =>
		"'''Vérification du mot de passe fournit pour les comptes non réunis...'''",
	
	// Administrator's console
	'centralauth' => 'Administration comptes unifiés',
	'centralauth-admin-manage' =>
		'Gérer les données utilisateur',
	'centralauth-admin-username' =>
		'Nom d\'utilisateur :',
	'centralauth-admin-lookup' =>
		'Voir ou éditer les données utilisateur',
	'centralauth-admin-permission' =>
		'Seul les stewards peuvent réunir les comptes d\'autres personnes à leur place.',
	'centralauth-admin-unmerge' =>
		'Séparer sélection',
	'centralauth-admin-merge' =>
		'Réunir sélection',
	
	// Info panel in preferences
	'centralauth-globalid' =>
		'ID compte unifié :',
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
               'vo všetkých jazykoch.',
       'centralauth-incomplete-text' =>
               'Potom, ako budú vaše účty zjednotené sa budete môcť prihlásiť ' .
               'na ľubovoľnú wiki nadácie Wikimedia bez toho, aby ste si museli vytvárat ďalší účet; ' .
               'rovnaké užívateľské meno a heslo bude fungovať na ' .
               'projektoch Wikipedia, Wiktionary, Wikibooks a ďalších sesterských projektoch ' .
               'vo všetkých jazykoch.',
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

$wgCentralAuthMessages['sr-ec'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Статус уједињења налога',
	'centralauth-merge-notlogged' =>
		'Молимо вас да се <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} пријавите]' .
		'</span> како бисте проверили да ли је ваш налог спојен успешно.',
	
	// Big text on completion
	'centralauth-complete' =>
		'Спајање налога завршено!',
	'centralauth-incomplete' =>
		'Спајање налога није завршено!',
	
	// Wheeee
	'centralauth-complete-text' =>
		'Сада се можете пријавити на било који Викимедијин вики сајт без прављења' .
		'новог налога; исто корисничко име и лозинка ће свугде радити ' .
		'Википедија, Викиречник, Викикњиге, и њихови остали братски пројекти ' .
		'на свим језицима.',
	'centralauth-incomplete-text' =>
		'Када једном спојите налог, можете се пријавити ' .
		'на било који Викимедијин вики сајт без прављења; ' .
		'the same username and password will work on ' .
		'Википедија, Викиречник, Викикњиге, и њихови остали братски пројекти ' .
		'на свим језицима.',
	'centralauth-not-owner-text' =>
		'Корисничко име "$1" је аутоматски додељено власнику ' .
		"налога на $2.\n" .
		"\n" .
		"Уколико сте ово ви, можете једноставно завршити процес спајања " .
		"уписујући лозинку за налог овде::",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Прочитајте више о '''спајању налога''']]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'Налог "$1" на следећим сајтовима ' .
		'је аутоматски спојен:',
	'centralauth-list-unmerged' =>
		'Налог "$1" се не може аутоматски потврдити ' .
		'да припада вама на следећим сајтовима; ' .
		'највероватније имају различите лозинке него ваш ' .
		'примаран налог:',
	'centralauth-foreign-link' =>
		'Корисник $1 на $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Заврши спајање',
	'centralauth-finish-text' =>
		'Уколико ови налози припадају вама, можете завршити ' .
		'процес спајања налога уписујући лозинку ' .
		'за остале налоге овде:',
	'centralauth-finish-password' =>
		'Лозинка:',
	'centralauth-finish-login' =>
		'Пријава',
	'centralauth-finish-send-confirmation' =>
		'Пошаљи лозинку на е-пошту',
	'centralauth-finish-problems' =>
		"Имате проблем, или ви нисте власник осталих налога? " .
		"[[meta:Help:Unified login problems|Помоћ]]...",
	
	'centralauth-merge-attempt' =>
		"'''Провера унете лозинке наспрам осталих налога који још нису спојени......'''",
	
	// Administrator's console
	'centralauth' => 'Администрација спајања налога',
	'centralauth-admin-manage' =>
		'Надгледање корисничких података',
	'centralauth-admin-username' =>
		'Корисничко име:',
	'centralauth-admin-lookup' =>
		'Преглед или измена корисничких података',
	'centralauth-admin-permission' =>
		"Само стјуарди могу да споје остале корисничке налоге за њих.",
	'centralauth-admin-unmerge' =>
		'Одвоји селектоване',
	'centralauth-admin-merge' =>
		'Споји селектоване',
	
	// Info panel in preferences
	'centralauth-globalid' =>
		'Јединствен ID налога:',
);

$wgCentralAuthMessages['sr-el'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Status ujedinjenja naloga',
	'centralauth-merge-notlogged' =>
		'Molimo vas da se <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} prijavite]' .
		'</span> kako biste proverili da li je vaš nalog spojen uspešno.',
	
	// Big text on completion
	'centralauth-complete' =>
		'Spajanje naloga završeno!',
	'centralauth-incomplete' =>
		'Spajanje naloga nije završeno!',
	
	// Wheeee
	'centralauth-complete-text' =>
		'Sada se možete prijaviti na bilo koji Vikimedijin viki sajt bez pravljenja' .
		'novog naloga; isto korisničko ime i lozinka će svugde raditi ' .
		'Vikipedija, Vikirečnik, Vikiknjige, i njihovi ostali bratski projekti ' .
		'na svim jezicima.',
	'centralauth-incomplete-text' =>
		'Kada jednom spojite nalog, možete se prijaviti ' .
		'na bilo koji Vikimedijin viki sajt bez pravljenja; ' .
		'the same username and password will work on ' .
		'Vikipedija, Vikirečnik, Vikiknjige, i njihovi ostali bratski projekti ' .
		'na svim jezicima.',
	'centralauth-not-owner-text' =>
		'Korisničko ime "$1" je automatski dodeljeno vlasniku ' .
		"naloga na $2.\n" .
		"\n" .
		"Ukoliko ste ovo vi, možete jednostavno završiti proces spajanja " .
		"upisujući lozinku za nalog ovde::",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Pročitajte više o '''spajanju naloga''']]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'Nalog "$1" na sledećim sajtovima ' .
		'je automatski spojen:',
	'centralauth-list-unmerged' =>
		'Nalog "$1" se ne može automatski potvrditi ' .
		'da pripada vama na sledećim sajtovima; ' .
		'najverovatnije imaju različite lozinke nego vaš ' .
		'primaran nalog:',
	'centralauth-foreign-link' =>
		'Korisnik $1 na $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Završi spajanje',
	'centralauth-finish-text' =>
		'Ukoliko ovi nalozi pripadaju vama, možete završiti ' .
		'proces spajanja naloga upisujući lozinku ' .
		'za ostale naloge ovde:',
	'centralauth-finish-password' =>
		'Lozinka:',
	'centralauth-finish-login' =>
		'Prijava',
	'centralauth-finish-send-confirmation' =>
		'Pošalji lozinku na e-poštu',
	'centralauth-finish-problems' =>
		"Imate problem, ili vi niste vlasnik ostalih naloga? " .
		"[[meta:Help:Unified login problems|Pomoć]]...",
	
	'centralauth-merge-attempt' =>
		"'''Provera unete lozinke naspram ostalih naloga koji još nisu spojeni......'''",
	
	// Administrator's console
	'centralauth' => 'Administracija spajanja naloga',
	'centralauth-admin-manage' =>
		'Nadgledanje korisničkih podataka',
	'centralauth-admin-username' =>
		'Korisničko ime:',
	'centralauth-admin-lookup' =>
		'Pregled ili izmena korisničkih podataka',
	'centralauth-admin-permission' =>
		"Samo stjuardi mogu da spoje ostale korisničke naloge za njih.",
	'centralauth-admin-unmerge' =>
		'Odvoji selektovane',
	'centralauth-admin-merge' =>
		'Spoji selektovane',
	
	// Info panel in preferences
	'centralauth-globalid' =>
		'Jedinstven ID naloga:',
);

$wgCentralAuthMessages['pt'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Status da unificação de logins',
	'centralauth-merge-notlogged' =>
		'Por gentileza, <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} faça login]' .
		'</span> para verificar se as suas contas foram corretamente fundidas.',

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

	// Administrator's console
	'centralauth' => 'Unified login administration',
	'centralauth-admin-manage' =>
		'Manusear dados de utilizador',
	'centralauth-admin-username' =>
		'Utilizador:',
	'centralauth-admin-lookup' =>
		'Ver ou editar dados de utilizador',
	'centralauth-admin-permission' =>
		"Apenas stewards podem fundir as contas de outras pessoas.",
	'centralauth-admin-unmerge' =>
		'Desfazer a fusão nos seleccionados',
	'centralauth-admin-merge' =>
		'Fundir seleccionados',
	
	// Info panel in preferences
	'centralauth-globalid' =>
		'ID de conta unificada:',
);

$wgCentralAuthMessages['pt-br'] = array(
// Because MediaWiki have system messages for pt-br dialect enabled, is interesting to have this
       // When not logged in...
	'mergeaccount' =>
		'Status da unificação de logins',
	'centralauth-merge-notlogged' =>
		'Por gentileza, <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} faça login]' .
		'</span> para verificar se as suas contas foram corretamente fundidas.',

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

	// Administrator's console
	'centralauth' => 'Unified login administration',
	'centralauth-admin-manage' =>
		'Manusear dados de utilizador',
	'centralauth-admin-username' =>
		'Utilizador:',
	'centralauth-admin-lookup' =>
		'Ver ou editar dados de usuário',
	'centralauth-admin-permission' =>
		"Apenas stewards podem fundir as contas de outras pessoas.",
	'centralauth-admin-unmerge' =>
		'Desfundir os selecionados',
	'centralauth-admin-merge' =>
		'Fundir os selecionados',
	
	// Info panel in preferences
	'centralauth-globalid' =>
		'ID de conta unificada:',
);

$wgCentralAuthMessages['de'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Status der Benutzerkonten-Zusammenführung',
	'centralauth-merge-notlogged' =>
		'Bitte <span class="plainlinks"> [{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} ' .
		'melden Sie sich an]</span>, um zu prüfen, ob Ihre Benutzerkonten vollständig zusammengeführt wurden.',

	// Big text on completion
	'centralauth-complete' =>
		'Die Zusammenführung der Benutzerkonten ist vollständig.',
	'centralauth-incomplete' =>
		'Die Zusammenführung der Benutzerkonten ist unvollständig!',

	// Wheeee
	'centralauth-complete-text' =>
		'Sie können sich nun auf jeder Wikimedia-Webseite anmelden ' .
		'ohne ein neues Benutzerkonto anzulegen; derselbe Benutzername ' .
		'und dasselbe Passwort ist für Wikipedia, Wiktionary, Wikibooks ' .
		'und alle Schwesterprojekte in allen Sprachen gültig.',

	'centralauth-incomplete-text' =>
		'Sobald Ihre Benutzerkonten zusammengeführt sind, können Sie sich ' .
		'auf jeder Wikimedia-Webseite anmelden ohne ein neues Benutzerkonto ' .
		'anzulegen; derselbe Benutzernamen und dasselbe Passwort ist für ' .
		'Wikipedia, Wiktionary, Wikibooks und alle Schwesterprojekte in allen Sprachen gültig.',

	'centralauth-not-owner-text' =>
		'Der Benutzername „$1“ wurde automatisch dem Eigentümer des Benutzerkontos auf ' .
		'$2 zugewiesen. Wenn dies Ihre Benutzername ist, können Sie die Zusammenführung ' .
		'der Benutzerkonten durch Eingabe des Haupt-Passwortes für dieses Benutzerkonto vollenden: ',
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Informationen über die '''Zusammenführung der Benutzerkonten''']]…''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'Die Benutzerkonten mit dem Namen „$1“ auf den folgenden Projekten wurden automatisch ' .
		' zusammengeführt: ',

	'centralauth-list-unmerged' =>
		'Das Benutzerkonto „$1“ konnte für die folgenden Projekte nicht ' .
		'automatisch als zu Ihnen gehörend bestätigt werden; vermutlich ' .
		'hat es ein anderes Passwort als Ihr primäres Benutzerkonto: ',

	'centralauth-foreign-link' =>
		'Benutzer $1 auf $2',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Zusammenführung vollenden',

	'centralauth-finish-text' =>
		'Wenn diese Benutzerkonten Ihnen gehören, können Sie hier den ' .
		'Prozess der Benutzerkonten-Zusammenführung durch die Eingabe ' .
		'des Passwortes für die anderen Benutzerkonto vollenden:',

	'centralauth-finish-password' =>
		'Passwort:',

	'centralauth-finish-login' =>
		'Anmeldung',

	'centralauth-finish-send-confirmation' =>
		'Passwort per E-Mail zusenden',

	'centralauth-finish-problems' =>
		'Haben Sie Probleme oder gehören Ihnen diese anderen Benutzerkonten nicht? ' .
		'[[meta:Help:Unified login problems|Hier finden Sie Hilfe]]…',
	
	'centralauth-merge-attempt' =>
		"'''Prüfe das eingegebene Passwort mit den restlichen Benutzerkonten…'''",

	// Administrator's console
	'centralauth-admin-permission' =>
		"Nur Benutzer mit Steward-Rechten dürfen fremde Benutzerkonten zusammenführen.",
);

$wgCentralAuthMessages['zh-cn'] = array(
	// When not logged in...
	'mergeaccount' =>
		'登录统一状态',
	'centralauth-merge-notlogged' =>
		'请<span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} 登录]' .
		'并检查您的账号是否都已经合并。',
	
	// Big text on completion
	'centralauth-complete' =>
		'完成登录统一！',
	'centralauth-incomplete' =>
		'登录统一失败！',
	
	// Wheeee
	'centralauth-complete-text' =>
		'您现在无需创建新帐号即可登录所有维基媒体网站；' .
		'同一组用户名和密码适用于' .
		'所有语言的' .
		'维基百科、维基词典、维基教科书及其他姊妹计划。',
	'centralauth-incomplete-text' =>
		'登录统一之后，您就无需创建新帐号即可登录' .
		'所有维基媒体网站；' .
		'同一组用户名和密码适用于' .
		'所有语言的' .
		'维基百科、维基词典、维基教科书及其他姊妹计划。',
	'centralauth-not-owner-text' =>
		'用户名“$1”已被自动分配给了$2上的账号。\n' .
		"of the account on $2.\n" .
		"\n" .
		"若这是您的账号，" .
		"请输入该帐号的密码，完成登录统一：",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|参阅关于'''登录统一'''的帮助文件]]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'以下网站的账号“$1”' .
		'已自动合并：',
	'centralauth-list-unmerged' =>
		'账号“$1”在以下网站' .
		'不能自动合并；' .
		'很可能因为它们的密码' .
		'与您主账号的不同：',
	'centralauth-foreign-link' =>
		'$2 的用户 $1',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'完成合并',
	'centralauth-finish-text' =>
		'如果这些帐号是您的，' .
		'请输入这些帐号的密码' .
		'即可完成登录统一：',
	'centralauth-finish-password' =>
		'密码：',
	'centralauth-finish-login' =>
		'登录',
	'centralauth-finish-send-confirmation' =>
		'透过电子邮件寄送密码',
	'centralauth-finish-problems' =>
		"有任何问题或者这些帐号不属于您？" .
		"请参阅[[meta:Help:Unified login problems|帮助信息]]...",
	
	'centralauth-merge-attempt' =>
		"'''检查未合并账号的密码...'''",
	
	// Administrator's console
	'centralauth-admin-permission' =>
		"只有监管员可以为其他人进行登录统一。",
	'centralauth-admin-unmerge' =>
		'拆分所选项',
	'centralauth-admin-merge' =>
		'合并所选项',
);

$wgCentralAuthMessages['zh-tw'] = array(
	// When not logged in...
	'mergeaccount' =>
		'帳號整合狀態',
	'centralauth-merge-notlogged' =>
		'請<span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}}登入]' .
		'</span>以查驗您的帳號是否已經完成整合。',
	
	// Big text on completion
	'centralauth-complete' =>
		'帳號整合已完成！',
	'centralauth-incomplete' =>
		'帳號整合未完成！',
	
	// Wheeee
	'centralauth-complete-text' =>
		'您現在可以使用同一組帳號與密碼登入所有維基媒體計畫網站，' .
		'無需再新建帳號；這組帳號與密碼將可登入' .
		'所有語言的' .
		'維基百科、維基詞典、維基教科書及其他姊妹計畫網站。',
	'centralauth-incomplete-text' =>
		'一旦您完成了帳號整合，你將可以登入' .
		'所有維基媒體計畫網站，無需再新建帳號；' .
		'用同一組帳號與密碼將可登入' .
		'所有語言的' .
		'維基百科、維基詞典、維基教科書及其他姊妹計畫網站。',
	'centralauth-not-owner-text' =>
		'用戶名："$1"已自動分配給' .
		"$2上的帳號。\n" .
		"\n" .
		"如果這是您的帳號，請輸入該帳號的密碼" .
		"以完成帳號整合：",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|了解更多'''帳號整合'''細節]]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'以下網站的帳號："$1' .
		'已自動完成整合：',
	'centralauth-list-unmerged' =>
		'以下網站的帳號："$1"' .
		'無法自動整合；' .
		'很可能是因為它們的密碼' .
		'和您的主帳號不同：',
	'centralauth-foreign-link' =>
		'$2 上的 $1',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'完成整合',
	'centralauth-finish-text' =>
		'如果這些帳號屬於您，' .
		'請輸入這些帳號的密碼，' .
		'以完成帳號整合：',
	'centralauth-finish-password' =>
		'密碼：',
	'centralauth-finish-login' =>
		'登入',
	'centralauth-finish-send-confirmation' =>
		'透過電子郵件寄送密碼',
	'centralauth-finish-problems' =>
		"遇到問題或者這些帳號不屬於您嗎？" .
		"[[meta:Help:Unified login problems|如何尋求協助]]...",
	
	'centralauth-merge-attempt' =>
		"'''正在查驗您輸入的密碼是否與其餘未整合的帳號相符...'''",
	
	// Administrator's console
	'centralauth-admin-permission' =>
		"只有監管員可以為用戶整合帳號。",
	'centralauth-admin-unmerge' =>
		'不整合所選取的帳號',
	'centralauth-admin-merge' =>
		'整合所選取的帳號',
);

$wgCentralAuthMessages['zh-hk'] = array(
	// When not logged in...
	'mergeaccount' =>
		'帳號整合狀態',
	'centralauth-merge-notlogged' =>
		'請<span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}}登入]' .
		'</span>以查驗您的帳號是否已經完成整合。',
	
	// Big text on completion
	'centralauth-complete' =>
		'帳號整合已完成！',
	'centralauth-incomplete' =>
		'帳號整合未完成！',
	
	// Wheeee
	'centralauth-complete-text' =>
		'您現在可以使用同一組帳號與密碼登入所有維基媒體計劃網站，' .
		'無需再新建帳號。這組帳號與密碼將可登入' .
		'所有語言的' .
		'維基百科、維基詞典、維基教科書及其他姊妹計劃網站。',
	'centralauth-incomplete-text' =>
		'一旦您完成了帳號整合，你將可以登入' .
		'所有維基媒體計劃網站，無需再新建帳號；' .
		'用同一組帳號與密碼將可登入' .
		'所有語言的' .
		'維基百科、維基詞典、維基教科書及其他姊妹計劃網站。',
	'centralauth-not-owner-text' =>
		'用戶名："$1"已自動分配給' .
		"$2上的帳號。\n" .
		"\n" .
		"如果這是您的帳號，請輸入該帳號的密碼" .
		"以完成帳號整合：",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|了解更多'''帳號整合'''細節]]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'以下網站的帳號："$1' .
		'已自動完成整合：',
	'centralauth-list-unmerged' =>
		'以下網站的帳號："$1"' .
		'無法自動整合；' .
		'很可能是因為它們的密碼' .
		'和您的主帳號不同：',
	'centralauth-foreign-link' =>
		'$2 上的 $1',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'完成整合',
	'centralauth-finish-text' =>
		'如果這些帳號屬於您，' .
		'請輸入這些帳號的密碼，' .
		'以完成帳號整合：',
	'centralauth-finish-password' =>
		'密碼：',
	'centralauth-finish-login' =>
		'登入',
	'centralauth-finish-send-confirmation' =>
		'透過電子郵件寄送密碼',
	'centralauth-finish-problems' =>
		"遇到問題或者這些帳號不屬於您嗎？" .
		"[[meta:Help:Unified login problems|如何尋求協助]]...",
	
	'centralauth-merge-attempt' =>
		"'''正在查驗您輸入的密碼是否與其餘未整合的帳號相符...'''",
	
	// Administrator's console
	'centralauth-admin-permission' =>
		"只有監管員可以為用戶整合帳號。",
	'centralauth-admin-unmerge' =>
		'不整合已選取的',
	'centralauth-admin-merge' =>
		'整合已選取的',
);

$wgCentralAuthMessages['zh-yue'] = array(
	// When not logged in...
	'mergeaccount' =>
		'登入統一狀態',
	'centralauth-merge-notlogged' =>
		'請<span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}}登入]' .
		'</span>去睇下檢查你嘅戶口係唔係已經完全整合。',
	
	// Big text on completion
	'centralauth-complete' =>
		'戶口統一已經搞掂！',
	'centralauth-incomplete' =>
		'戶口統一重未搞掂！',
	
	// Wheeee
	'centralauth-complete-text' =>
		'你而家可以響唔使個新戶口嘅情況之下' .
		'登入任何一個Wikimedia嘅wiki網站；用同一個用戶名同密碼' .
		'就可以登入響所有語言嘅' .
		'維基百科、維基詞典、維基教科書同埋佢哋嘅其它姊妹計劃網站。',
	'centralauth-incomplete-text' =>
		'一旦你嘅登入完成統一，你就可以登入' .
		'所有Wikimedia嘅wiki網站，而無需再開個新戶口；' .
		'用同一組用戶名同密碼就可以登入到' .
		'所有語言嘅' .
		'維基百科、維基詞典、維基教科書同埋佢哋嘅其它姊妹計劃網站。',
	'centralauth-not-owner-text' =>
		'用戶名 "$1" 已經自動分咗畀' .
		"$2 上面嘅戶口持有者。\n" .
		"\n" .
		"如果呢個係你，你可以輸入響嗰個戶口嘅主密碼" .
		"以完成登入統一嘅程序：",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|睇下更多有關'''統一登入'''嘅細節]]...''",
	
	// For lists of wikis/accounts:
	'centralauth-list-merged' =>
		'以下用戶名 "$1" 嘅戶口' .
		'已經自動噉樣合併咗：',
	'centralauth-list-unmerged' =>
		'以下網站嘅戶口 "$1" ' .
		'唔能夠自動噉樣合併；' .
		'好有可能佢哋嘅密碼' .
		'同你嘅主戶口唔同：',
	'centralauth-foreign-link' =>
		'響 $2 嘅用戶 $1',
	
	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'完成合併',
	'centralauth-finish-text' =>
		'如果呢啲戶口係屬於你嘅，' .
		'你可以響呢度輸入其它戶口嘅密碼，' .
		'以完成登入統一嘅程序：',
	'centralauth-finish-password' =>
		'密碼：',
	'centralauth-finish-login' =>
		'登入',
	'centralauth-finish-send-confirmation' =>
		'透過電郵寄密碼',
	'centralauth-finish-problems' =>
		"有問題，又或者你並無持有其它嘅戶口？" .
		"[[meta:Help:Unified login problems|如何尋求協助]]...",
	
	'centralauth-merge-attempt' =>
		"'''Checking provided password against remaining unmerged accounts...'''",
	'centralauth-merge-attempt' =>
		"'''檢查緊所輸入嘅密碼，同剩底未合併戶口相對...'''",
	
	// Administrator's console
	'centralauth-admin-permission' =>
		"只有執行員先至可以為用戶合併其它人嘅戶口。",
	'centralauth-admin-unmerge' =>
		'唔合併已經揀咗嘅',
	'centralauth-admin-merge' =>
		'合併已經揀咗嘅',
);
$wgCentralAuthMessages['zh-sg'] = $wgCentralAuthMessages['zh-cn'];

?>
