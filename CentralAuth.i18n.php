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
	'centralauth-merge-welcome' =>
		"'''Your user account has not yet been migrated to Wikimedia's ".
		"unified login system.'''\n" .
		"\n" .
		"If you choose to migrate your accounts, you'll be able to use the " .
		"same username and password to log in to all of Wikimedia's project " .
		"wikis in all available languages.\n" .
		"This makes it easier to work with shared projects such as uploading to " .
		"[http://commons.wikimedia.org/ Wikimedia Commons], and avoids the " .
		"confusion or conflict that could result from two people picking the same " .
		"username on different projects.\n" . 
		"\n" .
		"If someone else has already taken your username on another site " .
		"this won't disturb them, but it will give you a chance to work out " .
		"with them or an administrator later.\n" .
		"\n" .
		"== What happens next? ==\n" .
		"\n" .
		"When you opt-in to the unified login migration, the system will look over " .
		"each of the sites that we run -- Wikipedia, Wikinews, Commons, etc -- " .
		"and list out every one where your username has been registered.\n" .
		"\n" .
		"One of those wikis will be chosen as the 'home wiki' for the account, " .
		"usually the one that is most firmly established in usage. If that isn't " .
		"the wiki you're logged into right now, you may be asked to confirm " .
		"that you know the password to that account to proceed.\n" .
		"\n" .
		"The account information on the home wiki will be compared all the others, " .
		"and those which have matching passwords or e-mail addresses, or haven't been used, " .
		"will be automatically attached to your new global account.\n" .
		"\n" .
		"Those which don't match will be left alone, since the system can't be " .
		"sure that they're your accounts. You can complete the merging for those " .
		"accounts by providing the correct login passwords if they're yours; " .
		"if someone else registered them, you'll have a chance to leave them a message " .
		"and see if you can work something out.\n" .
		"\n" .
		"It's not ''required'' to merge all accounts; you can leave some separate, " .
		"and they'll be marked as such.",
	
	'centralauth-merge-step1-title' => 'Begin login unification',
	'centralauth-merge-step1-detail' =>
		'Your password and registered e-mail address will be checked against ' .
		'the accounts on other wikis to confirm that they match ' .
		'No changes will be made until you have confirmed that things look ok.',
	'centralauth-merge-step1-submit' =>
		'Confirm login information',

	'centralauth-merge-step2-title' => 'Confirm more accounts',
	'centralauth-merge-step2-detail' =>
		"Some of the accounts couldn't be automatically matched to the designated home wiki. " .
		"If these accounts belong to you, you can confirm that they are yours " .
		"by providing the password for them.\n",
	'centralauth-merge-step2-submit' =>
		'Confirm login information',
	
	'centralauth-merge-step3-title' => 'Create unified account',
	'centralauth-merge-step3-detail' =>
		"You're ready to create your unified account, with the following wikis attached:",
	'centralauth-merge-step3-submit' =>
		'Unify accounts',

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
		
	'centralauth-notice-dryrun' =>
		"<div class='successbox'>Demo mode only</div><br clear='all'/>",
	
	'centralauth-disabled-dryrun' =>
		"Account unification is currently in a demo / debugging mode, " .
		"so actual merging operations are disabled. Sorry!",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Read more about '''unified login''']]...''",

	// For lists of wikis/accounts:
	'centralauth-list-home-title' =>
		'Home wiki',
	'centralauth-list-home-dryrun' =>
		'The password and e-mail address set at this wiki will be used for your unified account, ' .
		'and your user page here will be automatically linked to from other wikis. ' .
		"You will be able to change which is your home wiki later.",
	'centralauth-list-attached-title' =>
		'Attached accounts',
	'centralauth-list-attached' =>
		'The account named "$1" on each the following sites ' .
		'have been automatically attached to the unified account:',
	'centralauth-list-attached-dryrun' =>
		'The account named "$1" on each the following sites ' .
		'will be automatically attached to the unified account:',
	'centralauth-list-unattached-title' =>
		'Unattached accounts',
	'centralauth-list-unattached' =>
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
	'centralauth-admin-bad-input' =>
		'Invalid merge selection',
	'centralauth-admin-none-selected' =>
		'No accounts selected to modify.',

	// Info panel in preferences
	'centralauth-prefs-status' =>
		'Global account status:',
	'centralauth-prefs-not-managed' =>
		'Not using unified account',
	'centralauth-prefs-unattached' =>
		'Unconfirmed',
	'centralauth-prefs-complete' =>
		'All in order!',
	'centralauth-prefs-migration' =>
		'In migration',
	'centralauth-prefs-count-attached' =>
		'Your account is active on $1 project sites.',
	'centralauth-prefs-count-unattached' =>
		'Unconfirmed accounts with your name remain on $1 projects.',
	'centralauth-prefs-detail-unattached' =>
		'This project site has not been confirmed as belonging to the global account.',
	'centralauth-prefs-manage' =>
		'Manage your global account',
	
	// Interaction with Special:Renameuser
	'centralauth-renameuser-abort' =>
		"<div class=\"errorbox\">" .
		"Cannot rename user $1 locally as this username has been migrated to the " .
		"unified login system.</div>",
	
);

/* Arabic (Meno25) */
$wgCentralAuthMessages['ar'] = array(
	'mergeaccount'                         => 'حالة توحيد الدخول',
	'centralauth-complete'                 => 'توحيد الدخول اكتمل!',
	'centralauth-incomplete'               => 'توحيد الدخول لم يكتمل!',
	'centralauth-complete-text'            => 'يمكنك الآن الولوج لأي مشروع من مشاريع ويكيميديا بدون إنشاء حساب جديد؛ نفس اسم المستخدم و كلم السر ستعمل في ويكيبيديا و ويكاموس و ويكي الكتب و مشاريعهم الشقيقة بكل اللغات.',
	'centralauth-incomplete-text'          => 'عندما يتم توحيد دخولك، يمكنك الولوج لأي مشروع من مشاريع ويكيميديا بدون إنشاء حساب جديد؛ نفس اسم المستخدم و كلم السر ستعمل في ويكيبيديا و ويكاموس و ويكي الكتب و مشاريعهم الشقيقة بكل اللغات.',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|اقرأ المزيد حول \'\'\'الدخول الموحد\'\'\']]...\'\'',
	'centralauth-list-attached'              => 'الحسابات المسماة "$1" على المواقع التالية تم دمجها تلقائيا:',
	'centralauth-foreign-link'             => 'المستخدم $1 في $2',
	'centralauth-finish-title'             => 'انتهاء الدمج',
	'centralauth-finish-password'          => 'كلمة السر:',
	'centralauth-finish-login'             => 'دخول',
	'centralauth-finish-send-confirmation' => 'أرسل كلمة السر عبر البريد الإلكتروني',
	'centralauth-finish-problems'          => 'لديك مشكلة، أو لا تمتلك هذه الحسابات الأخرى؟ [[meta:Help:Unified login problems|كيف تجد المساعدة]]...',
	'centralauth'                          => 'إدارة الدخول الموحد',
	'centralauth-admin-manage'             => 'إدارة بيانات المستخدم',
	'centralauth-admin-username'           => 'اسم المستخدم:',
	'centralauth-admin-lookup'             => 'عرض أو تعديل بيانات المستخدم',
	'centralauth-admin-permission'         => 'فقط المضيفون يمكنهم أن يدمجوا حسابات الآخرين.',
	'centralauth-admin-unmerge'            => 'تم اختيار الفصل',
	'centralauth-admin-merge'              => 'تم اختيار الدمج',
	'centralauth-admin-bad-input'          => 'اختيار دمج غير صحيح',
	'centralauth-admin-none-selected'      => 'لم يتم اختيار حسابات للدمج',
	'centralauth-prefs-not-managed'        => 'لا يستخدم الحساب الموحد',
	'centralauth-prefs-unattached'         => 'غير مؤكد',
	'centralauth-prefs-count-attached'     => 'حسابك نشط في $1 مشروع.',
	'centralauth-prefs-count-unattached'   => 'حسابات غير مؤكدة باسمك موجودة في $1 مشروع.',
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
	'centralauth-list-attached' =>
		'Die Benutzerkonten mit dem Namen „$1“ auf den folgenden ' .
		'Projekten wurden automatisch zusammengeführt:',

	'centralauth-list-unattached' =>
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
	'centralauth-admin-bad-input' =>
		'Ungültige Auswahl',
	'centralauth-admin-none-selected' =>
		'Es wurden keine zu ändernden Benutzerkonten ausgewählt.',

	// Info panel in preferences
       'centralauth-prefs-status'            => 'Benutzerkonten-Status:',
       'centralauth-prefs-not-managed'       => 'Es wird kein zusammengeführtes Benutzerkonto benutzt.',
       'centralauth-prefs-unattached'        => 'Unbestätigt',
       'centralauth-prefs-complete'          => 'Fertig!',
       'centralauth-prefs-migration'         => 'Zusammenführung in Arbeit',
       'centralauth-prefs-count-attached'    => 'Dein Benutzerkonto ist in $1 Projekten aktiv.',
       'centralauth-prefs-count-unattached'  => 'Es gibt in $1 Projekten unbestätigte Benutzerkonten mit deinem Namen.',
       'centralauth-prefs-detail-unattached' => 'Für dieses Projekt liegt keine Bestätigung für das zusammengeführte Benutzerkonto vor.',
       'centralauth-prefs-manage'            => 'Bearbeite dein zusammengeführtes Benutzerkonto',

);

// Based on r18928
// Ashar Voultoiz <hashar@altern.org>
$wgCentralAuthMessages['fr'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Statut d’unification du compte utilisateur',
	'centralauth-merge-notlogged' =>
		'Merci de bien vouloir <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} vous connecter]' .
		'</span> pour vérifier que vos comptes ont bien été réunis.',

	// Big text on completion
	'centralauth-complete' =>
		'Unification du compte terminée !',
	'centralauth-incomplete' =>
		'Unification du compte non terminée !',

	// Wheeee
	'centralauth-complete-text' =>
		'Vous pouvez maintenant vous connecter sur n’importe quel site ' .
		'Wikimedia sans avoir à créer un nouveau compte ; le même nom '.
		'd’utilisateur et mot de passe fonctionnent sur Wikipédia, '.
		'Wiktionary, Wikibooks et leurs projets sœurs, ceci pour toutes '.
		'les langues.',
	'centralauth-incomplete-text' =>
		'Une fois votre compte unifié, vous pourrez vous connecter sur n’importe quel site ' .
		'Wikimedia sans avoir à créer un nouveau compte ; le même nom '.
		'd’utilisateur et mot de passe fonctionneront sur Wikipédia, '.
		'Wiktionary, Wikibooks et leurs projets sœurs, ceci pour toutes '.
		'les langues.',
	'centralauth-not-owner-text' =>
		'Le compte utilisateur « $1 » a été automatiquement assigné au '.
		"propriétaire du compte sur $2.\n" .
		"\n" .
		'Si c’est vous, vous pourrez terminer le process d’unification de '.
		'compte en tapant le mot de passe maître pour ce compte sur :',

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[w:fr:Wikipédia:Login unique|En savoir plus sur le '''compte unifié''']]...''",

	// For lists of wikis/accounts:
	'centralauth-list-attached' =>
		'Les comptes utilisateurs nommés « $1 » ont été réunis pour les sites suivants :',
	'centralauth-list-unattached' =>
		'Le compte utilisateur « $1 » ne peut être confirmé automatiquement ' .
		'pour les sites qui suivent ; ils ont probablement un mot de passe '.
		'différent de votre compte maître :',
	'centralauth-foreign-link' =>
		'Utilisateur $1 sur $2',

	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Compléter l’unification',
	'centralauth-finish-text' =>
		'Si ces comptes vous appartiennent, vous pouvez terminer leur ' .
		'unification en tapant les mots de passe ci-dessous :',
	'centralauth-finish-password' =>
		'Mot de passe :',
	'centralauth-finish-login' =>
		'Compte utilisateur :',
	'centralauth-finish-send-confirmation' =>
		'Envoyer le mot de passe par e-mail',
	'centralauth-finish-problems' =>
		'En cas de problème ou si vous ne possédez pas ces autres comptes, ' .
		'voyez la page [[meta:Help:Unified login problems|Problèmes]] (en anglais)...',

	'centralauth-merge-attempt' =>
		"'''Vérification du mot de passe fourni pour les comptes non réunis...'''",

	// Administrator's console
	'centralauth' => 'Administration comptes unifiés',
	'centralauth-admin-manage' =>
		'Gérer les données utilisateur',
	'centralauth-admin-username' =>
		'Nom d’utilisateur :',
	'centralauth-admin-lookup' =>
		'Voir ou modifier les données utilisateur',
	'centralauth-admin-permission' =>
		'Seuls les stewards peuvent réunir les comptes d’autres personnes à leur place.',
	'centralauth-admin-unmerge' =>
		'Séparer sélection',
	'centralauth-admin-merge' =>
		'Réunir sélection',
);

$wgCentralAuthMessages['he'] = array(
	# When not logged in...
	'mergeaccount'                => 'מצב מיזוג החשבונות',
	'centralauth-merge-notlogged' => 'אנא <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} היכנסו לחשבון]</span> כדי לבדוק האם חשבונותיכם מוזגו במלואם.',
	'centralauth-merge-welcome'   => "'''חשבון המשתמש שלכם עדיין לא התווסף למערכת החשבונות הממוזגים של ויקימדיה.'''

אם תבחרו להוסיף את החשבונות שלכם, תוכלו להשתמש בשם משתמש וסיסמה זהים בכל מיזמי קרן ויקימדיה ובכל השפות. אפשרות זו תקל עליכם לעבוד במיזמים משותפים כגון העלאות ל[http://commons.wikimedia.org/ וויקישיתוף], וימנע בלבול או סכסוך שעלול להיגרם כתוצאה מכך ששני אנשים יבחרו שם משתמש זהה בשני מיזמים שונים.

אם מישהו כבר בחר את שם המשתמש שלכם באתר אחר, זה לא אמור להפריע להם, אבל יאפשר לכם לעבוד איתם או עם מנהל מערכת מאוחר יותר.

== מה יקרה אחר כך? ==
כשתצטרפו למערכת החשבונות הממוזגים, המערכת תבדוק בכל אחד מהאתרים שאנו מפעילים - ויקיפדיה, ויקיחדשות, ויקישיתוף וכו' - ותערוך רשימה של כל המיזמים שבהם חשבון עם שם המשתמש שלכם רשום.

אחד המיזמים האלה ייבחר להיות \"האתר הראשי\" לחשבון זה, בדרך כלל האתר שבו ערכתם יותר מבאתרים האחרים. אם זה לא האתר שבו אתם משתמשים עכשיו, ייתכן שתתבקשו לאשר שאתם יודעים את הסיסמה לחשבון הזה כדי להמשיך.

המערכת תשווה את המידע של חשבון המשתמש באתר הראשי שלכם עם המידע על החשבונות באתרים האחרים, ואלה שהסיסמאות וכתובות הדואר האלקטרוני שלהם יהיו זהות לאלה שבחשבון הראשי, או שלא נעשה בהם שימוש, יצורפו אוטומטית לחשבון הכללי החדש שלכם.

החשבונות שלא יתאימו יישארו כפי שהם, כיוון שהמערכת לא יכולה להיות בטוחה שהם החשבונות שלכם. באפשרותכם להשלים את המיזוג לחשבונות הללו באמצעות הזנת הסיסמאות הנכונות שלהם אם הם שלכם; אם מישהו אחר רשם אותם, תוכלו להשאיר להם הודעה, ולראות אם תוכלו להגיע להסכמה.

אין '''חובה''' למזג את כל החשבונות; תוכלו להשאיר כמה חשבונות נפרדים, והם יסומנו ככאלה.",

	'centralauth-merge-step1-title'  => 'תחילת מיזוג החשבונות',
	'centralauth-merge-step1-detail' => 'סיסמתכם וכתובת הדוא"ל הרשומה שלכם יושוו עם החשבונות באתרים האחרים כדי לוודא שהם זהים לאלה שלכם. לא יבוצעו שינויים עד שתאשרו אותם.',
	'centralauth-merge-step1-submit' => 'אישור של מידע הכניסה',

	'centralauth-merge-step2-title'  => 'אישור חשבונות נוספים',
	'centralauth-merge-step2-detail' => "לא ניתן היה לאשר אוטומטית שמספר חשבונות זהים לחשבון הראשי באתר המצוין. אם החשבונות הללו שייכים לכם, תוכלו לאשר זאת באמצעות כתיבת סיסמאותיהם.\n",
	'centralauth-merge-step2-submit' => 'אישור של מידע הכניסה',

	'centralauth-merge-step3-title'  => 'יצירת החשבון הממוזג',
	'centralauth-merge-step3-detail' => 'אתם מוכנים ליצירת החשבון הממוזג שלכם, שהחשבונות באתרים הבאים ימוזגו אליו:',
	'centralauth-merge-step3-submit' => 'מיזוג החשבונות',

	# Big text on completion
	'centralauth-complete'   => 'מיזוג החשבון הושלם!',
	'centralauth-incomplete' => 'מיזוג החשבון לא הושלם!',

	# Wheeee
	'centralauth-complete-text'   => 'כעת באפשרותכם להיכנס לכל אתר ויקי של ויקימדיה בלי ליצור חשבון חדש; שם המשתמש והסיסמה הזהים יעבדו בוויקיפדיה, בוויקימילון, בוויקיספר, ובמיזמים השונים בכל השפות.',
	'centralauth-incomplete-text' => 'כשמיזוג החשבון שלכם יושלם, יהיה באפשרותכם להיכנס לכל אתר ויקי של ויקימדיה בלי ליצור חשבון חדש; שם המשתמש והסיסמה הזהים יעבדו בוויקיפדיה, בוויקימילון, בוויקיספר, ובמיזמים השונים בכל השפות.',
	'centralauth-not-owner-text'  => 'שם המשתמש "$1" הוקצה אוטומטית לבעלי החשבון באתר $2.

אם אתם בעלי החשבון, באפשרותכם לסיים את תהליך מיזוג החשבונות פשוט על־ידי הקלדת הסיסמה של החשבון הכללי כאן:',

	# Appended to various messages above
	'centralauth-readmore-text' => ":[[meta:Help:Unified login|מידע נוסף על '''מיזוג החשבונות''']]...",

	# For lists of wikis/accounts:
	'centralauth-list-home-title'       => 'האתר הראשי',
	'centralauth-list-home-dryrun'      => 'הסיסמה וכתובת הדוא"ל שהוגדרו באתר הזה יהיו בשימוש עבור החשבון הממוזג שלכם, ויהיה קישור אוטומטי לדף המשתמש שלכם מאתרים אחרים. תוכלו לשנות את האתר הראשי שלכם מאוחר יותר.',
	'centralauth-list-attached-title'   => 'חשבונות ממוזגים',
	'centralauth-list-attached'         => 'החשבונות בשם "$1" באתרי ויקימדיה הבאים מוזגו באופן אוטומטי:',
	'centralauth-list-attached-dryrun'  => 'החשבונות בשם "$1" באתרי ויקימדיה הבאים ימוזגו באופן אוטומטי:',
	'centralauth-list-unattached-title' => 'חשבונות לא ממוזגים',
	'centralauth-list-unattached'       => 'לא ניתן היה לבדוק אוטומטית האם החשבונות בשם "$1" באתרים הבאים שייכים לכם; כנראה שיש להם סיסמאות שונות מאשר בחשבון הראשי שלכם:',
	'centralauth-foreign-link'          => 'המשתמש $1 באתר $2',

	# When not complete, offer to finish...
	'centralauth-finish-title'             => 'סיום המיזוג',
	'centralauth-finish-text'              => 'אם חשבונות אלה אכן שייכים לכם, באפשרותכם לסיים את תהליך מיזוג החשבונות פשוט על־ידי הקלדת הסיסמאות לחשבונות האחרים כאן:',
	'centralauth-finish-password'          => 'סיסמה:',
	'centralauth-finish-login'             => 'כניסה',
	'centralauth-finish-send-confirmation' => 'שליחת סיסמה בדוא"ל',
	'centralauth-finish-problems'          => "יש לכם בעיה, או שאינכם בעלי החשבונות האחרים? [[meta:Help:Unified login problems|ניתן למצוא עזרה כאן]]...",

	'centralauth-merge-attempt' => "'''בודק האם הסיסמאות שניתנו תואמים לחשבונות הנותרים שלא מוזגו...'''",

	# Administrator's console
	'centralauth'                     => 'ניהול מיזוג החשבונות',
	'centralauth-admin-manage'        => 'ניהול המידע על המשתמש',
	'centralauth-admin-username'      => 'שם משתמש:',
	'centralauth-admin-lookup'        => 'הצגת ועריכת המידע על המשתמש',
	'centralauth-admin-permission'    => "רק דיילים יכולים למזג את חשבונותיהם של אנשים אחרים עבורם.",
	'centralauth-admin-unmerge'       => 'ביטול המיזוג של החשבונות שנבחרו',
	'centralauth-admin-merge'         => 'מיזוג החשבונות שנבחרו',
	'centralauth-admin-bad-input'     => 'בחירה שגויה של מיזוג',
	'centralauth-admin-none-selected' => 'לא נבחרו חשבונות לשינוי.',

	# Info panel in preferences
	'centralauth-prefs-status'            => 'מצב החשבון הכללי:',
	'centralauth-prefs-not-managed'       => 'לא משתמש בחשבון ממוזג',
	'centralauth-prefs-unattached'        => 'בלתי מאומת',
	'centralauth-prefs-complete'          => 'הכל תקין!',
	'centralauth-prefs-migration'         => 'בתהליך מיזוג',
	'centralauth-prefs-count-attached'    => 'החשבון שלכם פעיל ב־$1 מיזמים.',
	'centralauth-prefs-count-unattached'  => 'חשבונות בלתי מאומתים בשם המשתמש הזה נותרו ב־$1 מיזמים.',
	'centralauth-prefs-detail-unattached' => 'מיזם זה לא אושר כשייך לחשבון הכללי.',
	'centralauth-prefs-manage'            => 'ניהול החשבון הכללי',
);

$wgCentralAuthMessages['id'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Status penggabungan log masuk',
	'centralauth-merge-notlogged' =>
		'Harap <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} masuk log]' .
		'</span> untuk mengecek apakah akun Anda telah sepenuhnya digabungkan.',

	// Big text on completion
	'centralauth-complete' =>
		'Penggabungan log masuk berhasil!',
	'centralauth-incomplete' =>
		'Penggabungan log masuk tidak berhasil!',

	// Wheeee
	'centralauth-complete-text' =>
		'Kini Anda dapat masuk log ke situs wiki Wikimedia manapun tanpa membuat ' .
		'akun baru; nama pengguna dan kata santi yang sama dapat digunakan ' .
		'di Wikipedia, Wiktionary, Wikibooks, dan proyek-proyek lainnya ' .
		'dalam semua bahasa.',
	'centralauth-incomplete-text' =>
		'Setelah log masuk Anda digabungkan, Anda akan dapat masuk log ke situs ' .
		'wiki Wikimedia manapun tanpa harus membuat akukn baru; nama pengguna ' .
		'dan kata sandi yang sama akan dapat digunakan di Wikipedia, Wiktionary, ' .
		'Wikibooks, dan proyek-proyek lainnya dalam semua bahasa.',
	'centralauth-not-owner-text' =>
		'Nama pengguna "$1" diberikan secara otomatis kepada pemilik akun ' .
		"$2.\n\n" .
		"Jika ini adalah Anda, Anda dapat menyelesaikan proses penggabungan log masuk " .
		"dengan hanya mengetikkan kata kunci utama untuk akun tersebut di sini:",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Baca lebih lanjut mengenai '''log masuk gabungan''']]...''",

	// For lists of wikis/accounts:
	'centralauth-list-attached' =>
		'Akun "$1" di situs-situs berikut telah digabung secara otomatis:' ,
	'centralauth-list-unattached' =>
		'Akun "$1" tidak dapat dikonfirmasikan secara otomatis sebagai milik Anda ' .
		'di situs-situs berikut; kemungkinan besar karena mereka memiliki ' .
		'kata sandi yang berbeda dengan akun utama Anda:',
	'centralauth-foreign-link' =>
		'Pengguna $1 di $2',

	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Selesaikan penggabungan',
	'centralauth-finish-text' =>
		'Jika akun-akun ini miliki Anda, Anda dapat menyelesaikan proses ' .
		'penggabungan hanya dengan mengetikkan kata sandi untuk akun lain di sini:',
	'centralauth-finish-password' =>
		'Kata sandi:',
	'centralauth-finish-login' =>
		'Masuk log',
	'centralauth-finish-send-confirmation' =>
		'Kirim kata sandi',
	'centralauth-finish-problems' =>
		"Ada masalah, atau tidak memiliki akun-akun lain tersebut? " .
		"[[meta:Help:Unified login problems|Bagaimana mendapat bantuan]]...",

	'centralauth-merge-attempt' =>
		"'''Mengecek kata sandi yang diberikan terhadap akun-akun yang belum " .
		"digabungkan...'''",

	// Administrator's console
	'centralauth' => 'Pengelolaan log masuk gabungan',
	'centralauth-admin-manage' =>
		'Atur data pengguna',
	'centralauth-admin-username' =>
		'Nama pengguna:',
	'centralauth-admin-lookup' =>
		'Lihat atau ubah data pengguna',
	'centralauth-admin-permission' =>
		"Hanya steward yang dapat melakukan penggabungan akun orang lain.",
	'centralauth-admin-unmerge' =>
		'Batalkan penggabungan akun terpilih',
	'centralauth-admin-merge' =>
		'Gabungkan akun terpilih',
	'centralauth-admin-bad-input' =>
		'Pilihan penggabungan tak sah',
	'centralauth-admin-none-selected' =>
		'Tidak ada akun yang dipilih untuk diubah.',
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
	'centralauth-list-attached' =>
		'Gli account con nome utente "$1" sui progetti elencati ' .
		'di seguito sono stati unificati automaticamente:',
	'centralauth-list-unattached' =>
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


$wgCentralAuthMessages['kk-kz'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Тіркелгі бірегейлендіру күйі',
	'centralauth-merge-notlogged' =>
		'Тіркелгілеріңіз толық бірегейлендіруін тексеру үшін Please <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} кіріңіз]' .
		'</span>.',

	// Big text on completion
	'centralauth-complete' =>
		'Тіркелгі бірегейлендіруі бітті!',
	'centralauth-incomplete' =>
		'Тіркелгі бірегейлендіруі біткен жоқ!',

	// Wheeee
	'centralauth-complete-text' =>
		'Енді әрқайсы Wikimedia қорының уики торабына жаңа тіркелгі жасамастан ' .
		'кіруіңізге болады; дәл осы қатысушы атыңыз бен құпия сөзіңіз ' .
		'Уикипедия, Уикисөздік, Уикикітәп деген жобаларда және барлық  ' .
		'тілдердегі таруларында қызмет істейді.',
	'centralauth-incomplete-text' =>
		'Тіркелгіңіз бірегейлендірігеннен бастап әрқайсы Wikimedia ' .
		'қорының уики торабына жаңа тіркелгі жасамастан кіруіңізге болады; ' .
		'дәл осы қатысушы атыңыз бен құпия сөзіңіз ' .
		'Уикипедия, Уикисөздік, Уикикітәп, деген жобаларда және барлық ' .
		'тілдердегі таруларында қызмет істейді.',
	'centralauth-not-owner-text' =>
		'«$1» деген қатысушы аты өздік түрде ' .
		"$2 деген тіркелгі иесіне түйістірілген.\n" .
		"\n" .
		"Бұл өзіңіз болсаңыз, басқы құпия сөзіңізді кәдімгідей мында енгізіп " .
		"тіркелгі бірегейлендіру үдірісін бітіруіңізге болады:",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|'''Бірегейлендірілген тіркелгі''' туралы оқыңыз]]...''",

	// For lists of wikis/accounts:
	'centralauth-list-attached' =>
		'«$1» деп аталған тіркелгілер келесі тораптарда ' .
		'өздік түрде түйістірілген:',
	'centralauth-list-unattached' =>
		'«$1» деген тіркелгіңіз келесі тораптарда ' .
		'өздік түрде расталмады; ' .
		'бәлкім бұларда басқы тіркелгіден сан-қилы ' .
		'құпия сөздер бар:',
	'centralauth-foreign-link' =>
		'$2 дегендегі $1 деген қатысушы',

	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Түйістірілу бітті',
	'centralauth-finish-text' =>
		'Бұл тіркелгілер сіздікі болса, құпия сөзідерңізді ' .
		'басқа тіркелгілеріңізге кәдімгідей мында енгізіп ' .
		'тіркелгілеріңізді бірегейлендіруін бітіруңізге болады:',
	'centralauth-finish-password' =>
		'Құпия сөз:',
	'centralauth-finish-login' =>
		'Кіру',
	'centralauth-finish-send-confirmation' =>
		'Құпия сөзді хатпен жіберу',
	'centralauth-finish-problems' =>
		"Қиын жағдайға ұшырадыңыз, немесе басқа тіркелгілер сіздікі емес? " .
		"[[meta:Help:Unified login problems|Қалай анықталуыңызға болады]]...",

	'centralauth-merge-attempt' =>
		"'''Жабдықталынған құпия сөзді қалған түйістірілмеген тіркелгілермен тексеруде…'''",

	// Administrator's console
	'centralauth' => 'Бірегейлендірген тіркелгіні меңгеру',
	'centralauth-admin-manage' =>
		'Қатысушы деректерін меңгеру',
	'centralauth-admin-username' =>
		'Қатысушы аты:',
	'centralauth-admin-lookup' =>
		'Қатысушы деректерін қарау не өңдеу',
	'centralauth-admin-permission' =>
		"Басқалардың тіркелгілерін бұған тек жетекшілер түйістірілейді.",
	'centralauth-admin-unmerge' =>
		'Талғанғанды түйістірілеме',
	'centralauth-admin-merge' =>
		'Талғанғанды түйістіріле',
);

$wgCentralAuthMessages['kk-tr'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Tirkelgi biregeýlendirw küýi',
	'centralauth-merge-notlogged' =>
		'Tirkelgileriñiz tolıq biregeýlendirwin tekserw üşin Please <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} kiriñiz]' .
		'</span>.',

	// Big text on completion
	'centralauth-complete' =>
		'Tirkelgi biregeýlendirwi bitti!',
	'centralauth-incomplete' =>
		'Tirkelgi biregeýlendirwi bitken joq!',

	// Wheeee
	'centralauth-complete-text' =>
		'Endi ärqaýsı Wikimedia qorınıñ wïkï torabına jaña tirkelgi jasamastan ' .
		'kirwiñizge boladı; däl osı qatıswşı atıñız ben qupïya söziñiz ' .
		'Wïkïpedïya, Wïkïsözdik, Wïkïkitäp degen jobalarda jäne barlıq  ' .
		'tilderdegi tarwlarında qızmet isteýdi.',
	'centralauth-incomplete-text' =>
		'Tirkelgiñiz biregeýlendirigennen bastap ärqaýsı Wikimedia ' .
		'qorınıñ wïkï torabına jaña tirkelgi jasamastan kirwiñizge boladı; ' .
		'däl osı qatıswşı atıñız ben qupïya söziñiz ' .
		'Wïkïpedïya, Wïkïsözdik, Wïkïkitäp, degen jobalarda jäne barlıq ' .
		'tilderdegi tarwlarında qızmet isteýdi.',
	'centralauth-not-owner-text' =>
		'«$1» degen qatıswşı atı özdik türde ' .
		"$2 degen tirkelgi ïesine tüýistirilgen.\n" .
		"\n" .
		"Bul öziñiz bolsañız, basqı qupïya söziñizdi kädimgideý mında engizip " .
		"tirkelgi biregeýlendirw üdirisin bitirwiñizge boladı:",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|'''Biregeýlendirilgen tirkelgi''' twralı oqıñız]]...''",

	// For lists of wikis/accounts:
	'centralauth-list-attached' =>
		'«$1» dep atalğan tirkelgiler kelesi toraptarda ' .
		'özdik türde tüýistirilgen:',
	'centralauth-list-unattached' =>
		'«$1» degen tirkelgiñiz kelesi toraptarda ' .
		'özdik türde rastalmadı; ' .
		'bälkim bularda basqı tirkelgiden san-qïlı ' .
		'qupïya sözder bar:',
	'centralauth-foreign-link' =>
		'$2 degendegi $1 degen qatıswşı',

	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Tüýistirilw bitti',
	'centralauth-finish-text' =>
		'Bul tirkelgiler sizdiki bolsa, qupïya söziderñizdi ' .
		'basqa tirkelgileriñizge kädimgideý mında engizip ' .
		'tirkelgileriñizdi biregeýlendirwin bitirwñizge boladı:',
	'centralauth-finish-password' =>
		'Qupïya söz:',
	'centralauth-finish-login' =>
		'Kirw',
	'centralauth-finish-send-confirmation' =>
		'Qupïya sözdi xatpen jiberw',
	'centralauth-finish-problems' =>
		"Qïın jağdaýğa uşıradıñız, nemese basqa tirkelgiler sizdiki emes? " .
		"[[meta:Help:Unified login problems|Qalaý anıqtalwıñızğa boladı]]...",

	'centralauth-merge-attempt' =>
		"'''Jabdıqtalınğan qupïya sözdi qalğan tüýistirilmegen tirkelgilermen tekserwde…'''",

	// Administrator's console
	'centralauth' => 'Biregeýlendirgen tirkelgini meñgerw',
	'centralauth-admin-manage' =>
		'Qatıswşı derekterin meñgerw',
	'centralauth-admin-username' =>
		'Qatıswşı atı:',
	'centralauth-admin-lookup' =>
		'Qatıswşı derekterin qaraw ne öñdew',
	'centralauth-admin-permission' =>
		"Basqalardıñ tirkelgilerin buğan tek jetekşiler tüýistirileýdi.",
	'centralauth-admin-unmerge' =>
		'Talğanğandı tüýistirileme',
	'centralauth-admin-merge' =>
		'Talğanğandı tüýistirile',
);

$wgCentralAuthMessages['kk-cn'] = array(
	// When not logged in...
	'mergeaccount' =>
		'تٸركەلگٸ بٸرەگەيلەندٸرۋ كٷيٸ',
	'centralauth-merge-notlogged' =>
		'تٸركەلگٸلەرٸڭٸز تولىق بٸرەگەيلەندٸرۋٸن تەكسەرۋ ٷشٸن Please <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special٪3AMergeAccount}} كٸرٸڭٸز]' .
		'</span>.',

	// Big text on completion
	'centralauth-complete' =>
		'تٸركەلگٸ بٸرەگەيلەندٸرۋٸ بٸتتٸ!',
	'centralauth-incomplete' =>
		'تٸركەلگٸ بٸرەگەيلەندٸرۋٸ بٸتكەن جوق!',

	// Wheeee
	'centralauth-complete-text' =>
		'ەندٸ ٵرقايسى Wikimedia قورىنىڭ ۋيكي تورابىنا جاڭا تٸركەلگٸ جاساماستان ' .
		'كٸرۋٸڭٸزگە بولادى; دٵل وسى قاتىسۋشى اتىڭىز بەن قۇپييا سٶزٸڭٸز ' .
		'ۋيكيپەدييا, ۋيكيسٶزدٸك, ۋيكيكٸتٵپ دەگەن جوبالاردا جٵنە بارلىق  ' .
		'تٸلدەردەگٸ تارۋلارىندا قىزمەت ٸستەيدٸ.',
	'centralauth-incomplete-text' =>
		'تٸركەلگٸڭٸز بٸرەگەيلەندٸرٸگەننەن باستاپ ٵرقايسى Wikimedia ' .
		'قورىنىڭ ۋيكي تورابىنا جاڭا تٸركەلگٸ جاساماستان كٸرۋٸڭٸزگە بولادى; ' .
		'دٵل وسى قاتىسۋشى اتىڭىز بەن قۇپييا سٶزٸڭٸز ' .
		'ۋيكيپەدييا, ۋيكيسٶزدٸك, ۋيكيكٸتٵپ, دەگەن جوبالاردا جٵنە بارلىق ' .
		'تٸلدەردەگٸ تارۋلارىندا قىزمەت ٸستەيدٸ.',
	'centralauth-not-owner-text' =>
		'«$1» دەگەن قاتىسۋشى اتى ٶزدٸك تٷردە ' .
		"$2 دەگەن تٸركەلگٸ يەسٸنە تٷيٸستٸرٸلگەن.\n" .
		"\n" .
		"بۇل ٶزٸڭٸز بولساڭىز, باسقى قۇپييا سٶزٸڭٸزدٸ كٵدٸمگٸدەي مىندا ەنگٸزٸپ " .
		"تٸركەلگٸ بٸرەگەيلەندٸرۋ ٷدٸرٸسٸن بٸتٸرۋٸڭٸزگە بولادى:",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|'''بٸرەگەيلەندٸرٸلگەن تٸركەلگٸ''' تۋرالى وقىڭىز]]...''",

	// For lists of wikis/accounts:
	'centralauth-list-attached' =>
		'«$1» دەپ اتالعان تٸركەلگٸلەر كەلەسٸ توراپتاردا ' .
		'ٶزدٸك تٷردە تٷيٸستٸرٸلگەن:',
	'centralauth-list-unattached' =>
		'«$1» دەگەن تٸركەلگٸڭٸز كەلەسٸ توراپتاردا ' .
		'ٶزدٸك تٷردە راستالمادى; ' .
		'بٵلكٸم بۇلاردا باسقى تٸركەلگٸدەن سان-قيلى ' .
		'قۇپييا سٶزدەر بار:',
	'centralauth-foreign-link' =>
		'$2 دەگەندەگٸ $1 دەگەن قاتىسۋشى',

	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'تٷيٸستٸرٸلۋ بٸتتٸ',
	'centralauth-finish-text' =>
		'بۇل تٸركەلگٸلەر سٸزدٸكٸ بولسا, قۇپييا سٶزٸدەرڭٸزدٸ ' .
		'باسقا تٸركەلگٸلەرٸڭٸزگە كٵدٸمگٸدەي مىندا ەنگٸزٸپ ' .
		'تٸركەلگٸلەرٸڭٸزدٸ بٸرەگەيلەندٸرۋٸن بٸتٸرۋڭٸزگە بولادى:',
	'centralauth-finish-password' =>
		'قۇپييا سٶز:',
	'centralauth-finish-login' =>
		'كٸرۋ',
	'centralauth-finish-send-confirmation' =>
		'قۇپييا سٶزدٸ حاتپەن جٸبەرۋ',
	'centralauth-finish-problems' =>
		"قيىن جاعدايعا ۇشىرادىڭىز, نەمەسە باسقا تٸركەلگٸلەر سٸزدٸكٸ ەمەس؟ " .
		"[[meta:Help:Unified login problems|قالاي انىقتالۋىڭىزعا بولادى]]...",

	'centralauth-merge-attempt' =>
		"'''جابدىقتالىنعان قۇپييا سٶزدٸ قالعان تٷيٸستٸرٸلمەگەن تٸركەلگٸلەرمەن تەكسەرۋدە…'''",

	// Administrator's console
	'centralauth' => 'بٸرەگەيلەندٸرگەن تٸركەلگٸنٸ مەڭگەرۋ',
	'centralauth-admin-manage' =>
		'قاتىسۋشى دەرەكتەرٸن مەڭگەرۋ',
	'centralauth-admin-username' =>
		'قاتىسۋشى اتى:',
	'centralauth-admin-lookup' =>
		'قاتىسۋشى دەرەكتەرٸن قاراۋ نە ٶڭدەۋ',
	'centralauth-admin-permission' =>
		"باسقالاردىڭ تٸركەلگٸلەرٸن بۇعان تەك جەتەكشٸلەر تٷيٸستٸرٸلەيدٸ.",
	'centralauth-admin-unmerge' =>
		'تالعانعاندى تٷيٸستٸرٸلەمە',
	'centralauth-admin-merge' =>
		'تالعانعاندى تٷيٸستٸرٸلە',
);

$wgCentralAuthMessages['kk'] = $wgCentralAuthMessages['kk-kz'];


$wgCentralAuthMessages['nl'] = array(
	// When not logged in...
	'mergeaccount' => 'Status samenvoegen gebruikers',
	'centralauth-merge-notlogged' => '<span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} Meld u aan]' .
		'</span> om te controleren of uw gebruikers volledig zijn samengevoegd.',

	// Big text on completion
	'centralauth-complete' => 'Samenvoegen gebruikers afgerond!',
	'centralauth-incomplete' => 'Samenvoegen gebruikers niet volledig!',

	// Wheeee
	'centralauth-complete-text' =>
		'U kunt nu aanmelden bij iedere wiki van Wikimedia zonder een nieuwe gebruiker aan te maken; ' .
		'dezelfde combinatie van gebruikersnaam en wachtwoord werkt voor ' .
		'Wikipedia, Wiktionary, Wikibooks en hun zusterprojecten in alle talen.',
	'centralauth-incomplete-text' =>
		'Als uw gebruikers zijn samengevoegd kunt u aanmelden bij iedere wiki van Wikimedia zonder een nieuwe gebruiker aan te maken; ' .
		'dezelfde combinatie van gebruikersnaam en wachtwoord werkt voor ' .
		'Wikipedia, Wiktionary, Wikibooks en hun zusterprojecten in alle talen.',
	'centralauth-not-owner-text' =>
		'De gebruikersnaam "$1" is automatisch toegewezen aan de eigenaar van de gebruiker ' .
		"op $2.\n" .
		"\n" .
		"Als u dat bent, kunt u het samenvoegen van gebruikers afronden " .
		"door hier het wachtwoord voor die gebruiker in te geven:",

	// Appended to various messages above
	'centralauth-readmore-text' => ":''[[meta:Help:Unified login|Lees meer over '''unified login''']]...''",

	// For lists of wikis/accounts:
	'centralauth-list-attached' => 'De gebruikers met de naam "$1" op de volgende sites zijn automatisch samengevoegd:',
	'centralauth-list-unattached' =>
		'De gebruiker "$1" kon niet automatisch aan u toegewezen worden voor de volgende sites; ' .
		'waarschijnlijk omdat het wachtwoord afwijkt van uw primaire gebruiker:',
	'centralauth-foreign-link' => 'Gebruiker $1 op $2',

	// When not complete, offer to finish...
	'centralauth-finish-title' => 'Samenvoegen afronden',
	'centralauth-finish-text' =>
		'Als deze gebruikers bij u horen, dan kunt u het proces van samenvoegen afronden ' .
		'door de wachtwoorden voor de andere gebruikers hier in te voeren:',
	'centralauth-finish-password' => 'Wachtwoord:',
	'centralauth-finish-login' => 'Gebruikersnaam',
	'centralauth-finish-send-confirmation' => 'E-mail wachtwoord',
	'centralauth-finish-problems' =>
		"Komt u er niet uit of zijn deze gebruikers niet van u? " .
		"[[meta:Help:Unified login problems|Hoe hulp vinden]]...",

	'centralauth-merge-attempt' =>
		"'''Bezig met het controleren van de opgegeven wachtwoorden voor de nog niet samengevoegde gebruikers...'''",

	// Administrator's console
	'centralauth' => 'Beheer unified login',
	'centralauth-admin-manage' => 'Gebruikersgegeven beheren',
	'centralauth-admin-username' => 'Gebruikersnaam:',
	'centralauth-admin-lookup' => 'Gebruikersgegevens bekijken of bewerken',
	'centralauth-admin-permission' => "Alleen stewards kunnen gebruikers van anderen samenvoegen.",
	'centralauth-admin-unmerge' => 'Splits geselecteerde gebruikers',
	'centralauth-admin-merge' => 'Voeg geselecteerde gebruikers samen',

);


$wgCentralAuthMessages['no'] = array(
	'mergeaccount'                         => 'Kontosammensmeltingsstatus',
	'centralauth-merge-notlogged'          => 'Vennligst <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special:MergeAccount}} logg inn]</span> for å sjekke om kontoene dine har blitt fullstendig sammensmeltet.',
	'centralauth-complete'                 => 'Kontosammensmelting fullført!',
	'centralauth-incomplete'               => 'Kontosammensmelting ikke ferdig!',
	'centralauth-complete-text'            => 'Du kan nå logge inn på enhver Wikimedia-wiki uten å opprette en ny konto; samme brukernavn vil fungere på Wikipedia, Wiktionary, Wikibooks og deres søsterprosjekter på alle språk.',
	'centralauth-incomplete-text'          => 'Når du har smeltet sammen kontoene dine, vil du kunne logge inn på enhver Wikimedia-wiki uten å opprette en ny konto; samme brukernavn og passord vil fungere på Wikipedia, Wiktionary, Wikibooks og deres søsterprosjekter på alle språk.',
	'centralauth-not-owner-text'           => 'Brukernavnet «$1» ble automatisk tildelt eieren av kontoen på $2.

Om dette er deg kan du fullføre kontosammensmeltingsprosessen ved å skrive inn hovedpassordet for den kontoen her:',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Les mer om \'\'\'kontosammensmelting\'\'\']]…\'\'',
	'centralauth-list-attached'              => 'Kontoene kalt «$1» på følgende sider har blitt automatisk sammensmeltet:',
	'centralauth-list-unattached'            => 'Kontoen «$1» på følgende sider kunne ikke automatisk stadfestes å være din; de har mest sannsynlig et annet passord enn din hovedkonto:',
	'centralauth-foreign-link'             => 'Bruker $1 på $2',
	'centralauth-finish-title'             => 'Fullfør sammensmelting',
	'centralauth-finish-text'              => 'Om disse kontoene ikke tilhører deg, kan du fullføre kontosammensmeltingen ved å skrive inn passordene for de andre kontoene her:',
	'centralauth-finish-password'          => 'Passord:',
	'centralauth-finish-login'             => 'Logg inn',
	'centralauth-finish-send-confirmation' => 'Send passord på e-post',
	'centralauth-finish-problems'          => 'Har du problemer, eller er ikke disse andre kontoene dine? [[meta:Help:Unified login problems|Hvordan finne hjelp…]]',
	'centralauth-merge-attempt'            => '\'\'\'Sjekker det oppgitte passordet mot gjenværende kontoer…\'\'\'',
	'centralauth'                          => 'Kontosammensmeltingsadministrasjon',
	'centralauth-admin-manage'             => 'Behandle brukerdata',
	'centralauth-admin-username'           => 'Brukernavn:',
	'centralauth-admin-lookup'             => 'Vis eller rediger brukerdata',
	'centralauth-admin-permission'         => 'Kun setwards kan smelte sammen andres kontoer for dem.',
	'centralauth-admin-unmerge'            => 'Skill ut valgte',
	'centralauth-admin-merge'              => 'Smelt sammen valgte',
	'centralauth-admin-bad-input'          => 'Ugyldig flettingsvalg',
	'centralauth-admin-none-selected'      => 'Ingen kontoer valgt for endring.',
);

$wgCentralAuthMessages['oc'] = array(
	'mergeaccount'                         => 'Estatut d’unificacion del compte d\'utilizaire',
	'centralauth-merge-notlogged'          => 'Mercé de plan voler <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} vos connectar]</span> per verificar que vòstres comptes son plan estats acampats.',
	'centralauth-complete'                 => 'Unificacion del compte acabada !',
	'centralauth-incomplete'               => 'Unificacion del compte pas acabada !',
	'centralauth-complete-text'            => 'Ara podètz vos connectar sus un sit Wikimedia qué qué siá sens aver de crear un compte novèl; lo meteis nom d’utilizaire e senhal foncionan sus Wikipèdia, Wikiccionari, Wikilibres e lors projèctes sòrres, aquò per totas las lengas.',
	'centralauth-incomplete-text'          => 'Un còp vòstre compte unificat, poirètz vos connectar sus un sit Wikimedia qué qué siá sens aver de crear un compte novèl ; lo meteis nom d’utilizaire e senhal foncionaràn sus Wikipèdia, Wikiccionari, Wikilibres e lors projèctes sòrres, aquò per totas las lengas.',
	'centralauth-not-owner-text'           => 'Lo compte d\'utilizaire « $1 » es estat automaticament assignat al proprietari del compte sus $2.

Se es vos, poirètz acabar lo procediment d’unificacion de compte en picant lo senhal mèstre per aqueste compte sus :',
	'centralauth-readmore-text'            => ':\'\'[[w:oc:Wikipèdia:Login unic|Ne saber mai sul \'\'\'compte unificat\'\'\']]...\'\'',
	'centralauth-list-attached'              => 'Los comptes d\'utilizaires nomenats « $1 » son estats acampats pels sites seguents :',
	'centralauth-list-unattached'            => 'Lo compte d\'utilizaire « $1 » pòt èsser confirmat automaticament pels sites que seguisson ; an probablament un senhal diferent de vòstre compte mèstre :',
	'centralauth-foreign-link'             => 'Utilizaire $1 sus $2',
	'centralauth-finish-title'             => 'Completar l’unificacion',
	'centralauth-finish-text'              => 'Se aquestes comptes vos apartenon, podètz acabar lor unificacion en picant los senhals çai jos :',
	'centralauth-finish-password'          => 'Senhal:',
	'centralauth-finish-login'             => 'Compte d\'utilizaire:',
	'centralauth-finish-send-confirmation' => 'Mandar lo senhal per corrièr electronic',
	'centralauth-finish-problems'          => 'En cas de problèma o se possedissètz pas aquestes autres comptes, vejatz la pagina [[meta:Help:Unified login problems|Problèmas]] (en anglés)...',
	'centralauth-merge-attempt'            => '\'\'\'Verificacion del senhal provesit pels comptes non acampats...\'\'\'',
	'centralauth'                          => 'Administracion comptes unificats',
	'centralauth-admin-manage'             => 'Admministrar las donadas d\'utilizaire',
	'centralauth-admin-username'           => 'Nom d\'utilizaire:',
	'centralauth-admin-lookup'             => 'Veire o modificar las donadas d\'utilizaire',
	'centralauth-admin-permission'         => 'Sols los stewards pòdon acampar los comptes d’autras personas a lor plaça.',
	'centralauth-admin-unmerge'            => 'Separar la seleccion',
	'centralauth-admin-merge'              => 'Acampar la seleccion',
);

$wgCentralAuthMessages['pms'] = array(
	'mergeaccount'                         => 'Stat dël process dla mës-cia dë stranòm e ciav',
	'centralauth-merge-notlogged'          => 'Për piasì <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} ch\'a rintra ant ël sistema]</span> për controlé che sò cont a sio stait mës-cià coma ch\'as dev.',
	'centralauth-complete'                 => 'Mës-cia dij cont bele faita!',
	'centralauth-incomplete'               => 'Mës-cia djë stranòm e dle ciav bele faita!',
	'centralauth-complete-text'            => 'Adess a peul rintré an qualsëssìa sit dla Wikimedia (ëd coj ch\'a travajo col programa dla wiki) sensa da manca dë deurb-se un cont; la midema cobia dë stranòm a ciav a travajo an qualsëssìa Wikipedia, Wiktionary, Wikibooks e ant sj\'àotri proget soe seur an qualsëssìa lenga.',
	'centralauth-incomplete-text'          => 'Na vira che sò stranòm e ciav a sio stait mës-cià a podrà rintré an qualsëssìa sit dla Wikimedia (ëd coj ch\'a travajo col programa dla wiki) sensa pa da manca dë deurb-se un cont neuv; la midema cobia dë stranòm e ciav a travajeran ant tute le Wikipedia, Wiktionary, Wikibooks e sò proget seur an qualsëssìa lenga.',
	'centralauth-not-owner-text'           => 'Lë stranòm "$1" e l\'é stait dait n\'aotomàtich al proprietari dël cont ansima a $2.

Se as trata ëd chiel/chila, a peul mandé a bon fin ël process dla mës-cia dë stranòm e ciav ën butand-ie ambelessì la ciav prinsipal dël cont:',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Për savejne dë pì, ch\'a varda \'\'\'Stranòm e ciav globaj\'\'\']]...\'\'',
	'centralauth-list-attached'              => 'Ij cont ch\'as ës-ciamo "$1" ansima a ij sit dla lista ambelessì sota a son stait mës-cià antra lor n\'aotomàtich:',
	'centralauth-list-unattached'            => 'Ant ij sit dla lista ambelessì sota ël cont "$1" a l\'é pa podusse confermé coma sò n\'aotomàtich; a l\'é belfé ch\'a-i sio dle ciav diferente da cola ëd sò cont prinsipal:',
	'centralauth-foreign-link'             => 'Stranòm $1 ansima a $2',
	'centralauth-finish-title'             => 'Finiss la mës-cia',
	'centralauth-finish-text'              => 'Se sti cont-sì a son sò, a peul andé a bon fin dël process ëd mës-cia dë stranòm e ciav mach ën butand-ie le ciav dj\'àotri cont ambelessì sota:',
	'centralauth-finish-password'          => 'Ciav:',
	'centralauth-finish-login'             => 'Rintré ant ël sistema',
	'centralauth-finish-send-confirmation' => 'Mandé la ciav për pòsta eletrònica',
	'centralauth-finish-problems'          => 'Ha-lo dle gran-e, ò pura l\'é-lo pa chiel/chila ël titolar d\'ës cont-sì? Ch\'a varda [[meta:Help:Unified login problems|coma trové d\'agiut]]...',
	'centralauth-merge-attempt'            => '\'\'\'I soma antramentr che i controloma le ciav ch\'a l\'ha butà con cole dij cont anco\' da mës-cé...\'\'\'',
	'centralauth'                          => 'Aministrassion unificà dj\'intrade ant ël sistema',
	'centralauth-admin-manage'             => 'Gestion dij dat dl\'utent',
	'centralauth-admin-username'           => 'Stranòm:',
	'centralauth-admin-lookup'             => 'Vardé ò modifiché ij dat dl\'utent',
	'centralauth-admin-permission'         => 'Mach ij vardian a peulo mës-cé ëd cont d\'àotra gent.',
	'centralauth-admin-unmerge'            => 'Dasmës-cia selessionà',
	'centralauth-admin-merge'              => 'Mës-cia selessionà',
	'centralauth-admin-bad-input'          => 'La selession dla mës-cia a l\'é pa giusta.',
	'centralauth-admin-none-selected'      => 'Pa gnun cont da modifiché selessionà.',
	'centralauth-prefs-status'             => 'Stat dël cont global:',
	'centralauth-prefs-not-managed'        => 'A dòvra nen ël cont mës-cià',
	'centralauth-prefs-unattached'         => 'Pa confermà',
	'centralauth-prefs-complete'           => 'Gnun-a gran-a!',
	'centralauth-prefs-migration'          => 'Antramentr ch\'as fa la migrassion',
	'centralauth-prefs-count-attached'     => 'Sò cont a travaj ansima a ij sit dij proget $1.',
	'centralauth-prefs-count-unattached'   => 'A resto dij cont nen confermà con sò stranòm ansima a ij proget $1.',
	'centralauth-prefs-detail-unattached'  => 'Ës sit-sì a l\'é pa restà confermà coma bon për sò cont global.',
	'centralauth-prefs-manage'             => 'Gestion ëd sò cont global',
);

$wgCentralAuthMessages['ru'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Состояние объединения учётных записей',
	'centralauth-merge-notlogged' =>
		'Пожалуйста, <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} представьтесь]' .
		'</span>, чтобы проверить, были ли ваши учётные записи объединены.',

	// Big text on completion
	'centralauth-complete' =>
		'Объединение учётных записей завершено!',
	'centralauth-incomplete' =>
		'Объединение учётных записей не завершено!',

	// Wheeee
	'centralauth-complete-text' =>
		'Вы можете сейчас представляться любому сайту Викимедиа, без создания ' .
		'новой учётной записи. Одни и те же имя участника и пароль будут работать в '.
		'Википедии, Викисловаре, Викиучебнике и других проектах ' .
		'на всех языках.',
	'centralauth-incomplete-text' =>
		'Как только ваша учётная запись будет объединена, вы сможете представляться ' .
		'на любых проектах Викимедии не создавая новых учётных записей. ' .
		'Одни и те же имя участника и пароль будут работать в ' .
		'Википедии, Викисловаре, Викиучебнике и других проектах ' .
		'на всех языках.',
	'centralauth-not-owner-text' =>
		'Имя «$1» было автоматически передано владельцу ' .
		"учётной записи «$2».\n" .
		"\n" .
		"Если это вы, то вы можете завершить процесс объединения учётных записей " .
		"введя здесь основной пароль этой учётной записи:",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Информация об '''объединении учётных записей''']]...''",

	// For lists of wikis/accounts:
	'centralauth-list-attached' =>
		'Учётная запись «$1» на следующих сайтах ' .
		'была автоматически объединена:',
	'centralauth-list-unattached' =>
		'Принадлежность вам учётной записи «$1» не может быть автоматически подтверждено ' .
		'на указанных ниже сайтах; ' .
		'вероятно, пароль на них не совдает с паролем вашей ' .
		'основной учётной записи:',
	'centralauth-foreign-link' =>
		'Пользователь $1 на $2',

	// When not complete, offer to finish...
	'centralauth-finish-title' =>
		'Окончание объединения',
	'centralauth-finish-text' =>
		'Если эти учётные записи принадлежат вам, то вы можете завершить ' .
		'процесс объединения, введя здесь пароли  ' .
		'для других учётных записей:',
	'centralauth-finish-password' =>
		'Пароль:',
	'centralauth-finish-login' =>
		'Имя пользователя',
	'centralauth-finish-send-confirmation' =>
		'Выслать пароль по эл. почте',
	'centralauth-finish-problems' =>
		"Если возникли проблемы, или вы не являетесь владельцем указанных учётных записей " .
		"[[meta:Help:Unified login problems|обратитель к справочной информации]]...",

	'centralauth-merge-attempt' =>
		"'''Проверка введённого пароля на оставшихся необъединённых учётных записях...'''",

	// Administrator's console
	'centralauth' => 'Администрирование объединения имён',
	'centralauth-admin-manage' =>
		'Управление информацией об участниках',
	'centralauth-admin-username' =>
		'Имя участника:',
	'centralauth-admin-lookup' =>
		'Просмотр или редактирование информации об участнике',
	'centralauth-admin-permission' =>
		"Только стюарды могут объединять учётные записи других людей.",
	'centralauth-admin-unmerge' =>
		'Разделить выбранные',
	'centralauth-admin-merge' =>
		'Объединить выбранные',
);

$wgCentralAuthMessages['sk'] = array(
	'mergeaccount'                         => 'Stav zjednotenia prihlasovacích účtov',
	'centralauth-merge-notlogged'          => 'Prosím, <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} prihláste sa]</span>, aby ste mohli skontrolovať, či sú vaše účty celkom zjednotené.',
	'centralauth-complete'                 => 'Zjednotenie prihlasovacích účtov dokončené!',
	'centralauth-incomplete'               => 'Zjednotenie prihlasovacích účtov nebolo dokončené!',
	'centralauth-complete-text'            => 'Teraz sa môžete prihlásiť na ľubovoľnú wiki nadácie Wikimedia bez toho, aby ste si museli vytvárať nový účet; rovnaké užívateľské meno a heslo bude fungovať na projektoch Wikipedia, Wiktionary, Wikibooks a ďalších sesterských projektoch vo všetkých jazykoch.',
	'centralauth-incomplete-text'          => 'Potom, ako budú vaše účty zjednotené sa budete môcť prihlásiť na ľubovoľnú wiki nadácie Wikimedia bez toho, aby ste si museli vytvárat ďalší účet; rovnaké užívateľské meno a heslo bude fungovať na projektoch Wikipedia, Wiktionary, Wikibooks a ďalších sesterských projektoch vo všetkých jazykoch.',
	'centralauth-not-owner-text'           => 'Užívateľské meno "$1" bolo automaticky priradené vlastníkovi účtu na projekte $2.

Ak ste to vy, môžete dokončiť proces zjednotenia účtov jednoducho napísaním hesla pre uvedený účet sem:',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Prečítajte si viac o \'\'\'zjednotení prihlasovacích účtov\'\'\']]...\'\'',
	'centralauth-list-attached'              => 'Účty z názvom "$1" na nasledujúcich projektoch boli automaticaticky zjednotené:',
	'centralauth-list-unattached'            => 'Nebolo možné automaticky potvrdiť, že účet "$1" na nasledujúcich projektoch patrí vám; pravdepodobne má odlišné heslo ako váš primárny účet:',
	'centralauth-foreign-link'             => 'Užívateľ $1 na $2',
	'centralauth-finish-title'             => 'Dokončiť zjednotenie',
	'centralauth-finish-text'              => 'Ak tieto účty naozaj patria vám, môžete skončiť proces zjednotenia jednoducho napísaním hesiel dotyčných účtov:',
	'centralauth-finish-password'          => 'Heslo:',
	'centralauth-finish-login'             => 'Prihlasovacie meno',
	'centralauth-finish-send-confirmation' => 'Zaslať heslo emailom',
	'centralauth-finish-problems'          => 'Máte problém alebo nie ste vlastníkom týchto účtov? [[meta:Help:Unified login problems|Ako hľadat pomoc]]...',
	'centralauth-merge-attempt'            => '\'\'\'Kontrolujem poskytnuté heslá voči zostávajúcim zatiaľ nezjednoteným účtom...\'\'\'',
	'centralauth'                          => 'Administrácia zjednoteného prihlasovania',
	'centralauth-admin-manage'             => 'Správa údajov o používateľoch',
	'centralauth-admin-username'           => 'POužívateľské meno:',
	'centralauth-admin-lookup'             => 'Zobraziť alebo upravovať údaje o používateľovi',
	'centralauth-admin-permission'         => 'Iba stewardi môžu za druhých ľudí zlučovať ich účty.',
	'centralauth-admin-unmerge'            => 'Oddelenie zvolených',
	'centralauth-admin-merge'              => 'Zlúčenie zvolených',
	'centralauth-admin-bad-input'          => 'Neplatný výber pre zlúčenie',
	'centralauth-admin-none-selected'      => 'Neboli vybrané účty, ktoré sa majú zmeniť.',
	'centralauth-prefs-status'             => 'Globálny stav účtu:',
	'centralauth-prefs-not-managed'        => 'Nepoužíva zjednotený účet',
	'centralauth-prefs-unattached'         => 'Nepotvrdené',
	'centralauth-prefs-complete'           => 'Všetko v poriadku!',
	'centralauth-prefs-migration'          => 'Prebieha migrácia',
	'centralauth-prefs-count-attached'     => 'Váš účet je aktívny na $1 projektoch.',
	'centralauth-prefs-count-unattached'   => 'Nepotvrdené účty s vašim menom zostávajú na $1 projektoch.',
	'centralauth-prefs-detail-unattached'  => 'Nebolo potvrdené, že účet na tomto projekte patrí ku globálnemu účtu.',
	'centralauth-prefs-manage'             => 'Spravovať váš globálny účet',
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
	'centralauth-list-attached' =>
		'Налог "$1" на следећим сајтовима ' .
		'је аутоматски спојен:',
	'centralauth-list-unattached' =>
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
	'centralauth-list-attached' =>
		'Nalog "$1" na sledećim sajtovima ' .
		'je automatski spojen:',
	'centralauth-list-unattached' =>
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
	'centralauth-list-attached' =>
		'A conta nomeada como "$1" nos seguintes sítios ' .
		'foram automaticamente fundidos:',
	'centralauth-list-unattached' =>
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
	'centralauth-admin-bad-input' =>
		'Selecção para fusão inválida',
	'centralauth-admin-none-selected' =>
		'Não foram seleccionadas contas a serem modificadas.',
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
	'centralauth-list-attached' =>
		'A conta nomeada como "$1" nos seguintes sítios ' .
		'foram automaticamente fundidos:',
	'centralauth-list-unattached' =>
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
	'centralauth-admin-bad-input' =>
		'Selecção para fusão inválida',
	'centralauth-admin-none-selected' =>
		'Não foram seleccionadas contas a serem modificadas.',
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
	'centralauth-list-attached' =>
		'Die Benutzerkonten mit dem Namen „$1“ auf den folgenden Projekten wurden automatisch ' .
		' zusammengeführt: ',

	'centralauth-list-unattached' =>
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

$wgCentralAuthMessages['su'] = array(
	'mergeaccount'                         => 'Status ngahijikeun log asup',
	'centralauth-merge-notlogged'          => 'Mangga <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} lebet log]</span> pikeun mariksa anggeus/henteuna rekening anjeun dihijieun.',
	'centralauth-complete'                 => 'Ngahijikeun log asup geus réngsé!',
	'centralauth-incomplete'               => 'Ngahijikeun log asup can anggeus!',
	'centralauth-complete-text'            => 'Ayeuna anjeun bisa asup log ka loka wiki Wikimédia tanpa kudu nyieun rekening anyar; ladihan pamaké katut sandina bisa dipaké dina Wikipédia, Wikikamus, Wikipustaka, sarta proyék sawargina dina basa séjén.',
	'centralauth-incomplete-text'          => 'Mun log asupna geus dihijikeun, anjeun bakal bisa asup log ka loka wiki Wikimédia mana waé tanpa kudu nyieun rekening anyar; landihan pamaké katut sandina bakal bisa dipaké dina Wikipédia, Wikikamus, Wikipustaka, sarta proyék sawargina dina basa séjén.',
	'centralauth-not-owner-text'           => 'Landihan pamaké "$1" geus diajangkeun ka rekening di $2.

Mun éta téh anjeun, anjeun bisa nganggeuskeun prosés ngahijikeun log asup ku cara ngetikkeun sandi master pikeun éta rekening di dieu:',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Baca lengkepna ngeunaan \'\'\'log asup nu dihijikeun\'\'\']]...\'\'',
	'centralauth-list-attached'              => 'Rekening nu ngaranna "$1" di loka di handap ieu geus sacara otomatis dihijikeun:',
	'centralauth-list-unattached'            => 'Rekening "$1" teu bisa otomatis dikompirmasi milik anjeun di loka di handap ieu; sigana mah kusabab sandina béda jeung sandi dina rekening utama anjeun:',
	'centralauth-foreign-link'             => 'Pamaké $1 di $2',
	'centralauth-finish-title'             => 'Réngsé ngahijikeun',
	'centralauth-finish-text'              => 'Mun rekening ieu bener boga anjeun, mangga réngsékeun prosés ngahijikeun log asup ku cara ngasupkeun sandi rekening lianna di dieu:',
	'centralauth-finish-password'          => 'Sandi:',
	'centralauth-finish-login'             => 'Asup log',
	'centralauth-finish-send-confirmation' => 'Kirimkeun sandi kana surélék',
	'centralauth-finish-problems'          => 'Aya masalah? Teu boga rekening lianna ieu? [[meta:Help:Unified login problems|Ménta pitulung]]...',
	'centralauth-merge-attempt'            => '\'\'\'Ngakurkeun sandi nu disadiakeun jeung rekening nu can dihijikeun...\'\'\'',
	'centralauth'                          => 'Administrasi log asup nu dihijikeun',
	'centralauth-admin-manage'             => 'Kokolakeun data pamaké',
	'centralauth-admin-username'           => 'Landihan pamaké:',
	'centralauth-admin-lookup'             => 'Témbongkeun atawa robah data pamaké',
	'centralauth-admin-permission'         => 'Nu bisa ngahijikeun rekening batur mah ngan steward.',
	'centralauth-admin-unmerge'            => 'Pisahkeun nu dipilih',
	'centralauth-admin-merge'              => 'Hijikeun nu dipilih',
);

$wgCentralAuthMessages['yue'] = array(
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
	'centralauth-list-attached' =>
		'以下用戶名 "$1" 嘅戶口' .
		'已經自動噉樣合併咗：',
	'centralauth-list-unattached' =>
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

$wgCentralAuthMessages['zh-hans'] = array(
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
	'centralauth-list-attached' =>
		'以下网站的账号“$1”' .
		'已自动合并：',
	'centralauth-list-unattached' =>
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

$wgCentralAuthMessages['zh-hant'] = array(
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
	'centralauth-list-attached' =>
		'以下網站的帳號："$1' .
		'已自動完成整合：',
	'centralauth-list-unattached' =>
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
	'centralauth-list-attached' =>
		'以下網站的帳號："$1' .
		'已自動完成整合：',
	'centralauth-list-unattached' =>
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
$wgCentralAuthMessages['zh-cn'] = $wgCentralAuthMessages['zh-hans'];
$wgCentralAuthMessages['zh-hk'] = $wgCentralAuthMessages['zh-hant'];
$wgCentralAuthMessages['zh-sg'] = $wgCentralAuthMessages['zh-hans'];
$wgCentralAuthMessages['zh-yue'] = $wgCentralAuthMessages['yue'];



