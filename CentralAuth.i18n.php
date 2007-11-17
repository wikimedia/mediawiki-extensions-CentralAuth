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
		"with them or an administrator later.",
	
	'centralauth-merge-step1-title' => 'Begin login unification',
	'centralauth-merge-step1-detail' =>
		'Your password and registered e-mail address will be checked against ' .
		'the accounts on other wikis to confirm that they match. ' .
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

	# When unattached, offer to attach...
	'centralauth-attach-list-attached'     => 'The unified account named "$1" includes the following accounts:',
	'centralauth-attach-title'             => 'Confirm account',
	'centralauth-attach-text'              => 'This account has not yet been migrated to the unified account. If the global account is yours too, you can merge this account if you type the global account password:',
	'centralauth-attach-submit'            => 'Migrate account',
	'centralauth-attach-success'           => 'The account was migrated to the unified account.',

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
		'Your account is active on $1 project {{plural:$1|site|sites}}.',
	'centralauth-prefs-count-unattached' =>
		'Unconfirmed accounts with your name remain on $1 {{plural:$1|project|projects}}.',
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

$wgCentralAuthMessages['ang'] = array(
	'centralauth-admin-username'           => 'Brūcendnama:',
);

/* Arabic (Meno25) */
$wgCentralAuthMessages['ar'] = array(
	'mergeaccount'                         => 'حالة توحيد الدخول',
	'centralauth-merge-notlogged'          => 'من فضلك <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} قم بتسجيل الدخول]</span> لتتحقق من أن حساباتك تم دمجها بالكامل.',
	'centralauth-merge-welcome'            => '\'\'\'حساب المستخدم الخاص بك لم يتم نقله إلى نظام ويكيميديا لتوحيد الدخول.\'\'\'

إذا اخترت دمج حساباتك، سيمكنك استخدام نفس اسم المستخدم و كلمة السر للدخول لكل مشاريع ويكيميديا بكل اللغات المتوفرة.
هذا يجعل من السهل العمل مع المشاريع المشتركة مثل الرفع ل [http://commons.wikimedia.org/ ويكيميديا كومنز]، و يتجنب الارتباك أو التعارض الذي قد ينشأ عندما يستخدم شخصان نفس اسم المستخدم في مشاريع مختلفة.

لو كان شخص آخر أخذ اسم المستخدم الخاص بك في موقع آخر هذا لن يزعجهم، ولكن سيعطيك فرصة للتعامل معهم أو مع إداري فيما بعد.',
	'centralauth-merge-step1-title'        => 'ابدأ توحيد الدخول',
	'centralauth-merge-step1-detail'       => 'كلمة السر الخاصة بك وبريدك الإلكتروني المسجل سيتم فحصهما مع الحسابات في مواقع الويكي الأخرى للتأكد من أنهما يتطابقان. لن يتم عمل أي تغييرات حتى تؤكد أن الأمور على ما يرام.',
	'centralauth-merge-step1-submit'       => 'أكد معلومات الدخول',
	'centralauth-merge-step2-title'        => 'أكد مزيد من الحسابات',
	'centralauth-merge-step2-detail'       => 'بعض الحسابات لم يمكن مطابقتها تلقائيا لموقع الويكي الرئيسي المعين. لو أن هذه الحسابات تنتمي لك، يمكنك تأكيد ذلك عن طريق توفير كلمة السر لهم.',
	'centralauth-merge-step2-submit'       => 'أكد معلومات الدخول',
	'centralauth-merge-step3-title'        => 'أنشىء الحساب الموحد',
	'centralauth-merge-step3-detail'       => 'أنت جاهز لإنشاء حسابك الموحد، وستكون مواقع الويكي التالية ملحقة به:',
	'centralauth-merge-step3-submit'       => 'وحد الحسابات',
	'centralauth-complete'                 => 'توحيد الدخول اكتمل!',
	'centralauth-incomplete'               => 'توحيد الدخول لم يكتمل!',
	'centralauth-complete-text'            => 'يمكنك الآن الدخول لأي مشروع من مشاريع ويكيميديا بدون إنشاء حساب جديد؛ نفس اسم المستخدم وكلمة السر ستعمل في ويكيبيديا وويكاموس وويكي الكتب ومشاريعهم الشقيقة بكل اللغات.',
	'centralauth-incomplete-text'          => 'عندما يتم توحيد دخولك، يمكنك الدخول لأي مشروع من مشاريع ويكيميديا بدون إنشاء حساب جديد؛ نفس اسم المستخدم وكلمة السر ستعمل في ويكيبيديا وويكاموس وويكي الكتب ومشاريعهم الشقيقة بكل اللغات.',
	'centralauth-not-owner-text'           => 'اسم المستخدم "$1" تم إعطاؤه تلقائيا لمالك الحساب على $2.

لو كان هذا أنت، يمكنك إنهاء عملية توحيد الدخول ببساطة بكتابة كلمة السر الرئيسية لذلك الحساب هنا:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>نمط التجربة فقط</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'توحيد الحساب حاليا في طور التجربة/تصحيح الأخطاء، لذا عمليات الدمج الفعلية معطلة. عذرا!',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|اقرأ المزيد حول \'\'\'الدخول الموحد\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'موقع الويكي الرئيسي',
	'centralauth-list-home-dryrun'         => 'كلمة السر وعنوان البريد الإلكتروني المحدد في هذه الويكي سيتم استخدامها لحسابك الموحد، وصفحة المستخدم الخاصة بك هنا سيتم تلقائيا وصلها من مواقع الويكي الأخرى. سيمكنك تغيير أيها هي موقع الويكي الرئيسي الخاص بك فيما بعد.',
	'centralauth-list-attached-title'      => 'الحسابات الملحقة',
	'centralauth-list-attached'            => 'الحساب المسمى "$1" على كل من المواقع التالية تم إلحاقه تلقائيا بالحساب الموحد:',
	'centralauth-list-attached-dryrun'     => 'الحساب المسمى "$1" على كل من المواقع التالية سيتم إلحاقها تلقائيا للحساب الموحد:',
	'centralauth-list-unattached-title'    => 'حسابات غير ملحقة',
	'centralauth-list-unattached'          => 'الحساب "$1" لم يمكن تأكيده تلقائيا كمنتمي لك في المواقع التالية؛ على الأرجح لأنهم يمتلكون كلمة سر مختلفة عن حسابك الأساسي:',
	'centralauth-foreign-link'             => 'المستخدم $1 في $2',
	'centralauth-finish-title'             => 'انتهاء الدمج',
	'centralauth-finish-text'              => 'لو كانت هذه الحسابات تنتمي لك, يمكنك إنهاء عملية توحيد الدخول ببساطة بكتابة كلمات السر للحسابات الأخرى هنا:',
	'centralauth-finish-password'          => 'كلمة السر:',
	'centralauth-finish-login'             => 'دخول',
	'centralauth-finish-send-confirmation' => 'أرسل كلمة السر عبر البريد الإلكتروني',
	'centralauth-finish-problems'          => 'لديك مشكلة، أو لا تمتلك هذه الحسابات الأخرى؟ [[meta:Help:Unified login problems|كيف تجد المساعدة]]...',
	'centralauth-merge-attempt'            => '\'\'\'التحقق من كلمة السر المعطاة ضد الحسابات الباقية غير المدمجة...\'\'\'',
	'centralauth-attach-list-attached'     => 'الحساب الموحد المسمى "$1" يتضمن الحسابات التالية:',
	'centralauth-attach-title'             => 'تأكيد الحساب',
	'centralauth-attach-text'              => 'هذا الحساب لم يتم دمجه بعد مع الحساب الموحد. لو أن الحساب العام ملكك أيضا، يمكنك دمج هذا الحساب لو كتبت كلمة سر الحساب العام:',
	'centralauth-attach-submit'            => 'دمج الحساب',
	'centralauth-attach-success'           => 'الحساب تم دمجه مع الحساب الموحد.',
	'centralauth'                          => 'إدارة الدخول الموحد',
	'centralauth-admin-manage'             => 'إدارة بيانات المستخدم',
	'centralauth-admin-username'           => 'اسم المستخدم:',
	'centralauth-admin-lookup'             => 'عرض أو تعديل بيانات المستخدم',
	'centralauth-admin-permission'         => 'فقط المضيفون يمكنهم أن يدمجوا حسابات الآخرين.',
	'centralauth-admin-unmerge'            => 'تم اختيار الفصل',
	'centralauth-admin-merge'              => 'تم اختيار الدمج',
	'centralauth-admin-bad-input'          => 'اختيار دمج غير صحيح',
	'centralauth-admin-none-selected'      => 'لم يتم اختيار حسابات للدمج',
	'centralauth-prefs-status'             => 'حالة الحساب العام:',
	'centralauth-prefs-not-managed'        => 'لا يستخدم الحساب الموحد',
	'centralauth-prefs-unattached'         => 'غير مؤكد',
	'centralauth-prefs-complete'           => 'الكل في ترتيب!',
	'centralauth-prefs-migration'          => 'في الهجرة',
	'centralauth-prefs-count-attached'     => 'حسابك نشط في $1 مشروع.',
	'centralauth-prefs-count-unattached'   => 'حسابات غير مؤكدة باسمك موجودة في $1 مشروع.',
	'centralauth-prefs-detail-unattached'  => 'موقع المشروع هذا لم يتم تأكيده كمنتمي للحساب العام.',
	'centralauth-prefs-manage'             => 'أدر حسابك العام',
	'centralauth-renameuser-abort'         => '<div class="errorbox">لا يمكن إعادة تسمية المستخدم $1 محليا حيث أن اسم المستخدم هذا تم نقله إلى نظام الدخول الموحد.</div>',
);

$wgCentralAuthMessages['de'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Status der Benutzerkonten-Zusammenführung',
	'centralauth-merge-notlogged' =>
		'Bitte <span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} melde dich an], ' .
		'</span> um zu prüfen, ob deine Benutzerkonten vollständig zusammengeführt wurden.',

	'centralauth-merge-welcome' =>
		"'''Dein Benutzerkonto wurde noch nicht in das globale Wikimedia-Anmelde-System überführt" .
		"\n" .
		"Falls du dich für eine Migration deines Benutzerkontos entscheidest, wird es dir möglich sein, " .
		"dich mit einem gemeinsamen Benutzernamen und Passwort in alle Wikimedia-Projekte in allen " .
		"verfügbaren Sprachen anzumelden.\n" .
		"Dies macht die Arbeit in gemeinsam genutztzen Projekten einfacher, z.B. das Hochladen von Dateien nach " .
		"[http://commons.wikimedia.org/ Wikimedia Commons] und vermeidet Verwirrungen und Konflikte," .
		"die entstehen können, wenn zwei Menschen den selben Benutzernamen in verschiedenen Projekten benutzen.\n" . 
		"\n" .
		"Wenn jemand anderes deinen Benutzernamen bereits in einem anderen Projekt benutzt, " .
		"so beeinträchtigt es diesen nicht, aber du hast du Möglichkeit, " .
		"später mit diesem anderen Benutzer oder in Zusammenarbeit mit einem Administrator nach einer Lösung zu suchen.",
	
	'centralauth-merge-step1-title' => 'Beginn der Benutzerkonten-Zusammenführung',
	'centralauth-merge-step1-detail' =>
		'Dein Passwort und deine eingetragene E-Mail-Adresse wird mit ' .
		'Benutzerkonten in den anderes Wikis abgeglichen, um Übereinstimmungen zu finden. ' .
		'Es werden keine Änderungen vorgenommen, bis du bestätigst, dass alles richtig ist.',
	'centralauth-merge-step1-submit' =>
		'Anmelde-Informationen bestätigen',

	'centralauth-merge-step2-title' => 'Bestätige weitere Benutzerkonten',
	'centralauth-merge-step2-detail' =>
		"Einige der Benutzerkonten konnten nicht automatisch deinem Heimat-Wiki zugerechnet werden. " .
		"Wenn diese Konten dir gehören, kannst du dies bestätigen, indem du das Passwort zu diesen Konten eingibst.\n",
	'centralauth-merge-step2-submit' =>
		'Anmelde-Informationen bestätigen',

	'centralauth-merge-step3-title' => 'Erzeuge globales Benutzerkonto',
	'centralauth-merge-step3-detail' =>
		"Du kannst nun ein globales Benutzerkonto für die folgenden Wikis erezugen. ",
	'centralauth-merge-step3-submit' =>
		'Benutzerkonten zusammenführen',


	// Big text on completion
	'centralauth-complete'   => 'Die Zusammenführung der Benutzerkonten ist vollständig.',
	'centralauth-incomplete' => 'Die Zusammenführung der Benutzerkonten ist unvollständig!',

	// Wheeee
	'centralauth-complete-text' =>
		'Du kannst dich nun auf jeder Wikimedia-Webseite anmelden ' .
		'ohne ein neues Benutzerkonto anzulegen; ' .
		'derselbe Benutzername und dasselbe Passwort ist für Wikipedia, ' .
		'Wiktionary, Wikibooks und alle Schwesterprojekte '.
		'in allen Sprachen gültig.',

	'centralauth-incomplete-text' =>
		'Sobald deine Benutzerkonten zusammengeführt sind, ' .
		'kannst du sich auf jeder Wikimedia-Webseite anmelden ohne ein ' .
		'neues Benutzerkonto anzulegen; derselbe Benutzernamen ' .
		'und dasselbe Passwort ist für Wikipedia, Wiktionary, ' .
		'Wikibooks und alle Schwesterprojekte in allen Sprachen gültig.',

	'centralauth-not-owner-text' =>
		'Der Benutzername „$1“ wurde automatisch dem Eigentümer ' .
		"des Benutzerkontos auf $2 zugewiesen.\n" .
		"\n" .
		"Wenn dies dein Benutzername ist, kannst du die Zusammenführung " .
		"der Benutzerkonten durch Eingabe des Haupt-Passwortes".
		"für dieses Benutzerkonto vollenden:",

	'centralauth-notice-dryrun' =>
		"<div class='successbox'>Demonstrationsmodus</div><br clear='all'/>",

	'centralauth-disabled-dryrun' =>
		"Die Benutzerkonto-Zusammenführung befindet sich gegenwärtig in einem Demonstrations/Fehlersuch-Modus. " .
		"Zusammenführungs-Aktionen sind deaktiviert.",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|Informationen über die '''Zusammenführung der Benutzerkonten''']] …''",

	// For lists of wikis/accounts:
	'centralauth-list-home-title' =>
		'Heimat-Wiki',
	'centralauth-list-home-dryrun' =>
		'Das Passwort und die E-Mail-Adresse, die du in diesem Wiki eingetragen hast, wird für die Zusammenführung der Benutzerkonten verwendet ' .
		'und deine Benutzerseite wird automatisch von den anderen Wikis verlinkt. ' .
		"Du kannst später dein Heimat-Wiki noch ändern.",
	'centralauth-list-attached-title' =>
		'Zusammengeführte Benutzerkonten',
	'centralauth-list-attached' =>
		'Die Benutzerkonten mit dem Namen „$1“ auf den folgenden ' .
		'Projekten werden automatisch zusammengeführt:',
	'centralauth-list-attached-dryrun' =>
		'Die Benutzerkonten mit dem Namen „$1“ auf den folgenden ' .
		'Projekten werden automatisch zusammengeführt:',
	'centralauth-list-unattached-title' =>
		'Nicht zusammengeführte Benutzerkonten',
	'centralauth-list-unattached' =>
		'Das Benutzerkonto „$1“ konnte für die folgenden Projekte ' .
		'nicht automatisch als zu dir gehörend bestätigt werden; ' .
		'vermutlich hat es ein anderes Passwort ' .
		'als dein primäres Benutzerkonto:',
	'centralauth-foreign-link' =>
		'Benutzer $1 auf $2',

	// When not complete, offer to finish...
	'centralauth-finish-title'             => 'Zusammenführung vollenden',
	'centralauth-finish-text'              => 'Wenn diese Benutzerkonten dir gehören, kannst du hier ' .
		'den Prozess der Benutzerkonten-Zusammenführung durch die ' .
		'Eingabe des Passwortes für die anderen Benutzerkonto vollenden:',
	'centralauth-finish-password'          => 'Passwort:',
	'centralauth-finish-login'             => 'Anmeldung',
	'centralauth-finish-send-confirmation' => 'Passwort per E-Mail zusenden',
	'centralauth-finish-problems'          => "Hast du Probleme oder gehören dir diese anderen " .
		"Benutzerkonten nicht? [[meta:Help:Unified login problems|Hier findest du Hilfe]] …",

	'centralauth-merge-attempt'            => "'''Prüfe das eingegebene Passwort mit den restlichen Benutzerkonten…'''",

	# When unattached, offer to attach...
	'centralauth-attach-list-attached'     => 'Das globale Benutzerkonto mit dem Namen „$1“ beinhaltet die folgenden Benutzerkonten:',
	'centralauth-attach-title'             => 'Benutzerkonto bestätigen',
	'centralauth-attach-text'              => 'Dieses Benutzerkonto wurde noch nicht in ein globales Benutzerkonto integriert. 
	Wenn das globale Benutzerkonto auch von dir ist, kannst du die Zusammenführung veranlassen, indem du hier das Passowrt des globalen Benutzerkontos eingibts:',
	'centralauth-attach-submit'            => 'Benutzerkonto integrieren',
	'centralauth-attach-success'           => 'Das Benutzerkonto wurde in das globale Benutzerkonto integriert.',

	// Administrator's console
	'centralauth'                     => 'Verwaltung der Benutzerkonten-Zusammenführung',
	'centralauth-admin-manage'        => 'Benutzerdaten verwalten',
	'centralauth-admin-username'      => 'Benutzername:',
	'centralauth-admin-lookup'        => 'Benutzerdaten ansehen oder bearbeiten',
	'centralauth-admin-permission'    => "Die Zusammenführung von Benutzerkonten für andere Benutzer kann nur durch Stewards erfolgen.",
	'centralauth-admin-unmerge'       => 'Ausgewählte Benutzerkonten trennen',
	'centralauth-admin-merge'         => 'Ausgewählte Benutzerkonten zusammenführen',
	'centralauth-admin-bad-input'     => 'Ungültige Auswahl',
	'centralauth-admin-none-selected' => 'Es wurden keine zu ändernden Benutzerkonten ausgewählt.',

	// Info panel in preferences
       'centralauth-prefs-status'            => 'Benutzerkonten-Status:',
       'centralauth-prefs-not-managed'       => 'Es wird kein zusammengeführtes Benutzerkonto benutzt.',
       'centralauth-prefs-unattached'        => 'Unbestätigt',
       'centralauth-prefs-complete'          => 'Fertig!',
       'centralauth-prefs-migration'         => 'Zusammenführung in Arbeit',
       'centralauth-prefs-count-attached'    => 'Dein Benutzerkonto ist in $1 {{PLURAL:$1|Projekt|Projekten}} aktiv.',
       'centralauth-prefs-count-unattached'  => 'Es gibt in $1 {{PLURAL:$1|Projekt|Projekten}} unbestätigte Benutzerkonten mit deinem Namen.',
       'centralauth-prefs-detail-unattached' => 'Für dieses Projekt liegt keine Bestätigung für das zusammengeführte Benutzerkonto vor.',
       'centralauth-prefs-manage'            => 'Bearbeite dein zusammengeführtes Benutzerkonto',

	// Interaction with Special:Renameuser
	'centralauth-renameuser-abort' => "<div class=\"errorbox\">Benutzer $1 kann nicht lokal umbenannt werden, da er bereits in das globale Benutzerkonten-System übernommen wurde.</div>",
);

$wgCentralAuthMessages['bcl'] = array(
	'centralauth-list-home-title'          => 'Harong na wiki',
	'centralauth-finish-login'             => 'Maglaog',
);

$wgCentralAuthMessages['br'] = array(
	'centralauth-finish-password'          => 'Ger-tremen :',
	'centralauth-admin-username'           => 'Anv implijer :',
);

$wgCentralAuthMessages['ext'] = array(
	'centralauth-admin-username'           => 'Nombri d´usuáriu:',
);

$wgCentralAuthMessages['fr'] = array(
	'mergeaccount'                         => 'Statut de la fusion des comptes utilisateur',
	'centralauth-merge-notlogged'          => 'Merci de bien vouloir <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} vous connecter]</span> pour vérifier si vos comptes ont bien été fusionnés.',
	'centralauth-merge-welcome'            => '\'\'\'Vos comptes utilisateur n’ont pas encore été migrés vers la système de compte unique de Wikimedia.\'\'\'
Si vous choisissez de faire migrer vos comptes, vous pourrez utiliser le même nom d’utilisateur et le même mot de passe sur tous les projets Wikimedia dans toutes les langues.
Ainsi, le travail inter-projets sera facilité de même que, par exemple, l’import d’images sur [http://commons.wikimedia.org/ Wikimedia Commons] ; cela évitera aussi la confusion survenant quand deux personnes utilisent le même nom d’utilisateur sur deux projets différents.

Si vous avez déjà le même nom d’utilisateur sur tous les projets, il ne devrait pas y avoir de problème. Si une autre personne a le même nom d’utilisateur que vous sur un autre projet, vous aurez l\'occasion d\'entrer en contact avec cette personne ou avec un administrateur plus tard.',
	'centralauth-merge-step1-title'        => 'Commencer le processus de fusion des comptes',
	'centralauth-merge-step1-detail'       => 'Nous allons comparer votre adresse courriel et votre mot de passe avec ceux des comptes homonymes sur les autes wikis, et vérifier qu’ils correspondent. Aucun changement ne sera effectué tant que vous n’aurez pas donné votre accord.',
	'centralauth-merge-step1-submit'       => 'Confirmer les informations',
	'centralauth-merge-step2-title'        => 'Inclure d’autres comptes',
	'centralauth-merge-step2-detail'       => 'Certains des comptes n’ont pas pu être rattachés automatiquement à votre compte principal. Si ces comptes vous appartiennent, veuillez confirmer qu\'ils sont à vous en entrant le mot de passe correspondant.
',
	'centralauth-merge-step2-submit'       => 'Confirmer les informations',
	'centralauth-merge-step3-title'        => 'Création du compte unique',
	'centralauth-merge-step3-detail'       => 'Vous êtes maintenant prêt à créer votre compte unique, comprenant les wikis suivants :',
	'centralauth-merge-step3-submit'       => 'Fusionner les comptes',
	'centralauth-complete'                 => 'Fusion des comptes terminée !',
	'centralauth-incomplete'               => 'Fusion des comptes non terminée !',
	'centralauth-complete-text'            => 'Vous pouvez maintenant vous connecter à n’importe quel projet Wikimedia sans avoir à créer un nouveau compte ; le même nom d’utilisateur et le même mot de passe fonctionnent sur Wikipédia, Wiktionary, Wikibooks et leurs projets sœurs, et ceci pour toutes les langues.',
	'centralauth-incomplete-text'          => 'Une fois vos comptes fusionnés, vous pourrez vous connecter sur n’importe quel projet Wikimedia sans avoir à créer un nouveau compte ; le même nom d’utilisateur et mot de passe fonctionneront sur Wikipédia, Wiktionary, Wikibooks et leurs projets sœurs, et ceci pour toutes les langues.',
	'centralauth-not-owner-text'           => 'Le compte utilisateur « $1 » a été automatiquement assigné au propriétaire du compte sur $2.

Si c’est vous, vous pourrez terminer le processus d’unification de compte en tapant le mot de passe maître pour ce compte sur :',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Mode de démonstration seulement</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'La fusion des comptes est actuellement en mode de démonstration ou de test, on ne peut donc pas encore réellement fusionner de comptes. Désolé !',
	'centralauth-readmore-text'            => ':\'\'[[w:fr:Wikipédia:Login unique|En savoir plus sur le \'\'\'compte unique\'\'\']]\'\'',
	'centralauth-list-home-title'          => 'Projet principal',
	'centralauth-list-home-dryrun'         => 'Le mot de passe et l’adresse courriel du projet principal ci-dessous seront utilisés pour votre compte unique, et votre page utilisateur sur le projet principal sera automatiquement liée depuis les autres projets. Vous pourrez modifier votre projet principal plus tard.',
	'centralauth-list-attached-title'      => 'Comptes rattachés',
	'centralauth-list-attached'            => 'Les comptes utilisateur nommés « $1 » ont été rattachés pour les projets suivants :',
	'centralauth-list-attached-dryrun'     => 'Le compte nommé « $1 » sur chacun des sites suivants sera automatiquement rattaché au compte unique :',
	'centralauth-list-unattached-title'    => 'Comptes non rattachés',
	'centralauth-list-unattached'          => 'Les comptes utilisateur nommés « $1 » sur les sites suivants ne peuvent pas être rattachés automatiquement ; ils ont probablement un mot de passe différent de votre compte maître :',
	'centralauth-foreign-link'             => 'Utilisateur $1 sur $2',
	'centralauth-finish-title'             => 'Terminer l’unification',
	'centralauth-finish-text'              => 'Si ces comptes vous appartiennent, vous pouvez terminer leur unification en tapant leurs mots de passe ci-dessous :',
	'centralauth-finish-password'          => 'Mot de passe :',
	'centralauth-finish-login'             => 'Compte utilisateur :',
	'centralauth-finish-send-confirmation' => 'Envoyer le mot de passe par courriel',
	'centralauth-finish-problems'          => 'En cas de problème ou si vous ne possédez pas ces autres comptes, voyez [[meta:Help:Unified login problems|Problèmes]] (en anglais)...',
	'centralauth-merge-attempt'            => '\'\'\'Vérification du mot de passe fourni pour les comptes non réunis...\'\'\'',
	'centralauth-attach-list-attached'     => 'Le compte unifié nommé "$1" inclut les comptes suivants :',
	'centralauth-attach-title'             => 'Confirmer le compte',
	'centralauth-attach-text'              => 'Ce compte n\'a pas encore été migré en un compte unifié. Si le compte global vous appartient également, vous pouvez fusionner ce compte si vous tapez le mot de passe du compte global :',
	'centralauth-attach-submit'            => 'Migrer les comptes',
	'centralauth-attach-success'           => 'Le compte a été migré en un compte unifié.',
	'centralauth'                          => 'Administration des comptes unifiés',
	'centralauth-admin-manage'             => 'Gérer les données utilisateur',
	'centralauth-admin-username'           => 'Nom d’utilisateur :',
	'centralauth-admin-lookup'             => 'Voir ou modifier les données utilisateur',
	'centralauth-admin-permission'         => 'Seuls les stewards peuvent réunir les comptes d’autres personnes à leur place.',
	'centralauth-admin-unmerge'            => 'Séparer la sélection',
	'centralauth-admin-merge'              => 'Fusionner la sélection',
	'centralauth-admin-bad-input'          => 'Sélection invalide',
	'centralauth-admin-none-selected'      => 'Aucun compte sélectionné.',
	'centralauth-prefs-status'             => 'Statut du compte unique :',
	'centralauth-prefs-not-managed'        => 'Pas de compte unique',
	'centralauth-prefs-unattached'         => 'Non confirmé',
	'centralauth-prefs-complete'           => 'Tout va bien !',
	'centralauth-prefs-migration'          => 'En migration',
	'centralauth-prefs-count-attached'     => 'Votre compte est actif sur $1 projets.',
	'centralauth-prefs-count-unattached'   => 'Des comptes non confirmés avec le même nom d’utilisateur que le vôtre se trouvent sur $1 projets.',
	'centralauth-prefs-detail-unattached'  => 'Votre compte sur ce projet n’a pas pu être rattaché au compte unique.',
	'centralauth-prefs-manage'             => 'Gérez votre compte global',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Impossible de renommer le compte utilisateur $1 localement : cet utilisateur a maintenant un compte unique.</div>',
);

$wgCentralAuthMessages['frp'] = array(
	'mergeaccount'                         => 'Statut de la fusion des comptos utilisator',
	'centralauth-merge-notlogged'          => 'Marci de franc volêr vos <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} conèctar]</span> por controlar se voutros comptos ont bien étâ fusionâs.',
	'centralauth-merge-welcome'            => '\'\'\'Voutros comptos utilisator ont p’oncor étâ migrâs vers lo sistèmo de compto unico de Wikimedia.\'\'\'

Se vos chouèsésséd/cièrde de fâre migrar voutros comptos, vos porréd utilisar lo mémo nom d’utilisator et lo mémo mot de pâssa sur tôs los projèts Wikimedia dens totes les lengoues.
D’ense, lo travâly entèrprojèts serat facilitâ coment, per ègzemplo, l’impôrt d’émâges dessus [http://commons.wikimedia.org/ Wikimedia Commons] ; cen èviterat asse-ben la confusion arrevent quand doves gens utilisont lo mémo nom d’utilisator sur doux projèts difèrents.

Se vos avéd ja lo mémo nom d’utilisator sur tôs los projèts, devrêt pas y avêr de problèmo. S’una ôtra pèrsona at lo mémo nom d’utilisator que vos sur un ôtro projèt, vos aréd l’ocasion de vos veriér vers cela pèrsona ou ben vers un administrator ples târd.',
	'centralauth-merge-step1-title'        => 'Comenciér lo procès de fusion des comptos',
	'centralauth-merge-step1-detail'       => 'Nos alens comparar voutra adrèce de mèl et voutron mot de pâssa avouéc celos des comptos homonimos sur los ôtros vouiquis, et controlar que corrèspondont. Nion changement serat fêt tant que vos aréd pas balyê voutron acôrd.',
	'centralauth-merge-step1-submit'       => 'Confirmar les enformacions',
	'centralauth-merge-step2-title'        => 'Encllure d’ôtros comptos',
	'centralauth-merge-step2-detail'       => 'Cèrtins des comptos ont pas possu étre apondus ôtomaticament a voutron compto principâl. Se celos comptos sont a vos, volyéd confirmar que sont a vos en entrent lo mot de pâssa corrèspondent.',
	'centralauth-merge-step2-submit'       => 'Confirmar les enformacions',
	'centralauth-merge-step3-title'        => 'Crèacion du compto unico',
	'centralauth-merge-step3-detail'       => 'Orendrêt, vos éte prèst a crèar voutron compto unico, compregnent los vouiquis siuvents :',
	'centralauth-merge-step3-submit'       => 'Fusionar los comptos',
	'centralauth-complete'                 => 'Fusion des comptos chavonâ !',
	'centralauth-incomplete'               => 'Fusion des comptos pas chavonâ !',
	'centralauth-complete-text'            => 'Orendrêt, vos pouede vos conèctar a quint que seye lo projèt Wikimedia sen avêr a crèar un novél compto ; lo mémo nom d’utilisator et lo mémo mot de pâssa fonccioneront dessus Vouiquipèdia, Vouiccionèro, Vouiquilévros et lors projèts serors, et cen por totes les lengoues.',
	'centralauth-incomplete-text'          => 'Un côp voutros comptos fusionâs, vos porréd vos conèctar a quint que seye lo projèt Wikimedia sen avêr a crèar un novél compto ; lo mémo nom d’utilisator et lo mémo mot de pâssa fonccioneront dessus Vouiquipèdia, Vouiccionèro, Vouiquilévros et lors projèts serors, et cen por totes les lengoues.',
	'centralauth-not-owner-text'           => 'Lo compto utilisator « $1 » at étâ ôtomaticament assignê u propriètèro du compto dessus $2.

S’o est vos, vos porréd chavonar lo procès de fusion des comptos en tapent lo mot de pâssa mêtre por cél compto dessus :',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Môdo de dèmonstracion solament</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'La fusion des comptos est ora en môdo de dèmonstracion ou d’èprôva, on pôt vêr p’oncor verément fusionar de comptos. Dèsolâ !',
	'centralauth-readmore-text'            => ':\'\'[[w:frp:Vouiquipèdia:Login unico|Nen savêr més sur lo \'\'\'compto unico\'\'\']]\'\'',
	'centralauth-list-home-title'          => 'Projèt principâl',
	'centralauth-list-home-dryrun'         => 'Lo mot de pâssa et l’adrèce de mèl du projèt principâl ce-desot seront utilisâs por voutron compto unico, et voutra pâge utilisator sur lo projèt principâl serat ôtomaticament liyê dês los ôtros projèts. Vos porréd modifiar voutron projèt principâl ples târd.',
	'centralauth-list-attached-title'      => 'Comptos apondus',
	'centralauth-list-attached'            => 'Los comptos utilisator apelâs « $1 » ont étâ apondus por los projèts siuvents :',
	'centralauth-list-attached-dryrun'     => 'Lo compto apelâ « $1 » sur châcun des setos siuvents serat ôtomaticament apondu u compto unico :',
	'centralauth-list-unattached-title'    => 'Comptos pas apondus',
	'centralauth-list-unattached'          => 'Los comptos utilisator apelâs « $1 » sur los setos siuvents pôvont pas étre apondus ôtomaticament ; ils ont probâblament un mot de pâssa difèrent de voutron compto mêtre :',
	'centralauth-foreign-link'             => 'Utilisator $1 dessus $2',
	'centralauth-finish-title'             => 'Chavonar la fusion des comptos',
	'centralauth-finish-text'              => 'Se cetos comptos sont a vos, vos pouede chavonar lor fusion en tapent lors mots de pâssa ce-desot :',
	'centralauth-finish-password'          => 'Mot de pâssa :',
	'centralauth-finish-login'             => 'Compto utilisator :',
	'centralauth-finish-send-confirmation' => 'Emmandar lo mot de pâssa per mèl',
	'centralauth-finish-problems'          => 'En câs de problèmo ou ben se vos possèdâd pas cetos ôtros comptos, vêde [[meta:Help:Unified login problems|<span title="« Help:Unified login problems » : pâge en anglès" style="text-decoration:none">Problèmos</span>]]...',
	'centralauth-merge-attempt'            => '\'\'\'Contrôlo du mot de pâssa forni por los comptos pas rapondus...\'\'\'',
	'centralauth-attach-list-attached'     => 'Lo compto unico apelâ « $1 » encllut los comptos siuvents :',
	'centralauth-attach-title'             => 'Confirmar lo compto',
	'centralauth-attach-text'              => 'Ceti compto at p’oncor étâ migrâ en un compto unico. Se lo compto unico est asse-ben a vos, vos pouede fusionar ceti compto se vos tapâd lo mot de pâssa du compto unico :',
	'centralauth-attach-submit'            => 'Migrar los comptos',
	'centralauth-attach-success'           => 'Lo compto at étâ migrâ en un compto unico.',
	'centralauth'                          => 'Administracion des comptos unicos',
	'centralauth-admin-manage'             => 'G·èrar les balyês utilisator',
	'centralauth-admin-username'           => 'Nom d’utilisator :',
	'centralauth-admin-lookup'             => 'Vêre ou modifiar les balyês utilisator',
	'centralauth-admin-permission'         => 'Solèts los stevârds pôvont rapondre los comptos d’ôtres gens a lor place.',
	'centralauth-admin-unmerge'            => 'Sèparar la sèlèccion',
	'centralauth-admin-merge'              => 'Fusionar la sèlèccion',
	'centralauth-admin-bad-input'          => 'Sèlèccion envalida',
	'centralauth-admin-none-selected'      => 'Nion compto sèlèccionâ.',
	'centralauth-prefs-status'             => 'Statut du compto unico :',
	'centralauth-prefs-not-managed'        => 'Pas de compto unico',
	'centralauth-prefs-unattached'         => 'Pas confirmâ',
	'centralauth-prefs-complete'           => 'Tot vat bien !',
	'centralauth-prefs-migration'          => 'En migracion',
	'centralauth-prefs-count-attached'     => 'Voutron compto est actif dessus $1 projèts.',
	'centralauth-prefs-count-unattached'   => 'Des comptos pas confirmâs avouéc lo mémo nom d’utilisator que lo voutro sè trovont dessus $1 projèts.',
	'centralauth-prefs-detail-unattached'  => 'Voutron compto sur ceti projèt at pas possu étre apondu u compto unico.',
	'centralauth-prefs-manage'             => 'G·èrâd voutron compto unico',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Empossiblo de renomar lo compto utilisator $1 localament : ceti utilisator at ora un compto unico.</div>',
);

$wgCentralAuthMessages['gl'] = array(
	'mergeaccount'                         => 'Estado da unificación do rexistro',
	'centralauth-merge-notlogged'          => 'Por favor, <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} rexístrese]</span> para comprobar se as súas contas se  unificaron completamente.',
	'centralauth-merge-welcome'            => '\'\'\'A súa conta de usuario aínda non se pasou ao sistema de rexistro unificado de Wikimedia.\'\'\'

Se escolle unificar as súas contas, poderá empregar o mesmo nome de usuario e contrasinal para se rexistrar en todos os wikis dos proxectos de Wikimedia en todas as linguas disponíbeis.
Isto fai que sexa máis doado traballar con proxectos compartidos, como enviar a [http://commons.wikimedia.org/ Wikimedia Commons], e evita a confusión ou conflito que pode resultar se dúas persoas escollen o mesmo nome de usuario en proxectos diferentes.',
	'centralauth-merge-step1-title'        => 'Comezar a unificación do rexistro',
	'centralauth-merge-step1-detail'       => 'O seu contrasinal e os enderezos de correo electrónico rexistrados comprobaranse nas contas doutros wikis para confirmar que coinciden. Non se realizarán cambios até que vostede confirme que todo está ben.',
	'centralauth-merge-step1-submit'       => 'Confirme a información de rexistro',
	'centralauth-merge-step2-title'        => 'Confirmar máis contas',
	'centralauth-merge-step2-detail'       => 'Algunhas contas non se puideron comprobar automaticamente no wiki sinalado. Se estas contas lle pertencen, pode confirmar que son súas introducindo o contrasinal que usa nelas.',
	'centralauth-merge-step2-submit'       => 'Confirme a información de rexistro',
	'centralauth-merge-step3-title'        => 'Crear unha conta unificada',
	'centralauth-merge-step3-detail'       => 'Xa pode crear a súa conta unificada cos seguintes wikis relacionados:',
	'centralauth-merge-step3-submit'       => 'Unificar contas',
	'centralauth-complete'                 => 'Completouse a unificación do rexistro!',
	'centralauth-incomplete'               => 'A unificación do rexistro non está completa!',
	'centralauth-complete-text'            => 'Agora pode rexistrarse en calquer sitio wiki de Wikimedia sen crear unha conta nova; os mesmos nome de usuario e contrasinal valerán en Wikipedia, Wiktionary, Wikibooks e os seus proxectos irmáns en todas as linguas.',
	'centralauth-incomplete-text'          => 'Unha vez se unificar o rexistro, poderá rexistrarse en calquer sitio wiki de Wikimedia sen crear unha conta nova; os mesmos nome de usuario e contrasinal valerán en Wikipedia, Wiktionary, Wikibooks e os seus proxectos irmáns en todas as linguas.',
	'centralauth-not-owner-text'           => 'O nome de usuario "$1" asignouse automaticamente ao propietario da conta en $2.

Se se trata de vostede, pode completar o proceso de unificación de rexistro simplemente con escribir o contrasinal mestre desa conta aquí:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Só modo demostración</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'A unificación de contas é actualmente só un modo de demostración / depuración, polo que as operacións de unificación non están activadas. Sentímolo!',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Lea máis acerca do \'\'\'rexistro unificado\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Wiki primario',
	'centralauth-list-home-dryrun'         => 'Usaranse o contrasinal e enderezo de correo electrónico indicados neste wiki para a súa conta unificada, e a súa páxina de usuario ligarase automaticamente desde outros wikis. Poderá mudar o seu wiki primario máis tarde.',
	'centralauth-list-attached-title'      => 'Contas relacionadas',
	'centralauth-list-attached'            => 'A conta chamada "$1" en cada un dos sitios seguintes relacionouse automaticamente coa conta unificada:',
	'centralauth-list-attached-dryrun'     => 'A conta chamada "$1" en cada un dos sitios seguintes relacionarase automaticamente coa conta unificada:',
	'centralauth-list-unattached-title'    => 'Contas non relacionadas',
	'centralauth-list-unattached'          => 'Non se puido confirmar que a conta "$1" lle pertenza a vostede nos sitios seguintes; o máis probábel é que teñan un contrasinal diferente do da súa conta primaria:',
	'centralauth-foreign-link'             => 'Usuario $1 en $2',
	'centralauth-finish-title'             => 'Finalizar a unificación',
	'centralauth-finish-text'              => 'Se estas contas lle pertencen a vostede, pode finalizar o proceso de unificación do rexistro simplemente con introducir os contrasinais das outras contas aquí:',
	'centralauth-finish-password'          => 'Contrasinal:',
	'centralauth-finish-login'             => 'Rexistro',
	'centralauth-finish-send-confirmation' => 'Enviar o contrasinal por correo electrónico',
	'centralauth-finish-problems'          => 'Ten problemas ou non é o dono destoutras contas? [[meta:Help:Unified login problems|Como atopar axuda]]...',
	'centralauth-merge-attempt'            => '\'\'\'A contrastar o contrasinal fornecido coas demais contas aínda sen unificar...\'\'\'',
	'centralauth-attach-list-attached'     => 'A conta unificada chamada "$1" inclúe as contas seguintes:',
	'centralauth-attach-title'             => 'Confirmar conta',
	'centralauth-attach-text'              => 'Esta conta aínda non se pasou á conta unificada. Se a conta global tamén é súa, pode unificar esta conta se escribe o contrasinal da conta global:',
	'centralauth-attach-submit'            => 'Unificar conta',
	'centralauth-attach-success'           => 'A conta pasou á conta unificada.',
	'centralauth'                          => 'Administración do rexistro unificado',
	'centralauth-admin-manage'             => 'Xestionar os datos de usuario',
	'centralauth-admin-username'           => 'Nome de usuario:',
	'centralauth-admin-lookup'             => 'Ver ou editar os datos de usuario',
	'centralauth-admin-permission'         => 'Só os stewards poden unificar as contas doutra xente.',
	'centralauth-admin-unmerge'            => 'Seleccionouse des-unificar',
	'centralauth-admin-merge'              => 'Seleccionouse unificar',
	'centralauth-admin-bad-input'          => 'A selección de unificación non é válida',
	'centralauth-admin-none-selected'      => 'Non se seleccionaron contas para modificar.',
	'centralauth-prefs-status'             => 'Estado da conta global:',
	'centralauth-prefs-not-managed'        => 'Non está a usar a conta unificada',
	'centralauth-prefs-unattached'         => 'Sen confirmar',
	'centralauth-prefs-complete'           => 'Todo perfecto!',
	'centralauth-prefs-migration'          => 'En proceso de unificación',
	'centralauth-prefs-count-attached'     => 'A súa conta está activada en $1 sitios de proxectos.',
	'centralauth-prefs-count-unattached'   => 'Fican contas sen confirmar co seu nome en $1 proxectos.',
	'centralauth-prefs-detail-unattached'  => 'Non se confirmou que o sitio do proxecto pertenza á conta global.',
	'centralauth-prefs-manage'             => 'Xestionar a súa conta global',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Non se lle pode mudar o nome ao usuario $1 localmente xa que este nome de usuario pasou ao sistema de rexistro unificado.</div>',
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

	'centralauth-notice-dryrun'   => '<div class="successbox">מצב הדגמה בלבד</div><br clear="all" />',
	'centralauth-disabled-dryrun' => 'מיזוג החשבונות הוא כרגע במצב הדגמה ובדיקה, ולכן לא ניתן למזג את החשבונות. מצטערים!',

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

	# When unattached, offer to attach...
	'centralauth-attach-list-attached'     => 'החשבון הממוזג בשם המשתמש "$1" כולל את החשבונות הבאים:',
	'centralauth-attach-title'             => 'אימות החשבון',
	'centralauth-attach-text'              => 'חשבון זה לא נוסף עדיין לחשבון הממוזג. אם גם החשבון הממוזג שייך לכם, באפשרותכם למזג חשבון זה פשוט על־ידי הקלדת סיסמת החשבון הכללי:',
	'centralauth-attach-submit'            => 'מיזוג החשבון',
	'centralauth-attach-success'           => 'החשבון נוסף לחשבון הממוזג.',

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
	'centralauth-prefs-count-attached'    => 'החשבון שלכם פעיל ב{{plural:$1|מיזם אחד|־$1 מיזמים}}.',
	'centralauth-prefs-count-unattached'  => 'חשבונות בלתי מאומתים בשם המשתמש הזה נותרו ב{{plural:$1|מיזם אחד|־$1 מיזמים}}.',
	'centralauth-prefs-detail-unattached' => 'מיזם זה לא אושר כשייך לחשבון הכללי.',
	'centralauth-prefs-manage'            => 'ניהול החשבון הכללי',

	# Interaction with Special:Renameuser
	'centralauth-renameuser-abort' => '<div class="errorbox">לא ניתן לשנות את שם המשתמש של $1 באופן מקומי, כיוון שהוא כבר התווסף למערכת החשבונות הממוזגים.</div>',
);

$wgCentralAuthMessages['hsb'] = array(
	'mergeaccount'                         => 'Status zjednoćenja wužiwarskich kontow',
	'centralauth-merge-notlogged'          => 'Prošu <span class="plainlinks"> [{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} přizjew so]</span>, zo by přepruwował, hač su so twoje wužiwarske konta dospołnje zjednoćili.',
	'centralauth-merge-welcome'            => '\'\'\'Twoje wužiwarske konto njeje so hišće do zhromadneho systema přizjewjenja Wikimedije přiwzało.\'\'\'

Hdyž so rozsudźiš twoje konta tam składować, budźe móžno ze samsnym wužiwarskim mjenom a hesłom we wšěch projektach Wikimedije dźěłać.
To zjednori runočasne dźěło we wjacorych wikijach kaž nahraće datajow do [http://commons.wikimedia.org/ Wikimedia Commons] a wobeńdźe konflikty a mylenja hdyž chce něchto druhi samsne přimjeno kaž ty w druhich projektach wužiwać.',
	'centralauth-merge-step1-title'        => 'Wužiwarske konta so zjednoća',
	'centralauth-merge-step1-detail'       => 'Twoje hesło a zregistrowana e-mejlowa adresa přirunatej so z kontami w druhich wikijach zo by so jenakosć zwěsćiła. Ničo změnjene njebudźe doniž njewobkrućiš, zo je wšo w porjadku.',
	'centralauth-merge-step1-submit'       => 'Přizjewjenske daty potwjerdźić',
	'centralauth-merge-step2-title'        => 'Dalše konta potwjerdźić',
	'centralauth-merge-step2-detail'       => 'Někotre z wužiwarskich kontow njemóžachu so awtomatisce zjednoćić. Hdyž su konta twoje, móžeš to z hesłom dopokazać.',
	'centralauth-merge-step2-submit'       => 'Přizjewjenske daty potwjerdźić',
	'centralauth-merge-step3-title'        => 'Zjednoćene konto wutworić',
	'centralauth-merge-step3-detail'       => 'Sy hotowy swoje zjednoćene konto wutworić, ze slědowacymi připowěsnjenymi wikijemi:',
	'centralauth-merge-step3-submit'       => 'Konta zjednoćić',
	'centralauth-complete'                 => 'Wužiwarske konta su so dospołnje zjednoćili.',
	'centralauth-incomplete'               => 'Wužiwarske konta <b>njejsu</b> so dospołnje zjednoćili!',
	'centralauth-complete-text'            => 'Móžeš so nětko we wšěch projektach Wikimedije přizjewić bjez toho, zo by tam nowe konto wutworić dyrbjał; samsne přimjeno a samsne hesło stej   płaćiwej za Wikipedija, Wikisłownik, Wikiknihi a wšě sotrowske projekty we wšěch rěčach.',
	'centralauth-incomplete-text'          => 'Tak ruče kaž su so twoje wužiwarske konta zjednoćili, móžeš so we wšěch projektach Wikimedije přizjewić bjez toho, zo by tam nowe konto wutworić dyrbjał; samsne přimjeno a samsne hesło stej   płaćiwej za Wikipedija, Wikisłownik, Wikiknihi a wšě sotrowske projekty we wšěch rěčach.',
	'centralauth-not-owner-text'           => 'Wužiwarske mjeno "$1" bu awtomatisce swójstwownikej wužiwarskeho konta $2 připokazane. Jeli je to twoje wužiwarske mjeno, móžeš zjednoćenje wužiwarskich kontow přez zapodaće hłowneho hesła za tute wužiwarske konto dokónčić:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Jenož demonstraciski modus</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'Kontowe zjednoćenje je runje w demonstraciskim modusu abo při pytanju za zmylkami, tohodla su aktuelne zjednoćenske procesy znjemóžnjene. Bohužel!',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Informacije wo \'\'\'zjednoćenju wužiwarskich kontow\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Domjacy wiki',
	'centralauth-list-home-dryrun'         => 'Hesło a e-mejlowa adresa nastajenej w tutym wikiju budźetej so za twoje zjednoćene konto wužiwać, a twoja wužiwarska strona tu budźe so awtomatisce z druhich wwikijow wotkazować. Móžeš tež pozdźišo swoju domjacy wiki změnić.',
	'centralauth-list-attached-title'      => 'Připowěsnjene konta',
	'centralauth-list-attached'            => 'Konto z mjenom "$1" na slědowacych sydłow buchu awtomatisce zjednoćenemu kontu přidate.',
	'centralauth-list-attached-dryrun'     => 'Konto z mjenom "$1" na kóždym ze slědowacych sydłow budźe so awtomatisće zjednoćenemu kontu přidać:',
	'centralauth-list-unattached-title'    => 'Njepřipowěsnjene konta',
	'centralauth-list-unattached'          => 'Njeda so awtomatisce potwjerdźeć, zo by konto "S1" za slědowace sydła je twoje; najskerje je wone druhe hesło hač twoej primarne konto.',
	'centralauth-foreign-link'             => 'Wužiwar $1 na $2',
	'centralauth-finish-title'             => 'Zjednoćenje dokónčić',
	'centralauth-finish-text'              => 'Jeli tute wužiwarske konta su twoje, móžeš proces zjednoćenja wužiwarskich kontow přez zapodaće hesłow za druhe konta dokónčić.',
	'centralauth-finish-password'          => 'Hesło:',
	'centralauth-finish-login'             => 'Přizjewjenje',
	'centralauth-finish-send-confirmation' => 'Hesło mejlować',
	'centralauth-finish-problems'          => 'Maš problemy abo njejsu tute druhe konto twoje? [[meta:Help:Unified login problems|Tu namakaš pomoc]]...',
	'centralauth-merge-attempt'            => '\'\'\'Zapodate hesło so z njezjednoćenymi wužiwarskimi kontami přepruwuje...\'\'\'',
	'centralauth-attach-list-attached'     => 'Zjednoćene konto z mjenom "$1" zapřijima slědowace konta:',
	'centralauth-attach-title'             => 'Konto wobkrućić',
	'centralauth-attach-text'              => 'Tute konto hišće njeje so do zjednoćeneho konta přewzało. Jeli tež globalne konto tebi słuša, móžeš tute konto zapřijeć, jeli zapodaš hesło globalneho konta:',
	'centralauth-attach-submit'            => 'Konto přewzać',
	'centralauth-attach-success'           => 'Tute konto bu do zjednoćeneho konta přewzate.',
	'centralauth'                          => 'Zarjadowanje kontoweho zjednoćenja',
	'centralauth-admin-manage'             => 'Wužiwarske daty zrjadować',
	'centralauth-admin-username'           => 'Wužiwarske mjeno',
	'centralauth-admin-lookup'             => 'Wužiwarske daty wobhladać abo wobdźěłać',
	'centralauth-admin-permission'         => 'Jenož stewardźa smědźa konta druhich wužiwarjow zjednoćić.',
	'centralauth-admin-unmerge'            => 'Wubrane wotdźělić',
	'centralauth-admin-merge'              => 'Wubrane zjednoćić',
	'centralauth-admin-bad-input'          => 'Njepłaćiwy wuběr za zjednoćenje',
	'centralauth-admin-none-selected'      => 'Žane konto za změnjenje wubrane.',
	'centralauth-prefs-status'             => 'Status globalneho konta',
	'centralauth-prefs-not-managed'        => 'Njewužiwa so zjednoćene konto',
	'centralauth-prefs-unattached'         => 'Njepotwjerdźeny',
	'centralauth-prefs-complete'           => 'Wšo w porjadku!',
	'centralauth-prefs-migration'          => 'W přeměnje',
	'centralauth-prefs-count-attached'     => 'Twoje konto je na $1 projekotwych sydłach aktiwne.',
	'centralauth-prefs-count-unattached'   => 'Njepotwjerdźene konta z twojim mjenom zwostanu na $1 projektach.',
	'centralauth-prefs-detail-unattached'  => 'Njeje potwjerdźenje, zo tute projektowe sydło ke globalnemu kontu słuša.',
	'centralauth-prefs-manage'             => 'Twoje globalne konto zrjadować',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Wužiwar $1 njeda so lokalnje přemjenować, dokelž tute wužiwarske mjeno je do systema zjednoćeneho přizjewjenja přešoł.</div>',
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
	'mergeaccount'                         => 'Processo di unificazione delle utenze - status',
	'centralauth-merge-notlogged'          => 'Si prega di <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} effettuare il login]</span> per verificare se il processo di unificazione delle proprie utenze è completo.',
	'centralauth-merge-welcome'            => '\'\'\'Il tuo account utente non è ancora stato importato nel sistema di identificazione unificato di Wikimedia (Wikimedia\'s unified login system).\'\'\' Se decidi di unificare i tuoi account, potrai usare lo stesso nome utente e la stessa password per accedere a tutti i progetti wiki di Wikimedia in tutte le lingue disponibili. Questo faciliterà il lavoro con i progetti comuni, ad esempio caricare file su [http://commons.wikimedia.org/ Wikimedia Commons], ed eviterà la confusione ed i conflitti che nascerebbero se due o più utenti scegliessero lo stesso nome utente su più progetti. Se qualcun altro ha già preso il tuo nome utente su un altro sito, questo non lo disturberà, ma l\'unificazione darà a te la possibilità di sottoporre in futuro il problema all\'altro utente o ad un amministratore.',
	'centralauth-merge-step1-title'        => 'Avvia l\'unificazione dei login',
	'centralauth-merge-step1-detail'       => 'La tua password e l\'indirizzo e-mail registrato saranno ora controllati sugli account in altre wiki per confermare che corrispondano. Nessuna modifica sarà effettuata prima della tua conferma che tutto appare in regola.',
	'centralauth-merge-step1-submit'       => 'Conferma le informazioni per il login',
	'centralauth-merge-step2-title'        => 'Conferma altri account',
	'centralauth-merge-step2-detail'       => 'Non è stato possibile collegare automaticamente alcuni account a quello sulla tua wiki principale. Se sei il titolare di questi account, prova che ti appartengono indicando le password per ciascuno di essi.',
	'centralauth-merge-step2-submit'       => 'Conferma le informazioni di login',
	'centralauth-merge-step3-title'        => 'Crea l\'account unificato',
	'centralauth-merge-step3-detail'       => 'È tutto pronto per creare il tuo account unificato sulle seguenti wiki:',
	'centralauth-merge-step3-submit'       => 'Unifica gli account',
	'centralauth-complete'                 => 'Il processo di unificazione delle utenze è stato completato.',
	'centralauth-incomplete'               => 'Il processo di unificazione delle utenze non è ancora stato completato.',
	'centralauth-complete-text'            => 'È ora possibile accedere a tutti i siti Wikimedia senza dover creare nuovi account; questo nome utente e questa password sono attivi su tutte le edizioni di Wikipedia, Wiktionary, Wikibooks, ecc. nelle varie lingue e su tutti i progetti correlati.',
	'centralauth-incomplete-text'          => 'Dopo aver unificato le proprie utenze, sarà possibile accedere a tutti i siti Wikimedia senza dover creare nuovi account; il nome utente e la password saranno attivi su tutte le edizioni di Wikipedia, Wiktionary, Wikibooks, ecc. nelle varie lingue e su tutti i progetti correlati.',
	'centralauth-not-owner-text'           => 'Il nome utente "$1" è stato assegnato automaticamente al titolare dell\'account con lo stesso nome sul progetto $2.

Se si è il titolare dell\'utenza, per terminare il processo di unificazione è sufficiente inserire la password principale di quell\'account qui di seguito:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Solo modalità Demo</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'L\'unificazione degli account attualmente può essere sperimentata solo in modalità \'\'demo\'\' o \'\'debugging\'\', quindi le operazioni di effettiva fusione dei dati sono disabilitate. Siamo spiacenti!',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Per saperne di più sul \'\'\'login unico\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Wiki principale',
	'centralauth-list-home-dryrun'         => 'La password e l\'indirizzo e-mail registrati in questo wiki saranno usati per l\'account unificato, la tua pagina utente in questo wiki sarà automaticamente linkata dagli altri wiki. Potrai in seguito cambiare il tuo wiki principale.',
	'centralauth-list-attached-title'      => 'Account collegati',
	'centralauth-list-attached'            => 'Gli account con nome utente "$1" sui progetti elencati di seguito sono stati unificati automaticamente:',
	'centralauth-list-attached-dryrun'     => 'L\'account chiamato "\'\'\'$\'\'\'1" su ciascuno dei seguenti siti sarà automaticamente collegato all\'account unificato:',
	'centralauth-list-unattached-title'    => 'Account non collegati',
	'centralauth-list-unattached'          => 'Non è stato possibile verificare automaticamente che gli account con nome utente "$1" sui progetti elencati di seguito appartengano allo stesso titolare; è probabile che sia stata usata una password diversa da quella dell\'account principale:',
	'centralauth-foreign-link'             => 'Utente $1 su $2',
	'centralauth-finish-title'             => 'Completa il processo di unificazione',
	'centralauth-finish-text'              => 'Se si è il titolare di queste utenze, per completare il processo di unificazione degli account è sufficiente inserire le password relative alle utenze stesse qui di seguito:',
	'centralauth-finish-login'             => 'Esegui il login',
	'centralauth-finish-send-confirmation' => 'Invia password via e-mail',
	'centralauth-finish-problems'          => 'Se non si è il titolare di queste utenze, o se si incontrano altri problemi, si invita a consultare la [[meta:Help:Unified login problems|pagina di aiuto]]...',
	'centralauth-merge-attempt'            => '\'\'\'Verifica della password inserita sulle utenze non ancora unificate...\'\'\'',
	'centralauth-attach-list-attached'     => 'L\'account unificato chiamato "\'\'\'$1\'\'\'" include i seguenti account:',
	'centralauth-attach-title'             => 'Conferma l\'account',
	'centralauth-attach-text'              => 'Questo account non è ancora stato collegato all\'account unificato. Se sei il titolare dell\'account globale, puoi collegare questo account inserendo la password dell\'account globale:',
	'centralauth-attach-submit'            => 'Collega l\'account',
	'centralauth-attach-success'           => 'L\'account è stato trasferito all\'account unificato.',
	'centralauth'                          => 'Amministrazione del login unificato',
	'centralauth-admin-manage'             => 'Gestione dati utente',
	'centralauth-admin-username'           => 'Nome utente',
	'centralauth-admin-lookup'             => 'Visualizza o modifica i dati utente',
	'centralauth-admin-permission'         => 'Solo gli steward possono unificare gli account altrui per loro conto.',
	'centralauth-admin-unmerge'            => 'Scollega gli account selezionati',
	'centralauth-admin-merge'              => 'Collega gli account selezionati',
	'centralauth-admin-bad-input'          => 'Selezione per l\'unificazione NON valida',
	'centralauth-admin-none-selected'      => 'Non sono stati selezionati account da modificare',
	'centralauth-prefs-status'             => 'Situazione dell\'account globale:',
	'centralauth-prefs-not-managed'        => 'Account unificato non in uso',
	'centralauth-prefs-unattached'         => 'Non confermato',
	'centralauth-prefs-complete'           => 'Tutto a posto!',
	'centralauth-prefs-migration'          => 'In corso di trasferimento',
	'centralauth-prefs-count-attached'     => 'Il tuo account è attivo su $1 siti di progetto.',
	'centralauth-prefs-count-unattached'   => 'Ci sono account non confermati con il tuo nome utente su $1 progetti.',
	'centralauth-prefs-detail-unattached'  => 'Questo sito non è stato confermato come appartenente all\'account globale.',
	'centralauth-prefs-manage'             => 'Gestione del tuo account globale',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Impossibile rinominare localmente l\'utente $1 perché questa utenza è stata trasferita al sistema unificato di identificazione (unified login system).</div>',
);

$wgCentralAuthMessages['ja'] = array(
	'centralauth-finish-password'          => 'パスワード:',
	'centralauth-finish-login'             => 'ログイン',
	'centralauth-finish-send-confirmation' => '電子メールパスワード',
	'centralauth-admin-username'           => '利用者名:',
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

$wgCentralAuthMessages['la'] = array(
	'centralauth-finish-password'          => 'Tessera:',
	'centralauth-admin-username'           => 'Nomen usoris:',
);

$wgCentralAuthMessages['nl'] = array(
	'mergeaccount'                         => 'Status samenvoegen gebruikers',
	'centralauth-merge-notlogged'          => '<span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} Meld u aan]</span> om te controleren of uw gebruikers volledig zijn samengevoegd.',
	'centralauth-merge-welcome'            => '\'\'\'Uw gebruiker is nog niet gemigreerd naar Wikimedia\'s samengevoegde aanmeldsysteem.\'\'\'

Als u ervoor kiest om uw gebruikers te migreren, dan kunt u met dezelfde gebruikersnaam-wachtwoordcombinatie aanmelden bij alle projectwiki\'s van Wikimedia in alle beschikbare talen.
Dit maakt het eenvoudiger om te werken met gedeelde projecten, zoals het uploaden naar [http://commons.wikimedia.org/ Wikimedia Commons], en voorkomt verwarring of conflicten doordat twee mensen dezelfde gebruikersnaam kiezen op verschillende projecten.

Als iemand anders met uw gebruikersnaam al actief is op een andere site, dan heeft dat geen gevolgen voor die gebruiker. U heeft de mogelijkheid dat niet die gebruiker of een beheerder op een later moment op te lossen.',
	'centralauth-merge-step1-title'        => 'Start samenvoegen gebruikers',
	'centralauth-merge-step1-detail'       => 'Uw wachtwoord en geregistreerd e-mailadres worden gecontroleerd tegen de gebruikers op andere wiki\'s om te bevestigen dat ze overeenkomen. Er worden geen wijzigingen gemaakt tot u heeft aangegeven dat alles in orde lijkt.',
	'centralauth-merge-step1-submit'       => 'Bevestig aanmeldinformatie',
	'centralauth-merge-step2-title'        => 'Bevestig meer gebruikers',
	'centralauth-merge-step2-detail'       => 'Een aantal van de gebruikers konden niet aan de opgegeven thuiswiki gekoppeld worden. Als deze gebruikers van u zijn, kunt u dat aangeven door het wachtwoord voor de gebruikers op te geven.',
	'centralauth-merge-step2-submit'       => 'Bevestig aanmeldinformatie',
	'centralauth-merge-step3-title'        => 'Maak samengevoegde gebruiker aan',
	'centralauth-merge-step3-detail'       => 'U kunt nu uw samengevoegde gebruiker maken, met daarin opgenomen de volgende wiki\'s:',
	'centralauth-merge-step3-submit'       => 'Gebruikers samenvoegen',
	'centralauth-complete'                 => 'Samenvoegen gebruikers afgerond!',
	'centralauth-incomplete'               => 'Samenvoegen gebruikers niet volledig!',
	'centralauth-complete-text'            => 'U kunt nu aanmelden bij iedere wiki van Wikimedia zonder een nieuwe gebruiker aan te maken; dezelfde combinatie van gebruikersnaam en wachtwoord werkt voor Wikipedia, Wiktionary, Wikibooks en hun zusterprojecten in alle talen.',
	'centralauth-incomplete-text'          => 'Als uw gebruikers zijn samengevoegd kunt u aanmelden bij iedere wiki van Wikimedia zonder een nieuwe gebruiker aan te maken; dezelfde combinatie van gebruikersnaam en wachtwoord werkt voor Wikipedia, Wiktionary, Wikibooks en hun zusterprojecten in alle talen.',
	'centralauth-not-owner-text'           => 'De gebruikersnaam "$1" is automatisch toegewezen aan de eigenaar van de gebruiker op $2.

Als u dat bent, kunt u het samenvoegen van gebruikers afronden door hier het wachtwoord voor die gebruiker in te geven:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Alleen demonstratiemodus</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'Samenvoegen gebruikers is op dit moment beschikbaar in demonstratie- en debugmodus. Het samenvoegen van gebruikers is op dit moment dus niet mogelijk.',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Lees meer over \'\'\'samengevoegd aanmelden\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Thuiswiki',
	'centralauth-list-home-dryrun'         => 'Het wachtwoord en e-mailadres dat voor deze wiki is ingesteld wordt gebruikt voor uw samengevoegde gebruiker. Uw gebruikerspagina op die wiki wordt automatisch gelinkt vanuit andere wiki\'s. Later kunt u instellen welke wiki uw thuiswiki is.',
	'centralauth-list-attached-title'      => 'Betrokken gebruikers',
	'centralauth-list-attached'            => 'De gebruikers met de naam "$1" op de volgende sites zijn automatisch samengevoegd:',
	'centralauth-list-attached-dryrun'     => 'De gebruiker met de naam "$1" op de volgende sites wordt automatisch toegevoegd aan de samengevoegde gebruiker:',
	'centralauth-list-unattached-title'    => 'Niet betrokken gebruikers',
	'centralauth-list-unattached'          => 'De gebruiker "$1" kon niet automatisch aan u toegewezen worden voor de volgende sites; waarschijnlijk omdat het wachtwoord afwijkt van uw primaire gebruiker:',
	'centralauth-foreign-link'             => 'Gebruiker $1 op $2',
	'centralauth-finish-title'             => 'Samenvoegen afronden',
	'centralauth-finish-text'              => 'Als deze gebruikers bij u horen, dan kunt u het proces van samenvoegen afronden door de wachtwoorden voor de andere gebruikers hier in te voeren:',
	'centralauth-finish-password'          => 'Wachtwoord:',
	'centralauth-finish-login'             => 'Gebruikersnaam',
	'centralauth-finish-send-confirmation' => 'E-mail wachtwoord',
	'centralauth-finish-problems'          => 'Komt u er niet uit of zijn deze gebruikers niet van u? [[meta:Help:Unified login problems|Hoe hulp vinden]]...',
	'centralauth-merge-attempt'            => '\'\'\'Bezig met het controleren van de opgegeven wachtwoorden voor de nog niet samengevoegde gebruikers...\'\'\'',
	'centralauth-attach-list-attached'     => 'De samengevoegde gebruiker "$1" bestaat uit de volgende gebruikers:',
	'centralauth-attach-title'             => 'Gebruiker bevestigen',
	'centralauth-attach-text'              => 'Deze gebruiker is nog niet gemigreerd naar een samengevoegde gebruiker. Als de overkoepelende gebruiker ook van u is, dan kunt u deze gebruiker samenvoegen als u het wachtwoord voor de overkoepelende gebruiker ingeeft:',
	'centralauth-attach-submit'            => 'Gebruiker migreren',
	'centralauth-attach-success'           => 'De gebruiker is gemigreerd naar de samengevoegde gebruiker.',
	'centralauth'                          => 'Beheer samengevoegd aanmelden',
	'centralauth-admin-manage'             => 'Gebruikersgegevens beheren',
	'centralauth-admin-username'           => 'Gebruikersnaam:',
	'centralauth-admin-lookup'             => 'Gebruikersgegevens bekijken of bewerken',
	'centralauth-admin-permission'         => 'Alleen stewards kunnen gebruikers van anderen samenvoegen.',
	'centralauth-admin-unmerge'            => 'Splits geselecteerde gebruikers',
	'centralauth-admin-merge'              => 'Voeg geselecteerde gebruikers samen',
	'centralauth-admin-bad-input'          => 'Onjuiste samenvoegselectie',
	'centralauth-admin-none-selected'      => 'Er zijn geen gebruikers geselecteerd om te wijzigen',
	'centralauth-prefs-status'             => 'Globale gebruikerstatus',
	'centralauth-prefs-not-managed'        => 'Gebruikt geen samengevoegde gebruiker',
	'centralauth-prefs-unattached'         => 'Niet bevestigd',
	'centralauth-prefs-complete'           => 'Alles in orde!',
	'centralauth-prefs-migration'          => 'Bezig met migreren',
	'centralauth-prefs-count-attached'     => 'Uw gebruikers is actief in $1 projectsites.',
	'centralauth-prefs-count-unattached'   => 'Niet bevestigde gebruikers met uw naam zijn nog aanwezig op $1 projecten.',
	'centralauth-prefs-detail-unattached'  => 'Deze projectsite is niet bevestigd als behorende bij de globale gebruiker.',
	'centralauth-prefs-manage'             => 'Beheer uw globale gebruiker',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Gebruiker $1 kan lokaal niet hernoemd worden omdat deze gebruiker is gemigreerd naar het systeem van samengevoegde gebruikers.</div>',
);

$wgCentralAuthMessages['no'] = array(
	'mergeaccount'                         => 'Kontosammensmeltingsstatus',
	'centralauth-merge-notlogged'          => 'Vennligst <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special:MergeAccount}} logg inn]</span> for å sjekke om kontoene dine har blitt fullstendig sammensmeltet.',
	'centralauth-merge-welcome'            => '\'\'\'Din brukerkonto har ennå ikke blitt flyttet til Wikimedias enhetlige innlogginssystem.\'\'\' Om du velger å flytte kontoene dine kan du bruke samme brukernavn og passord for å logge inn på alle Wikimedias prosjekter på alle språk. Dette gjør det raskere å arbeide med delte prosjekter, som opplasting til [http://commons.wikimedia.org/ Wikimedia Commons], og unngår forvirringene og konfliktene som kan oppstå dersom to personer på forskjellige prosjekter bruker samme brukernavn. Dersom noen allerede har tatt ditt brukernavn på et annet prosjekt vil ikke dette forstyrre dem, men gi deg muligheten til å finne ut av sakene med dem eller en administrator senere.',
	'centralauth-merge-step1-title'        => 'Begynn kontosammenslåing',
	'centralauth-merge-step1-detail'       => 'Ditt passord og din e-postadresse vil bli sjekket opp mot kontoene på andre wikier for å bekrefte at de stemmer overens. Ingen endringer vil bli gjort før du har bekreftet at alt ser riktig ut.',
	'centralauth-merge-step1-submit'       => 'Bekreft innloggingsinformasjon',
	'centralauth-merge-step2-title'        => 'Bekreft flere kontoer',
	'centralauth-merge-step2-detail'       => 'Noen av kontoene kunne ikke matches med hjemmewikien automatisk. Om disse kontoene tilhører deg kan du bekrefte at de er dine ved å oppgi kontoenes passord.',
	'centralauth-merge-step2-submit'       => 'Bekreft innloggingsinformasjon',
	'centralauth-merge-step3-title'        => 'Opprett sammenslått konto',
	'centralauth-merge-step3-detail'       => 'Du er klar for å opprette din sammenslåtte konto, med følgende wikier koblet til kontoen:',
	'centralauth-merge-step3-submit'       => 'Slå sammen kontoene',
	'centralauth-complete'                 => 'Kontosammensmelting fullført!',
	'centralauth-incomplete'               => 'Kontosammensmelting ikke ferdig!',
	'centralauth-complete-text'            => 'Du kan nå logge inn på enhver Wikimedia-wiki uten å opprette en ny konto; samme brukernavn vil fungere på Wikipedia, Wiktionary, Wikibooks og deres søsterprosjekter på alle språk.',
	'centralauth-incomplete-text'          => 'Når du har smeltet sammen kontoene dine, vil du kunne logge inn på enhver Wikimedia-wiki uten å opprette en ny konto; samme brukernavn og passord vil fungere på Wikipedia, Wiktionary, Wikibooks og deres søsterprosjekter på alle språk.',
	'centralauth-not-owner-text'           => 'Brukernavnet «$1» ble automatisk tildelt eieren av kontoen på $2.

Om dette er deg kan du fullføre kontosammensmeltingsprosessen ved å skrive inn hovedpassordet for den kontoen her:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Kun demonstrasjonsmodus</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'Kontosammenslåing er foreløpig i en demonstrasjonsmodus, så faktisk sammenslåing er ikke mulig. Beklager!',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Les mer om \'\'\'kontosammensmelting\'\'\']]…\'\'',
	'centralauth-list-home-title'          => 'Hjemmewiki',
	'centralauth-list-home-dryrun'         => 'Passordet og e-postadressen som brukes på denne wikien vil bli brukt for din sammenslåtte konto, og andre wikier vil automatisk lenke til brukersiden din her. Du kan endre hvilken wiki som er din hjemmewiki senere.',
	'centralauth-list-attached-title'      => 'Tilkoblede kontoer',
	'centralauth-list-attached'            => 'Kontoene kalt «$1» på følgende sider har blitt automatisk sammensmeltet:',
	'centralauth-list-attached-dryrun'     => 'Kontoen ved navn «$1» på følgende sider vil kobles til den sammenslåtte kontoen automatisk:',
	'centralauth-list-unattached-title'    => 'Ikke tilkoblede kontoer',
	'centralauth-list-unattached'          => 'Kontoen «$1» på følgende sider kunne ikke automatisk stadfestes å være din; de har mest sannsynlig et annet passord enn din hovedkonto:',
	'centralauth-foreign-link'             => 'Bruker $1 på $2',
	'centralauth-finish-title'             => 'Fullfør sammensmelting',
	'centralauth-finish-text'              => 'Om disse kontoene ikke tilhører deg, kan du fullføre kontosammensmeltingen ved å skrive inn passordene for de andre kontoene her:',
	'centralauth-finish-password'          => 'Passord:',
	'centralauth-finish-login'             => 'Logg inn',
	'centralauth-finish-send-confirmation' => 'Send passord på e-post',
	'centralauth-finish-problems'          => 'Har du problemer, eller er ikke disse andre kontoene dine? [[meta:Help:Unified login problems|Hvordan finne hjelp…]]',
	'centralauth-merge-attempt'            => '\'\'\'Sjekker det oppgitte passordet mot gjenværende kontoer…\'\'\'',
	'centralauth-attach-list-attached'     => 'Den sammenslåtte kontoen ved navn «$1» inkluderer følgende kontoer:',
	'centralauth-attach-title'             => 'Bekreft konto',
	'centralauth-attach-text'              => 'Denne kontoen har ennå ikke blitt flyttet til den sammenslåtte kontoen. Om den sammenslåtte kontoen også er din kan du koble denne kontoen til den sammenslåtte ved å skrive inn passordet på den sammenslåtte kontoen:',
	'centralauth-attach-submit'            => 'Koble til konto',
	'centralauth-attach-success'           => 'Kontoen ble koblet til den sammenslåtte kontoen.',
	'centralauth'                          => 'Kontosammensmeltingsadministrasjon',
	'centralauth-admin-manage'             => 'Behandle brukerdata',
	'centralauth-admin-username'           => 'Brukernavn:',
	'centralauth-admin-lookup'             => 'Vis eller rediger brukerdata',
	'centralauth-admin-permission'         => 'Kun setwards kan smelte sammen andres kontoer for dem.',
	'centralauth-admin-unmerge'            => 'Skill ut valgte',
	'centralauth-admin-merge'              => 'Smelt sammen valgte',
	'centralauth-admin-bad-input'          => 'Ugyldig flettingsvalg',
	'centralauth-admin-none-selected'      => 'Ingen kontoer valgt for endring.',
	'centralauth-prefs-status'             => 'Status på sammenslått konto:',
	'centralauth-prefs-not-managed'        => 'Bruker ikke sammenslått konto',
	'centralauth-prefs-unattached'         => 'Ubekreftet',
	'centralauth-prefs-complete'           => 'Alt i orden!',
	'centralauth-prefs-migration'          => 'I ferd med å kobles til',
	'centralauth-prefs-count-attached'     => 'Kontoen din er aktiv på $1 prosjekter.',
	'centralauth-prefs-count-unattached'   => 'Det er fortsatt ubekreftede kontoer med ditt navn på $1 prosjekter.',
	'centralauth-prefs-detail-unattached'  => 'Denne kontoen har ikke blitt bekreftet å tilhøre den sammenslåtte kontoen.',
	'centralauth-prefs-manage'             => 'Behandle din sammenslåtte konto',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Kan ikke gi brukeren $1 nytt navn lokalt fordi brukernavnet er koblet til en sammenslått konto.</div>',
);

$wgCentralAuthMessages['oc'] = array(
	'mergeaccount'                         => 'Estatut d’unificacion del compte d\'utilizaire',
	'centralauth-merge-notlogged'          => 'Mercé de plan voler <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} vos connectar]</span> per verificar que vòstres comptes son plan estats acampats.',
	'centralauth-merge-welcome'            => '\'\'\'Vòstres comptes d\'utilizaire son pas encara estats migrats vèrs lo sistèma de compte unic de Wikimedia\'\'\' Se causissètz de far migrer vòstres comptes, poiretz utilizar lo meteis nom d’utilizaire e lo meteis senhal sus totes los projèctes Wikimedia dins totas las lengas. Atal, lo trabalh inter-projèctes serà mai aisit, e mai, per exemple, l’impòrt d’imatges sus [http://commons.wikimedia.org/ Wikimedia Commons] ; aquò evitarà tanben la confusion susvenent quand doas personas utilizant lo meteis nom d’utilizaire sus dos projèctes diferents. Se avètz ja lo meteis nom d’utilizaire sus totes los projèctes, deurià pas i aver de problèma. Se una autra persona a lo meteis nom d’utilizaire que vos sus un autre projècte, aurètz l\'occasion de dintrar en contacte amb aquesta persona o amb un administrator mai tard.',
	'centralauth-merge-step1-title'        => 'Començar lo procediment de fusion dels comptes',
	'centralauth-merge-step1-detail'       => 'Anam comparar vòstra adreça e-mail e vòstre senhal amb los dels comptes omonims suls autres wikis, e verificar que correspòndon. Cap de cambiament serà pas efectuat tant qu’aurètz pas balhat vòstre acòrdi.',
	'centralauth-merge-step1-submit'       => 'Confirmar las informacions',
	'centralauth-merge-step2-title'        => 'Inclòure d’autres comptes',
	'centralauth-merge-step2-detail'       => 'Cèrts dels comptes an pas pogut èsser ratachats automaticament a vòstre compte principal. Se aquestes comptes vos apartenon, confirmatz que son de vos en dintrant lo senhal correspondent.',
	'centralauth-merge-step2-submit'       => 'Confirmar las informacions',
	'centralauth-merge-step3-title'        => 'Creacion del compte unic',
	'centralauth-merge-step3-detail'       => 'Ara sètz prèst per crear vòstre compte unic, comprenent las wikis seguentas :',
	'centralauth-merge-step3-submit'       => 'Fusionar los comptes',
	'centralauth-complete'                 => 'Unificacion del compte acabada !',
	'centralauth-incomplete'               => 'Unificacion del compte pas acabada !',
	'centralauth-complete-text'            => 'Ara podètz vos connectar sus un sit Wikimedia qué qué siá sens aver de crear un compte novèl; lo meteis nom d’utilizaire e senhal foncionan sus Wikipèdia, Wikiccionari, Wikilibres e lors projèctes sòrres, aquò per totas las lengas.',
	'centralauth-incomplete-text'          => 'Un còp vòstre compte unificat, poirètz vos connectar sus un sit Wikimedia qué qué siá sens aver de crear un compte novèl ; lo meteis nom d’utilizaire e senhal foncionaràn sus Wikipèdia, Wikiccionari, Wikilibres e lors projèctes sòrres, aquò per totas las lengas.',
	'centralauth-not-owner-text'           => 'Lo compte d\'utilizaire « $1 » es estat automaticament assignat al proprietari del compte sus $2.

Se es vos, poirètz acabar lo procediment d’unificacion de compte en picant lo senhal mèstre per aqueste compte sus :',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Mòde de demonstracion solament</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'La fusion dels comptes es actualament en mòde de demonstracion o de tèst, se pòt doncas pas encara vertadièrament fusionar los comptes. O planhem !',
	'centralauth-readmore-text'            => ':\'\'[[w:oc:Wikipèdia:Login unic|Ne saber mai sul \'\'\'compte unificat\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Projècte principal',
	'centralauth-list-home-dryrun'         => 'Lo senhal e l’adreça e-mail del projècte principal çaijos seràn utilizats per vòstre compte unic, e vòstra pagina d\'utilizaire sul projècte principal serà automaticament ligada dempuèi los autres projèctes. Poirètz modificar vòstre projècte principal mai tard.',
	'centralauth-list-attached-title'      => 'Comptes ratachats',
	'centralauth-list-attached'            => 'Los comptes d\'utilizaires nomenats « $1 » son estats acampats pels sites seguents :',
	'centralauth-list-attached-dryrun'     => 'Lo compte nomenat « $1 » sus cadun dels sites seguents serà automaticament ratachat al compte unic :',
	'centralauth-list-unattached-title'    => 'Comptes non ratachats',
	'centralauth-list-unattached'          => 'Lo compte d\'utilizaire « $1 » pòt èsser confirmat automaticament pels sites que seguisson ; an probablament un senhal diferent de vòstre compte mèstre :',
	'centralauth-foreign-link'             => 'Utilizaire $1 sus $2',
	'centralauth-finish-title'             => 'Completar l’unificacion',
	'centralauth-finish-text'              => 'Se aquestes comptes vos apartenon, podètz acabar lor unificacion en picant los senhals çai jos :',
	'centralauth-finish-password'          => 'Senhal:',
	'centralauth-finish-login'             => 'Compte d\'utilizaire:',
	'centralauth-finish-send-confirmation' => 'Mandar lo senhal per corrièr electronic',
	'centralauth-finish-problems'          => 'En cas de problèma o se possedissètz pas aquestes autres comptes, vejatz la pagina [[meta:Help:Unified login problems|Problèmas]] (en anglés)...',
	'centralauth-merge-attempt'            => '\'\'\'Verificacion del senhal provesit pels comptes non acampats...\'\'\'',
	'centralauth-attach-list-attached'     => 'Lo compte unificat nomenat "$1" inclutz los comptes seguents :',
	'centralauth-attach-title'             => 'Confirmar lo compte',
	'centralauth-attach-text'              => 'Aqueste compte a pas encara estat migrat en un compte unificat. Se lo compte global tanben vos aparten, podètz fusionar aqueste compte se picatz lo senhal del compte global :',
	'centralauth-attach-submit'            => 'Migrar los comptes',
	'centralauth-attach-success'           => 'Lo compte es estat migrat en un compte unificat.',
	'centralauth'                          => 'Administracion dels comptes unificats',
	'centralauth-admin-manage'             => 'Administrar las donadas d\'utilizaire',
	'centralauth-admin-username'           => 'Nom d\'utilizaire:',
	'centralauth-admin-lookup'             => 'Veire o modificar las donadas d\'utilizaire',
	'centralauth-admin-permission'         => 'Sols los stewards pòdon acampar los comptes d’autras personas a lor plaça.',
	'centralauth-admin-unmerge'            => 'Separar la seleccion',
	'centralauth-admin-merge'              => 'Acampar la seleccion',
	'centralauth-admin-bad-input'          => 'Seleccion invalida',
	'centralauth-admin-none-selected'      => 'Cap de compte seleccionat.',
	'centralauth-prefs-status'             => 'Estatut del compte unic :',
	'centralauth-prefs-not-managed'        => 'Pas de compte unic',
	'centralauth-prefs-unattached'         => 'Pas confirmat',
	'centralauth-prefs-complete'           => 'Tot va plan!',
	'centralauth-prefs-migration'          => 'En migracion',
	'centralauth-prefs-count-attached'     => 'Vòstre compte es actiu sus $1 projèctes.',
	'centralauth-prefs-count-unattached'   => 'De comptes non confirmats amb lo meteis nom d’utilizaire que lo vòstre se tròban sus $1 projèctes.',
	'centralauth-prefs-detail-unattached'  => 'Vòstre compte sus aqueste projècte a pas pogut èsser ratachat al compte unic.',
	'centralauth-prefs-manage'             => 'Administrar vòstre compte global',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Impossible de renomenar lo compte d\'utilizaire $1 localament : ara, aqueste utilizaire a un compte unic.</div>',
);

$wgCentralAuthMessages['pl'] = array(
	'mergeaccount'                         => 'Stan unifikacji loginu',
	'centralauth-merge-notlogged'          => '<span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} Zaloguj się]</span> by sprawdzić, czy twoje konta zostały w pełni połączone.',
	'centralauth-merge-welcome'            => '\'\'\'Twoje konto użytkownika nie zostało jeszcze przeniesione do ujednoliconego systemu logowania Wikimedia.\'\'\' Jeśli wybierzesz przeniesienie twoich kont, będziesz mógł (mogła) używać tej samej nazwy użytkownika i hasła by logować się do wszystkich projektów Wikimedia we wszystkich językach. Ułatwia to np. ładowanie plików na [http://commons.wikimedia.org/ Wikimedia Commons] i pozwala unikać nieporozumień lub konfliktów, które powstają, gdy dwóch ludzi wybiera tę samą nazwę użytkownika na różnych projektach. Jeśli ktoś inny przyjął już twoją nazwę użytkownika n innym projekcie, ten proces nie przeszkodzi mu, ale da ci szansę na późniejsze rozwiązanie sprawy z tą osobą lub administratorem.',
	'centralauth-merge-step1-title'        => 'Zacznij unifikację loginów',
	'centralauth-merge-step1-detail'       => 'Twoje hasło i zarejestrowany adres e-mail zostaną porównane z kontami na innych wiki, aby potwierdzić, że się zgadzają. Żadne zmiany nie zostaną dokonane, dopóki nie potwierdzisz, że wszystko jest w porządku.',
	'centralauth-merge-step1-submit'       => 'Potwierdzenie informacji o loginie',
	'centralauth-merge-step2-title'        => 'Potwierdź więcej kont',
	'centralauth-merge-step2-detail'       => 'Niektóre z kont nie mogły być automatycznie dopasowane do wyznaczonego podstawowego wiki. Jeśli te konta należą do ciebie, możesz potwierdzić, że są twoje przez podanie haseł do nich.',
	'centralauth-merge-step2-submit'       => 'Potwierdź informację o loginie',
	'centralauth-merge-step3-title'        => 'Utworzenie zunifikowanego konta',
	'centralauth-merge-step3-detail'       => 'System jest gotowy do utworzenia twojego zunifikowanego konta, z dołączonymi następującymi wiki:',
	'centralauth-merge-step3-submit'       => 'Unifikuj konta',
	'centralauth-complete'                 => 'Unifikacja loginu zakończona!',
	'centralauth-incomplete'               => 'Unifikacja loginu niekompletna!',
	'centralauth-complete-text'            => 'Możesz teraz logować się na każde wiki Fundacji Wikimedia bez tworzenia nowego konta; ta sama nazwa użytkownika i hasło będzie działać na Wikipedii, Wikisłowniku, Wikipedii i ich projektach siostrzanych we wszystkich językach.',
	'centralauth-incomplete-text'          => 'Kiedy twój login zostanie zunifikowany, będziesz w stanie zalogować się do każdego wiki Fundacji Wikimedia bez tworzenia nowego konta; ta sama nazwa użytkownika i hasło będzie działać na Wikipedii, Wikisłowniku, Wikibooks i ich projektach siostrzanych we wszystkich językach.',
	'centralauth-not-owner-text'           => 'Nazwa użytkownika "$1" została automatycznie przypisana właścicielowi konta na $2. Jeśli ty nim jesteś, możesz zakończyć unifikację loginu wpisując główne hasło tego konta w tym miejscu:',
	'centralauth-notice-dryrun'            => '<div class="successbox">Tylko tryb demonstracyjny</div><br style="clear:both" />',
	'centralauth-disabled-dryrun'          => 'Unifikacja konta jest obecnie tylko w trybie demonstracyjnym/debugującym, więc właściwe operacje łączenia kont są wyłączone. Przepraszamy!',
	'centralauth-finish-password'          => 'Hasło:',
);

$wgCentralAuthMessages['pms'] = array(
	'mergeaccount'                         => 'Stat dël process dla mës-cia dë stranòm e ciav',
	'centralauth-merge-notlogged'          => 'Për piasì <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} ch\'a rintra ant ël sistema]</span> për controlé che sò cont a sio stait mës-cià coma ch\'as dev.',
	'centralauth-merge-welcome'            => '\'\'\'Sò cont a l\'é nen stait portà al sistema d\'intrada unificà ëd Wikimedia\'\'\'. S\'a decid ëd porté sò cont a podrà dovré midem stranòm e ciav për rintré an qualsëssìa proget Wikimedia an qualsëssìa dle lenghe disponibij. Sòn a dovrìa fé belfé dovré dij proget coma la caria d\'archivi ansima a [http://commons.wikimedia.org/ Wikimedia Commons], e gavé via dla confusion ch\'a peul seurt-ie fòra quand doe person-e për cas as sërno ël midem stranòm an doj proget diferent. S\'a fussa mai riva-ie che cheidun a l\'avèissa gia sërnusse lë stranòm ch\'a dòvra chiel/chila, sòn a-j darìa gnun fastudi a gnun, ma pì anans a-j darìa a tuti la possibilità d\'arzolve ël problema ansema a j\'aministrator.',
	'centralauth-merge-step1-title'        => 'Anandié l\'unificassion djë stranòm',
	'centralauth-merge-step1-detail'       => 'Soa ciav e soa adrëssa ëd pòsta eletrònica a saran controlà con cole dij cont ëd j\'àutre wiki, për confermé ch\'a van bin. A-i rivërà nen gnun cambiament fin ch\'an dis nen che tut a va bin.',
	'centralauth-merge-step1-submit'       => 'Confermé j\'anformassion për l\'intrada ant ël sistema',
	'centralauth-merge-step2-title'        => 'Confermé dj\'àutri cont',
	'centralauth-merge-step2-detail'       => 'Cheidun dij cont a l\'é pa podusse cobié n\'aotomàtich con la wiki ëd destinassion. Se sti cont-sì a son sò, a peul confermene la proprietà ën butand-se la ciav giusta.',
	'centralauth-merge-step2-submit'       => 'Confermé j\'anformassion për l\'intrada ant ël sistema',
	'centralauth-merge-step3-title'        => 'Creé sò cont unificà',
	'centralauth-merge-step3-detail'       => 'Tut a l\'é pront për creé sò cont unificà, andova a l\'andrìo a finì ij cont dle wiki ambelessì sota:',
	'centralauth-merge-step3-submit'       => 'Unifiché ij cont',
	'centralauth-complete'                 => 'Mës-cia dij cont bele faita!',
	'centralauth-incomplete'               => 'Mës-cia djë stranòm e dle ciav bele faita!',
	'centralauth-complete-text'            => 'Adess a peul rintré an qualsëssìa sit dla Wikimedia (ëd coj ch\'a travajo col programa dla wiki) sensa da manca dë deurb-se un cont; la midema cobia dë stranòm a ciav a travajo an qualsëssìa Wikipedia, Wiktionary, Wikibooks e ant sj\'àotri proget soe seur an qualsëssìa lenga.',
	'centralauth-incomplete-text'          => 'Na vira che sò stranòm e ciav a sio stait mës-cià a podrà rintré an qualsëssìa sit dla Wikimedia (ëd coj ch\'a travajo col programa dla wiki) sensa pa da manca dë deurb-se un cont neuv; la midema cobia dë stranòm e ciav a travajeran ant tute le Wikipedia, Wiktionary, Wikibooks e sò proget seur an qualsëssìa lenga.',
	'centralauth-not-owner-text'           => 'Lë stranòm "$1" e l\'é stait dait n\'aotomàtich al proprietari dël cont ansima a $2.

Se as trata ëd chiel/chila, a peul mandé a bon fin ël process dla mës-cia dë stranòm e ciav ën butand-ie ambelessì la ciav prinsipal dël cont:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Mach për dimostrassion, da bon a-i riva gnente</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'Ch\'a në scusa, ma l\'unificassion dij cont për adess a la travaja mach coma dimostrativ, për corege ël programa. L\'unificassion da bon as peul pa fesse.',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Për savejne dë pì, ch\'a varda \'\'\'Stranòm e ciav globaj\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Ca (soa wiki prinsipal)',
	'centralauth-list-home-dryrun'         => 'La ciav e l\'adrëssa ëd pòsta eletrònica trovà ant sta wiki-sì a saran cole dovrà për sò cont unificà, soa pàgina utent d\'ambelessì a resterà cola andova ch\'as riva da qualsëssìa àutra wiki. Sòn un a peul peuj cambiess-lo coma a-j ven mej a chiel/chila.',
	'centralauth-list-attached-title'      => 'Cont tacà',
	'centralauth-list-attached'            => 'Ij cont ch\'as ës-ciamo "$1" ansima a ij sit dla lista ambelessì sota a son stait mës-cià antra lor n\'aotomàtich:',
	'centralauth-list-attached-dryrun'     => 'Ël cont con lë stranòm "$1" ëd minca un dij sit ambelessì sota a sarà tacà al cont unificà:',
	'centralauth-list-unattached-title'    => 'Cont nen unificà',
	'centralauth-list-unattached'          => 'Ant ij sit dla lista ambelessì sota ël cont "$1" a l\'é pa podusse confermé coma sò n\'aotomàtich; a l\'é belfé ch\'a-i sio dle ciav diferente da cola ëd sò cont prinsipal:',
	'centralauth-foreign-link'             => 'Stranòm $1 ansima a $2',
	'centralauth-finish-title'             => 'Finiss la mës-cia',
	'centralauth-finish-text'              => 'Se sti cont-sì a son sò, a peul andé a bon fin dël process ëd mës-cia dë stranòm e ciav mach ën butand-ie le ciav dj\'àotri cont ambelessì sota:',
	'centralauth-finish-password'          => 'Ciav:',
	'centralauth-finish-login'             => 'Rintré ant ël sistema',
	'centralauth-finish-send-confirmation' => 'Mandé la ciav për pòsta eletrònica',
	'centralauth-finish-problems'          => 'Ha-lo dle gran-e, ò pura l\'é-lo pa chiel/chila ël titolar d\'ës cont-sì? Ch\'a varda [[meta:Help:Unified login problems|coma trové d\'agiut]]...',
	'centralauth-merge-attempt'            => '\'\'\'I soma antramentr che i controloma le ciav ch\'a l\'ha butà con cole dij cont anco\' da mës-cé...\'\'\'',
	'centralauth-attach-list-attached'     => 'Ël cont unificà con lë stranòm "$1" as ciapa andrinta ij cont listà ambelessì sota:',
	'centralauth-attach-title'             => 'Confermé \'l cont',
	'centralauth-attach-text'              => 'Ës cont-sì a l\'é anco\' nen stait migrà a col unificà. Se ëdcò ël cont global a resta sò, a peul unifiché ës cont-sì ën butand soa ciav globala:',
	'centralauth-attach-submit'            => 'Unifiché \'l cont',
	'centralauth-attach-success'           => 'Ël cont a l\'é stait giontà a col unificà',
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
	'centralauth-renameuser-abort'         => '<div class="errorbox">As peul pa arbatié l\'utent $1 an local, për via che stë stranòm-sì a l\'é col dël cont unificà.</div>',
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
	'centralauth-merge-welcome'            => '\'\'\'Váš používateľský účet ešte nebol migrovaný na zjednotený prihlasovací systém Wikimedia.\'\'\'

Ak si zvolíte, aby vaše účty boli migrované, budete môcť používať rovnaké používateľské meno a heslo na prihlasovanie do každého z wiki projektov nadácie Wikimedia vo všetkých dostupných jazykoch.
To zjednopduší prácu so zdieľanými projektami ako nahrávanie na [http://commons.wikimedia.org/Hlavná_stránka Wikimedia Commons] a zamedzí zmätkom v prípade, že dvaja ľudia majú rovnaké používateľské meno na rôznych projektoch.

Ak niekto iný už zabral vaše používateľské meno na inom projekte, toto ich nenaruší, ale dá vám možnosť dohodnúť sa s ním alebo s administrátorom neskôr.

== Čo sa stane ďalej? ==

Keď si zvolíte, že chcete migráciu na zjednotené prihlasovanie, systém sa pozrie na každý z projektov, ktorý prevádzkujeme -- Wikipedia, Wikinews, Commons, atď. -- a vypíše každý, kde bolo vaše používateľské meno zaregistrované.

Jedna z týchto wiki bude zvolená za "domovskú wiki" vášho účtu, zvyčajne tá, ktorá je najviac používaná. Ak to nie je wiki, do ktorej sa momentálne prihlasujete, môžete byť predtým, než proces bude pokračovať požiadaný o potvrdenie, že poznáte heslo k danému účtu.

Informácie účtu na domovskej wiki budú porovnané s každým s ostatných účtov a tie, ktorých heslo alebo emailová adresa sa zhodujú alebo neboli použité budú automaticky pripojené k vášmu novému globálnemu účtu.

Tie, ktoré sa nezhodujú budú vynechané, pretože systém nemôže s istotou určiť, či sú vaše účty. Pre tieto účty, ak patria vám, môžete dokončiť pripojenie zadaním správneho prihlasovacieho hesla; ak ich zaregistroval niekto iný, budete mať možnosť zanechať im správu a uvidíte, či niečo vymyslíte.

Nie je \'\'povinné\'\' spojiť všetky účty; niektoré môžete nechať oddelené, a budú tak označené.',
	'centralauth-merge-step1-title'        => 'Začať zjednotenie prihlasovania',
	'centralauth-merge-step1-detail'       => 'Vaše heslo a registrovaná emailová adresa bude porovnaná s účtami na ostatných wiki, aby sa potvrdilo, že sa zhodujú. Žiadne zmeny sa nevykonajú, kým nepotvrdíte, že je to v poriadku.',
	'centralauth-merge-step1-submit'       => 'Potvrdiť prihlasovacie informácie',
	'centralauth-merge-step2-title'        => 'Potvrdiť viac účtov',
	'centralauth-merge-step2-detail'       => 'Pri niektorých účtoch nebolo možné automaticky potvrdiť, že majú rovnakého vlastníka ako určená domovská wiki. Ak vám tieto účty patria, môžete to potvrdiť tým, že k nim zadáte heslo.',
	'centralauth-merge-step2-submit'       => 'Potvrdiť prihlasovanie informácie',
	'centralauth-merge-step3-title'        => 'Vytvoriť zjednotený účet',
	'centralauth-merge-step3-detail'       => 'Vytvorenie vášho zjednoteného účtu je pripravené s nasledovnými pripojenými wiki:',
	'centralauth-merge-step3-submit'       => 'Zjednotiť účty',
	'centralauth-complete'                 => 'Zjednotenie prihlasovacích účtov dokončené!',
	'centralauth-incomplete'               => 'Zjednotenie prihlasovacích účtov nebolo dokončené!',
	'centralauth-complete-text'            => 'Teraz sa môžete prihlásiť na ľubovoľnú wiki nadácie Wikimedia bez toho, aby ste si museli vytvárať nový účet; rovnaké užívateľské meno a heslo bude fungovať na projektoch Wikipedia, Wiktionary, Wikibooks a ďalších sesterských projektoch vo všetkých jazykoch.',
	'centralauth-incomplete-text'          => 'Potom, ako budú vaše účty zjednotené sa budete môcť prihlásiť na ľubovoľnú wiki nadácie Wikimedia bez toho, aby ste si museli vytvárat ďalší účet; rovnaké užívateľské meno a heslo bude fungovať na projektoch Wikipedia, Wiktionary, Wikibooks a ďalších sesterských projektoch vo všetkých jazykoch.',
	'centralauth-not-owner-text'           => 'Užívateľské meno "$1" bolo automaticky priradené vlastníkovi účtu na projekte $2.

Ak ste to vy, môžete dokončiť proces zjednotenia účtov jednoducho napísaním hesla pre uvedený účet sem:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Toto je iba demonštračný režim</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'Zjednotenie účtov prebieha momentálne iba v demonštračnom / ladiacom režime, takže samotné operácie spojenia sú vypnuté. Prepáčte!',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Prečítajte si viac o \'\'\'zjednotení prihlasovacích účtov\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Domovská wiki',
	'centralauth-list-home-dryrun'         => 'Heslo a emailová adresa nastavená na tejto wiki sa použije pre váš zjednotený účet a na vašu používateľskú stránku tu budú automaticky odkazovať ostatné wiki. Tiež bude možné zmeniť vašu domovskú wiki neskôr.',
	'centralauth-list-attached-title'      => 'Pripojené účty',
	'centralauth-list-attached'            => 'Účty z názvom "$1" na nasledujúcich projektoch boli automaticaticky zjednotené:',
	'centralauth-list-attached-dryrun'     => 'Účet s názvom "$1" na kažom z nasledovných projektov bude automaticky pripojený k zjednotenému účtu:',
	'centralauth-list-unattached-title'    => 'Nepripojené účty',
	'centralauth-list-unattached'          => 'Nebolo možné automaticky potvrdiť, že účet "$1" na nasledujúcich projektoch patrí vám; pravdepodobne má odlišné heslo ako váš primárny účet:',
	'centralauth-foreign-link'             => 'Užívateľ $1 na $2',
	'centralauth-finish-title'             => 'Dokončiť zjednotenie',
	'centralauth-finish-text'              => 'Ak tieto účty naozaj patria vám, môžete skončiť proces zjednotenia jednoducho napísaním hesiel dotyčných účtov:',
	'centralauth-finish-password'          => 'Heslo:',
	'centralauth-finish-login'             => 'Prihlasovacie meno',
	'centralauth-finish-send-confirmation' => 'Zaslať heslo emailom',
	'centralauth-finish-problems'          => 'Máte problém alebo nie ste vlastníkom týchto účtov? [[meta:Help:Unified login problems|Ako hľadať pomoc]]...',
	'centralauth-merge-attempt'            => '\'\'\'Kontrolujem poskytnuté heslá voči zostávajúcim zatiaľ nezjednoteným účtom...\'\'\'',
	'centralauth-attach-list-attached'     => 'K zjednotenému účtu s názvom „$1“ patria nasledovné účty:',
	'centralauth-attach-title'             => 'Potvrdiť účet',
	'centralauth-attach-text'              => 'Tento účet zatiaľ nebol migrovaný na zjednotený účet. Ak je globálny účet váš, môžete tento účet zlúčiť napísaním hesla ku globálnemu účtu:',
	'centralauth-attach-submit'            => 'Migrovať účet',
	'centralauth-attach-success'           => 'Účet bol migrovaný na zjednotený účet.',
	'centralauth'                          => 'Administrácia zjednoteného prihlasovania',
	'centralauth-admin-manage'             => 'Správa údajov o používateľoch',
	'centralauth-admin-username'           => 'Používateľské meno:',
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
	'centralauth-renameuser-abort'         => '<div class="errorbox">Nie je možné lokálne premenovať používateľa $1, keďže toto používateľské meno bolo migrované na zjednotený prihlasovací systém.</div>',
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
	'mergeaccount'                         => 'Estado da unificação de logins',
	'centralauth-merge-notlogged'          => 'Por gentileza, <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} faça login]</span> para verificar se as suas contas foram corretamente fundidas.',
	'centralauth-merge-welcome'            => '\'\'\'Sua conta de utilizador ainda não foi migrada para o sistema de autenticação (login) unificado da Wikimedia.\'\'\'

Caso você decida por migrar as suas contas, será possível utilizar o mesmo nome de usuário e senha para se autenticar em todas as wikis da Wikimedia (em todos os projetos e em todos os idiomas disponíveis).
Isso torna mais fácil trabalhar em projetos partilhados, tal como enviando um ficheiro ou imagem ao [http://commons.wikimedia.org/ Wikimedia Commons], e evita confusões ou conflitos que podem ocorrer quando duas pessoas escolhem o mesmo nome de utilizador em diferentes projetos.

Caso alguém já esteja com um nome de utilizador idêntico ao seu em alguma outra wiki, tal pessoa não será importunada. No entanto, será possível que você dialogue com a mesma ou com um administrador posteriormente.',
	'centralauth-merge-step1-title'        => 'Iniciar a unificação de logins',
	'centralauth-merge-step1-detail'       => 'Sua palavra-chave (senha) e endereço de e-mail serão comparados com os de contas de outras wikis, para confirmar se coincidem. Não serão feitas alterações até que você diga se tudo está correto.',
	'centralauth-merge-step1-submit'       => 'Confirmar informações de login',
	'centralauth-merge-step2-title'        => 'Confirmar contas adicionais',
	'centralauth-merge-step2-detail'       => 'Algumas das contas não coincidem com os dados da residência wiki fornecida. Caso tais contas pertençam a você, será possível confirmar de que são suas fornecendo a palavra-chave (senha) das mesmas.',
	'centralauth-merge-step2-submit'       => 'Confirmar informações de login',
	'centralauth-merge-step3-title'        => 'Criar conta unificada',
	'centralauth-merge-step3-detail'       => 'Tudo pronto para que a sua conta unificada, com as seguintes wikis à ela relacionada, seja criada:',
	'centralauth-merge-step3-submit'       => 'Unificar contas',
	'centralauth-complete'                 => 'Unificação de logins completa!',
	'centralauth-incomplete'               => 'Unificação de logins incompleta!',
	'centralauth-complete-text'            => 'Agora você poderá se logar em quaisquer das wikis da Wikimedia sem ter de criar uma nova conta; o mesmo nome de utilizador e senha funcionarãona Wikipedia, no Wikcionário, no Wikibooks e demais projetos, em todos os idiomas.',
	'centralauth-incomplete-text'          => 'Uma vez estando com seu login unificado, você poderá se logar em qualquer wiki da Wikimedia sem ter de criar novo cadastro; o mesmo nome de utilizador e senha funcionarãona Wikipedia, no Wikcionário, no Wikibooks e demais projetos, em todos os idiomas.',
	'centralauth-not-owner-text'           => 'O nome de utilizador "$1" foi automaticamente relacionado ao proprietário da conta em $2.

Se este for você, você poderá concluir o procedimento de unificação de login simplesmente digitando a senha principal de tal conta aqui:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Modo de demonstração</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'A unificação de contas se encontra no momento em modo exclusivamente de demonstração/testes. Lamentamos, mas as mesmas ainda não foram unificadas.',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Leia mais sobre o \'\'\'login unificado\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Residência wiki',
	'centralauth-list-home-dryrun'         => 'A palavra-chave (senha) e endereço de e-mail definidos nesta wiki serão os utilizados em sua conta unificada; sua página de utilizador será automaticamente lincada a partir de outras wikis. Será possível alterar qual é a sua residência wiki posteriormente.',
	'centralauth-list-attached-title'      => 'Contas relacionadas',
	'centralauth-list-attached'            => 'As contas nomeadas como "$1" nos seguintes sítios foram automaticamente fundidas:',
	'centralauth-list-attached-dryrun'     => 'A conta "$1" de cada um dos seguintes sítios será automaticamente fundida na conta unificada:',
	'centralauth-list-unattached-title'    => 'Contas não-relacionadas',
	'centralauth-list-unattached'          => 'A conta "$1" não pôde ser automaticamente confirmada como sendo tua nos seguintes sítios; provavelmente elas tenham uma senha diferente de sua conta principal:',
	'centralauth-foreign-link'             => 'Utilizador $1 em $2',
	'centralauth-finish-title'             => 'Completar fusão',
	'centralauth-finish-text'              => 'Se estas contas pertencem a ti, será possível concluir a unificação de logins simplesmente digitando as senhas das mesmas aqui:',
	'centralauth-finish-password'          => 'Senha:',
	'centralauth-finish-send-confirmation' => 'Enviar senha por e-mail',
	'centralauth-finish-problems'          => 'Está com problemas ou estas outras contas não são suas? [[meta:Help:Unified login problems|Como procurar por ajuda]]...',
	'centralauth-merge-attempt'            => '\'\'\'Verificando a senha fornecida para encontrar as demais contas ainda não fundidas...\'\'\'',
	'centralauth'                          => 'Administração de contas unificadas',
	'centralauth-admin-manage'             => 'Manusear dados de utilizador',
	'centralauth-admin-username'           => 'Utilizador:',
	'centralauth-admin-lookup'             => 'Ver ou editar dados de utilizador',
	'centralauth-admin-permission'         => 'Apenas stewards podem fundir as contas de outras pessoas.',
	'centralauth-admin-unmerge'            => 'Desfazer a fusão nos seleccionados',
	'centralauth-admin-merge'              => 'Fundir seleccionados',
	'centralauth-admin-bad-input'          => 'Selecção para fusão inválida',
	'centralauth-admin-none-selected'      => 'Não foram seleccionadas contas a serem modificadas.',
	'centralauth-prefs-status'             => 'Estado da conta unificada:',
	'centralauth-prefs-not-managed'        => 'Não está utilizando a conta unificada',
	'centralauth-prefs-unattached'         => 'Não confirmado',
	'centralauth-prefs-complete'           => 'Tudo em ordem!',
	'centralauth-prefs-migration'          => 'Migrando',
	'centralauth-prefs-count-attached'     => 'Sua conta se encontra ativa em $1 sítios de projetos.',
	'centralauth-prefs-count-unattached'   => 'Ainda existem contas não confirmadas com seu nome de utilizador em $1 projetos.',
	'centralauth-prefs-detail-unattached'  => 'Este sítio não foi confirmado como fazendo parte da conta unificada.',
	'centralauth-prefs-manage'             => 'Manusear sua conta unificada',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Não foi possível renomear localmente o utilizador $1 uma vez que a conta do mesmo foi migrada para o sistema de login universal.</div>',
);

$wgCentralAuthMessages['pt-br'] = $wgCentralAuthMessages['pt'];

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

$wgCentralAuthMessages['sv'] = array(
	'mergeaccount'                         => 'Status för förenad inloggning',
	'centralauth-merge-notlogged'          => 'Du måste <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} logga in]</span> för att se om dina konton har blivit fullständigt förenade.',
	'centralauth-merge-welcome'            => '\'\'\'Ditt konto har ännu inte flyttats över till Wikimedias förenade inloggningssystem.\'\'\'

Om du väljer att förena dina konton, så kommer du att kunna använda samma användarnamn och lösenord för att logga in på alla språkversioner av alla Wikimedias projekt.
På så sätt blir det enklare att arbeta på gemensamma projekt, till exempel att ladda upp filer till [http://commons.wikimedia.org/ Wikimedia Commons]. Det undviker också förvirring och andra problem som kan uppstå när olika personer har samma användarnamn på olika projekt.

Om någon annan redan har tagit ditt användarnamn på en annan sajt så påverkar det här inte henne nu, men du kommer att få möjlighet att reda ut det med henne eller med en administratör senare.',
	'centralauth-merge-step1-title'        => 'Påbörja förening av konton',
	'centralauth-merge-step1-detail'       => 'Ditt lösenord och din e-postadress kommer kontrolleras mot användarkonton på andra wikis för att bekräfta att de stämmer överens. Inga ändringar kommer genomföras innan du bekräftar att allting ser riktigt ut.',
	'centralauth-merge-step1-submit'       => 'Bekräfta lösenord',
	'centralauth-merge-step2-title'        => 'Bekräfta fler konton',
	'centralauth-merge-step2-detail'       => 'Några av konton kunde inte automatiskt matchas med kontot på den wiki som utsetts till hemwiki. Om dessa konton tillhör dig, så kan du bekräfta det genom att ange lösenorden för dem.',
	'centralauth-merge-step2-submit'       => 'Bekräfta lösenord',
	'centralauth-merge-step3-title'        => 'Skapa förenat konto',
	'centralauth-merge-step3-detail'       => 'Du kan nu skapa ditt förenade användarkonto, med följande wikis anslutna:',
	'centralauth-merge-step3-submit'       => 'Förena konton',
	'centralauth-complete-text'            => 'Du kan nu logga in på alla Wikimedias wikis utan att skapa nya konton. Samma användarnamn och lösenord kommer fungera på alla språkversioner av Wikipedia, Wiktionary, Wikibooks och deras systerprojekt.',
	'centralauth-not-owner-text'           => 'Användarnamnet "$1" tilldelades automatiskt ägaren av kontot på $2.

Om du är ägaren av det kontot, så kan du slutföra föreningsprocessen genom att ange lösenordet för det kontot här:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Endast demonstration</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'Förening av konton körs för närvarande i demonstrations- eller debugläge, så funktionerna som gör kontosammanslagningar är avaktiverade.',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Läs mer om \'\'\'förenad inloggning\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Hemwiki',
	'centralauth-list-home-dryrun'         => 'Ditt lösenord och din e-postadress på denna wiki kommer att användas för ditt förenade konto. Din användarsida på den wikin kommer automatiskt att länkas till från andra wikis. Du kommer senare kunna ändra wiki som är din hemwiki.',
	'centralauth-list-attached-title'      => 'Anslutna konton',
	'centralauth-list-attached'            => 'Konton med namnet "$1" på följade sajter har automatiskt anslutits till det förenade kontot:',
	'centralauth-list-attached-dryrun'     => 'Konton med namnet "$1" på följande sajter kommer automatiskt anslutas till det förenade kontot:',
	'centralauth-list-unattached-title'    => 'Ej anslutna konton',
	'centralauth-foreign-link'             => 'Användare $1 på $2',
	'centralauth-finish-password'          => 'Lösenord:',
	'centralauth-finish-login'             => 'Logga in',
	'centralauth-finish-send-confirmation' => 'Skicka lösenord via e-post',
	'centralauth'                          => 'Administration av förenad inloggning',
	'centralauth-admin-username'           => 'Användarnamn:',
	'centralauth-prefs-status'             => 'Status för globalt konto:',
	'centralauth-prefs-not-managed'        => 'Använder inte förenat konto',
	'centralauth-prefs-unattached'         => 'Obekräftat',
	'centralauth-prefs-count-attached'     => 'Ditt konto är aktivt på $1 projekt.',
	'centralauth-prefs-count-unattached'   => 'Obekräftade konton med ditt namn finns fortfarande på $1 projekt.',
	'centralauth-prefs-detail-unattached'  => 'Det är inte bekräftat att det här kontot tillhör det globala kontot.',
	'centralauth-prefs-manage'             => 'Hantera ditt globala konto',
);

$wgCentralAuthMessages['yue'] = array(
	// When not logged in...
	'mergeaccount' =>
		'登入統一狀態',
	'centralauth-merge-notlogged' =>
		'請<span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}}登入]' .
		'</span>去睇下檢查你嘅戶口係唔係已經完全整合。',
	'centralauth-merge-welcome' =>
		"'''你嘅用戶戶口重未整合到Wikimedia嘅統一登入系統。'''\n" .
		"\n" .
		"如果你係要去整合你個戶口嘅話，".
		"噉你就可以用同一個用戶名同密碼去登入全部Wikimedia中全部語言嘅計劃\n" .
		"噉樣做，可以更加容易噉響一啲共用嘅計劃度進行一啲工作，好似" .
		"[http://commons.wikimedia.org/ Wikimedia Commons]，" .
		"同埋避免用戶名混淆同相撞，以致響唔同嘅計劃度，兩位人揀咗同一個用戶名。\n" . 
		"\n" .
		"如果有另一啲人已經響另一個網站度揀咗你個用戶名嘅話，係唔會擾亂佢哋，" .
		"但係佢會畀你一個機會，稍後同佢地或者同管理員去處理。",

	'centralauth-merge-step1-title' => '開始登入統一',
	'centralauth-merge-step1-detail' =>
		'你嘅密碼同埋註冊嘅電郵地址會分別響其它wiki度檢查，去睇佢哋係一樣嘅。' .
		'直至到你確認啲嘢係無問題之前，都唔會有更改嘅。',
	'centralauth-merge-step1-submit' =>
		'確認登入資料',

	'centralauth-merge-step2-title' => '確認更多戶口',
	'centralauth-merge-step2-detail' =>
		"有啲戶口唔會自動噉同你自己嘅自家wiki站配合到。" .
		"如果呢啲戶口係屬於你嘅話，你可以為佢哋提供一個密碼去確認佢哋係屬於你嘅。\n",
	'centralauth-merge-step2-submit' =>
		'確認登入資料',

	'centralauth-merge-step3-title' => '開個統一戶口',
	'centralauth-merge-step3-detail' =>
		"你已經預備好響加入咗嘅wiki度，去開一個統一戶口：",
	'centralauth-merge-step3-submit' =>
		'統一戶口',

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

	'centralauth-notice-dryrun' =>
		"<div class='successbox'>只係示範模式</div><br clear='all'/>",
	
	'centralauth-disabled-dryrun' =>
		"戶口統一而家係響示範／除錯模式，" .
		"噉實際嘅合併動作已經停用。對唔住！",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|睇下更多有關'''統一登入'''嘅細節]]...''",

	// For lists of wikis/accounts:
	'centralauth-list-home-title' =>
		'自家wiki',
	'centralauth-list-home-dryrun' =>
		'你響呢個wiki嘅密碼同電郵地址會用來做你嘅統一戶口，' .
		'同時你響呢度嘅用戶頁會由其它嘅wiki度自動連結過來。' .
		"你可以響稍後嘅時間去改你嘅自家wiki。",
	'centralauth-list-attached-title' =>
		'已經附加嘅戶口',
	'centralauth-list-attached' =>
		'以下用戶名 "$1" 嘅戶口' .
		'已經自動噉樣合併咗：',
	'centralauth-list-attached-dryrun' =>
		'下面每一個網站，個名係"$1"嘅戶口' .
		'將會自動附加到一個統一戶口度：',
	'centralauth-list-unattached-title' =>
		'未附加嘅戶口',
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
		"'''檢查緊所輸入嘅密碼，同剩底未合併戶口相對...'''",

	// Administrator's console
	'centralauth' => '統一戶口管理',
	'centralauth-admin-manage' =>
		'管理用戶資料',
	'centralauth-admin-username' =>
		'用戶名：',
	'centralauth-admin-lookup' =>
		'去睇或者編輯用戶資料',
	'centralauth-admin-permission' =>
		"只有執行員先至可以為用戶合併其它人嘅戶口。",
	'centralauth-admin-unmerge' =>
		'唔合併已經揀咗嘅',
	'centralauth-admin-merge' =>
		'合併已經揀咗嘅',
	'centralauth-admin-bad-input' =>
		'唔啱嘅合併選擇',
	'centralauth-admin-none-selected' =>
		'無戶口揀咗去改。',

	// Info panel in preferences
	'centralauth-prefs-status' =>
		'全域戶口狀態：',
	'centralauth-prefs-not-managed' =>
		'唔係用緊統一戶口',
	'centralauth-prefs-unattached' =>
		'未確認',
	'centralauth-prefs-complete' =>
		'全部完成！',
	'centralauth-prefs-migration' =>
		'遷移中',
	'centralauth-prefs-count-attached' =>
		'你個戶口響$1個計劃網站度係活躍嘅。',
	'centralauth-prefs-count-unattached' =>
		'你響$1個計劃度重有未確認嘅戶口。',
	'centralauth-prefs-detail-unattached' =>
		'呢個計劃網站重未確認到個全域戶口。',
	'centralauth-prefs-manage' =>
		'管理你個全域戶口',
	
	// Interaction with Special:Renameuser
	'centralauth-renameuser-abort' =>
		"<div class=\"errorbox\">" .
		"由於呢個用戶名已經遷移到統一登入系統，因此唔可以響本地度改$1做呢個用戶名。</div>",

);

$wgCentralAuthMessages['zh-hans'] = array(
	// When not logged in...
	'mergeaccount' =>
		'登录统一状态',
	'centralauth-merge-notlogged' =>
		'请<span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} 登录]' .
		'并检查您的账号是否都已经合并。',
	'centralauth-merge-welcome' =>
		"'''您的用户账户尚未整合到维基媒体的统一登录系统。'''\n" .
		"\n" .
		"如果您系要去整合您的账户的话，".
		"那您就可以使用同一个用户名跟密码去登录所有维基媒体中所有语言的计划\n" .
		"这样做，可以更加容易地在一些共用的计划中进行一些工作，好像" .
		"[http://commons.wikimedia.org/ 维基共享资源]，" .
		"以及避免用户名混淆和相撞，以致在不同的计划中，两位人选择同一个用户名。\n" . 
		"\n" .
		"如果有另一些人已经在另一个网站中选择了您的用户名，是不会扰乱他们的，" .
		"但是它会给予您一个机会，稍后跟他们或者管理员去处理。",

	'centralauth-merge-step1-title' => '开始登录整合',
	'centralauth-merge-step1-detail' =>
		'您的密码以及注册的电邮地址会分别在其它维基站中检查，去查看它们是一样的。' .
		'直至到您确认这些是没有问题之前，都不会有所更改。',
	'centralauth-merge-step1-submit' =>
		'确认登录资料',

	'centralauth-merge-step2-title' => '确认更多账户',
	'centralauth-merge-step2-detail' =>
		"有些账户不会自动地跟您自己的自家网基站配合到。" .
		"如果这些账户是属于您的话，您可以为它们提供一个密码去确认它们是属于您的。\n",
	'centralauth-merge-step2-submit' =>
		'确认登录资料',

	'centralauth-merge-step3-title' => '建立统一账户',
	'centralauth-merge-step3-detail' =>
		"您已经预备好在已加入的维基站中，去创建一个统一账户：",
	'centralauth-merge-step3-submit' =>
		'合并账户',

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

	'centralauth-notice-dryrun' =>
		"<div class='successbox'>只是演示方式</div><br clear='all'/>",
	
	'centralauth-disabled-dryrun' =>
		"账户统一现正于演示／除错方式，" .
		"那实际的合并动作已经禁用。抱歉！",
	
	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|参阅关于'''登录统一'''的帮助文件]]...''",

	// For lists of wikis/accounts:
	'centralauth-list-home-title' =>
		'自家维基站',
	'centralauth-list-home-dryrun' =>
		'您在这个维基站的密码以及电邮地址会用来做您的统一账户，' .
		'同时您在这里的用户页会由其它的维基站中自动链接过来。' .
		"您可以在稍后的时间去更改你的自家站。",
	'centralauth-list-attached-title' =>
		'已经附加的账户',
	'centralauth-list-attached' =>
		'以下网站的账号“$1”' .
		'已自动合并：',
	'centralauth-list-attached-dryrun' =>
		'以下每一个网站，名字是"$1"的账户' .
		'将会自动附加到一个统一账户中：',
	'centralauth-list-unattached-title' =>
		'未附加的账户',
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
	'centralauth' => '统一账户管理',
	'centralauth-admin-manage' =>
		'管理用户资料',
	'centralauth-admin-username' =>
		'用户名称：',
	'centralauth-admin-lookup' =>
		'查看或编辑用户资料',
	'centralauth-admin-permission' =>
		"只有监管员可以为其他人进行登录统一。",
	'centralauth-admin-unmerge' =>
		'拆分所选项',
	'centralauth-admin-merge' =>
		'合并所选项',
	'centralauth-admin-bad-input' =>
		'不正确的整合选择',
	'centralauth-admin-none-selected' =>
		'没有帐户选择作修改。',

	// Info panel in preferences
	'centralauth-prefs-status' =>
		'全域账户状态：',
	'centralauth-prefs-not-managed' =>
		'不是正在使用统一账户',
	'centralauth-prefs-unattached' =>
		'未确认',
	'centralauth-prefs-complete' =>
		'全部完成！',
	'centralauth-prefs-migration' =>
		'迁移中',
	'centralauth-prefs-count-attached' =>
		'您的账户在$1个计划网站中是活跃的。',
	'centralauth-prefs-count-unattached' =>
		'您在$1个计划中还有未确认的账户。',
	'centralauth-prefs-detail-unattached' =>
		'这个计划网站还未确认到全域账户。',
	'centralauth-prefs-manage' =>
		'管理您的全域账户',
	
	// Interaction with Special:Renameuser
	'centralauth-renameuser-abort' =>
		"<div class=\"errorbox\">" .
		"由于这个用户名已经迁移到统一登入系统，因此不能在本地中更改$1作为这个用户名。</div>",
		
);

$wgCentralAuthMessages['zh-hant'] = array(
	// When not logged in...
	'mergeaccount' =>
		'帳號整合狀態',
	'centralauth-merge-notlogged' =>
		'請<span class="plainlinks">' .
		'[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}}登入]' .
		'</span>以查驗您的帳號是否已經完成整合。',
	'centralauth-merge-welcome' =>
		"'''您的用戶帳戶尚未整合到維基媒體的統一登入系統。'''\n" .
		"\n" .
		"如果您係要去整合您的帳戶的話，".
		"那您就可以使用同一個用戶名跟密碼去登入所有維基媒體中所有語言的計劃\n" .
		"這樣做，可以更加容易地在一些共用的計劃中進行一些工作，好像" .
		"[http://commons.wikimedia.org/ 維基共享資源]，" .
		"以及避免用戶名混淆和相撞，以致在不同的計劃中，兩位人選擇同一個用戶名。\n" . 
		"\n" .
		"如果有另一些人已經在另一個網站中選擇了您的用戶名，是不會擾亂他們的，" .
		"但是它會給予您一個機會，稍後跟他們或者管理員去處理。",

	'centralauth-merge-step1-title' => '開始登入整合',
	'centralauth-merge-step1-detail' =>
		'您的密碼以及註冊的電郵地址會分別在其它維基站中檢查，去查看它們是一樣的。' .
		'直至到您確認這些是沒有問題之前，都不會有所更改。',
	'centralauth-merge-step1-submit' =>
		'確認登入資料',

	'centralauth-merge-step2-title' => '確認更多帳戶',
	'centralauth-merge-step2-detail' =>
		"有些帳戶不會自動地跟您自己的自家網基站配合到。" .
		"如果這些帳戶是屬於您的話，您可以為它們提供一個密碼去確認它們是屬於您的。\n",
	'centralauth-merge-step2-submit' =>
		'確認登入資料',

	'centralauth-merge-step3-title' => '建立統一帳戶',
	'centralauth-merge-step3-detail' =>
		"您已經預備好在已加入的維基站中，去建立一個統一帳戶：",
	'centralauth-merge-step3-submit' =>
		'整合帳戶',

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

	'centralauth-notice-dryrun' =>
		"<div class='successbox'>只是示範模式</div><br clear='all'/>",
	
	'centralauth-disabled-dryrun' =>
		"帳戶整合現在於示範／除錯模式，" .
		"那實際的整合動作已經停用。抱歉！",

	// Appended to various messages above
	'centralauth-readmore-text' =>
		":''[[meta:Help:Unified login|了解更多'''帳號整合'''細節]]...''",

	// For lists of wikis/accounts:
	'centralauth-list-home-title' =>
		'自家維基站',
	'centralauth-list-home-dryrun' =>
		'您在這個維基站的密碼以及電郵地址會用來做您的統一帳戶，' .
		'同時您在這裡的用戶頁會由其它的維基站中自動連結過來。' .
		"您可以在稍後的時間去更改你的自家站。",
	'centralauth-list-attached-title' =>
		'已經附加的帳戶',
	'centralauth-list-attached' =>
		'以下網站的帳號："$1' .
		'已自動完成整合：',
	'centralauth-list-attached-dryrun' =>
		'以下每一個網站，名字是"$1"的帳戶' .
		'將會自動附加到一個統一帳戶中：',
	'centralauth-list-unattached-title' =>
		'未附加的帳戶',
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
	'centralauth' => '統一帳戶管理',
	'centralauth-admin-manage' =>
		'管理用戶資料',
	'centralauth-admin-username' =>
		'用戶名稱：',
	'centralauth-admin-lookup' =>
		'檢視或編輯用戶資料',
	'centralauth-admin-permission' =>
		"只有監管員可以為用戶整合帳號。",
	'centralauth-admin-unmerge' =>
		'不整合已選取的',
	'centralauth-admin-merge' =>
		'整合已選取的',
	'centralauth-admin-bad-input' =>
		'不正確的整合選擇',
	'centralauth-admin-none-selected' =>
		'沒有帳戶選擇作修改。',

	// Info panel in preferences
	'centralauth-prefs-status' =>
		'全域帳戶狀態：',
	'centralauth-prefs-not-managed' =>
		'不是正在使用統一帳戶',
	'centralauth-prefs-unattached' =>
		'未確認',
	'centralauth-prefs-complete' =>
		'全部完成！',
	'centralauth-prefs-migration' =>
		'遷移中',
	'centralauth-prefs-count-attached' =>
		'您的帳戶在$1個計劃網站中是活躍的。',
	'centralauth-prefs-count-unattached' =>
		'您在$1個計劃中還有未確認的帳戶。',
	'centralauth-prefs-detail-unattached' =>
		'這個計劃網站還未確認到全域帳戶。',
	'centralauth-prefs-manage' =>
		'管理您的全域帳戶',
	
	// Interaction with Special:Renameuser
	'centralauth-renameuser-abort' =>
		"<div class=\"errorbox\">" .
		"由於這個用戶名已經遷移到統一登入系統，因此不能在本地中更改$1作為這個用戶名。</div>",

);

$wgCentralAuthMessages['zh-tw'] = $wgCentralAuthMessages['zh-hant'];
$wgCentralAuthMessages['zh-tw'] = array(
	// When not logged in...
	'centralauth-merge-welcome' =>
		"'''您的用戶帳戶尚未整合到維基媒體的統一登入系統。'''\n" .
		"\n" .
		"如果您係要去整合您的帳戶的話，".
		"那您就可以使用同一個用戶名跟密碼去登入所有維基媒體中所有語言的計畫\n" .
		"這樣做，可以更加容易地在一些共用的計畫中進行一些工作，好像" .
		"[http://commons.wikimedia.org/ 維基共享資源]，" .
		"以及避免用戶名混淆和相撞，以致在不同的計畫中，兩位人選擇同一個用戶名。\n" . 
		"\n" .
		"如果有另一些人已經在另一個網站中選擇了您的用戶名，是不會擾亂他們的，" .
		"但是它會給予您一個機會，稍後跟他們或者管理員去處理。",

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

	// Info panel in preferences
	'centralauth-prefs-count-attached' =>
		'您的帳戶在$1個計畫網站中是活躍的。',
	'centralauth-prefs-count-unattached' =>
		'您在$1個計畫中還有未確認的帳戶。',
	'centralauth-prefs-detail-unattached' =>
		'這個計畫網站還未確認到全域帳戶。',

);

$wgCentralAuthMessages['zh'] = $wgCentralAuthMessages['zh-hans'];
$wgCentralAuthMessages['zh-cn'] = $wgCentralAuthMessages['zh-hans'];
$wgCentralAuthMessages['zh-hk'] = $wgCentralAuthMessages['zh-hant'];
$wgCentralAuthMessages['zh-sg'] = $wgCentralAuthMessages['zh-hans'];
$wgCentralAuthMessages['zh-yue'] = $wgCentralAuthMessages['yue'];
