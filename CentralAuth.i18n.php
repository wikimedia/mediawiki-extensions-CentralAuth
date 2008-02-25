<?php

$messages = array();

$messages['en'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Login unification status',
	'centralauth-desc' => 'Merge Account across Wikimedia Foundation wikis',
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

/** Afrikaans (Afrikaans)
 * @author SPQRobin
 */
$messages['af'] = array(
	'centralauth-foreign-link'   => 'Gebruiker $1 op $2',
	'centralauth-admin-username' => 'Gebruikersnaam:',
);

/** Aragonese (Aragonés)
 * @author Juanpabl
 */
$messages['an'] = array(
	'mergeaccount'                   => "Estau d'a unificazión de cuentas",
	'centralauth-desc'               => 'Unificar as cuentas en as wikis de Wikimedia Foundation',
	'centralauth-merge-notlogged'    => 'Por fabor <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} identifique-se]</span> ta comprebar si as suyas cuentas s\'han combinato de tot.',
	'centralauth-merge-welcome'      => "'''A suya cuenta d'usuario no s'ha tresladato encara ta o sistema de cuentas unificato de Wikimedia'''

Si triga migrar as suyas cuentas, podrá usar o mesmo nombre d'usuario y palabra de paso ta dentrar en toz os procheutos wiki de Wikimedia en todas as luengas.
Isto fa más fázil o treballo compartito entre procheutos, como cargar archibos ta [http://commons.wikimedia.org/ Wikimedia Commons], y priba que bi aiga a confusion u o conflito que podría escaizer si dos presonas trigan o mesmo nombre d'usuario en procheutos diferents.

Si bel usuario más ha pillato o suyo nombre d'usuario en atro sitio, podrá contautar con el u con un almenistrador más entadebant.",
	'centralauth-merge-step1-title'  => 'Prenzipiar a unificazión de cuentas',
	'centralauth-merge-step1-detail' => "A suya palabra de paso y adreza de correu-e rechistrada se comprebarán con as d'atras wikis ta confirmar si concuerdan. No se ferá garra cambio dica que confirme que o resultau pareixe correuto.",
	'centralauth-merge-step1-submit' => "Confirmar a informazión d'a cuenta",
	'centralauth-merge-step2-title'  => 'Confirmar más cuentas',
	'centralauth-merge-step2-detail' => "Bellas cuentas no s'han puesto concordar automaticament con o wiki endicato. Si estas cuentas le pertenexen, puede confirmar que son suyas escribindo as suyas palabras de paso.",
	'centralauth-merge-step2-submit' => "Confirmar a informazión d'a cuenta",
	'centralauth-merge-step3-title'  => 'Creyar una cuenta unificata',
	'centralauth-merge-step3-detail' => 'Ya ye parau ta creyar una cuenta unificada, con os siguients wikis binculatos:',
	'centralauth-merge-step3-submit' => 'Unificar cuentas',
	'centralauth-complete'           => "S'ha rematau a unificazión d'as cuentas!",
	'centralauth-incomplete'         => "No s'ha rematau a unificazión d'as cuentas!",
);

$messages['ang'] = array(
	'centralauth-admin-username'           => 'Brūcendnama:',
);

/** Arabic (العربية)
 * @author Meno25
 */
$messages['ar'] = array(
	'mergeaccount'                         => 'حالة توحيد الدخول',
	'centralauth-desc'                     => 'دمج الحساب عبر ويكيات مؤسسة ويكيميديا',
	'centralauth-merge-notlogged'          => 'من فضلك <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} قم بتسجيل الدخول]</span> لتتحقق من أن حساباتك تم دمجها بالكامل.',
	'centralauth-merge-welcome'            => "'''حساب المستخدم الخاص بك لم يتم نقله إلى نظام ويكيميديا لتوحيد الدخول.'''

إذا اخترت دمج حساباتك، سيمكنك استخدام نفس اسم المستخدم و كلمة السر للدخول لكل مشاريع ويكيميديا بكل اللغات المتوفرة.
هذا يجعل من السهل العمل مع المشاريع المشتركة مثل الرفع ل [http://commons.wikimedia.org/ ويكيميديا كومنز]، و يتجنب الارتباك أو التعارض الذي قد ينشأ عندما يستخدم شخصان نفس اسم المستخدم في مشاريع مختلفة.

لو كان شخص آخر أخذ اسم المستخدم الخاص بك في موقع آخر هذا لن يزعجهم، ولكن سيعطيك فرصة للتعامل معهم أو مع إداري فيما بعد.",
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
	'centralauth-notice-dryrun'            => "<div class='successbox'>نمط التجربة فقط</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'توحيد الحساب حاليا في طور التجربة/تصحيح الأخطاء، لذا عمليات الدمج الفعلية معطلة. عذرا!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|اقرأ المزيد حول '''الدخول الموحد''']]...''",
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
	'centralauth-merge-attempt'            => "'''التحقق من كلمة السر المعطاة ضد الحسابات الباقية غير المدمجة...'''",
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

/** Asturian (Asturianu)
 * @author Esbardu
 */
$messages['ast'] = array(
	'mergeaccount'                         => 'Estatus de fusión de cuentes',
	'centralauth-desc'                     => 'Fusiona cuentes ente les wikis de la Fundación Wikimedia',
	'centralauth-merge-notlogged'          => 'Por favor <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} identifícate]</span> pa comprobar si les tos cuentes se fusionaron completamente.',
	'centralauth-merge-welcome'            => "'''La to cuenta d'usuariu inda nun foi migrada al sistema de cuentes fusionaes de Wikimedia.'''

Si decides migrar les tos cuentes, podrás usar el mesmu nome d'usuariu y la mesma clave pa identificate en toles wikis de Wikimedia de cualesquier llingua disponible.
Esto facilita trabayar con proyeutos compartíos como les xubíes a [http://commons.wikimedia.org/ Wikimedia Commons], y evita la confusión o'l conflictu que pudiera surdir al escoyer dos persones el mesmu nome d'usuariu en proyeutos distintos.

Si daquién yá escoyó'l to nome d'usuariu n'otru sitiu nun habría haber problema, yá que podrás ponete en contautu con él o con un alministrador más tarde.",
	'centralauth-merge-step1-title'        => 'Emprimar la fusión de cuentes',
	'centralauth-merge-step1-detail'       => 'La to clave y la to direición rexistrada de corréu electrónicu van ser comprobaes coles de les cuentes de les demás wikis pa confirmar que concueyen. Nun se van facer cambeos hasta que confirmes que too ta correcho.',
	'centralauth-merge-step1-submit'       => 'Confirmar la fusión de cuentes',
	'centralauth-merge-step2-title'        => 'Confirmar más cuentes',
	'centralauth-merge-step2-detail'       => 'Dalgunes de les cuentes nun pudieron ser automáticamente asociaes a la wiki principal conseñada. Si estes cuentes son de yo, pues confirmalo escribiendo la so clave.',
	'centralauth-merge-step2-submit'       => 'Confirmar la información de cuentes',
	'centralauth-merge-step3-title'        => 'Crear la cuenta fusionada',
	'centralauth-merge-step3-detail'       => 'Tas a piques de crear la to cuenta fusionada, coles siguientes wikis asociaes:',
	'centralauth-merge-step3-submit'       => 'Fusionar cuentes',
	'centralauth-complete'                 => '¡Fusión de cuentes completada!',
	'centralauth-incomplete'               => '¡Fusión de cuentes non completada!',
	'centralauth-complete-text'            => "Agora yá pues identificate en cualesquier wiki de Wikimedia ensin crear una cuenta nueva; el mesmu nome d'usuariu y la mesma clave van funcionar en Wikipedia, Wiktionary, Wikiboos y los sos proyeutos hermanos en cualesquier llingua.",
	'centralauth-incomplete-text'          => "En cuantes que la to cuenta tea fusionada, podrás identificate en cualesquier wiki de Wikimedia ensin crear una cuenta nueva; el mesmu nome d'usuariu y la mesma clave van funcionar en Wikipedia, Wiktionary, Wikiboos y los sos proyeutos hermanos en cualesquier llingua.",
	'centralauth-not-owner-text'           => 'El nome d\'usuariu "$1" asignóse automáticamente al poseyedor de la cuenta en $2.

Si yes tu, pues finar el procesu de fusión de cuentes escribiendo simplemente la clave maestra pa esa cuenta equí:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Namái mou demo</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'La fusión de cuentes ta nestos momentos nun estáu de depuración / demo, polo que les operaciones de fusión tán desactivaes anguaño. ¡Sentímoslo!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Llei más tocante a '''cuenta fusionada''']]...''",
	'centralauth-list-home-title'          => 'Wiki principal',
	'centralauth-list-home-dryrun'         => "La clave y la direición de corréu electrónicu conseñaos nesta wiki va ser usada pola to cuenta fusionada, y la to páxina d'usuariu d'equí va ser enllaciada automáticamente dende les demás wikis. Podrás camudar cuála ye la to wiki principal más tarde.",
	'centralauth-list-attached-title'      => 'Cuentes asociaes',
	'centralauth-list-attached'            => 'La cuenta nomada "$1" en caún de los siguientes sitios foi asociada automáticamente a la cuenta fusionada:',
	'centralauth-list-attached-dryrun'     => 'La cuenta nomada "$1" en caún de los siguientes sitios va ser asociada automáticamente a la cuenta fusionada:',
	'centralauth-list-unattached-title'    => 'Cuentes non asociaes',
	'centralauth-list-unattached'          => 'Nun se pudo confirmar automáticamente que la cuenta "$1" ye de to nos siguientes sitios; lo más probable ye que tengan claves distintes a les de la to cuenta primaria:',
	'centralauth-foreign-link'             => 'Usuariu $1 en $2',
	'centralauth-finish-title'             => 'Completar la fusión',
	'centralauth-finish-text'              => 'Si estes cuentes son de to, pues finar el procesu de fusión de cuentes escribiendo simplemente les claves de les otres cuentes equí:',
	'centralauth-finish-password'          => 'Clave:',
	'centralauth-finish-login'             => 'Cuenta',
	'centralauth-finish-send-confirmation' => 'Clave de corréu electrónicu',
	'centralauth-finish-problems'          => '¿Tienes problemes o nun poseyes estes otres cuentes? [[meta:Help:Unified login problems|Cómo atopar aida]]...',
	'centralauth-merge-attempt'            => "'''Comprobando la clave conseñada pa les cuentes non fusionaes...'''",
	'centralauth-attach-list-attached'     => 'La cuenta fusionada nomada "$1" inclúi les siguientes cuentes:',
	'centralauth-attach-title'             => 'Confirmar cuenta',
	'centralauth-attach-text'              => 'Esta cuenta inda nun foi migrada a la cuenta fusionada. Si la cuenta global tamién ye de to, pues fusionar esta cuenta escribiendo la clave de cuenta global:',
	'centralauth-attach-submit'            => 'Migrar cuenta',
	'centralauth-attach-success'           => 'La cuenta foi migrada a la cuenta fusionada.',
	'centralauth'                          => 'Alministración de cuentes fusionaes',
	'centralauth-admin-manage'             => "Remanar los datos d'usuariu",
	'centralauth-admin-username'           => "Nome d'usuariu:",
	'centralauth-admin-lookup'             => "Ver o editar los datos d'usuariu",
	'centralauth-admin-permission'         => "Namái los stewards puen fusionar les cuentes d'otres persones por ellos.",
	'centralauth-admin-unmerge'            => 'Dixebrar seleicionaes',
	'centralauth-admin-merge'              => 'Fusionar seleicionaes',
	'centralauth-admin-bad-input'          => 'Seleición de fusión non válida',
	'centralauth-admin-none-selected'      => 'Nun se seleicionaron cuentes pa modificar.',
	'centralauth-prefs-status'             => 'Estatus de cuenta global:',
	'centralauth-prefs-not-managed'        => 'Nun se ta usando la cuenta fusionada',
	'centralauth-prefs-unattached'         => 'Non confirmada',
	'centralauth-prefs-complete'           => "¡Too n'orde!",
	'centralauth-prefs-migration'          => 'Migrando',
	'centralauth-prefs-count-attached'     => 'La to cuenta ta activa {{plural:$1|nun sitiu|en $1 sitios}} de proyeutu.',
	'centralauth-prefs-count-unattached'   => 'Queden cuentes non confirmaes col to nome {{plural:$1|nun proyeutu|en $1 proyeutos}}.',
	'centralauth-prefs-detail-unattached'  => "Nun se confirmó la pertenencia d'esti sitiu de proyeutu a la cuenta global.",
	'centralauth-prefs-manage'             => 'Remanar la to cuenta global',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Nun se pue renomar llocalmente al usuariu $1 yá que esti nome d\'usuariu foi migráu al sistema de cuentes fusionaes.</div>',
);

/** Kotava (Kotava)
 * @author Wikimistusik
 */
$messages['avk'] = array(
	'centralauth-merge-step1-submit'    => 'Va dogluyaragivara gruyel !',
	'centralauth-merge-step2-title'     => 'Va lo pata se gruyel !',
	'centralauth-merge-step2-submit'    => 'Va dogluyaragivara gruyel !',
	'centralauth-merge-step3-title'     => 'Redura va tutanana pata',
	'centralauth-merge-step3-submit'    => 'Tutanara va pata se',
	'centralauth-list-attached-title'   => 'Benef pateem',
	'centralauth-list-unattached-title' => 'Mebenef pateem',
	'centralauth-foreign-link'          => '$1 favesik moe $2',
	'centralauth-attach-title'          => 'Patagruyera',
	'centralauth-attach-submit'         => 'Patarrundara',
	'centralauth-admin-manage'          => 'Bowera va favesikorigeem',
	'centralauth-admin-username'        => 'Favesikyolt :',
	'centralauth-admin-lookup'          => 'Wira va favesikorigeem oku betara',
	'centralauth-admin-unmerge'         => 'Voljoara rebana',
	'centralauth-admin-merge'           => 'Joara rebana',
);

$messages['bcl'] = array(
	'centralauth-list-home-title'          => 'Harong na wiki',
	'centralauth-finish-login'             => 'Maglaog',
);

/** Bulgarian (Български)
 * @author DCLXVI
 */
$messages['bg'] = array(
	'centralauth-desc'            => 'Сливане на потребителски сметки от няколко уикита',
	'centralauth-notice-dryrun'   => "<div class='successbox'>Само демонстрационен режим</div><br clear='all'/>",
	'centralauth-foreign-link'    => 'Потребител $1 от $2',
	'centralauth-finish-password' => 'Парола:',
	'centralauth-finish-login'    => 'Влизане',
	'centralauth-attach-title'    => 'Потвърждаване на сметка',
	'centralauth-attach-submit'   => 'Мигриране на сметка',
	'centralauth-admin-manage'    => 'Управление на потребителските данни',
	'centralauth-admin-username'  => 'Потребителско име:',
	'centralauth-admin-lookup'    => 'Преглед и редактиране на потребителските данни',
);

/** Bengali (বাংলা)
 * @author Bellayet
 * @author Zaheen
 */
$messages['bn'] = array(
	'mergeaccount'                         => 'লগ-ইন একত্রিকরণ অবস্থা',
	'centralauth-desc'                     => 'উইকিমিডিয়া ফাউন্ডেশন উইকিসমূহের মধ্যে অ্যাকাউন্ট একীভূত করো',
	'centralauth-merge-notlogged'          => 'দয়াকরে <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} লগ-ইন করুন]</span> যাতে পরীক্ষা করতে পারেন আপনার অ্যাকাউন্ট সম্পূর্ণরূপে একীভূত হয়েছে কিনা।',
	'centralauth-merge-welcome'            => "'''আপনার ব্যবহারকারী অ্যাকাউন্ট উইকিমিডিয়ার একীভূত লগ-ইন পদ্ধতির সাথে একীভূত করা হয় নাই।'''

যদি একীভূত করার জন্য আপনার অ্যাকাউন্ট পছন্দ করেন, তাহলে আপনি উইকিমিডিয়ার প্রকল্পে সমস্ত ভাষার উইকিতে একই ব্যবহারকারী নাম এবং শব্দচাবি দিয়ে লগ-ইন করতে পারবেন।

এটি সহপ্রকল্পকে কাজ করা যেমন [http://commons.wikimedia.org/ উইকিমিডিয়া কমন্স] এ আপলোড করা সহজ করবে, এবং একই ব্যবহারকারী নাম বিভিন্ন প্রকল্পে একাধিক ব্যক্তি ব্যবহার করা নিয়ে দ্বিধা দ্বন্দের অবসা করবে।

যদি অন্য কেউ ইতিমধ্যে এই ব্যবহারকারী নাম অন্যকেউ অন্য কোন সাইটে ব্যবহার করে থাকে, তাদের বিরক্ত করবেন না, তবে এ ব্যপারে আপনাকে তাদের অথবা কোন প্রশাসকের সাথে কাজ করার সুযোগ করে দিবে।",
	'centralauth-merge-step1-title'        => 'লগইন একত্রীকরণ শুরু করো',
	'centralauth-merge-step1-detail'       => 'আপনার শব্দচাবি এবং নিবন্ধকৃত ইমেইল ঠিকানা, আপনার অ্যাকাউন্টের বিপক্ষে পরীক্ষা করা হবে যাতে অন্য উইকিতে নিশ্চিতভাবে মিলে যায়। এটি ঠিক আছে তা আপনি নিশ্চত না করা পর্যন্ত কোন পরিবর্তন করা হবে না।',
	'centralauth-merge-step1-submit'       => 'লগইন তথ্য নিশ্চিত করুন',
	'centralauth-merge-step2-title'        => 'আরও অ্যাকাউন্ট নিশ্চিত করুন',
	'centralauth-merge-step2-detail'       => 'কিছু অ্যাকাউন্টসমূহ সয়ংক্রিয় ভাবে আপনার প্রধান উইকির অ্যাকাউন্টের সাথে মিলে না। যদি ঐ অ্যাকাউন্টসমূহ আপনার হয়ে থাকে, শব্দচাবি ব্যবহার করে আপনি নিশ্চিত করুন যে  ঐ অ্যাকাউন্টগুলো আপনার।',
	'centralauth-merge-step2-submit'       => 'লগইন তথ্য নিশ্চিত করুন',
	'centralauth-merge-step3-title'        => 'একত্রিত অ্যাকাউন্ট সৃষ্টি করা হোক',
	'centralauth-merge-step3-detail'       => 'আপনি আপনার একত্রিত অ্যাকাউন্ট সৃষ্টি করতে প্রস্তুত, সাথে নিচের উইকিগুলি সংযুক্ত হবে:',
	'centralauth-merge-step3-submit'       => 'অ্যাকাউন্ট একত্রিত করা হোক',
	'centralauth-complete'                 => 'অ্যাকাউন্ট একত্রীকরণ সমাপ্ত!',
	'centralauth-incomplete'               => 'অ্যাকাউন্ট একত্রীকরণ নিষ্পন্ন হয়নি!',
	'centralauth-complete-text'            => 'আপনি এখন যেকোন উইকিমিডিয়া উইকি সাইটে অ্যাকাউন্ট তৈরি ছাড়াই লগ-ইন করতে পারবেন; উইকিপিডিয়া, উইকশনারি, উইকিবুক এবং সকল ভাষায় তাদের সহ প্রকল্পসমূহে  একই ব্যবহারকারী নাম এবং শব্দচাবি কাজ করবে।',
	'centralauth-incomplete-text'          => 'একবার আপনার লগ-ইন একীভূত হয়ে গেলে, আপনি যেকোন উইকিমিডিয়া উইকিতে নতুন অ্যাকাউন্ট তৈরি করা ছাড়াই লগ-ইন করতে পারবেন; উইকিপিডিয়া, উইকশনারি, উইকিবুক এবং সকল ভাষায় তাদের সহ প্রকল্পসমূহে একই ব্যবহারকারী নাম এবং শব্দচাবি কাজ করবে।',
	'centralauth-not-owner-text'           => 'ব্যবহারকারী নাম "$1" সয়ংক্রিয়ভাবে $2 তে অ্যাকাউন্টের মালিকের জন্য বরাদ্দ হয়েছে।

যদি আপনিই তিনি হন, তাহলে এখনে দেওয়া মূল/মাস্টার শব্দচাবি দিতে লগ-ইন করে লগ-ইন একীভূতকরণ শেষ করতে পারেন:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>ডেমো/পরীক্ষামূলক অবস্থা</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'অ্যাকাউন্ট একীভূতকরণ বর্তমানে ডেমো/পরীক্ষামূলক অবস্থায় আছে, তাই মূল একীকরণের কাজ সক্রিয় নয়। দুঃখিত!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|'''একীভূত লগ-ইন''' সম্পর্কে আরও পড়ুন]]...''",
	'centralauth-list-home-title'          => 'প্রধান উইকি',
	'centralauth-list-home-dryrun'         => 'এই উইকিতে দেওয়া শব্দচাবি এবং ইমেইল ঠিকানা আপনার একীভূত অ্যাকাউন্টের জন্য ব্যবহৃত হয়েছে, এবং আপনার ব্যবহারকারীপাতা এখান থেকে সয়ংক্রিয়ভাবে অন্যান্য উইকিসমূহে সংযুক্ত হবে। আপনার প্রধান উইকিতে যা আছে তা আপনি পরবর্তীতে পরিবর্তন করতে পারবেন।',
	'centralauth-list-attached-title'      => 'সংগবদ্ধ অ্যাকাউন্ট',
	'centralauth-list-attached'            => 'এই সমস্ত সাইটে সয়ংক্রিয়ভাবে অ্যাকাউন্ট নাম "$1" একীভূত অ্যাকাউন্টের সাথে যুক্ত হয়েছে:',
	'centralauth-list-attached-dryrun'     => 'এই সমস্ত সাইটে সয়ংক্রিয়ভাবে অ্যাকাউন্ট নাম "$1" একীভূত অ্যাকাউন্টের সাথে যুক্ত হবে:',
	'centralauth-list-unattached-title'    => 'অসংগবদ্ধ অ্যাকাউন্ট',
	'centralauth-list-unattached'          => 'অ্যাকাউন্ট "$1" সয়ংক্রিয়ভাবে এই সমস্ত সাইটে আপনাকে দেওয়ার জন্য নিশ্চিত করা যাচ্ছে না; সম্ভবত এগুলোতে আপনার প্রধান অ্যাকাউন্ট থেকে ভিন্ন শব্দচাবি ব্যবহৃত হয়েছে:',
	'centralauth-foreign-link'             => 'ব্যবহারকারী $2 তে $1',
	'centralauth-finish-title'             => 'একত্রীকরণ সম্পন্ন হয়েছে',
	'centralauth-finish-text'              => 'যদি এই অ্যাকাউন্টসমূহ আপনার হয়, তাহলে আপনি সহজেই অ্যাকাউন্টগুলোর জন্য শব্দচাবি প্রদান করে লগ-ইন একীভূতকরণের কাজ সম্পূর্ণ করতে পারেন:',
	'centralauth-finish-password'          => 'শব্দচাবি:',
	'centralauth-finish-login'             => 'প্রবেশ',
	'centralauth-finish-send-confirmation' => 'ই-মেইল শব্দচাবি',
	'centralauth-finish-problems'          => 'সমস্যা হচ্ছে, অথবা এই অ্যাকাউন্টসমূহ আপনার নয়? [[meta:Help:Unified login problems|কিভাবে সাহায্য খুজতে হবে]]...',
	'centralauth-merge-attempt'            => "'''প্রদত্ত শব্দচাবি বাকি বিচ্ছিন্ন অ্যাকাউন্টের বিপক্ষে পরীক্ষা করা হচ্ছে...'''",
	'centralauth-attach-list-attached'     => 'অ্যাকাউন্ট "$1" যে সব অ্যাকাউন্টসমূহের সাথে একীভূত হয়েছে:',
	'centralauth-attach-title'             => 'অ্যাকাউন্ট নিশ্চিত করুন',
	'centralauth-attach-text'              => 'এই অ্যাকাউন্টটি এখনও একীভূত অ্যাকাউন্টে সরিয়ে নেওয়া হয়নি। প্রধান অ্যাকাউন্টটি যদি আপনার হয় তাহলে আপনি তা ব্যবহার করে এই অ্যাকাউন্টটি একীভূত করে নিতে পারেন:',
	'centralauth-attach-submit'            => 'অ্যাকাউন্ট স্থানান্তর করো',
	'centralauth-attach-success'           => 'অ্যাকাউন্টটি সফলভাবে একীভূত অ্যাকাউন্টে সরিয়ে নেওয়া হয়েছে।',
	'centralauth'                          => 'একীভূত লগ-ইন প্রশাসন',
	'centralauth-admin-manage'             => 'ব্যবহারকারী ডাটা ব্যবস্থাপনা করুন',
	'centralauth-admin-username'           => 'ব্যবহারকারী নাম:',
	'centralauth-admin-lookup'             => 'ব্যবহারকারী ডাটা দেখুন অথবা সম্পাদনা করুন',
	'centralauth-admin-permission'         => 'শুধুমাত্র স্টুয়ার্ডগণ অন্যের অ্যাকাউন্ট তাদের জন্য একীভূত করতে পারেন।',
	'centralauth-admin-unmerge'            => 'নির্বাচিতসমূহ বিচ্ছিন্ন করো',
	'centralauth-admin-merge'              => 'নির্বাচিতসমূহ একীভূত করো',
	'centralauth-admin-bad-input'          => 'একীভূতকরণের জন্য অবৈধ নির্বাচন',
	'centralauth-admin-none-selected'      => 'পরিবর্তনের জন্য কোন অ্যাকাউন্ট নির্বাচন করা হয় নাই।',
	'centralauth-prefs-status'             => 'প্রধান অ্যাকাউন্টের অবস্থা:',
	'centralauth-prefs-not-managed'        => 'একীভূত অ্যাকাউন্ট ব্যবহৃত হচ্ছে না',
	'centralauth-prefs-unattached'         => 'অনিশ্চিত',
	'centralauth-prefs-complete'           => 'সমস্ত একই ক্রমে!',
	'centralauth-prefs-migration'          => 'সরানো হচ্ছে',
	'centralauth-prefs-count-attached'     => 'আপনার অ্যাকাউন্ট $1 প্রকল্প {{plural:$1|সাইট|সাইটসমূহ}} এ সক্রিয় আছে।',
	'centralauth-prefs-count-unattached'   => 'আপনার নাম দিয়ে নিশ্চিতকরণ হয় নাই এমন অ্যাকাউন্ট $1 {{plural:$1|প্রকল্পে|প্রকল্পসমূহে}} বাকি আছে।',
	'centralauth-prefs-detail-unattached'  => 'এই প্রকল্প সাইটটি গ্লোবাল অ্যাকাউন্টের জন্য প্রযোজ্য বলে নিশ্চিত করা হয়নি।',
	'centralauth-prefs-manage'             => 'আপনার প্রধান অ্যাকাউন্ট ব্যবস্থাপনা করুন',
	'centralauth-renameuser-abort'         => '<div class="errorbox">ব্যবহারকারী $1-কে স্থানীয়ভাবে পুনরায় নামকরণ করা যায়নি, কারণ এই ব্যবহারকারী নামটি একটি একত্রিত লগ-ইন ব্যবস্থায় স্থানান্তর করা হয়েছে।</div>',
);

/** Breton (Brezhoneg)
 * @author Fulup
 */
$messages['br'] = array(
	'mergeaccount'                         => "Statud unvaniñ ar c'hontoù implijer",
	'centralauth-desc'                     => 'Unvaniñ a ra kontoù implijer raktresoù wiki Diazezadur Wikimedia',
	'centralauth-merge-notlogged'          => 'Trugarez d\'en em <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} lugañ]</span> evit gwiriañ eo bet unvanet mat ho kontoù.',
	'centralauth-merge-welcome'            => "'''N'eo ket bet kaset ho kontoù implijer davet reizhiad emlugañ unvan Wikimedia c'hoazh.'''

Ma tibabit treuzkas ho kontoù e viot gouest da implijout an hevelep anv implijer ha ger-tremen hed-ha-hed holl raktresoù Wikimedia en holl yezhoù a c'haller kaout.
Gant se eo aesoc'h labourat a-dreuz ar raktresoù, aesaet eo an enporzhiañ skeudennoù war [http://commons.wikimedia.org/ Wikimedia Commons], hag evel-se ne vo ket tamm kemmesk ebet mui gant daou zen disheñvel oc'h ober gant an hevelep anv implijer war meur a raktres.

Ma rit dija gant an hevelep anv implijer war an holl raktresoù ne zlefe ket bezañ kudenn ebet. Ma ra unan bennak all gant an hevelep anv implijer ha c'hwi war ur raktres all e c'hallot mont e darempred gant an den-se pe gwelet se gant ur merour bennak.",
	'centralauth-merge-step1-title'        => "Kregiñ da unvaniñ ar c'hontoù",
	'centralauth-merge-step1-detail'       => "Keñveriet e vo ho ker-tremen hag ho chomlec'h postel gant re ar c'hontoù dezho an hevelep anv war ar wikioù all evit bezañ sur e klotont an eil gant egile. Ne vo degaset kemm ebet a-raok na vefe kadarnaet ganeoc'h emañ mat pep tra.",
	'centralauth-merge-step1-submit'       => 'Kadarnaat an titouroù',
	'centralauth-merge-step2-title'        => 'Lakaat kontoù all',
	'centralauth-merge-step2-detail'       => "Kontoù zo n'eus ket bet gallet stagañ anezho ent emgefre ouzh ar gont pennañ. Ma piaouit ar c'hontoù-se e c'hallit en kadarnaat en ur verkañ ar ger-tremen reizh evito.",
	'centralauth-merge-step2-submit'       => 'Kadarnaat an titouroù',
	'centralauth-merge-step3-title'        => 'Krouiñ ur gont unvan',
	'centralauth-merge-step3-detail'       => "Prest oc'h bremañ da grouiñ ur gont unvan evit ar wikioù-mañ :",
	'centralauth-merge-step3-submit'       => "Unvaniñ ar c'hontoù",
	'centralauth-complete'                 => "Unvanet eo ar c'hontoù !",
	'centralauth-incomplete'               => "N'eo ket echu unvaniñ ar c'hontoù !",
	'centralauth-notice-dryrun'            => "<div class='successbox'>Mod demo hepken</div><br clear='all'/>",
	'centralauth-list-home-title'          => 'Pennraktres',
	'centralauth-list-attached-title'      => 'Kontoù stag',
	'centralauth-list-attached'            => 'Staget war-eeun eo bet ar c\'hontoù implijer anvet "$1" d\'ar gont unvan evit ar raktresoù da-heul :',
	'centralauth-list-attached-dryrun'     => 'Staget war-eeun e vo ar c\'hontoù implijer anvet "$1" d\'ar gont unvan evit ar raktresoù da-heul :',
	'centralauth-list-unattached-title'    => 'Kontoù distag',
	'centralauth-foreign-link'             => 'Implijer $1 war $2',
	'centralauth-finish-title'             => 'Echuiñ kendeuziñ',
	'centralauth-finish-password'          => 'Ger-tremen :',
	'centralauth-finish-login'             => 'Kont implijer :',
	'centralauth-finish-send-confirmation' => 'Kas ar ger-tremen dre bostel',
	'centralauth-attach-title'             => 'Kadarnaat ar gont',
	'centralauth-attach-submit'            => "Treuzkas ar c'hontoù",
	'centralauth'                          => "Mererezh ar c'hontoù unvanet",
	'centralauth-admin-username'           => 'Anv implijer :',
	'centralauth-prefs-complete'           => 'Mat ar jeu !',
	'centralauth-prefs-migration'          => 'O treuzkas',
	'centralauth-prefs-manage'             => 'Merañ ho kont hollek',
);

/** Catalan (Català)
 * @author SMP
 */
$messages['ca'] = array(
	'mergeaccount' => "Estat de fusió de comptes d'usuari",
);

/** Czech (Česky)
 * @author Li-sung
 * @author Matěj Grabovský
 */
$messages['cs'] = array(
	'mergeaccount'                         => 'Stav sjednocení přihlašovacích účtů',
	'centralauth-desc'                     => 'Sloučení účtů na jednotlivých wiki nadace Wikimedia',
	'centralauth-merge-notlogged'          => 'Pokud se <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} přihlásíte]</span>, budete moci zkontrolovat, zda Vaše účty byly sloučeny.',
	'centralauth-merge-welcome'            => "'''Váš uživatelský účet nebyl dosud převeden na jednotný systém přihlašování projektů Wikimedia.'''

Pokud si vyberete převedení vašich účtů, budete moci používat stejné uživatelské jméno a heslo k přihlášení do všech projektů nadace Wikimedia ve všech dostupných jazycích.
To umožní snazší práci se sdílenými projekty, například načítání souborů do [http://commons.wikimedia.org/ Wikimedia Commons], a předejde se konfliktům a zmatkům, které mohou vzniknout, pokud dva si lidé na různých projektech vyberou stejné uživatelské jméno.

Pokud si již někde jiný vzal vaše uživatelské jméno na jiném projektu, tak to neovlivníte, ale budete mít možnost se později dohodnou na řešení buď s ním nebo s pomocí správců.",
	'centralauth-merge-step1-title'        => 'Začít sjednocovat přihlašování',
	'centralauth-merge-step1-detail'       => 'Bude porovnáno zda souhlasí Vaše heslo a registrovaný e-mail s účty na jiných wiki. Žádné změny nebudou provedeny dokud nepotvrdíte, že je vše v pořádku.',
	'centralauth-merge-step1-submit'       => 'Potvrdit přihlašovací informace',
	'centralauth-merge-step2-title'        => 'Potvrzení více účtů',
	'centralauth-merge-step2-detail'       => 'Některé účty nebylo možné automaticky přiřadit k určené domovské wiki. Potvrďte, že tyto účty jsou Vaše, zadáním jejich hesla.',
	'centralauth-merge-step2-submit'       => 'Potvrdit přihlašovací informace',
	'centralauth-merge-step3-title'        => 'Vytvoření jednotného účtu',
	'centralauth-merge-step3-detail'       => 'Vytvoření vašeho jednotného účtu je připraveno. Účty z následujících wiki budou připojeny:',
	'centralauth-merge-step3-submit'       => 'Sjednotit účty',
	'centralauth-complete'                 => 'Sjednocení přihlašovacích účtů bylo dokončeno!',
	'centralauth-incomplete'               => 'Sjednocení přihlašovacích účtů nebylo dokončeno!',
	'centralauth-complete-text'            => 'Nyní se můžete přihlásit na kterýkoliv projekt nadace Wikimedia, aniž byste si museli zakládat nový účet; stejné uživatelské jméno a heslo bude fungovat na Wikipedii, Wikislovníku, Wikiknihách a dalších sesterských projektech ve všech jazycích.',
	'centralauth-incomplete-text'          => 'Až bude vaše přihlašování sjednoceno, budete se moci přihlásit na kterýkoliv projekt nadace Wikimedia aniž byste si museli zakládat nový účet; stejné uživatelské jméno a heslo bude fungovat na Wikipedii, Wikcislovníku, Wikiknihách a dalších sesterských projektech ve všech jazycích.',
	'centralauth-not-owner-text'           => 'Uživatelské jméno „$1“ bylo automaticky přiřazeno majiteli účtu na projektu $2.

Pokud to jste vy, můžete dokončit proces sjednocení přihlašování zadáním hesla pro uvedený účet:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Toto je jen demonstrační režim</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Sloučení účtů probíhá momentálně jen v demonstračním / ladícím režimu, takže samotné operace spojení jsou vypnuté. Promiňte!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Přečtěte si více o '''sjednocení přihlašování''']]...''",
	'centralauth-list-home-title'          => 'Domovská wiki',
	'centralauth-list-home-dryrun'         => 'Heslo a e-mailová adresa nastavené na této wiki budou použity i na vašem jednotném účtu a z ostatních projektů se bude automaticky odkazovat na vaši uživatelskou stránku zde. Příležitost změnit si nastavený domovský projekt budete mít i později.',
	'centralauth-list-attached-title'      => 'Připojené účty',
	'centralauth-list-attached'            => 'Účty se jménem „$1“ na všech následujících projektech byly automaticky připojeny k jednotnému účtu:',
	'centralauth-list-attached-dryrun'     => 'Účty se jménem „$1“ na všech následujících projektech budou automaticky připojeny k jednotnému účtu:',
	'centralauth-list-unattached-title'    => 'Nepřipojené účty',
	'centralauth-list-unattached'          => 'Nebylo možné automaticky potvrdit, že účet „$1“ na následujících projektech patří vám; pravděpodobně má jiné heslo, než váš primární účett:',
	'centralauth-foreign-link'             => 'Uživatel $1 na $2',
	'centralauth-finish-title'             => 'Dokončit sloučení',
	'centralauth-finish-text'              => 'Pokud tyto účty opravdu patří vám, můžete skončit proces sloučení jednoduše napsáním hesel dotyčných účtů:',
	'centralauth-finish-password'          => 'Heslo:',
	'centralauth-finish-login'             => 'Přihlašovací jméno',
	'centralauth-finish-send-confirmation' => 'Zaslat heslo emailem',
	'centralauth-finish-problems'          => 'Máte problém nebo nejste vlastníkem těchto účtů? [[meta:Help:Unified login problems|Jak hledat pomoc]]…',
	'centralauth-merge-attempt'            => "'''Prověřuje se, zda poskytnuté heslo odpovídá zbylým nesloučeným účtům...'''",
	'centralauth-attach-list-attached'     => 'K jednotnému účtu s názvem „$1“ patří následující účty:',
	'centralauth-attach-title'             => 'Potvrdit účet',
	'centralauth-attach-text'              => 'Tento účet nebyl dosud převeden na jednotný účet. Pokud je jednotný účet také váš, můžete připojit tento účet k jednotnému účtu, když napíšete heslo k jednotnému účtu:',
	'centralauth-attach-submit'            => 'Převést účet',
	'centralauth-attach-success'           => 'Účet byl převeden na jednotný účet.',
	'centralauth'                          => 'Správa jednotného přihlašování',
	'centralauth-admin-manage'             => 'Správa údajů o uživatelích',
	'centralauth-admin-username'           => 'Uživatelské jméno:',
	'centralauth-admin-lookup'             => 'Zobrazit nebo změnit data uživatele',
	'centralauth-admin-permission'         => 'Pouze stevardi mohou slučovat účty za jiné uživatele.',
	'centralauth-admin-unmerge'            => 'Rozdělit vybrané',
	'centralauth-admin-merge'              => 'Sloučit vybrané',
	'centralauth-admin-bad-input'          => 'Neplatný výběr ke sloučení',
	'centralauth-admin-none-selected'      => 'Ke změně nebyl vybrán žádný účet.',
	'centralauth-prefs-status'             => 'Stav jednotného účtu',
	'centralauth-prefs-not-managed'        => 'Jednotný účet není používán',
	'centralauth-prefs-unattached'         => 'Nepotvrzený',
	'centralauth-prefs-complete'           => 'Vše v pořádku!',
	'centralauth-prefs-migration'          => 'Probíhá převod',
	'centralauth-prefs-count-attached'     => 'Váš účet je aktivní na $1 {{plural:$1|projektu|projektech|projektech}}.',
	'centralauth-prefs-count-unattached'   => 'Na $1 {{plural:$1|projektu zůstává nepotvrzený účet|projektech zůstávají nepotvrzené účty|projektech zůstávají nepotvrzené účty}} s vaším jménem.',
	'centralauth-prefs-detail-unattached'  => 'Dosud nebylo potvrzeno, zda účet na tomto projektu patří k jednotnému účtu.',
	'centralauth-prefs-manage'             => 'Správa jednotného účtu',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Uživatele $1 není možné lokálně přejmenovat, protože toto uživatelské jméno bylo přesunuto do systému jednotných přihlašovacích účtů.</div>',
);

/** Danish (Dansk)
 * @author Jon Harald Søby
 * @author M.M.S.
 */
$messages['da'] = array(
	'centralauth-list-home-title' => 'Hjemwiki',
	'centralauth-foreign-link'    => 'Bruger $1 på $2',
	'centralauth-admin-username'  => 'Brugernavn:',
);

$messages['de'] = array(
	// When not logged in...
	'mergeaccount' =>
		'Status der Benutzerkonten-Zusammenführung',
	'centralauth-desc' => 'Benutzerkonten in Wikis der Wikimedia Foundation zusammenführen',
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

/** Ewe (Eʋegbe)
 * @author M.M.S.
 */
$messages['ee'] = array(
	'centralauth-finish-login' => 'Ge ɖe eme',
);

/** Greek (Ελληνικά)
 * @author Lou
 * @author Consta
 */
$messages['el'] = array(
	'mergeaccount'                         => 'Κατάσταση της ενοποίησης των ονομάτων χρήστη',
	'centralauth-merge-notlogged'          => 'Παρακαλούμε <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} συνδεθείτε]</span> για να ελέγξετε εάν οι λογαριασμοί σας έχουν συγχωνευθεί πλήρως.',
	'centralauth-merge-welcome'            => "'''Ο λογαριασμός χρήστη σας δεν έχει ακόμη μετακινηθεί στο ενοποιημένο σύστημα ονομάτων χρήστη της Wikimedia.'''

Εάν διαλέξετε να μετακινήσετε τους λογαριασμούς σας, θα μπορείτε να χρησιμοποιήσετε το ίδιο όνομα χρήστη και τον ίδιο κωδικό σε όλα τα wikis των projects της Wikimedia σε όλες τις διαθέσιμες γλώσσες.
Αυτό διευκολύνει τις κοινές εργασίες μεταξύ των projects,  όπως είναι η επιφόρτωση αρχείων στο [http://commons.wikimedia.org/ Wikimedia Commons], και αποφεύγει τη σύγχιση ή αντιπαράθεση που θα μπορούσε να προκύψει εάν δύο χρήστες διάλεγαν το ίδιο όνομα σε διαφορετικά projects.

Εάν κάποιος άλλος έχει ήδη πάρει το όνομα χρήστη σας σε άλλον ιστοχώρο, αυτό δεν θα τον ενοχλήσει, θα σας δώσει όμως την ευκαιρία αργότερα να λύσετε το πρόβλημα μαζί του ή με κάποιον διαχειριστή.",
	'centralauth-merge-step1-title'        => 'Αρχή της ενοποίησης των ονομάτων χρήστη',
	'centralauth-merge-step1-detail'       => 'Ο κωδικός σας και η διεύθυνση e-mail που έχετε δηλώσει θα υποβληθούν σε έλεγχο με τους λογαριασμούς σε άλλα wikis για να επιβεβαιωθεί ότι ταιριάζουν. Δεν θα γίνει καμία αλλαγή έως ότου επιβεβαιώσετε ότι τα πάντα είναι εντάξει.',
	'centralauth-merge-step1-submit'       => 'Επαλήθευση των πληροφοριών χρήστη',
	'centralauth-merge-step2-title'        => 'Επιβεβαίωση περισσότερων λογαριασμών',
	'centralauth-merge-step2-detail'       => 'Μερικοί λογαριασμοί δεν έγινε δυνατό να συνταιριάξουν αυτόματα με το αρχικό wiki που δηλώσατε. Εάν αυτοί οι λογαριασμοί σας ανήκουν, μπορείτε να επιβεβαιώσετε ότι είναι δικοί σας παρέχοντας τον κωδικό τους.',
	'centralauth-merge-step2-submit'       => 'Επιβεβαίωση πληροφοριών χρήστη',
	'centralauth-merge-step3-title'        => 'Δημιουργία ενοποιημένου λογαριασμού',
	'centralauth-merge-step3-detail'       => 'Είστε έτοιμος να δημιουργήσετε τον ενοποιημένο σας λογαριασμό, με την επισύναψη των επόμενων wikis:',
	'centralauth-merge-step3-submit'       => 'Ενοποίηση λογαριασμών',
	'centralauth-complete'                 => 'Η ενοποίηση των ονομάτων χρήστη ολοκληρώθηκε!',
	'centralauth-incomplete'               => 'Η ενοποίηση των ονομάτων χρήστη δεν ολοκληρώθηκε!',
	'centralauth-complete-text'            => 'Μπορείτε πλέον να συνδεθείτε σε οποιονδήποτε ιστοχώρο wiki της Wikimedia χωρίς να δημιουργήσετε νέο λογαριασμό. Το ίδιο όνομα χρήστη και ο ίδιος κωδικός πρόσβασης ισχύουν για την Βικιπαίδεια, το Βικιλεξικό, τα Βικιβιβλία και τα αδελφά τους προγράμματα σε όλες τις γλώσσες.',
	'centralauth-incomplete-text'          => 'Από τη στιγμή που το όνομα χρήστη σας θα ενοποιηθεί, θα μπορείτε να συνδεθείτε σε οποιονδήποτε ιστοχώρο wiki της Wikimedia χωρίς να δημιουργήσετε νέο λογαριασμό. Το ίδιο όνομα χρήστη και ο ίδιος κωδικός πρόσβασης θα ισχύουν για την Βικιπαίδεια, το Βικιλεξικό, τα Βικιβιβλία και τα αδελφά τους προγράμματα σε όλες τις γλώσσες.',
	'centralauth-not-owner-text'           => 'Το όνομα χρήστη "$1" παραχωρήθηκε στον ιδιοκτήτη του λογαριασμού στο project $2.

Εάν είστε εσείς ο ίδιος, μπορείτε να τελειώσετε την διαδικασία ενοποίησης των ονομάτων χρήστη πληκτρολογώντας τον κύριο κωδικό πρόσβασης εκείνου του λογαριασμού εδώ:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Πειραματική λειτουργία μόνο</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => "Λυπόμαστε πολύ αλλά, προς το παρόν, η ενοποίηση των λογαριασμών βρίσκεται σε πειραματική περίοδο. Γι' αυτόν τον λόγο, οι πραγματικές λειτουργίες συγχώνευσης έχουν απενεργοποιηθεί.",
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Διαβάστε περισσότερα σχετικά με την '''ενοποιημένη μέθοδο πρόσβασης''']]...''",
	'centralauth-list-home-title'          => 'Αρχικό wiki',
	'centralauth-list-home-dryrun'         => 'Ο κωδικός πρόσβασης και η διεύθυνση e-mail που δημιουργήθηκε σε αυτό το wiki θα χρησιμοποιηθεί για τον ενοποιημένο σας λογαριασμό και η σελίδα χρήστη σας εδώ θα συνδεθεί αυτόματα με τα άλλα wikis. Εάν θελήσετε, θα μπορέσετε αργότερα να αλλάξετε το αρχικό σας wiki.',
	'centralauth-list-attached-title'      => 'Λογαριασμοί που έχουν επισυναφθεί',
	'centralauth-list-attached'            => 'Ο λογαριασμός που έχει το όνομα "$1" στον κάθε ένα από τους επόμενους ιστοχώρους έχει επισυναφθεί αυτόματα προς τον ενοποιημένο λογαριασμό:',
	'centralauth-list-attached-dryrun'     => 'Ο λογαριασμός που έχει το όνομα "$1" στον κάθε ένα από τους επόμενους ιστοχώρους θα επισυναφθεί αυτόματα προς τον ενοποιημένο λογαριασμό:',
	'centralauth-list-unattached-title'    => 'Λογαριασμοί που δεν έχουν επισυναφθεί',
	'centralauth-list-unattached'          => 'Δεν έγινε δυνατή η επιβεβαίωση ότι ο λογαριασμός "$1" των επόμενων ιστοχώρων σας ανήκει. Είναι πολύ πιθανόν ότι έχουν διαφορετικό κωδικό πρόσβασης από τον πρωτεύοντα λογαριασμό σας:',
	'centralauth-foreign-link'             => 'Χρήστης $1 στον ιστοχώρο $2',
	'centralauth-finish-title'             => 'Αποτελειώστε την συγχώνευση',
	'centralauth-finish-text'              => 'Εάν αυτοί οι λογαριασμοί ανήκουν πραγματικά σε σας, μπορείτε να αποτελειώσετε απλά την διαδικασία ενοποιημένης πρόσβασης πληκτρολογώντας εδώ τους κωδικούς πρόσβασης των άλλων λογαριασμών:',
	'centralauth-finish-password'          => 'Κωδικός:',
	'centralauth-finish-login'             => 'Εγγραφή',
	'centralauth-finish-send-confirmation' => 'Κωδικός του e-mail',
	'centralauth-finish-problems'          => 'Συναντήσατε προβλήματα ή δεν είστε ο ιδιοκτήτης των λογαριασμών που ακολουθούν; [[meta:Help:Unified login problems|Πώς να βρείτε βοήθεια]]...',
	'centralauth-merge-attempt'            => "'''Ο κωδικός πρόσβασης που πληκτρολογήσατε ελέγχεται με τους λογαριασμούς που δεν έχουν ακόμα επισυναφθεί...'''",
	'centralauth-attach-list-attached'     => 'Ο ενοποιημένος λογαριασμός "$1" συμπεριλαμβάνει τους επόμενους λογαριασμούς:',
	'centralauth-attach-title'             => 'Επιβεβαίωση του λογαριασμού',
	'centralauth-attach-text'              => 'Αυτός ο λογαριασμός δεν έχει ακόμη μετακινηθεί προς τον ενοποιημένο λογαριασμό. Εάν ο γενικός λογαριασμός σας ανήκει επίσης, μπορείτε να συγχωνεύσετε αυτόν εδώ τον λογαριασμό πληκτρολογώντας τον κωδικό πρόσβασης του γενικού λογαριασμού:',
	'centralauth-attach-submit'            => 'Μετακίνηση του λογαριασμού',
	'centralauth-attach-success'           => 'Ο λογαριασμός μετακινήθηκε προς τον ενοποιημένο λογαριασμό.',
	'centralauth'                          => 'Διαχείριση του ενιαίου ονόματος χρήστη',
	'centralauth-admin-manage'             => 'Διαχείριση δεδομένων χρήστη',
	'centralauth-admin-username'           => 'Όνομα χρήστη:',
	'centralauth-admin-lookup'             => 'Δείτε ή τροποποιήστε τα δεδομένα χρήστη',
	'centralauth-admin-permission'         => 'Μόνο stewards μπορούν να συγχωνεύσουν λογαριασμούς άλλων χρηστών στη θέση τους.',
	'centralauth-admin-unmerge'            => 'Διαλέξατε την ακύρωση της συγχώνευσης',
	'centralauth-admin-merge'              => 'Διαλέξατε τη συγχώνευση',
	'centralauth-admin-bad-input'          => 'Η επιλογή για την συγχώνευση είναι άκυρη',
	'centralauth-admin-none-selected'      => 'Δεν διαλέξατε λογαριασμούς προς τροποποίηση.',
	'centralauth-prefs-status'             => 'Κατάσταση του γενικού λογαριασμού:',
	'centralauth-prefs-not-managed'        => 'Δεν χρησιμοποιεί ενιαίο λογαριασμό',
	'centralauth-prefs-unattached'         => 'Δεν έχει επιβεβαιωθεί',
	'centralauth-prefs-complete'           => 'Όλα εντάξει!',
	'centralauth-prefs-migration'          => 'Η μετακίνηση βρίσκεται σε εξέλιξη',
	'centralauth-prefs-count-attached'     => 'Ο λογαριασμός σας είναι ενεργός σε $1 project {{plural:$1|ιστοχώρο|ιστοχώρους}}.',
	'centralauth-prefs-count-unattached'   => 'Παραμένουν ανεπιβεβαίωτοι λογαριασμοί με το όνομά σας σε $1 {{plural:$1|project|projects}}.',
	'centralauth-prefs-detail-unattached'  => 'Δεν έχει επιβεβαιωθεί ότι αυτός ο ιστοχώρος ανήκει στον γενικό λογαριασμό.',
	'centralauth-prefs-manage'             => 'Διαχείριση του γενικού σας λογαριασμού',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Ο χρήστης $1 δεν μπορεί να μετονομαστεί τοπικά καθώς αυτό το όνομα χρήστη έχει μετακινηθεί προς το ενοποιημένο σύστημα πρόσβασης.</div>',
);

$messages['ext'] = array(
	'centralauth-admin-username'           => 'Nombri d´usuáriu:',
);

/** فارسی (فارسی)
 * @author Huji
 */
$messages['fa'] = array(
	'mergeaccount'                         => 'وضعیت یکی کردن حساب‌ها',
	'centralauth-desc'                     => 'یکی کردن حساب‌های کاربری در ویکی‌های بنیاد ویکی‌مدیا',
	'centralauth-merge-notlogged'          => 'لطفاً <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} به سیستم وارد شوید]</span> تا از ادغام حساب‌های کاربر‌ی‌تان اطمینان حاصل کنید.',
	'centralauth-merge-welcome'            => "'''حساب کاربری شما هنوز به سامانه یکی کردن حساب‌های ویکی‌مدیا انتقال داده نشده‌است.'''

اگر شما تصمیم بگیرید که حساب‌های کاربری‌تان را انتقال بدهید، شما قادر خواهید بود که با یک حساب کاربری و کلمه عبور در تمام پروژه‌های ویکی‌مدیا به تمام زبان‌ها وارد شوید.
این عمل باعث آسان‌تر شدن کار با پروژه‌های مشترک نظیر بارگذاری تصاویر در [http://commons.wikimedia.org/ ویکی‌انبار] می‌شود، و مانع از سردرگمی‌هایی می‌شود که از استفادهٔ دو نفر از یک نام کاربری در دو پروژهٔ مختلف ناشی می‌شود.

اگر نام کاربری شما قبلاً در پروژهٔ دیگری توسط شخص دیگری استفاده شده باشد، این کار باعث مزاحمت برای آن‌ها نمی‌شود بلکه به شما این امکان را می‌دهد که بعداً این مشکل را با کمک آن‌ها یا یک مدیر حل کنید.",
	'centralauth-merge-step1-title'        => 'آغاز یکی کردن حساب‌های کاربری',
	'centralauth-merge-step1-detail'       => 'کلمه عبور و نشانی پست الکترونیکی ثبت‌شده توسط شما با حساب‌های کاربری دیگر ویکی‌ها مقایسه می‌شود تا از مطابقت آن‌ها اطمینان حاصل گردد. تا زمانی که شما تایید نکنید که همه چیز درست است، تغییر صورت نمی‌گیرد.',
	'centralauth-merge-step1-submit'       => 'تایید اطلاعات ورود به سیستم',
	'centralauth-merge-step2-title'        => 'تایید حساب‌های کاربری بیشتر',
	'centralauth-merge-step2-detail'       => 'برخی از حساب‌های کاربری را نمی‌توان به طور خودکار با حساب ویکی اصلی مطابقت داد. اگر این حساب‌های کاربری متعلق به شما هستند، شما می‌توانید این مساله را با وارد کردن کلمه عبور این حساب‌ها تایید کنید.',
	'centralauth-merge-step2-submit'       => 'تایید اطلاعات ورود به سیستم',
	'centralauth-merge-step3-title'        => 'ایجاد حساب مشترک',
	'centralauth-merge-step3-detail'       => 'شما آماده‌اید که حساب مشترک خود را در ویکی‌های زیر ایجاد کنید:',
	'centralauth-merge-step3-submit'       => 'یکی کردن حساب‌ها',
	'centralauth-complete'                 => 'یکی کردن حساب‌ها کامل شد!',
	'centralauth-incomplete'               => 'یکی کردن حساب‌ها کامل نشد!',
	'centralauth-complete-text'            => 'اکنون شما می‌توانید در هر یک از ویکی‌های ویکی‌مدیا وارد شوید بدون آن که حساب جدیدی بسازید؛ حساب کاربری مشترک شما در ویکی‌پدیا، ویکی‌واژه، ویکی‌نسک و دیگر پروژه‌های خواهر، در تمام زبان‌ها کار خواهد کرد.',
	'centralauth-incomplete-text'          => 'از زمانی که حساب‌های کاربری شما یکی شود، شما قادر خواهید بود در هر یک از ویکی‌های ویکی‌مدیا وارد شوید بدون آن که حساب کاربری جدیدی بسازید؛ حساب کاربری مشترک شما در ویکی‌پدیا، ویکی‌واژه، ویکی‌نسک و دیگر پروژه‌های خواهر، در تمام زبان‌ها کار خواهد کرد.',
	'centralauth-not-owner-text'           => 'حساب کاربری «$1» به طور خودکار به صاحب حساب کاربری در $2 اختصاص داده شد.

اگر شما صاحب این حساب هستید، شما می‌توانید روند یکی کردن حساب‌های کاربری را با وارد کردن کلمه عبور سراسری در این‌جا به پایان برسانید:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>فقط مدل نمایشی</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'سامانه یکی کردن حساب‌های کاربری در حال حاضر به طور آزمایشی و برای رفع ایراد فعال است، بنابراین یکی کردن واقعی حساب‌های کاربری هنوز فعال نیست. متاسفیم!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|اطلاعات بیشتر دربارهٔ '''حساب کاربری مشترک''']]...''",
	'centralauth-list-home-title'          => 'ویکی اصلی',
	'centralauth-list-home-dryrun'         => 'کلمه عبور و نشانی پست الکترونیکی انتخاب شده در این ویکی برای حساب کاربری مشترک شما مورد استفاده قرار خواهد گرفت، و حساب کاربری شما در دیگر ویکی‌ها به طور خودکار به این ویکی پیوند خواهد شد. شما می‌توانید بعداً ویکی اصلی خود را تغییر دهید.',
	'centralauth-list-attached-title'      => 'حساب‌های کاربری غیرمتصل',
	'centralauth-list-attached'            => 'حساب کاربری «$1» در هر یک از پروژه‌های زیر به طور خودکار به حساب کاربری مشترک شما متصل شده‌است:',
	'centralauth-list-attached-dryrun'     => 'حساب کاربری «$1» در هر یک از پروژه‌های زیر به طور خودکار به حساب کاربری مشترک شما متصل خواهد شد:',
	'centralauth-list-unattached-title'    => 'حساب‌های کاربری متصل',
	'centralauth-list-unattached'          => 'حساب کاربری $1 را در وبگاه‌های زیر نمی‌توان به طور خودکار متعلق به شما دانست؛ به احتمال زیاد کلمه عبور آن‌ها با حساب کاربری اصلی شما متفاوت است:',
	'centralauth-foreign-link'             => 'حساب کاربری $1 در $2',
	'centralauth-finish-title'             => 'خاتمه ادغام',
	'centralauth-finish-text'              => 'اگر شما صاحب این حساب‌ها هستید، می‌توانید روند یکی کردن حساب‌های کاربری را با وارد کردن کلمه عبور سراسری در این‌جا به پایان برسانید:',
	'centralauth-finish-password'          => 'کلمه عبور:',
	'centralauth-finish-login'             => 'ورود به سیستم',
	'centralauth-finish-send-confirmation' => 'کلمه عبور پست الکترونیکی',
	'centralauth-finish-problems'          => 'دچار مشکلی شده‌اید یا صاحب حساب‌های کاربری دیگری که فهرست شده‌اند نیستید؟ [[meta:Help:Unified login problems|راهنما را بخوانید]]...',
	'centralauth-merge-attempt'            => "'''مطابقت دادن کلمه عبور وارد شده با حساب‌های ادغام‌نشدهٔ باقی‌مانده...'''",
	'centralauth-attach-list-attached'     => 'حساب کاربری مشترک «$1» شامل این حساب‌های کاربری می‌شود:',
	'centralauth-attach-title'             => 'تایید حساب کاربری',
	'centralauth-attach-text'              => 'این حساب کاربری هنوز به حساب کاربری مشترک انتقال داده نشده‌است. اگر حساب کاربری مشترک متعلق به شما است، می‌توانید این حساب را هم با وارد کردن کلمه عبور آن به حساب کاربری مشترک متصل کنید:',
	'centralauth-attach-submit'            => 'انتقال حساب کاربری',
	'centralauth-attach-success'           => 'حساب کاربری به حساب کاربری مشترک انتقال داده شد.',
	'centralauth'                          => 'مدیریت حساب کاربری مشترک',
	'centralauth-admin-manage'             => 'مدیریت اطلاعات کاربر',
	'centralauth-admin-username'           => 'نام کاربری:',
	'centralauth-admin-lookup'             => 'مشاهده یا تغییر اطلاعات کاربری',
	'centralauth-admin-permission'         => 'تنها ویکیبدها می‌توانند حساب کاربری دیگر کاربرها را یکی کنند.',
	'centralauth-admin-unmerge'            => 'از ادغام درآوردن موارد انتخاب شده',
	'centralauth-admin-merge'              => 'انتخاب ادغام',
	'centralauth-admin-bad-input'          => 'انتخاب غیرمجاز برای ادغام',
	'centralauth-admin-none-selected'      => 'هیچ حساب کاربری برای تغییر انتخاب نشده‌است.',
	'centralauth-prefs-status'             => 'وضعیت حساب کاربری مشترک',
	'centralauth-prefs-not-managed'        => 'عدم استفاده از حساب کاربری مشترک',
	'centralauth-prefs-unattached'         => 'تایید نشده',
	'centralauth-prefs-complete'           => 'همه‌چیز مرتب است!',
	'centralauth-prefs-migration'          => 'در حال انتقال',
	'centralauth-prefs-count-attached'     => 'حساب کاربری شما در $1 {{plural:$1|پروژه|پروژه}} فعال است.',
	'centralauth-prefs-count-unattached'   => 'حساب‌های کاربری تایید نشده‌ای با نام شما در $1 {{plural:$1|پروژه|پروژه}} باقی می‌مانند.',
	'centralauth-prefs-detail-unattached'  => 'وبگاه این پروژه مورد تایید برای استفاده از حساب کاربری مشترک قرار نگرفته‌است.',
	'centralauth-prefs-manage'             => 'مدیریت حساب کاربری مشترک',
	'centralauth-renameuser-abort'         => '<div class="errorbox">امکان تغییر نام حساب کاربری $1 به طور محلی وجود ندارد، زیرا این حساب به سامانه یکی کردن حساب‌های کاربری منتقل شده‌است.</div>',

);

/** Finnish (Suomi)
 * @author Nike
 * @author Cimon Avaro
 * @author Crt
 */
$messages['fi'] = array(
	'mergeaccount'                         => 'Käyttäjätunnusten yhdistämisen tila',
	'centralauth-desc'                     => 'Mahdollistaa käyttäjätunnusten yhdistämisen Wikimedian wikeissä.',
	'centralauth-merge-notlogged'          => 'Kirjaudu <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} sisään]</span>, jos haluat tarkistaa, ovatko käyttäjätunnuksesi yhdistetty.',
	'centralauth-merge-welcome'            => "'''Tunnustasi ei ole vielä siirretty Wikimedian yhden tunnuksen kirjautumisjärjestelmään.'''

Jos päivität tunnuksesi , voit jatkossa kirjautua kaikkiin Wikimedian projekteihin ja niiden kieliversioihin samalla käyttäjätunnuksella ja salasanalla.
Vain yhden tunnuksen olemassa olo helpottaa yhteisten projektien käyttöä, kuten esimerkiksi kuvien ja muiden tiedostojen tallentamisen [http://commons.wikimedia.org/ Wikimedia Commonsiin]. Se myös vähentää sekaannuksia, jos eri ihmisillä on sama käyttäjänimi eri projekteissa.

Jos käyttäjänimesi on jo varattu toisessa projektissa, yhdistäminen ei haittaa näitä toisia käyttäjiä, mutta antaa sinulle mahdollisuuden neuvotella käyttäjänimestä näiden toisten käyttäjien kanssa, tai myöhemmin ylläpitäjän kanssa.",
	'centralauth-merge-step1-title'        => 'Tunnusten yhdistäminen',
	'centralauth-merge-step1-detail'       => 'Salasanaasi ja asettamaasi sähköpostiosoitetta verrataan muissa wikeissä oleviin tunnuksiin, niiden samuuden varmistamiseksi. Mitään muutoksia ei toteuteta ennen kuin varmistat, että kaikki näyttää hyvältä.',
	'centralauth-merge-step1-submit'       => 'Vahvista yhdistäminen',
	'centralauth-merge-step2-title'        => 'Muiden tunnusten yhdistäminen',
	'centralauth-merge-step2-detail'       => 'Joidenkin käyttäjätunnusten samuutta kotiwikin käyttäjätunnukseen ei  voitu varmistaa. Jos nämä tunnukset kuuluvat sinulle, voit todistaa niiden kuuluvan sinulle antamalla niiden salasanan.',
	'centralauth-merge-step2-submit'       => 'Vahvista yhdistäminen',
	'centralauth-merge-step3-title'        => 'Yhdistetyn käyttäjätunnuksen luominen',
	'centralauth-merge-step3-detail'       => 'Voit nyt luoda yhdistetyn käyttäjätunnuksen, johon on tehty kytkökset seuraavista wikeistä:',
	'centralauth-merge-step3-submit'       => 'Yhdistä tunnukset',
	'centralauth-complete'                 => 'Käyttäjätunnusten yhdistäminen on valmis.',
	'centralauth-incomplete'               => 'Käyttäjätunnusten yhdistäminen ei ole valmis.',
	'centralauth-complete-text'            => 'Voit nyt kirjautua mihin tahansa Wikimedian wikiin luomatta uutta käyttäjätunnusta. Sama käyttäjänimi ja salasana toimii Wikipediassa, Wikisanakirjassa, Wikikirjastossa ja muissa projekteissa sekä niiden kaikissa kieliversioissa.',
	'centralauth-incomplete-text'          => 'Kun kaikki tunnuksesi on yhdistetty, voit kirjautua mihin tahansa Wikimedian wikiin luomatta uutta käyttäjätunnusta. Sama käyttäjänimi ja salasana toimii Wikipediassa, Wikisanakirjassa, Wikikirjastossa ja muissa projekteissa sekä niiden kaikissa kieliversioissa.',
	'centralauth-not-owner-text'           => 'Käyttäjänimi ”$1” annettiin automaattisesti käyttäjätunnuksen $2 omistajalle.

Jos tämä tunnus on sinun, voi viimeistellä tunnusten yhdistämisen antamalla päätunnuksen salasanan:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Vain testi</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Tunnusten yhdistäminen on tällä hetkellä kokeilutilassa. Tunnusten yhdistämisoperaatioita ei suoriteta.',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Lisätietoja '''yhdistetystä tunnuksesta''']]...''",
	'centralauth-list-home-title'          => 'Kotiwiki',
	'centralauth-list-home-dryrun'         => 'Kotiwikissä olevan tunnuksen salasanaa ja sähköpostiosoitetta käytetään yhdistetyssä tunnuksessa ja siellä olevaan käyttäjäsivuun luodaan automaattisesti linkit muista wikeistä. Voit vaihtaa kotiwikiäsi myöhemmin.',
	'centralauth-list-attached-title'      => 'Liitetyt tunnukset',
	'centralauth-list-attached'            => 'Tunnus nimeltä ”$1” on liitetty automaattisesti yhdistettyyn käyttäjätunnukseesi seuraavista wikeistä:',
	'centralauth-list-attached-dryrun'     => 'Tunnus nimeltä ”$1” liitetään automaattisesti yhdistettyyn käyttäjätunnukseesi seuraavista wikeistä:',
	'centralauth-list-unattached-title'    => 'Liittämättömät tunnukset',
	'centralauth-list-unattached'          => 'Tunnusta ”$1” ei voitu liittää automaattisesti seuraavista wikeistä, koska niissä todennäköisesti on eri salasana:',
	'centralauth-foreign-link'             => 'Tunnus $1 projektissa $2',
	'centralauth-finish-title'             => 'Suorita yhdistäminen',
	'centralauth-finish-text'              => 'Jos nämä käyttäjätunnukset kuuluvat sinulle, voit suorittaa käyttäjätunnusten yhdistämisen kirjoittamalla toisten käyttäjätunnusten salasanat:',
	'centralauth-finish-password'          => 'Salasana',
	'centralauth-finish-login'             => 'Kirjaudu sisään',
	'centralauth-finish-send-confirmation' => 'Lähetä salasana sähköpostitse',
	'centralauth-finish-problems'          => 'Ongelmia? Etkö omista näitä tunnuksia? [[meta:Help:Unified login problems|Apua voi etsiä täältä]]...',
	'centralauth-merge-attempt'            => "'''Tarkistetaan annettua salasanaa jäljellä oleviin liittämättömiin tunnuksiin...'''",
	'centralauth-attach-list-attached'     => 'Yhdistetty käyttäjätunnuksesi ”$1” sisältää seuraavat tunnukset:',
	'centralauth-attach-title'             => 'Vahvista käyttäjätunnus',
	'centralauth-attach-text'              => 'Tätä tunnusta ei ole vielä liitetty yhdistettyyn käyttäjätunnukseen. Jos myös päätunnus kuuluu sinulle, voit yhdistää tämän tunnuksen antamalla päätunnuksen salasanan:',
	'centralauth-attach-submit'            => 'Liitä tunnus',
	'centralauth-attach-success'           => 'Tunnus liitettiin yhdistettyyn käyttäjätunnukseen.',
	'centralauth'                          => 'Yhdistetyn käyttäjätunnuksen hallinta',
	'centralauth-admin-manage'             => 'Käyttäjätietojen hallinta',
	'centralauth-admin-username'           => 'Käyttäjätunnus',
	'centralauth-admin-lookup'             => 'Näytä tai muokkaa käyttäjätietoja',
	'centralauth-admin-permission'         => 'Vain ylivalvojat (steward) voivat yhdistää toisten ihmisten tunnuksia heidän puolestaan.',
	'centralauth-admin-unmerge'            => 'Erota valitut',
	'centralauth-admin-merge'              => 'Liitä valitut',
	'centralauth-admin-bad-input'          => 'Kelpaamaton liitosvalinta',
	'centralauth-admin-none-selected'      => 'Et valinnut tunnuksia, joita haluat muokata.',
	'centralauth-prefs-status'             => 'Päätunnuksen tila',
	'centralauth-prefs-not-managed'        => 'Yhdistämätön tunnus',
	'centralauth-prefs-unattached'         => 'Varmistamaton',
	'centralauth-prefs-complete'           => 'Kaikki kunnossa',
	'centralauth-prefs-migration'          => 'Liittäminen kesken',
	'centralauth-prefs-count-attached'     => 'Tunnuksesi on käytössä $1 {{plural:$1|wikissä}}.',
	'centralauth-prefs-count-unattached'   => 'Liittämättömiä tunnuksia on $1 {{plural:$1|wikissä}}.',
	'centralauth-prefs-detail-unattached'  => 'Tätä sivua ei ole varmistettu päätunnukseen kuuluvaksi.',
	'centralauth-prefs-manage'             => 'Päätunnuksen hallinta',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Käyttäjätunnusta $1 ei nimetä uudelleen, koska se on yhdistetty käyttäjätunnus.</div>',
);

/** French (Français)
 * @author Sherbrooke
 * @author Urhixidur
 * @author Seb35
 */
$messages['fr'] = array(
	'mergeaccount'                         => 'Statut de la fusion des comptes utilisateur',
	'centralauth-desc'                     => 'Fusionne les comptes utilisateur de projets wikis de la Wikimedia Fondation',
	'centralauth-merge-notlogged'          => 'Merci de bien vouloir <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} vous connecter]</span> pour vérifier si vos comptes ont bien été fusionnés.',
	'centralauth-merge-welcome'            => "'''Vos comptes utilisateur n’ont pas encore été migrés vers la système de compte unique de Wikimedia.'''
Si vous choisissez de faire migrer vos comptes, vous pourrez utiliser le même nom d’utilisateur et le même mot de passe sur tous les projets Wikimedia dans toutes les langues.
Ainsi, le travail inter-projets sera facilité de même que, par exemple, l’import d’images sur [http://commons.wikimedia.org/ Wikimedia Commons] ; cela évitera aussi la confusion survenant quand deux personnes utilisent le même nom d’utilisateur sur deux projets différents.

Si vous avez déjà le même nom d’utilisateur sur tous les projets, il ne devrait pas y avoir de problème. Si une autre personne a le même nom d’utilisateur que vous sur un autre projet, vous aurez l'occasion d'entrer en contact avec cette personne ou avec un administrateur plus tard.",
	'centralauth-merge-step1-title'        => 'Commencer le processus de fusion des comptes',
	'centralauth-merge-step1-detail'       => 'Nous allons comparer votre adresse courriel et votre mot de passe avec ceux des comptes homonymes sur les autes wikis, et vérifier qu’ils correspondent. Aucun changement ne sera effectué tant que vous n’aurez pas donné votre accord.',
	'centralauth-merge-step1-submit'       => 'Confirmer les informations',
	'centralauth-merge-step2-title'        => 'Inclure d’autres comptes',
	'centralauth-merge-step2-detail'       => "Certains des comptes n’ont pas pu être rattachés automatiquement à votre compte principal. Si ces comptes vous appartiennent, veuillez confirmer qu'ils sont à vous en entrant le mot de passe correspondant.
",
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
	'centralauth-notice-dryrun'            => "<div class='successbox'>Mode de démonstration seulement</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'La fusion des comptes est actuellement en mode de démonstration ou de test, on ne peut donc pas encore réellement fusionner de comptes. Désolé !',
	'centralauth-readmore-text'            => ":''[[w:fr:Wikipédia:Login unique|En savoir plus sur le '''compte unique''']]''",
	'centralauth-list-home-title'          => 'Projet principal',
	'centralauth-list-home-dryrun'         => 'Le mot de passe et l’adresse courriel du projet principal ci-dessous seront utilisés pour votre compte unique, et votre page utilisateur sur le projet principal sera automatiquement liée depuis les autres projets. Vous pourrez modifier votre projet principal plus tard.',
	'centralauth-list-attached-title'      => 'Comptes rattachés',
	'centralauth-list-attached'            => 'Les comptes utilisateur nommés « $1 » ont été rattachés pour les projets suivants :',
	'centralauth-list-attached-dryrun'     => 'Le compte nommé « $1 » sur chacun des sites suivants sera automatiquement rattaché au compte unique :',
	'centralauth-list-unattached-title'    => 'Comptes non rattachés',
	'centralauth-list-unattached'          => 'Les comptes utilisateur nommés « $1 » sur les sites suivants ne peuvent pas être rattachés automatiquement ; ils ont probablement un mot de passe différent de votre compte maître :',
	'centralauth-foreign-link'             => 'Utilisateur $1 sur $2',
	'centralauth-finish-title'             => 'Terminer la fusion',
	'centralauth-finish-text'              => 'Si ces comptes vous appartiennent, vous pouvez terminer leur fusion en tapant leurs mots de passe ci-dessous :',
	'centralauth-finish-password'          => 'Mot de passe :',
	'centralauth-finish-login'             => 'Compte utilisateur :',
	'centralauth-finish-send-confirmation' => 'Envoyer le mot de passe par courriel',
	'centralauth-finish-problems'          => 'En cas de problème ou si vous ne possédez pas ces autres comptes, voyez [[meta:Help:Unified login problems|Problèmes]] (en anglais)...',
	'centralauth-merge-attempt'            => "'''Vérification du mot de passe fourni pour les comptes non réunis...'''",
	'centralauth-attach-list-attached'     => 'Le compte unique nommé "$1" inclut les comptes suivants :',
	'centralauth-attach-title'             => 'Confirmer le compte',
	'centralauth-attach-text'              => "Ce compte n'a pas encore été migré en un compte unique. Si le compte global vous appartient également, vous pouvez fusionner ce compte si vous tapez le mot de passe du compte global :",
	'centralauth-attach-submit'            => 'Migrer les comptes',
	'centralauth-attach-success'           => 'Le compte a été migré en un compte unique.',
	'centralauth'                          => 'Administration des comptes uniques',
	'centralauth-admin-manage'             => 'Gérer les données utilisateur',
	'centralauth-admin-username'           => 'Nom d’utilisateur :',
	'centralauth-admin-lookup'             => 'Voir ou modifier les données utilisateur',
	'centralauth-admin-permission'         => 'Seuls les stewards peuvent fusionner les comptes d’autres personnes à leur place.',
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

/** Franco-Provençal (Arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'mergeaccount'                         => 'Statut de la fusion des comptos utilisator',
	'centralauth-desc'                     => 'Fusione los comptos utilisator de projèts de la Wikimedia Foundation.',
	'centralauth-merge-notlogged'          => 'Marci de franc volêr vos <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} conèctar]</span> por controlar se voutros comptos ont bien étâ fusionâs.',
	'centralauth-merge-welcome'            => "'''Voutros comptos utilisator ont p’oncor étâ migrâs vers lo sistèmo de compto unico de Wikimedia.'''

Se vos chouèsésséd/cièrde de fâre migrar voutros comptos, vos porréd utilisar lo mémo nom d’utilisator et lo mémo mot de pâssa sur tôs los projèts Wikimedia dens totes les lengoues.
D’ense, lo travâly entèrprojèts serat facilitâ coment, per ègzemplo, l’impôrt d’émâges dessus [http://commons.wikimedia.org/ Wikimedia Commons] ; cen èviterat asse-ben la confusion arrevent quand doves gens utilisont lo mémo nom d’utilisator sur doux projèts difèrents.

Se vos avéd ja lo mémo nom d’utilisator sur tôs los projèts, devrêt pas y avêr de problèmo. S’una ôtra pèrsona at lo mémo nom d’utilisator que vos sur un ôtro projèt, vos aréd l’ocasion de vos veriér vers cela pèrsona ou ben vers un administrator ples târd.",
	'centralauth-merge-step1-title'        => 'Comenciér lo procès de fusion des comptos',
	'centralauth-merge-step1-detail'       => 'Nos alens comparar voutra adrèce de mèl et voutron mot de pâssa avouéc celos des comptos homonimos sur los ôtros vouiquis, et controlar que corrèspondont. Nion changement serat fêt tant que vos aréd pas balyê voutron acôrd.',
	'centralauth-merge-step1-submit'       => 'Confirmar les enformacions',
	'centralauth-merge-step2-title'        => 'Encllure d’ôtros comptos',
	'centralauth-merge-step2-detail'       => 'Cèrtins des comptos ont pas possu étre rapondus ôtomaticament a voutron compto principâl. Se celos comptos sont a vos, volyéd confirmar que sont a vos en entrent lo mot de pâssa corrèspondent.',
	'centralauth-merge-step2-submit'       => 'Confirmar les enformacions',
	'centralauth-merge-step3-title'        => 'Crèacion du compto unico',
	'centralauth-merge-step3-detail'       => 'Orendrêt, vos éte prèst a crèar voutron compto unico, compregnent los vouiquis siuvents :',
	'centralauth-merge-step3-submit'       => 'Fusionar los comptos',
	'centralauth-complete'                 => 'Fusion des comptos chavonâ !',
	'centralauth-incomplete'               => 'Fusion des comptos pas chavonâ !',
	'centralauth-complete-text'            => 'Orendrêt, vos pouede vos conèctar a quint que seye lo projèt Wikimedia sen avêr a crèar un novél compto ; lo mémo nom d’utilisator et lo mémo mot de pâssa fonccioneront dessus Vouiquipèdia, Vouiccionèro, Vouiquilévros et lors projèts serors, et cen por totes les lengoues.',
	'centralauth-incomplete-text'          => 'Un côp voutros comptos fusionâs, vos porréd vos conèctar a quint que seye lo projèt Wikimedia sen avêr a crèar un novél compto ; lo mémo nom d’utilisator et lo mémo mot de pâssa fonccioneront dessus Vouiquipèdia, Vouiccionèro, Vouiquilévros et lors projèts serors, et cen por totes les lengoues.',
	'centralauth-not-owner-text'           => 'Lo compto utilisator « $1 » at étâ ôtomaticament assignê u propriètèro du compto dessus $2.

S’o est vos, vos porréd chavonar lo procès de fusion des comptos en buchient lo mot de pâssa mêtre por cél compto dessus :',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Môdo de dèmonstracion solament</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'La fusion des comptos est ora en môdo de dèmonstracion ou d’èprôva, pôvont vêr p’oncor verément fusionar de comptos. Dèsolâ !',
	'centralauth-readmore-text'            => ":''[[w:frp:Vouiquipèdia:Login unico|Nen savêr de ples sur lo '''compto unico''']]...''",
	'centralauth-list-home-title'          => 'Projèt principâl',
	'centralauth-list-home-dryrun'         => 'Lo mot de pâssa et l’adrèce de mèl du projèt principâl ce-desot seront utilisâs por voutron compto unico, et voutra pâge utilisator sur lo projèt principâl serat ôtomaticament liyê dês los ôtros projèts. Vos porréd modifiar voutron projèt principâl ples târd.',
	'centralauth-list-attached-title'      => 'Comptos rapondus',
	'centralauth-list-attached'            => 'Los comptos utilisator apelâs « $1 » ont étâ rapondus por los projèts siuvents :',
	'centralauth-list-attached-dryrun'     => 'Lo compto apelâ « $1 » sur châcun des setos siuvents serat ôtomaticament rapondu u compto unico :',
	'centralauth-list-unattached-title'    => 'Comptos pas rapondus',
	'centralauth-list-unattached'          => 'Los comptos utilisator apelâs « $1 » sur los setos siuvents pôvont pas étre rapondus ôtomaticament ; ils ont probâblament un mot de pâssa difèrent de voutron compto mêtre :',
	'centralauth-foreign-link'             => 'Utilisator $1 dessus $2',
	'centralauth-finish-title'             => 'Chavonar la fusion des comptos',
	'centralauth-finish-text'              => 'Se cetos comptos sont a vos, vos pouede chavonar lor fusion en buchient lors mots de pâssa ce-desot :',
	'centralauth-finish-password'          => 'Mot de pâssa :',
	'centralauth-finish-login'             => 'Compto utilisator :',
	'centralauth-finish-send-confirmation' => 'Emmandar lo mot de pâssa per mèl',
	'centralauth-finish-problems'          => 'En câs de problèmo ou ben se vos avéd pas cetos ôtros comptos, vêde [[meta:Help:Unified login problems|<span title="« Help:Unified login problems » : pâge en anglès" style="text-decoration:none">Problèmos</span>]]...',
	'centralauth-merge-attempt'            => "'''Contrôlo du mot de pâssa forni por los comptos pas rapondus...'''",
	'centralauth-attach-list-attached'     => 'Lo compto unico apelâ « $1 » encllut los comptos siuvents :',
	'centralauth-attach-title'             => 'Confirmar lo compto',
	'centralauth-attach-text'              => 'Ceti compto at p’oncor étâ migrâ en un compto unico. Se lo compto unico est asse-ben a vos, vos pouede fusionar ceti compto se vos buchiéd lo mot de pâssa du compto unico :',
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
	'centralauth-prefs-detail-unattached'  => 'Voutron compto sur ceti projèt at pas possu étre rapondu u compto unico.',
	'centralauth-prefs-manage'             => 'G·èrâd voutron compto unico',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Empossiblo de renomar lo compto utilisator $1 localament : ceti utilisator at ora un compto unico.</div>',
);

/** Galician (Galego)
 * @author Xosé
 * @author Alma
 */
$messages['gl'] = array(
	'mergeaccount'                         => 'Estado da unificación do rexistro',
	'centralauth-desc'                     => 'Fusionar Contas entre wikis da Fundación Wikimedia',
	'centralauth-merge-notlogged'          => 'Por favor, <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} rexístrese]</span> para comprobar se as súas contas se  unificaron completamente.',
	'centralauth-merge-welcome'            => "'''A súa conta de usuario aínda non se pasou ao sistema de rexistro unificado de Wikimedia.'''

Se escolle unificar as súas contas, poderá empregar o mesmo nome de usuario e contrasinal para se rexistrar en todos os wikis dos proxectos de Wikimedia en todas as linguas disponíbeis.
Isto fai que sexa máis doado traballar con proxectos compartidos, como enviar a [http://commons.wikimedia.org/ Wikimedia Commons], e evita a confusión ou conflito que pode resultar se dúas persoas escollen o mesmo nome de usuario en proxectos diferentes.",
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
	'centralauth-notice-dryrun'            => "<div class='successbox'>Só modo demostración</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'A unificación de contas é actualmente só un modo de demostración / depuración, polo que as operacións de unificación non están activadas. Sentímolo!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Lea máis acerca do '''rexistro unificado''']]...''",
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
	'centralauth-merge-attempt'            => "'''A contrastar o contrasinal fornecido coas demais contas aínda sen unificar...'''",
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

$messages['he'] = array(
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

$messages['hr'] = array(
	'mergeaccount'                         => 'Status centralizacije prijave',
	'centralauth-merge-notlogged'          => 'Molimo <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} prijavite se]</span> radi provjere da li su Vaši suradnički računi uspješno spojeni.',
	'centralauth-merge-welcome'            => '\'\'\'Vaš suradnički račun nije još premješten na Wikimediijin centralni sustav prijave.\'\'\'

Ukoliko izaberete spajanje vaših računa, moći ćete se prijaviti s istim imenom i lozinkom na sve Wikimedijine projekte.
Takav pristup omogućuje lakši rad na dijeljenim projektima kao i postavljanje slika na [http://commons.wikimedia.org/ Wikimedijin zajednički poslužitelj], i izbjegava se moguća zabuna ukoliko dvoje osobe izaberu isto ime na različitim projektima.

Ukoliko je netko već uporabio vaše ime/nadimak na nekom drugom projektu, ovo neće smetati niti jednu stranu, nego će vam dati šansu da se konflikt naknadno riješi s njima ili administratorom.',
	'centralauth-merge-step1-title'        => 'Počni spajanje suradničkih računa',
	'centralauth-merge-step1-detail'       => 'Vaša lozinka i prijavljena e-mail adresa će biti uspoređeni s podacima na drugim wikijima da se ustanovi da li odgovaraju jedni drugima. Promjene neće biti izvršene dok ne potvrdite da je sve u redu.',
	'centralauth-merge-step1-submit'       => 'Potvrdi podatke o prijavi',
	'centralauth-merge-step2-title'        => 'Potvrdi više suradničkih računa',
	'centralauth-merge-step2-detail'       => 'Neki računi ne odgovaraju onom koji ste naveli kao polazišni wiki. Ako su vaši, potvrdite to navođenjem lozinke za te račune.',
	'centralauth-merge-step2-submit'       => 'Potvrdi podatke o prijavi',
	'centralauth-merge-step3-title'        => 'Stvori centralni suradnički račun',
	'centralauth-merge-step3-detail'       => 'Možete stvoriti centralni račun, koji spaja račune na slijedećim wikiprojektima:',
	'centralauth-merge-step3-submit'       => 'Spoji suradničke račune',
	'centralauth-complete'                 => 'Spajanje suradničkih računa izvršeno!',
	'centralauth-incomplete'               => 'Spajanje suradničkih računa nije izvršeno!',
	'centralauth-complete-text'            => 'Sad se možete prijaviti na bilo koji Wikimedijin projekt bez otvaranja novog računa; isto ime i lozinka vam vrijede na Wikipediji, Wječniku, Wikiknjigama i ostalim projektima na svim jezicima.',
	'centralauth-incomplete-text'          => 'Kad vaš račun bude jedinstven, moći ćete se prijaviti na svaki Wikimedija projekt bez stvaranja novog računa; isto ime i lozinka vrijedit će na Wikipediji, Wječniku, Wikiknjigama, i svim ostalim Wikiprojektima na svim jezicima.',
	'centralauth-not-owner-text'           => 'Suradničko ime "$1" je automatski dodijeljeno suradniku na $2.

Ukoliko ste to vi, možete dovršiti proces spajanja računa unošenjem glavne lozinke:',
	'centralauth-notice-dryrun'            => '<div class=\'successbox\'>Samo demo mod</div><br clear=\'all\'/>',
	'centralauth-disabled-dryrun'          => 'Spajanje računa je trenutno u demo / debugging modu, pa je stvarno spajanje računa onemogućeno.',
	'centralauth-readmore-text'            => ':\'\'[[meta:Help:Unified login|Saznaj više o \'\'\'centralnoj prijavi\'\'\']]...\'\'',
	'centralauth-list-home-title'          => 'Bazni wiki',
	'centralauth-list-home-dryrun'         => 'Lozinka i e-mail adresa postavljeni na ovom wikiju bit će uporabljeni za vaš centralni račun, i drugi wikiji će sadržavati poveznice na vašu suradničku stranicu ovdje. Naravno, moguća je promjena baznog wikija.',
	'centralauth-list-attached-title'      => 'Spojeni suradnički računi',
	'centralauth-list-attached'            => 'Suradnički račun "$1" na slijedećim stranicama (wikijima) je automatski priključen centralnom računu:',
	'centralauth-list-attached-dryrun'     => 'Suradnički račun "$1" na slijedećim stranicama (wikijima) će biti automatski priključen centralnom računu:',
	'centralauth-list-unattached-title'    => 'Nespojeni suradnički računi',
	'centralauth-list-unattached'          => 'Suradnički račun "$1" ne može vam biti automatski pridružen na slijedećim projektima; najvjerojatnije ima različitu lozinku od vaše primarne:',
	'centralauth-foreign-link'             => 'Suradnik $1 na $2',
	'centralauth-finish-title'             => 'Završi spajanje',
	'centralauth-finish-text'              => 'Ukoliko su slijedeći računi vaši, možete završiti proces spajanja računa unošenjem lozinki za preostale račune:',
	'centralauth-finish-password'          => 'Lozinka:',
	'centralauth-finish-login'             => 'Prijavite se',
	'centralauth-finish-send-confirmation' => 'Pošalji lozinku e-poštom',
	'centralauth-finish-problems'          => 'Imate problem, ili ovi računi nisu vaši? [[meta:Help:Unified login problems|Kako naći pomoć]]...',
	'centralauth-merge-attempt'            => '\'\'\'Provjeravam navedene lozinke za ostale još nespojene račune...\'\'\'',
	'centralauth-attach-list-attached'     => 'Jedinstveni račun "$1" uključuje slijedeće račune:',
	'centralauth-attach-title'             => 'Potvrdi suradnički račun',
	'centralauth-attach-text'              => 'Ovaj račun nije još spojen s jedinstvenim računom. Ukoliko je jedinstaveni račun vaš, možete ga spojiti ako znate lozinku jedinstvenog računa:',
	'centralauth-attach-submit'            => 'Prenesi suradnički račun',
	'centralauth-attach-success'           => 'Vaš suradnički račun je postao centraliziran.',
	'centralauth'                          => 'Administracija centralnog suradničkog računa',
	'centralauth-admin-manage'             => 'Upravljanje suradničkim podacima',
	'centralauth-admin-username'           => 'Suradničko ime:',
	'centralauth-admin-lookup'             => 'Vidi ili uredi suradničke podatke',
	'centralauth-admin-permission'         => 'Samo stjuardi mogu spajati suradničke račune umjesto njih.',
	'centralauth-admin-unmerge'            => 'Razdvoji odabrane',
	'centralauth-admin-merge'              => 'Spoji odabrane',
	'centralauth-admin-bad-input'          => 'Nevaljan odabir',
	'centralauth-admin-none-selected'      => 'Nijedan račun nije odabran za promjenu.',
	'centralauth-prefs-status'             => 'Status centralnog suradničkog računa:',
	'centralauth-prefs-not-managed'        => 'Ne rabite centralni račun',
	'centralauth-prefs-unattached'         => 'Nepotvrđeno',
	'centralauth-prefs-complete'           => 'Sve u redu!',
	'centralauth-prefs-migration'          => 'Migracija u tijeku',
	'centralauth-prefs-count-attached'     => 'Vaš suradnički račun je aktivan na $1 {{PLURAL:$1|projektu|projekta|projekata}}.',
	'centralauth-prefs-count-unattached'   => 'Nepotvrđeni računi s vašim imenom postoje još na {{plural:$1|slijedećem projektu|slijedećim projektima|slijedećim projektima}} $1.',
	'centralauth-prefs-detail-unattached'  => 'Pripadnost ovog projekta jedinstvenom sustavu prijave nije potvrđena.',
	'centralauth-prefs-manage'             => 'Uredite Vaš centralni suradnički račun',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Ne mogu preimenovati suradnika $1 lokalno jer je to suradničko ime spojeno u jedinstveni sustav prijave.</div>',
);

/** Upper Sorbian (Hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'mergeaccount'                         => 'Status zjednoćenja wužiwarskich kontow',
	'centralauth-desc'                     => 'Konto přez wikije Wikimedijoweje Załožby zjednoćić',
	'centralauth-merge-notlogged'          => 'Prošu <span class="plainlinks"> [{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} přizjew so]</span>, zo by přepruwował, hač su so twoje wužiwarske konta dospołnje zjednoćili.',
	'centralauth-merge-welcome'            => "'''Twoje wužiwarske konto njeje so hišće do zhromadneho systema přizjewjenja Wikimedije přiwzało.'''

Hdyž so rozsudźiš twoje konta tam składować, budźe móžno ze samsnym wužiwarskim mjenom a hesłom we wšěch projektach Wikimedije dźěłać.
To zjednori runočasne dźěło we wjacorych wikijach kaž nahraće datajow do [http://commons.wikimedia.org/ Wikimedia Commons] a wobeńdźe konflikty a mylenja hdyž chce něchto druhi samsne přimjeno kaž ty w druhich projektach wužiwać.",
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
	'centralauth-notice-dryrun'            => "<div class='successbox'>Jenož demonstraciski modus</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Kontowe zjednoćenje je runje w demonstraciskim modusu abo při pytanju za zmylkami, tohodla su aktuelne zjednoćenske procesy znjemóžnjene. Bohužel!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Informacije wo '''zjednoćenju wužiwarskich kontow''']]...''",
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
	'centralauth-merge-attempt'            => "'''Zapodate hesło so z njezjednoćenymi wužiwarskimi kontami přepruwuje...'''",
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

/** Hungarian (Magyar)
 * @author Bdanee
 * @author Dorgan
 * @author KossuthRad
 */
$messages['hu'] = array(
	'mergeaccount'                         => 'Felhasználói fiókok egyesítésének állapota',
	'centralauth-desc'                     => 'Felhasználói fiókok egységesítése a Wikimedia Alapítvány wikijein',
	'centralauth-merge-notlogged'          => '<span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} Jelentkezz be]</span>, hogy ellenőrizhessük, felhasználói fiókjaid egyesítve vannak-e.',
	'centralauth-merge-welcome'            => "'''A felhasználói fiókod még nincs integrálva a Wikimedia egységesített bejelentkezési rendszerébe.'''

Ha fiókjaid egységesítését választod, egyetlen felhasználói névvel és jelszóval léphetsz be a Wikimedia összes wikijébe.
Ez könnyebb munkát tesz lehetővé például a [http://commons.wikimedia.org/ Wikimedia Commonsban], és nem fordulhat elő az, hogy két felhasználó ugyanazt a nevet választja két különböző projektben.

Ha valaki más már korábban beregisztrált a neveden egy másik oldalon, a folyamat nem fogja őket zavarni, de később esélyed lesz megoldásra jutni velük vagy egy adminisztrátorral.",
	'centralauth-merge-step1-title'        => 'Bejeletkezés egységesítésének megkezdése',
	'centralauth-merge-step1-detail'       => 'Ellenőrizzük, hogy jelszavad és regisztrált e-mail címed melyik más wikin lévő felhasználói nevekkel egyezik meg. Nem történik változás, míg meg nem erősíted, hogy minden rendben van.',
	'centralauth-merge-step1-submit'       => 'Bejelentkezési információk megerősítése',
	'centralauth-merge-step2-title'        => 'Más fiókok megerősítése',
	'centralauth-merge-step2-detail'       => 'Néhány fiókot nem sikerült automatikusan párosítani a kijelölt saját wikihez. Ha ezek hozzád tartoznak, erősítsd meg a jelszó megadásával, hogy tényleg hozzád tartoznak.',
	'centralauth-merge-step2-submit'       => 'Bejelentkezési információ megerősítése',
	'centralauth-merge-step3-title'        => 'Egységesített felhasználói fiók elkészítése',
	'centralauth-merge-step3-detail'       => 'Most már készen állsz az egységesített felhasználói fiók elkészítéséhez, amelyekhez a következő wikiket csatoljuk:',
	'centralauth-merge-step3-submit'       => 'Felhasználói fiókok egységesítése',
	'centralauth-complete'                 => 'A bejelentkezés egységesítése sikeresen befejeződött!',
	'centralauth-incomplete'               => 'A bejelentkezés egységesítése nincs befejezve!',
	'centralauth-complete-text'            => 'Most már bármelyik Wikimedia webhelyre beléphetsz anélkül, hogy újabb felhasználói fiókot hoznál létre; például a magyar Wikipédiában regisztrált felhasználóneveddel bejelentkezhetsz Wikihírekbe és akár az összes további Wikimédia webhelyre bármely nyelven.',
	'centralauth-incomplete-text'          => 'Ha bejelentkezésed egységesítve lesz, bármelyik Wikimedia wikibe bejelentkezhetsz új felhasználói fiók létrehozása nélkül: ugyanazt a felhasználói nevet és jelszót használhatod a Wikipédia, Wikiszótár, Wikikönyvek és más testvérprojektek minden nyelvű változatánál.',
	'centralauth-not-owner-text'           => 'A(z) „$1” felhasználói nevet automatikusan hozzárendeltük a(z) $2-s tulajdonosához.

Ha ez te vagy, akkor a gazda jelszavának megadásával erősítsd meg az itteni fiókodat:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Demó mód</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'A felhasználói fiókok egységesítése jelenleg csak demó / hibakeresés céljából működik, ezért a valós egységesítés le van tiltva. Sajnáljuk!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Tudj meg többet az '''egységesített bejelentkezésről''']]...''",
	'centralauth-list-home-title'          => 'Saját wiki',
	'centralauth-list-home-dryrun'         => 'Az ezen a wikin beállított jelszavad és e-mail címed lesz használva az egységesített felhasználói fiókodnál, és az itteni felhasználói lapodra automatikusan linkelve lesz más wikikről. Későbbiekben megváltoztathatod, hogy melyik a saját wikid.',
	'centralauth-list-attached-title'      => 'Csatolt felhasználói fiókok',
	'centralauth-list-attached'            => 'A(z) „$1” nevű felhasználói fiókok automatikusan csatolva lettek az egységesített felhasználói fiókhoz:',
	'centralauth-list-attached-dryrun'     => 'A(z) „$1” nevű felhasználói fiókok automatikusan csatolva lesznek az egységesített felhasználói fiókhoz:',
	'centralauth-list-unattached-title'    => 'Csatolatlan felhasználói fiókok',
	'centralauth-list-unattached'          => 'A(z) „$1” nevű felhasználói fiókról nem sikerült automatikusan megállapítani, hogy hozzád tartozik az alábbi oldalakon; valószínűleg más jelszóval rendelkeznek:',
	'centralauth-foreign-link'             => '$1 szerkesztő, $2',
	'centralauth-finish-title'             => 'Egyesítés befejezése',
	'centralauth-finish-text'              => 'Ha ezek a felhasználói fiókok hozzád tartoznak, befejezheted az egységesítési folyamatot, úgy, hogy megadod a hozzájuk tartozó jelszavakat:',
	'centralauth-finish-password'          => 'Jelszó:',
	'centralauth-finish-login'             => 'Bejelentkezés',
	'centralauth-finish-send-confirmation' => 'E-mail jelszó',
	'centralauth-finish-problems'          => 'Problémád van, vagy ezek nem hozzád tartoznak? [[meta:Help:Unified login problems|Hogyan találhatsz segítséget]]…',
	'centralauth-merge-attempt'            => "'''A megadott jelszavak ellenőrzése a hátralévő, még nem egységesített felhasználói fiókoknál…'''",
	'centralauth-attach-list-attached'     => 'A(z) „$1” nevű egységesített felhasználói fiók a következőket tartalmazza:',
	'centralauth-attach-title'             => 'Felhasználói fiók megerősítése',
	'centralauth-attach-text'              => 'Ez a felhasználói fiók még nincs integrálva az egységesített fiókba. Ha a globális fiók a tied, egységesítheted azzal, hogy megadod a jelszavadat:',
	'centralauth-attach-submit'            => 'Felhasználói fiók költöztetése',
	'centralauth-attach-success'           => 'A felhasználói fiók sikeresen integrálva lett az egységesített fiókba.',
	'centralauth'                          => 'Egységes belépés adminisztrációja',
	'centralauth-admin-manage'             => 'Felhasználói adatok beállítása',
	'centralauth-admin-username'           => 'Felhasználói név:',
	'centralauth-admin-lookup'             => 'Felhasználói adatok megtekintése vagy szerkesztése',
	'centralauth-admin-permission'         => 'Csak helytartók integrálhatják más emberek számára a fiókjukat.',
	'centralauth-admin-unmerge'            => 'Kiválasztott integrálásának megszüntetése',
	'centralauth-admin-merge'              => 'Kiválasztott integrálása',
	'centralauth-admin-bad-input'          => 'Érvénytelen integrálandó kiválasztás',
	'centralauth-admin-none-selected'      => 'Nem volt módosítandó fiók kiválasztva.',
	'centralauth-prefs-status'             => 'Globális felhasználói fiók állapota:',
	'centralauth-prefs-not-managed'        => 'Nem használsz egységesített felhasználói fiókot',
	'centralauth-prefs-unattached'         => 'Nincs megerősítve',
	'centralauth-prefs-complete'           => 'Minden rendben!',
	'centralauth-prefs-migration'          => 'Költöztetés alatt',
	'centralauth-prefs-count-attached'     => 'A felhasználói fiókod $1 oldalon van használva.',
	'centralauth-prefs-count-unattached'   => '$1 nem megerősített felhasználói fiók van a neveddel $1 oldalon.',
	'centralauth-prefs-detail-unattached'  => 'Még nem lett megerősítve, hogy az oldal a globális felhasználói fiókodhoz tartozik.',
	'centralauth-prefs-manage'             => 'Globális felhasználói fiók beállítása',
	'centralauth-renameuser-abort'         => '<div class="errorbox">$1 nem nevezhető át helyben, mivel integrálva van az egységesített bejelentkezési rendszerbe.</div>',
);

$messages['id'] = array(
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

/** Icelandic (Íslenska)
 * @author S.Örvarr.S
 */
$messages['is'] = array(
	'centralauth-merge-step3-submit'       => 'Sameina aðganga',
	'centralauth-list-home-title'          => 'Heimawiki',
	'centralauth-foreign-link'             => 'Notandi $1 á $2',
	'centralauth-finish-password'          => 'Lykilorð:',
	'centralauth-finish-login'             => 'Innskráning',
	'centralauth-finish-send-confirmation' => 'Senda lykilorð í tölvupósti',
	'centralauth-admin-username'           => 'Notandanafn:',
);

/** Italian (Italiano)
 * @author Gianfranco
 * @author BrokenArrow
 */
$messages['it'] = array(
	'mergeaccount'                         => 'Processo di unificazione delle utenze - status',
	'centralauth-desc'                     => 'Unifica gli account su tutti i siti Wikimedia Foundation',
	'centralauth-merge-notlogged'          => 'Si prega di <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} effettuare il login]</span> per verificare se il processo di unificazione delle proprie utenze è completo.',
	'centralauth-merge-welcome'            => "'''Il tuo account utente non è ancora stato importato nel sistema di identificazione unificato di Wikimedia (Wikimedia's unified login system).''' Se decidi di unificare i tuoi account, potrai usare lo stesso nome utente e la stessa password per accedere a tutti i progetti wiki di Wikimedia in tutte le lingue disponibili. Questo faciliterà il lavoro con i progetti comuni, ad esempio caricare file su [http://commons.wikimedia.org/ Wikimedia Commons], ed eviterà la confusione ed i conflitti che nascerebbero se due o più utenti scegliessero lo stesso nome utente su più progetti. Se qualcun altro ha già preso il tuo nome utente su un altro sito, questo non lo disturberà, ma l'unificazione darà a te la possibilità di sottoporre in futuro il problema all'altro utente o ad un amministratore.",
	'centralauth-merge-step1-title'        => "Avvia l'unificazione dei login",
	'centralauth-merge-step1-detail'       => "La tua password e l'indirizzo e-mail registrato saranno ora controllati sugli account in altre wiki per confermare che corrispondano. Nessuna modifica sarà effettuata prima della tua conferma che tutto appare in regola.",
	'centralauth-merge-step1-submit'       => 'Conferma le informazioni per il login',
	'centralauth-merge-step2-title'        => 'Conferma altri account',
	'centralauth-merge-step2-detail'       => 'Non è stato possibile collegare automaticamente alcuni account a quello sulla tua wiki principale. Se sei il titolare di questi account, prova che ti appartengono indicando le password per ciascuno di essi.',
	'centralauth-merge-step2-submit'       => 'Conferma le informazioni di login',
	'centralauth-merge-step3-title'        => "Crea l'account unificato",
	'centralauth-merge-step3-detail'       => 'È tutto pronto per creare il tuo account unificato sulle seguenti wiki:',
	'centralauth-merge-step3-submit'       => 'Unifica gli account',
	'centralauth-complete'                 => 'Il processo di unificazione delle utenze è stato completato.',
	'centralauth-incomplete'               => 'Il processo di unificazione delle utenze non è ancora stato completato.',
	'centralauth-complete-text'            => 'È ora possibile accedere a tutti i siti Wikimedia senza dover creare nuovi account; questo nome utente e questa password sono attivi su tutte le edizioni di Wikipedia, Wiktionary, Wikibooks, ecc. nelle varie lingue e su tutti i progetti correlati.',
	'centralauth-incomplete-text'          => 'Dopo aver unificato le proprie utenze, sarà possibile accedere a tutti i siti Wikimedia senza dover creare nuovi account; il nome utente e la password saranno attivi su tutte le edizioni di Wikipedia, Wiktionary, Wikibooks, ecc. nelle varie lingue e su tutti i progetti correlati.',
	'centralauth-not-owner-text'           => 'Il nome utente "$1" è stato assegnato automaticamente al titolare dell\'account con lo stesso nome sul progetto $2.

Se si è il titolare dell\'utenza, per terminare il processo di unificazione è sufficiente inserire la password principale di quell\'account qui di seguito:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Solo modalità Demo</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => "L'unificazione degli account attualmente può essere sperimentata solo in modalità ''demo'' o ''debugging'', quindi le operazioni di effettiva fusione dei dati sono disabilitate. Siamo spiacenti!",
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Per saperne di più sul '''login unico''']]...''",
	'centralauth-list-home-title'          => 'Wiki principale',
	'centralauth-list-home-dryrun'         => "La password e l'indirizzo e-mail registrati in questo wiki saranno usati per l'account unificato, la tua pagina utente in questo wiki sarà automaticamente linkata dagli altri wiki. Potrai in seguito cambiare il tuo wiki principale.",
	'centralauth-list-attached-title'      => 'Account collegati',
	'centralauth-list-attached'            => 'Gli account con nome utente "$1" sui progetti elencati di seguito sono stati unificati automaticamente:',
	'centralauth-list-attached-dryrun'     => "L'account chiamato \"'''\$'''1\" su ciascuno dei seguenti siti sarà automaticamente collegato all'account unificato:",
	'centralauth-list-unattached-title'    => 'Account non collegati',
	'centralauth-list-unattached'          => 'Non è stato possibile verificare automaticamente che gli account con nome utente "$1" sui progetti elencati di seguito appartengano allo stesso titolare; è probabile che sia stata usata una password diversa da quella dell\'account principale:',
	'centralauth-foreign-link'             => 'Utente $1 su $2',
	'centralauth-finish-title'             => 'Completa il processo di unificazione',
	'centralauth-finish-text'              => 'Se si è il titolare di queste utenze, per completare il processo di unificazione degli account è sufficiente inserire le password relative alle utenze stesse qui di seguito:',
	'centralauth-finish-password'          => 'Password:',
	'centralauth-finish-login'             => 'Esegui il login',
	'centralauth-finish-send-confirmation' => 'Invia password via e-mail',
	'centralauth-finish-problems'          => 'Se non si è il titolare di queste utenze, o se si incontrano altri problemi, si invita a consultare la [[meta:Help:Unified login problems|pagina di aiuto]]...',
	'centralauth-merge-attempt'            => "'''Verifica della password inserita sulle utenze non ancora unificate...'''",
	'centralauth-attach-list-attached'     => "L'account unificato chiamato \"'''\$1'''\" include i seguenti account:",
	'centralauth-attach-title'             => "Conferma l'account",
	'centralauth-attach-text'              => "Questo account non è ancora stato collegato all'account unificato. Se sei il titolare dell'account globale, puoi collegare questo account inserendo la password dell'account globale:",
	'centralauth-attach-submit'            => "Collega l'account",
	'centralauth-attach-success'           => "L'account è stato trasferito all'account unificato.",
	'centralauth'                          => 'Amministrazione del login unificato',
	'centralauth-admin-manage'             => 'Gestione dati utente',
	'centralauth-admin-username'           => 'Nome utente',
	'centralauth-admin-lookup'             => 'Visualizza o modifica i dati utente',
	'centralauth-admin-permission'         => 'Solo gli steward possono unificare gli account altrui per loro conto.',
	'centralauth-admin-unmerge'            => 'Scollega gli account selezionati',
	'centralauth-admin-merge'              => 'Collega gli account selezionati',
	'centralauth-admin-bad-input'          => "Selezione per l'unificazione NON valida",
	'centralauth-admin-none-selected'      => 'Non sono stati selezionati account da modificare',
	'centralauth-prefs-status'             => "Situazione dell'account globale:",
	'centralauth-prefs-not-managed'        => 'Account unificato non in uso',
	'centralauth-prefs-unattached'         => 'Non confermato',
	'centralauth-prefs-complete'           => 'Tutto a posto!',
	'centralauth-prefs-migration'          => 'In corso di trasferimento',
	'centralauth-prefs-count-attached'     => 'Il tuo account è attivo su $1 siti di progetto.',
	'centralauth-prefs-count-unattached'   => 'Ci sono account non confermati con il tuo nome utente su $1 progetti.',
	'centralauth-prefs-detail-unattached'  => "Questo sito non è stato confermato come appartenente all'account globale.",
	'centralauth-prefs-manage'             => 'Gestione del tuo account globale',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Impossibile rinominare localmente l\'utente $1 perché questa utenza è stata trasferita al sistema unificato di identificazione (unified login system).</div>',
);

/** Japanese (日本語)
 * @author JtFuruhata
 * @author Broad-Sky
 */
$messages['ja'] = array(
	'mergeaccount'                         => '統一ログイン状態',
	'centralauth-desc'                     => 'ウィキメデイア財団が運営する各種ウィキのアカウント統合',
	'centralauth-merge-notlogged'          => 'あなたのアカウントが完全に統合されたかどうか、<span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} ログイン]</span>して試してください。',
	'centralauth-merge-welcome'            => "'''あなたのアカウントは、まだウィキメディア統一ログインシステムに移行していません。'''

このシステムにアカウントを移行すると、ウィキメディアが運営しているの全プロジェクトの全言語版ウィキで、同じユーザ名とパスワードを利用したログインを行うことができます。
これにより、[http://commons.wikimedia.org/ ウィキメディア・コモンズ]へのアップロードなど共有プロジェクトでの作業が容易になり、また別の人が別のプロジェクトで同じ名前を使うといった競合や混乱を回避することができます。

もし他の誰かが既にあなたのユーザ名を他のサイトで使っていた場合、それを妨げることはできません。ただし、後にこの問題をその人や管理者と解消するきっかけとなるでしょう。",
	'centralauth-merge-step1-title'        => '統一ログインの開始',
	'centralauth-merge-step1-detail'       => '他ウィキ上のアカウントと一致するかどうかの確認に備え、あなたのパスワード及び登録電子メールアドレスをチェックします。あなたが問題ないと確認するまで、いかなる変更も起こりません。',
	'centralauth-merge-step1-submit'       => 'ログイン情報の確認',
	'centralauth-merge-step2-title'        => '外部アカウントの確認',
	'centralauth-merge-step2-detail'       => 'いくつかのアカウントで、ホームウィキとの自動照合ができませんでした。もしこれらのアカウントがあなたのものである場合、パスワードを入力することによって自分のものであると証明できます。',
	'centralauth-merge-step2-submit'       => 'ログイン情報の確認',
	'centralauth-merge-step3-title'        => '統一アカウントの作成',
	'centralauth-merge-step3-detail'       => '以下のウィキに関連付けられた、あなたの統一アカウントの作成準備が完了しました:',
	'centralauth-merge-step3-submit'       => 'アカウントの統一',
	'centralauth-complete'                 => 'アカウントの統一が完了しました！',
	'centralauth-incomplete'               => 'アカウントの統一に失敗しました！',
	'centralauth-complete-text'            => 'ウィキペディアやウィクショナリー、ウィキブックスといったウィキメディアの姉妹プロジェクト全言語版に、新しいアカウントを作成せずとも、同じ利用者名とパスワードでログインすることが可能になりました。',
	'centralauth-incomplete-text'          => '統一アカウントを作成すると、ウィキペディアやウィクショナリー、ウィキブックスといったウィキメディアの姉妹プロジェクト全言語版に、新しいアカウントを作成せずとも、同じ利用者名とパスワードでログインすることが可能になります。',
	'centralauth-not-owner-text'           => '"$1" という利用者名は、アカウント $2 の利用者へ既に自動割当済みです。

もしこれがあなたであるならば、このアカウントのパスワードをここに入力することで、アカウント統一処理を簡単に完了することができます:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>デモモード限定</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'アカウントの統一は、現在デモ / デバッグモードとなっており、実際の統合操作は行われません。すみません！',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|詳しくは、'''統一ログイン'''をご覧ください]]...''",
	'centralauth-list-home-title'          => 'ホームウィキ',
	'centralauth-list-home-dryrun'         => 'このウィキで設定されたパスワードと電子メールアドレスは統一アカウントでも利用され、ここの利用者ページは他のウィキから自動的にリンクされます。どこをホームウィキにするかは、あとから変更することも可能です。',
	'centralauth-list-attached-title'      => '関連付けされるアカウント',
	'centralauth-list-attached'            => '以下に示すサイトの "$1" という名前のアカウントは、統一アカウントへ自動的に関連付けられます:',
	'centralauth-list-attached-dryrun'     => '以下に示すサイトの "$1" という名前のアカウントは、統一アカウントへ自動的に関連付けられる予定です:',
	'centralauth-list-unattached-title'    => '関連付けされないアカウント',
	'centralauth-list-unattached'          => '以下に示すサイトの "$1" という名前のアカウントは、おそらく最初のアカウントとパスワードが異なるため、あなたに関連付けられるものとして自動承認されませんでした。:',
	'centralauth-foreign-link'             => '$2 上の利用者 $1',
	'centralauth-finish-title'             => '統合完了',
	'centralauth-finish-text'              => '以下のアカウントがあなたのものであるなら、それらのパスワードを入力することで、アカウント統一処理を簡単に完了することができます:',
	'centralauth-finish-password'          => 'パスワード:',
	'centralauth-finish-login'             => 'ログイン',
	'centralauth-finish-send-confirmation' => 'パスワードを電子メールで送信',
	'centralauth-finish-problems'          => 'トラブルが発生しました。もしくは、本当にこれらはあなたのアカウントですか？ [[meta:Help:Unified login problems|ヘルプを確認してください]]...',
	'centralauth-merge-attempt'            => "'''まだ統合されていないアカウントに対するパスワードを確認中です...'''",
	'centralauth-attach-list-attached'     => '統一アカウント "$1" には、以下のアカウントが含まれます:',
	'centralauth-attach-title'             => 'アカウントの確認',
	'centralauth-attach-text'              => 'このアカウントは、まだ統一アカウントに移行していません。既に統一アカウントをお持ちの場合、そのパスワードを入力することで、このアカウントを統合することができます:',
	'centralauth-attach-submit'            => 'アカウントの統合',
	'centralauth-attach-success'           => 'このアカウントは、統一アカウントへ移行されました。',
	'centralauth'                          => '統一ログインの管理',
	'centralauth-admin-manage'             => '利用者データの管理',
	'centralauth-admin-username'           => '利用者名:',
	'centralauth-admin-lookup'             => '利用者データの閲覧/編集',
	'centralauth-admin-permission'         => '他人同士のアカウントを統合できるのは、スチュワードだけです。',
	'centralauth-admin-unmerge'            => '選択された利用者の統合を解除',
	'centralauth-admin-merge'              => '選択された利用者を統合',
	'centralauth-admin-bad-input'          => '統合の選択が不正です',
	'centralauth-admin-none-selected'      => '修正すべきアカウントがひとつも選択されていません。',
	'centralauth-prefs-status'             => '統一アカウントの状態:',
	'centralauth-prefs-not-managed'        => '統一アカウントを利用していません',
	'centralauth-prefs-unattached'         => '承認されていません',
	'centralauth-prefs-complete'           => '準備完了！',
	'centralauth-prefs-migration'          => '移行中',
	'centralauth-prefs-count-attached'     => 'あなたのアカウントは、$1プロジェクト{{plural:$1|サイト|サイト}}で有効です。',
	'centralauth-prefs-count-unattached'   => 'あなたの利用者名は、$1{{plural:$1|プロジェクト|プロジェクト}}で承認されていません。',
	'centralauth-prefs-detail-unattached'  => 'このプロジェクトサイトはまだ統一アカウントに承認されていません。',
	'centralauth-prefs-manage'             => '統一アカウントの管理',
	'centralauth-renameuser-abort'         => '<div class="errorbox">利用者 $1 は統一ログインシステムに移行済みのため、ローカルでの利用者名変更はできません。</div>',
);

/** Jutish (Jysk)
 * @author Huslåke
 */
$messages['jut'] = array(
	'centralauth-list-home-title' => 'Jæm wiki',
	'centralauth-foreign-link'    => 'Bruger $1 åp $2',
	'centralauth-finish-login'    => 'Loĝge på',
	'centralauth-admin-username'  => 'Bruger:',
	'centralauth-prefs-complete'  => "I'n årdnenge!",
	'centralauth-prefs-migration' => 'I migråsje',
);

$messages['kk-arab'] = array(
	'mergeaccount'                         => 'تىركەلگى بىرەگەيلەندىرۋ كۇيى',
	'centralauth-merge-notlogged'          => 'تىركەلگىلەرىڭىز تولىق بىرەگەيلەندىرۋىن تەكسەرۋ ٴۇشىن Please <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} كىرىڭىز]</span>.',
	'centralauth-merge-welcome'            => "'''قاتىسۋشى تىركەلگىڭىز Wikimedia بىرەگەيلەنگەن تىركەلگى جۇيەسىنە الىدە اۋىستىرىلمادى.'''

ەگەر تىركەلگىلەرىڭىزدى اۋىستىرۋدى تاڭداعان بولساڭىز, ٴبىر قاتىسۋشى اتىڭىز بەن قۇپىييا ٴسوزىڭىزدى پايدالانىپ بارلىق Wikimedia جوبالارىنىڭ بارلىق تىلدەرىندەگى ۋىيكىيلەرىنە كىرە الاسىز.
بۇل  [http://commons.wikimedia.org/ Wikimedia ورتاققورىنا] قوتارۋى سىيياقتى بولىسەتىن جوبالارمەن جۇمىس ىستەۋگە جەڭىلدىلىك بەرەدى, جانە ٴارتۇرلى جوبالاردا ەكى تۇلعا ٴبىر قاتىسۋشى اتىن پايدالانعاننان شىعاتىن بىلىقتى نە داۋ-جانجالدى بولدىرمايدى.

ەگەر باسقا بىرەۋ ٴسىزدىڭ قاتىسۋشى اتىڭىزدى باسقا تاراۋدا الداقاشان پايدالانىپ وتىرسا, بۇل وعان كەدەرگى كەلتىرمەيدى, بىراق بۇل سوڭىرا ولمەن نە اكىمشىمەن جۇمىسىن وتەۋدى بەرەدى.",
	'centralauth-merge-step1-title'        => 'تىركەلگى بىرەگەيلەندىرۋىن باستاۋ',
	'centralauth-merge-step1-detail'       => 'قاتىسۋشى اتىڭىز بەن تىركەلگەن ە-پوشتا جايڭىز سايكەستىگىن قۇپتاۋ ٴۇشىن باسقا ۋىيكىيلەردەگى تىركەلگىلەرگە قارسى تەكسەرىلەدى. بۇل نارسەلەر جارايتىنىن ٴوزىڭىز قۇپتاعانشا دەيىن ەش وزگەرىستەر جاسالمايدى.',
	'centralauth-merge-step1-submit'       => 'تىركەلگى مالىمەتتەرىن قۇپتاۋ',
	'centralauth-merge-step2-title'        => 'كوبىرەك تىركەلگىلەردى قۇپتاۋ',
	'centralauth-merge-step2-detail'       => 'تىركەلگىلەردىڭ كەيبىرەۋلەرى ەنگىزىلگەن مەكەن ۋىيكىيگە وزدىكتىك سايكەستىرىلمەدى. ەگەر وسى تىركەلگىلەر سىزدىكى بولسا, قۇپىييا سوزدەرىن كەلتىرىپ بۇنى قۇپتاي الاسىز.',
	'centralauth-merge-step2-submit'       => 'تىركەلگى مالىمەتتەرىن قۇپتاۋ',
	'centralauth-merge-step3-title'        => 'بىرەگەيلەنگەن تىركەلگى جاراتۋ',
	'centralauth-merge-step3-detail'       => 'كەلەسى ۋىيكىيلەردى تىركەمە ەتىپ, بىرەگەيلەنگەن تىركەلگىڭىزدى جاراتۋعا دايىنسىز:',
	'centralauth-merge-step3-submit'       => 'تىركەلگىلەردى بىرەگەيلەندىرۋ',
	'centralauth-complete'                 => 'تىركەلگى بىرەگەيلەندىرۋى ٴبىتتى!',
	'centralauth-incomplete'               => 'تىركەلگى بىرەگەيلەندىرۋى بىتكەن جوق!',
	'centralauth-complete-text'            => 'ەندى ارقايسى Wikimedia قورىنىڭ ۋىيكىي تورابىنا جاڭا تىركەلگى جاساماستان كىرۋىڭىزگە بولادى; ٴدال وسى قاتىسۋشى اتىڭىز بەن قۇپىييا ٴسوزىڭىز ۋىيكىيپەدىييا, ۋىيكىيسوزدىك, ۋىيكىيكىتاپ دەگەن جوبالاردا جانە بارلىق  تىلدەردەگى تارۋلارىندا قىزمەت ىستەيدى.',
	'centralauth-incomplete-text'          => 'تىركەلگىڭىز بىرەگەيلەندىرىگەننەن باستاپ ارقايسى Wikimedia قورىنىڭ ۋىيكىي تورابىنا جاڭا تىركەلگى جاساماستان كىرۋىڭىزگە بولادى; ٴدال وسى قاتىسۋشى اتىڭىز بەن قۇپىييا ٴسوزىڭىز ۋىيكىيپەدىييا, ۋىيكىيسوزدىك, ۋىيكىيكىتاپ, دەگەن جوبالاردا جانە بارلىق تىلدەردەگى تارۋلارىندا قىزمەت ىستەيدى.',
	'centralauth-not-owner-text'           => '«$1» قاتىسۋشى اتى $2 دەگەندەگى تىركەلگى ىييەسىنە وزدىكتىك تۇيىستىرىلگەن.

بۇل ٴوزىڭىز بولساڭىز, باسقى قۇپىييا ٴسوزىڭىزدى كادىمگىدەي مىندا ەنگىزىپ تىركەلگى بىرەگەيلەندىرۋ ٴۇدىرىسىن بىتىرۋىڭىزگە بولادى:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>تەك تانىسۋ ٴادىسى</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'تىركەلگى بىرەگەيلەندىرۋى اعىمدا كورسەتۋ / مىنەتۋ كۇيىندە تۇر, سونىمەن كوكەيكەستى ٴتۇيىستىرۋ ارەكەتتەرى وشىرىلگەن. عافۋ ەتىڭىز!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|'''بىرەگەيلەندىرىلگەن تىركەلگى''' تۋرالى كوبىرەك وقىڭىز]]...''",
	'centralauth-list-home-title'          => 'مەكەن ۋىيكىي',
	'centralauth-list-home-dryrun'         => 'بۇل ۋىيكىيدەگى تاعايىندالعان قۇپىييا ٴسوز بەن ە-پوشتا جايى بىرەگەيلەنگەن تىركەلگىڭىزگە پايدالانادى, جانە جەكە بەتىڭىز باسقا ۋىيكىيلەرگە وزدىكتىك سىلتەنەدى. قاي ۋىيكىيى مەكەن ەكەن دەپ سوڭىرا  وزگەرتە الاسىز.',
	'centralauth-list-attached-title'      => 'تىركەمە ەتىلگەن تىركەلگىلەر',
	'centralauth-list-attached'            => '«$1» دەپ اتالعان تىركەلگىسى ارقايسى كەلەسى توراپتاردا بىرەگەيلەنگەن تىركەلىگە وزدىكتىك تىركەمە ەتىلگەن:',
	'centralauth-list-attached-dryrun'     => '«$1» دەپ اتالعان تىركەلگىسى ارقايسى كەلەسى توراپتاردا بىرەگەيلەنگەن تىركەلىگە وزدىكتىك تىركەمە ەتىلەدى:',
	'centralauth-list-unattached-title'    => 'تىركەمە ەتىلمەگەن تىركەلگىلەر',
	'centralauth-list-unattached'          => '«$1» دەگەن تىركەلگى كەلەسى توراپتاردا سىزگە ٴتان دەپ وزدىكتىك قۇپتالمادى; بۇلاردا باسقى تىركەلگىدەگى قۇپىييا سوزدەن ايىرماشىلىقتارى بار بولۋى ەڭ ىيقتىيمال:',
	'centralauth-foreign-link'             => '$2 دەگەندەگى $1 قاتىسۋشىسى',
	'centralauth-finish-title'             => 'ٴتۇيىستىرىلۋ ٴبىتۋى',
	'centralauth-finish-text'              => 'بۇل تىركەلگىلەر سىزگە ٴتان بولسا, قۇپىييا سوزىدەرڭىزدى باسقا تىركەلگىلەرىڭىزگە كادىمگىدەي مىندا ەنگىزىپ تىركەلگى بىرەگەيلەندىرۋىن بىتىرۋڭىزگە بولادى:',
	'centralauth-finish-password'          => 'قۇپىييا ٴسوز:',
	'centralauth-finish-login'             => 'كىرۋ',
	'centralauth-finish-send-confirmation' => 'قۇپىييا ٴسوزدى حاتپەن جىبەرۋ',
	'centralauth-finish-problems'          => 'قىيىن جاعدايعا ۇشىرادىڭىز, نەمەسە باسقا تىركەلگىلەر سىزدىكى ەمەس? [[meta:Help:Unified login problems|قالاي انىقتالۋىڭىزعا بولادى]]...',
	'centralauth-merge-attempt'            => "'''كەلتىرىلگەن قۇپىييا ٴسوزدى قالعان تۇيىستىرىلمەگەن تىركەلگىلەر ٴۇشىن تەكسەرۋدە…'''",
	'centralauth-attach-list-attached'     => '«$1» دەپ اتالعان بىرەگەيلەنگەن تىركەلگىگە كەلەسى تىركەلگىلەر ەنگەن:',
	'centralauth-attach-title'             => 'تىركەلگىنى قۇپتاۋ',
	'centralauth-attach-text'              => 'بۇل تىركەلگى ٴالى دە بىرەگەيلەنگەن تىركەلگىگە اۋىستىرىلماعان. ەگەر عالامدىق تىركەلگى دە سىزگە ٴتان بولسا, عالامدىق تىركەلگىنىڭ قۇپىييا ٴسوزىن ەنگىزىپ بۇل تىركەلگىنى تۇيىستىرۋىڭىزگە بولادى:',
	'centralauth-attach-submit'            => 'تىركەلگىنى اۋىستىرتۋ',
	'centralauth-attach-success'           => 'وسى تىركەلگى بىرەگەيلەنگەن تىركەلگىگە اۋىcتىرىلدى.',
	'centralauth'                          => 'بىرەگەيلەندىرگەن تىركەلگىلەردى اكىمشىلىك مەڭگەرۋى',
	'centralauth-admin-manage'             => 'قاتىسۋشى دەرەكتەرىن رەتتەۋ',
	'centralauth-admin-username'           => 'قاتىسۋشى اتى:',
	'centralauth-admin-lookup'             => 'قاتىسۋشى دەرەكتەرىن قاراۋ نە وڭدەۋ',
	'centralauth-admin-permission'         => 'باسقالاردىڭ تىركەلگىلەرىن بۇعان تەك جەتەكشىلەر تۇيىستىرىلەيدى.',
	'centralauth-admin-unmerge'            => 'بولەكتەنگەندى تۇيىستىرىلەمە',
	'centralauth-admin-merge'              => 'بولەكتەنگەندى تۇيىستىرلە',
	'centralauth-admin-bad-input'          => 'ٴتۇيىستىرلۋ بولەكتەنۋى جارامسىز',
	'centralauth-admin-none-selected'      => 'تۇرلەندىرۋ ٴۇشىن ەش تىركەلگى بولەكتەنبەگەن.',
	'centralauth-prefs-status'             => 'عالامدىق تىركەلگىنىڭ كۇيى:',
	'centralauth-prefs-not-managed'        => 'بىرەگەيلەنگەن تىركەلگىسى پايدالانۋسىز',
	'centralauth-prefs-unattached'         => 'قۇپتالماعان',
	'centralauth-prefs-complete'           => 'بارلىعى رەتتەلىنگەن!',
	'centralauth-prefs-migration'          => 'اۋىستىرىلۋدا',
	'centralauth-prefs-count-attached'     => 'تىركەلگىڭىز $1 جوبا {{plural:$1|تورابىندا|توراپتارىندا}} بەلسەندى بولدى.',
	'centralauth-prefs-count-unattached'   => 'ٴسىزدىڭ اتىڭىز بار قۇپتالماعان تىركەلگىلەر $1 {{plural:$1|جوبادا|جوبالاردا}} قالدى.',
	'centralauth-prefs-detail-unattached'  => 'وسى جوبا تورابى عالامدىق تىركەلگىگە ٴتان دەپ قۇپتالماعان.',
	'centralauth-prefs-manage'             => 'عالامدىق تىركەلگىڭىزدى رەتتەۋ',
	'centralauth-renameuser-abort'         => '<div class="errorbox">$1 قاتىسۋشىسىن جەرگىلىكتە قايتا اتاۋعا بولمايدى. بۇل قاتىسۋشى اتى بىرەگەيلەنگەن تىركەلگى جۇيەسىنە اۋىستىرىلعان.</div>',
);

$messages['kk-cyrl'] = array(
	'mergeaccount'                         => 'Тіркелгі бірегейлендіру күйі',
	'centralauth-merge-notlogged'          => 'Тіркелгілеріңіз толық бірегейлендіруін тексеру үшін Please <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} кіріңіз]</span>.',
	'centralauth-merge-welcome'            => "'''Қатысушы тіркелгіңіз Wikimedia бірегейленген тіркелгі жүйесіне әліде ауыстырылмады.'''

Егер тіркелгілеріңізді ауыстыруды таңдаған болсаңыз, бір қатысушы атыңыз бен құпия сөзіңізді пайдаланып барлық Wikimedia жобаларының барлық тілдеріндегі уикилеріне кіре аласыз.
Бұл  [http://commons.wikimedia.org/ Wikimedia Ортаққорына] қотаруы сияқты бөлісетін жобалармен жұмыс істеуге жеңілділік береді, және әртүрлі жобаларда екі тұлға бір қатысушы атын пайдаланғаннан шығатын былықты не дау-жанжалды болдырмайды.

Егер басқа біреу сіздің қатысушы атыңызды басқа тарауда алдақашан пайдаланып отырса, бұл оған кедергі келтірмейді, бірақ бұл соңыра олмен не әкімшімен жұмысын өтеуді береді.",
	'centralauth-merge-step1-title'        => 'Тіркелгі бірегейлендіруін бастау',
	'centralauth-merge-step1-detail'       => 'Қатысушы атыңыз бен тіркелген е-пошта жайңыз сәйкестігін құптау үшін басқа уикилердегі тіркелгілерге қарсы тексеріледі. Бұл нәрселер жарайтынын өзіңіз құптағанша дейін еш өзгерістер жасалмайды.',
	'centralauth-merge-step1-submit'       => 'Тіркелгі мәліметтерін құптау',
	'centralauth-merge-step2-title'        => 'Көбірек тіркелгілерді құптау',
	'centralauth-merge-step2-detail'       => 'Тіркелгілердің кейбіреулері енгізілген мекен уикиге өздіктік сәйкестірілмеді. Егер осы тіркелгілер сіздікі болса, құпия сөздерін келтіріп бұны құптай аласыз.',
	'centralauth-merge-step2-submit'       => 'Тіркелгі мәліметтерін құптау',
	'centralauth-merge-step3-title'        => 'Бірегейленген тіркелгі жарату',
	'centralauth-merge-step3-detail'       => 'Келесі уикилерді тіркеме етіп, бірегейленген тіркелгіңізді жаратуға дайынсыз:',
	'centralauth-merge-step3-submit'       => 'Тіркелгілерді бірегейлендіру',
	'centralauth-complete'                 => 'Тіркелгі бірегейлендіруі бітті!',
	'centralauth-incomplete'               => 'Тіркелгі бірегейлендіруі біткен жоқ!',
	'centralauth-complete-text'            => 'Енді әрқайсы Wikimedia қорының уики торабына жаңа тіркелгі жасамастан кіруіңізге болады; дәл осы қатысушы атыңыз бен құпия сөзіңіз Уикипедия, Уикисөздік, Уикикітәп деген жобаларда және барлық  тілдердегі таруларында қызмет істейді.',
	'centralauth-incomplete-text'          => 'Тіркелгіңіз бірегейлендірігеннен бастап әрқайсы Wikimedia қорының уики торабына жаңа тіркелгі жасамастан кіруіңізге болады; дәл осы қатысушы атыңыз бен құпия сөзіңіз Уикипедия, Уикисөздік, Уикикітәп, деген жобаларда және барлық тілдердегі таруларында қызмет істейді.',
	'centralauth-not-owner-text'           => '«$1» қатысушы аты $2 дегендегі тіркелгі иесіне өздіктік түйістірілген.

Бұл өзіңіз болсаңыз, басқы құпия сөзіңізді кәдімгідей мында енгізіп тіркелгі бірегейлендіру үдірісін бітіруіңізге болады:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Тек танысу әдісі</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Тіркелгі бірегейлендіруі ағымда көрсету / мінету күйінде тұр, сонымен көкейкесті түйістіру әрекеттері өшірілген. Ғафу етіңіз!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|'''Бірегейлендірілген тіркелгі''' туралы көбірек оқыңыз]]...''",
	'centralauth-list-home-title'          => 'Мекен уики',
	'centralauth-list-home-dryrun'         => 'Бұл уикидегі тағайындалған құпия сөз бен е-пошта жайы бірегейленген тіркелгіңізге пайдаланады, және жеке бетіңіз басқа уикилерге өздіктік сілтенеді. Қай уикиі мекен екен деп соңыра  өзгерте аласыз.',
	'centralauth-list-attached-title'      => 'Тіркеме етілген тіркелгілер',
	'centralauth-list-attached'            => '«$1» деп аталған тіркелгісі әрқайсы келесі тораптарда бірегейленген тіркеліге өздіктік тіркеме етілген:',
	'centralauth-list-attached-dryrun'     => '«$1» деп аталған тіркелгісі әрқайсы келесі тораптарда бірегейленген тіркеліге өздіктік тіркеме етіледі:',
	'centralauth-list-unattached-title'    => 'Тіркеме етілмеген тіркелгілер',
	'centralauth-list-unattached'          => '«$1» деген тіркелгі келесі тораптарда сізге тән деп өздіктік құпталмады; бұларда басқы тіркелгідегі құпия сөзден айырмашылықтары бар болуы ең иқтимал:',
	'centralauth-foreign-link'             => '$2 дегендегі $1 қатысушысы',
	'centralauth-finish-title'             => 'Түйістірілу бітуі',
	'centralauth-finish-text'              => 'Бұл тіркелгілер сізге тән болса, құпия сөзідерңізді басқа тіркелгілеріңізге кәдімгідей мында енгізіп тіркелгі бірегейлендіруін бітіруңізге болады:',
	'centralauth-finish-password'          => 'Құпия сөз:',
	'centralauth-finish-login'             => 'Кіру',
	'centralauth-finish-send-confirmation' => 'Құпия сөзді хатпен жіберу',
	'centralauth-finish-problems'          => 'Қиын жағдайға ұшырадыңыз, немесе басқа тіркелгілер сіздікі емес? [[meta:Help:Unified login problems|Қалай анықталуыңызға болады]]...',
	'centralauth-merge-attempt'            => "'''Келтірілген құпия сөзді қалған түйістірілмеген тіркелгілер үшін тексеруде…'''",
	'centralauth-attach-list-attached'     => '«$1» деп аталған бірегейленген тіркелгіге келесі тіркелгілер енген:',
	'centralauth-attach-title'             => 'Тіркелгіні құптау',
	'centralauth-attach-text'              => 'Бұл тіркелгі әлі де бірегейленген тіркелгіге ауыстырылмаған. Егер ғаламдық тіркелгі де сізге тән болса, ғаламдық тіркелгінің құпия сөзін енгізіп бұл тіркелгіні түйістіруіңізге болады:',
	'centralauth-attach-submit'            => 'Тіркелгіні ауыстырту',
	'centralauth-attach-success'           => 'Осы тіркелгі бірегейленген тіркелгіге ауыcтырылды.',
	'centralauth'                          => 'Бірегейлендірген тіркелгілерді әкімшілік меңгеруі',
	'centralauth-admin-manage'             => 'Қатысушы деректерін реттеу',
	'centralauth-admin-username'           => 'Қатысушы аты:',
	'centralauth-admin-lookup'             => 'Қатысушы деректерін қарау не өңдеу',
	'centralauth-admin-permission'         => 'Басқалардың тіркелгілерін бұған тек жетекшілер түйістірілейді.',
	'centralauth-admin-unmerge'            => 'Бөлектенгенді түйістірілеме',
	'centralauth-admin-merge'              => 'Бөлектенгенді түйістірле',
	'centralauth-admin-bad-input'          => 'Түйістірлу бөлектенуі жарамсыз',
	'centralauth-admin-none-selected'      => 'Түрлендіру үшін еш тіркелгі бөлектенбеген.',
	'centralauth-prefs-status'             => 'Ғаламдық тіркелгінің күйі:',
	'centralauth-prefs-not-managed'        => 'Бірегейленген тіркелгісі пайдаланусыз',
	'centralauth-prefs-unattached'         => 'Құпталмаған',
	'centralauth-prefs-complete'           => 'Барлығы реттелінген!',
	'centralauth-prefs-migration'          => 'Ауыстырылуда',
	'centralauth-prefs-count-attached'     => 'Тіркелгіңіз $1 жоба {{plural:$1|торабында|тораптарында}} белсенді болды.',
	'centralauth-prefs-count-unattached'   => 'Сіздің атыңыз бар құпталмаған тіркелгілер $1 {{plural:$1|жобада|жобаларда}} қалды.',
	'centralauth-prefs-detail-unattached'  => 'Осы жоба торабы ғаламдық тіркелгіге тән деп құпталмаған.',
	'centralauth-prefs-manage'             => 'Ғаламдық тіркелгіңізді реттеу',
	'centralauth-renameuser-abort'         => '<div class="errorbox">$1 қатысушысын жергілікте қайта атауға болмайды. Бұл қатысушы аты бірегейленген тіркелгі жүйесіне ауыстырылған.</div>',
);

$messages['kk-latn'] = array(
	'mergeaccount'                         => 'Tirkelgi biregeýlendirw küýi',
	'centralauth-merge-notlogged'          => 'Tirkelgileriñiz tolıq biregeýlendirwin tekserw üşin Please <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} kiriñiz]</span>.',
	'centralauth-merge-welcome'            => "'''Qatıswşı tirkelgiñiz Wikimedia biregeýlengen tirkelgi jüýesine älide awıstırılmadı.'''

Eger tirkelgileriñizdi awıstırwdı tañdağan bolsañız, bir qatıswşı atıñız ben qupïya söziñizdi paýdalanıp barlıq Wikimedia jobalarınıñ barlıq tilderindegi wïkïlerine kire alasız.
Bul  [http://commons.wikimedia.org/ Wikimedia Ortaqqorına] qotarwı sïyaqtı bölisetin jobalarmen jumıs istewge jeñildilik beredi, jäne ärtürli jobalarda eki tulğa bir qatıswşı atın paýdalanğannan şığatın bılıqtı ne daw-janjaldı boldırmaýdı.

Eger basqa birew sizdiñ qatıswşı atıñızdı basqa tarawda aldaqaşan paýdalanıp otırsa, bul oğan kedergi keltirmeýdi, biraq bul soñıra olmen ne äkimşimen jumısın ötewdi beredi.",
	'centralauth-merge-step1-title'        => 'Tirkelgi biregeýlendirwin bastaw',
	'centralauth-merge-step1-detail'       => 'Qatıswşı atıñız ben tirkelgen e-poşta jaýñız säýkestigin quptaw üşin basqa wïkïlerdegi tirkelgilerge qarsı tekseriledi. Bul närseler jaraýtının öziñiz quptağanşa deýin eş özgerister jasalmaýdı.',
	'centralauth-merge-step1-submit'       => 'Tirkelgi mälimetterin quptaw',
	'centralauth-merge-step2-title'        => 'Köbirek tirkelgilerdi quptaw',
	'centralauth-merge-step2-detail'       => 'Tirkelgilerdiñ keýbirewleri engizilgen meken wïkïge özdiktik säýkestirilmedi. Eger osı tirkelgiler sizdiki bolsa, qupïya sözderin keltirip bunı quptaý alasız.',
	'centralauth-merge-step2-submit'       => 'Tirkelgi mälimetterin quptaw',
	'centralauth-merge-step3-title'        => 'Biregeýlengen tirkelgi jaratw',
	'centralauth-merge-step3-detail'       => 'Kelesi wïkïlerdi tirkeme etip, biregeýlengen tirkelgiñizdi jaratwğa daýınsız:',
	'centralauth-merge-step3-submit'       => 'Tirkelgilerdi biregeýlendirw',
	'centralauth-complete'                 => 'Tirkelgi biregeýlendirwi bitti!',
	'centralauth-incomplete'               => 'Tirkelgi biregeýlendirwi bitken joq!',
	'centralauth-complete-text'            => 'Endi ärqaýsı Wikimedia qorınıñ wïkï torabına jaña tirkelgi jasamastan kirwiñizge boladı; däl osı qatıswşı atıñız ben qupïya söziñiz Wïkïpedïya, Wïkïsözdik, Wïkïkitäp degen jobalarda jäne barlıq  tilderdegi tarwlarında qızmet isteýdi.',
	'centralauth-incomplete-text'          => 'Tirkelgiñiz biregeýlendirigennen bastap ärqaýsı Wikimedia qorınıñ wïkï torabına jaña tirkelgi jasamastan kirwiñizge boladı; däl osı qatıswşı atıñız ben qupïya söziñiz Wïkïpedïya, Wïkïsözdik, Wïkïkitäp, degen jobalarda jäne barlıq tilderdegi tarwlarında qızmet isteýdi.',
	'centralauth-not-owner-text'           => '«$1» qatıswşı atı $2 degendegi tirkelgi ïesine özdiktik tüýistirilgen.

Bul öziñiz bolsañız, basqı qupïya söziñizdi kädimgideý mında engizip tirkelgi biregeýlendirw üdirisin bitirwiñizge boladı:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Tek tanısw ädisi</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Tirkelgi biregeýlendirwi ağımda körsetw / minetw küýinde tur, sonımen kökeýkesti tüýistirw äreketteri öşirilgen. Ğafw etiñiz!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|'''Biregeýlendirilgen tirkelgi''' twralı köbirek oqıñız]]...''",
	'centralauth-list-home-title'          => 'Meken wïkï',
	'centralauth-list-home-dryrun'         => 'Bul wïkïdegi tağaýındalğan qupïya söz ben e-poşta jaýı biregeýlengen tirkelgiñizge paýdalanadı, jäne jeke betiñiz basqa wïkïlerge özdiktik siltenedi. Qaý wïkïi meken eken dep soñıra  özgerte alasız.',
	'centralauth-list-attached-title'      => 'Tirkeme etilgen tirkelgiler',
	'centralauth-list-attached'            => '«$1» dep atalğan tirkelgisi ärqaýsı kelesi toraptarda biregeýlengen tirkelige özdiktik tirkeme etilgen:',
	'centralauth-list-attached-dryrun'     => '«$1» dep atalğan tirkelgisi ärqaýsı kelesi toraptarda biregeýlengen tirkelige özdiktik tirkeme etiledi:',
	'centralauth-list-unattached-title'    => 'Tirkeme etilmegen tirkelgiler',
	'centralauth-list-unattached'          => '«$1» degen tirkelgi kelesi toraptarda sizge tän dep özdiktik quptalmadı; bularda basqı tirkelgidegi qupïya sözden aýırmaşılıqtarı bar bolwı eñ ïqtïmal:',
	'centralauth-foreign-link'             => '$2 degendegi $1 qatıswşısı',
	'centralauth-finish-title'             => 'Tüýistirilw bitwi',
	'centralauth-finish-text'              => 'Bul tirkelgiler sizge tän bolsa, qupïya söziderñizdi basqa tirkelgileriñizge kädimgideý mında engizip tirkelgi biregeýlendirwin bitirwñizge boladı:',
	'centralauth-finish-password'          => 'Qupïya söz:',
	'centralauth-finish-login'             => 'Kirw',
	'centralauth-finish-send-confirmation' => 'Qupïya sözdi xatpen jiberw',
	'centralauth-finish-problems'          => 'Qïın jağdaýğa uşıradıñız, nemese basqa tirkelgiler sizdiki emes? [[meta:Help:Unified login problems|Qalaý anıqtalwıñızğa boladı]]...',
	'centralauth-merge-attempt'            => "'''Keltirilgen qupïya sözdi qalğan tüýistirilmegen tirkelgiler üşin tekserwde…'''",
	'centralauth-attach-list-attached'     => '«$1» dep atalğan biregeýlengen tirkelgige kelesi tirkelgiler engen:',
	'centralauth-attach-title'             => 'Tirkelgini quptaw',
	'centralauth-attach-text'              => 'Bul tirkelgi äli de biregeýlengen tirkelgige awıstırılmağan. Eger ğalamdıq tirkelgi de sizge tän bolsa, ğalamdıq tirkelginiñ qupïya sözin engizip bul tirkelgini tüýistirwiñizge boladı:',
	'centralauth-attach-submit'            => 'Tirkelgini awıstırtw',
	'centralauth-attach-success'           => 'Osı tirkelgi biregeýlengen tirkelgige awıctırıldı.',
	'centralauth'                          => 'Biregeýlendirgen tirkelgilerdi äkimşilik meñgerwi',
	'centralauth-admin-manage'             => 'Qatıswşı derekterin rettew',
	'centralauth-admin-username'           => 'Qatıswşı atı:',
	'centralauth-admin-lookup'             => 'Qatıswşı derekterin qaraw ne öñdew',
	'centralauth-admin-permission'         => 'Basqalardıñ tirkelgilerin buğan tek jetekşiler tüýistirileýdi.',
	'centralauth-admin-unmerge'            => 'Bölektengendi tüýistirileme',
	'centralauth-admin-merge'              => 'Bölektengendi tüýistirle',
	'centralauth-admin-bad-input'          => 'Tüýistirlw bölektenwi jaramsız',
	'centralauth-admin-none-selected'      => 'Türlendirw üşin eş tirkelgi bölektenbegen.',
	'centralauth-prefs-status'             => 'Ğalamdıq tirkelginiñ küýi:',
	'centralauth-prefs-not-managed'        => 'Biregeýlengen tirkelgisi paýdalanwsız',
	'centralauth-prefs-unattached'         => 'Quptalmağan',
	'centralauth-prefs-complete'           => 'Barlığı rettelingen!',
	'centralauth-prefs-migration'          => 'Awıstırılwda',
	'centralauth-prefs-count-attached'     => 'Tirkelgiñiz $1 joba {{plural:$1|torabında|toraptarında}} belsendi boldı.',
	'centralauth-prefs-count-unattached'   => 'Sizdiñ atıñız bar quptalmağan tirkelgiler $1 {{plural:$1|jobada|jobalarda}} qaldı.',
	'centralauth-prefs-detail-unattached'  => 'Osı joba torabı ğalamdıq tirkelgige tän dep quptalmağan.',
	'centralauth-prefs-manage'             => 'Ğalamdıq tirkelgiñizdi rettew',
	'centralauth-renameuser-abort'         => '<div class="errorbox">$1 qatıswşısın jergilikte qaýta atawğa bolmaýdı. Bul qatıswşı atı biregeýlengen tirkelgi jüýesine awıtırılğan.</div>',
);

/** Khmer (ភាសាខ្មែរ)
 * @author Chhorran
 */
$messages['km'] = array(
	'centralauth-merge-step1-submit'       => 'ពត៌មាន បញ្ជាក់ទទួលស្គាល់ ការពិនិត្យចូល',
	'centralauth-merge-step2-title'        => 'បញ្ជាក់ទទួលស្គាល់ ច្រើនគណនី ទៀត',
	'centralauth-merge-step2-submit'       => 'ពត៌មាន បញ្ជាក់ទទួលស្គាល់ ការពិនិត្យចូល',
	'centralauth-list-home-title'          => 'វិគី ទំព័រដើម',
	'centralauth-finish-password'          => 'ពាក្យសំងាត់ ៖',
	'centralauth-finish-login'             => 'ពិនិត្យចូល',
	'centralauth-finish-send-confirmation' => 'អ៊ីមែវល៍ ពាក្យសំងាត់',
	'centralauth-attach-title'             => 'បញ្ជាក់ទទួលស្គាល់ គណនី',
	'centralauth-admin-username'           => 'ឈ្មោះ នៃអ្នកប្រើប្រាស់៖',
	'centralauth-prefs-unattached'         => 'លែងបាន បញ្ជាក់ទទួលស្គាល់',
);


$messages['la'] = array(
	'centralauth-finish-password'          => 'Tessera:',
	'centralauth-admin-username'           => 'Nomen usoris:',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'centralauth-list-home-title'          => 'Heemechts-Wiki',
	'centralauth-foreign-link'             => 'Benotzer $1 op $2',
	'centralauth-finish-password'          => 'Passwuert:',
	'centralauth-finish-login'             => 'Umeldung',
	'centralauth-finish-send-confirmation' => 'Passwuert per E-Mail zouschécken',
	'centralauth-admin-manage'             => 'Benotzerdate verwalten',
	'centralauth-admin-username'           => 'Benotzernumm:',
	'centralauth-admin-lookup'             => 'Benotzerdate kucken oder änneren',
	'centralauth-admin-permission'         => "Nëmme Stewarde kënnen d'Benotzerkonnte vun anere Benotzer fusionnéieren.",
	'centralauth-prefs-unattached'         => 'Net confirméiert',
	'centralauth-prefs-complete'           => 'Fäerdeg!',
	'centralauth-prefs-count-attached'     => 'Äre Benotzerkont ass op $1 {{PLURAL:$1|Projet|Projetën}} aktiv.',
	'centralauth-prefs-manage'             => 'Geréiert äre globale Benotzerkont',
);

/** Limburgish (Limburgs)
 * @author Ooswesthoesbes
 */
$messages['li'] = array(
	'mergeaccount'                         => 'Status samevoege gebroekers',
	'centralauth-desc'                     => "Samegevoegde gebroekers binne Wikimedia Foundation wiki's",
	'centralauth-merge-notlogged'          => '<span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} Meldj uch aan]</span> óm te konterlere of uche gebroekers volledig zeen samegevoeg.',
	'centralauth-merge-welcome'            => "'''Uche gebroeker is nag neet gemigreerd nao Wikimedia zien samegevoegdje aanmeldjsysteem.'''

Es geer uch d'rveur kees óm uche gebroekers te migrere, den kintj geer uch mit dezelfdje gebroekersnaaam-wachwaordcombinatie aanmelje bie alle projekwiki's van Wikimedia in alle besjikbare täöl. Dit maak 't einvoudiger óm te wèrke mit gedeildje projekte wie 't uploade nao [http://commons.wikimedia.org/ Wikimedia Commons] en veurkömp verwarring of konflikte doordet twieë miense dezelfdje gebroekersnaam keze op verjsillendje projekte.

Es emes anges mit uche gebroekersnaam al actief is op 'ne angere site, den haet det gein gevolge veur dae gebroeker. Geer höb de meugelikheid det mit dae gebroeker of 'ne beheerder op 'n later memènt op te losse.",
	'centralauth-merge-step1-title'        => 'Begin gebroekerssamevoege',
	'centralauth-merge-step1-detail'       => "Uch wachwaord en geregistreerd e-mailadres waere gekonterleerd taenge de gebroekers op anger wiki's óm te bevestige det ze euvereinkómme. D'r waere gein wieziginge gemaak toetdet geer höb aangegaeve det alles in orde liek.",
	'centralauth-merge-step1-submit'       => 'Bevestig aanmeldjinformatie',
	'centralauth-merge-step2-title'        => 'Bevestig mieë gebroekers',
	'centralauth-merge-step2-detail'       => "'n Aantal van de gebroekers kóste neet aan de opgegaeve thoeswiki gekoppeldj waere. Es dees gebroekers van uch zeen, kintj geer uch det aangaeve door 't wachwaord veur de gebroekers op te gaeve.",
	'centralauth-merge-step2-submit'       => 'Bevestig aanmeldjinformatie',
	'centralauth-merge-step3-title'        => 'Maak samegevoegdje gebroek aan',
	'centralauth-merge-step3-detail'       => "Geer kintj uch noe uche samegevoegdje gebroeker make mit dao in de volgendje wiki's opgenaome:",
	'centralauth-merge-step3-submit'       => 'Gebroekers samevoege',
	'centralauth-complete'                 => 'Samevoege gebroekers aafgeróndj!',
	'centralauth-incomplete'               => 'Samevoege gebroekers neet volledig!',
	'centralauth-complete-text'            => "Geer kint uch nu aanmelje bie edere wiki van Wikimedia zónger 'ne nuje gebroeker aan te make; dezelfdje combinatie van gebroekersnaam en wachwaord werk veur Wikipedia, Wiktionair, Wikibeuk en häör zösterperjèkter in alle täöl.",
	'centralauth-incomplete-text'          => "Es uche gebroekers zeen samegevoeg kintj geer uch aanmelje bie edere wiki van Wikimedia zóner 'ne nuje gebroeker aan te make; dezelfdje combinatie van gebroekersnaam en wachwaord werk veur Wikipedia, Wiktionair, Wikibeuk en häör zösterperjèkter in alle täöl.",
	'centralauth-not-owner-text'           => 'De gebroekersnaam "$1" is automatisch toegeweze aan de eigenaar van de gebroeker op $2.

Es geer det böntj, kintj geer uch \'t samevoege van gebroekers aafrönje door hiej \'t wachwaprd veur dae gebroeker te gaeve:',
	'centralauth-notice-dryrun'            => "<div class='succesbox'>Allein demonstratiemodus</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => "Samevoege gebroekers is op dit memènt besjikbaar in demonstratie- en debugmodus. 't Sameviege van gebroekers is op dit memènt dus neet meugelik.",
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Lae meer euver '''samegevoegdj aanmelde''']]...''",
	'centralauth-list-home-title'          => 'Thoeswiki',
	'centralauth-list-home-dryrun'         => "'t Wachwaord en e-mailadres det veur deze wiki is ingesteld wörd gebroek veur uche samegevoegde gebroeker. Uche gebroekerspazjena op dae wiki wörd automatisch gelink vanoet anger wiki's. Later kint geer instelle welke wiki uche thoeswiki is.",
	'centralauth-list-attached-title'      => 'Betróg gebroekers',
	'centralauth-list-attached'            => 'De gebroekers mit de naam "$1" op de volgende sites zeen automatisch samegevoeg:',
	'centralauth-list-attached-dryrun'     => 'De gebroeker mit de naam "$1" op de volgende sites wuuertj automatisch toegevoeg aan de samegevoegde gebroeker:',
	'centralauth-list-unattached-title'    => 'Neet betróg gebroekers',
	'centralauth-list-unattached'          => 'De gebroeker "$1" kós neet automatisch aan uch toegeweze waere veur de volgende sites;
waorsjienliek ómdet \'t wachwaord aafwiek van uche primaire gebroeker:',
	'centralauth-foreign-link'             => 'Gebroeker $1 op $2',
	'centralauth-finish-title'             => 'Samevoege aafrönje',
	'centralauth-finish-text'              => "Es dees gebroekers bie uch heure kinse 't proces van samevoege aafrönje door de wachwäörd veur de anger gebroekers hiej in te veure:",
	'centralauth-finish-password'          => 'Wachwaord:',
	'centralauth-finish-login'             => 'Gebroekersnaam',
	'centralauth-finish-send-confirmation' => 'E-mail wachwaord',
	'centralauth-finish-problems'          => "Kömp geer d'r neet oet of zeen dees gebroekers neet ucher? [[meta:Help:Unified login problems|Wie detse hölp vinjs]]....",
	'centralauth-merge-attempt'            => "'''Bezig mit 't controlere van de opgegaeve wachwäörd veur de nag neet samegevoegde gebroekers...'''",
	'centralauth-attach-list-attached'     => 'De samegevoegde gebroeker "$1" besteit oet de volgende gebroekers:',
	'centralauth-attach-title'             => 'Gebroeker bevestige',
	'centralauth-attach-text'              => "Deze gebroeker is nag neet gemigreerd nao 'ne samegevoegde gebroeker. Es de euverkoepelde gebroeker ouch van uch is den kint geer deze gebroeker samevoege es geer 't wachwaord veur de euverkoepelende gebroeker inguf:",
	'centralauth-attach-submit'            => 'Gebroeker migrere',
	'centralauth-attach-success'           => 'De gebroeker is gemigreerd nao de samegevoegde gebroeker.',
	'centralauth'                          => 'Beheer samegevoegdj aanmelje',
	'centralauth-admin-manage'             => 'Gebroekersgegaeves behere',
	'centralauth-admin-username'           => 'Gebroekersnaam:',
	'centralauth-admin-lookup'             => 'Gebroekersgegaeves bekieke of bewerke',
	'centralauth-admin-permission'         => 'Allein stewards kinne gebroekers van anger luuj samevoege.',
	'centralauth-admin-unmerge'            => 'Geselecteerde gebroekers splitse',
	'centralauth-admin-merge'              => 'Geselecteerde gebroekers samevoege',
	'centralauth-admin-bad-input'          => 'Onzjuuste samevoegselectie',
	'centralauth-admin-none-selected'      => "d'r Zeen gein gebroekers geselecteerd óm te verangere.",
	'centralauth-prefs-status'             => 'Globale gebroekersstatus:',
	'centralauth-prefs-not-managed'        => 'Gebroek gein samegevoegde gebroeker',
	'centralauth-prefs-unattached'         => 'Neet bevestig',
	'centralauth-prefs-complete'           => 'Alles in orde!',
	'centralauth-prefs-migration'          => 'Bezig mit migrere',
	'centralauth-prefs-count-attached'     => 'Diene gebroeker is actief in $1 {{plural:$1|perjèksite|perjèksites}}',
	'centralauth-prefs-count-unattached'   => 'Neet bevestigdje gebroekers mit uche naam zeen nag aanwezig op $1 perjèkter.',
	'centralauth-prefs-detail-unattached'  => 'Dees perjèksite is neet bevestig es beheurendje bie de globale gebroeker.',
	'centralauth-prefs-manage'             => 'Beheer diene globale gebroeker.',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Gebroeker $1 kin lokaal neet hernömp waere ómdet deze gebroeker is gemigreerdj nao \'t syteem van samegevoegdje gebroekers.</div>',
);

/** Lithuanian (Lietuvių)
 * @author Matasg
 */
$messages['lt'] = array(
	'centralauth-finish-password' => 'Slaptažodis',
	'centralauth-admin-username'  => 'Naudotojo vardas:',
);

/** Malayalam (മലയാളം)
 * @author Jacob.jose
 */
$messages['ml'] = array(
	'centralauth-finish-password'          => 'രഹസ്യവാക്ക്:',
	'centralauth-finish-send-confirmation' => 'രഹസ്യവാക്ക് ഇ-മെയില്‍ ചെയ്യുക',
	'centralauth-attach-title'             => 'അക്കൗണ്ട് സ്ഥിരീകരിക്കുക',
	'centralauth-admin-username'           => 'ഉപയോക്തൃനാമം:',
);

/** Erzya (эрзянь кель)
 * @author Amdf
 */
$messages['myv'] = array(
	'centralauth-admin-username' => 'Совицянь лем:',
);

/** Low German (Plattdüütsch)
 * @author Slomox
 */
$messages['nds'] = array(
	'centralauth-merge-step3-submit' => 'Brukers tohoopföhren',
	'centralauth-list-home-title'    => 'Heimatwiki',
	'centralauth-foreign-link'       => 'Bruker $1 op $2',
	'centralauth-finish-password'    => 'Passwoort:',
	'centralauth-admin-username'     => 'Brukernaam:',
);

/** Dutch (Nederlands)
 * @author Siebrand
 * @author SPQRobin
 */
$messages['nl'] = array(
	'mergeaccount'                         => 'Status samenvoegen gebruikers',
	'centralauth-desc'                     => "Samengevoegde gebruikers binnen Wikimedia Foundation wiki's",
	'centralauth-merge-notlogged'          => '<span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} Meld u aan]</span> om te controleren of uw gebruikers volledig zijn samengevoegd.',
	'centralauth-merge-welcome'            => "'''Uw gebruiker is nog niet gemigreerd naar Wikimedia's samengevoegde aanmeldsysteem.'''

Als u ervoor kiest om uw gebruikers te migreren, dan kunt u met dezelfde gebruikersnaam-wachtwoordcombinatie aanmelden bij alle projectwiki's van Wikimedia in alle beschikbare talen.
Dit maakt het eenvoudiger om te werken met gedeelde projecten, zoals het uploaden naar [http://commons.wikimedia.org/ Wikimedia Commons], en voorkomt verwarring of conflicten doordat twee mensen dezelfde gebruikersnaam kiezen op verschillende projecten.

Als iemand anders met uw gebruikersnaam al actief is op een andere site, dan heeft dat geen gevolgen voor die gebruiker. U heeft de mogelijkheid dat niet die gebruiker of een beheerder op een later moment op te lossen.",
	'centralauth-merge-step1-title'        => 'Samenvoegen gebruikers starten',
	'centralauth-merge-step1-detail'       => "Uw wachtwoord en geregistreerd e-mailadres worden gecontroleerd tegen de gebruikers op andere wiki's om te bevestigen dat ze overeenkomen. Er worden geen wijzigingen gemaakt tot u heeft aangegeven dat alles in orde lijkt.",
	'centralauth-merge-step1-submit'       => 'Aanmeldinformatie bevestigen',
	'centralauth-merge-step2-title'        => 'Meer gebruikers bevestigen',
	'centralauth-merge-step2-detail'       => 'Een aantal van de gebruikers konden niet aan de opgegeven thuiswiki gekoppeld worden. Als deze gebruikers van u zijn, kunt u dat aangeven door het wachtwoord voor de gebruikers op te geven.',
	'centralauth-merge-step2-submit'       => 'Aanmeldinformatie bevestigen',
	'centralauth-merge-step3-title'        => 'Samengevoegde gebruiker aanmaken',
	'centralauth-merge-step3-detail'       => "U kunt nu uw samengevoegde gebruiker maken, met daarin opgenomen de volgende wiki's:",
	'centralauth-merge-step3-submit'       => 'Gebruikers samenvoegen',
	'centralauth-complete'                 => 'Samenvoegen gebruikers afgerond!',
	'centralauth-incomplete'               => 'Samenvoegen gebruikers niet volledig!',
	'centralauth-complete-text'            => 'U kunt nu aanmelden bij iedere wiki van Wikimedia zonder een nieuwe gebruiker aan te maken; dezelfde combinatie van gebruikersnaam en wachtwoord werkt voor Wikipedia, Wiktionary, Wikibooks en hun zusterprojecten in alle talen.',
	'centralauth-incomplete-text'          => 'Als uw gebruikers zijn samengevoegd kunt u aanmelden bij iedere wiki van Wikimedia zonder een nieuwe gebruiker aan te maken; dezelfde combinatie van gebruikersnaam en wachtwoord werkt voor Wikipedia, Wiktionary, Wikibooks en hun zusterprojecten in alle talen.',
	'centralauth-not-owner-text'           => 'De gebruikersnaam "$1" is automatisch toegewezen aan de eigenaar van de gebruiker op $2.

Als u dat bent, kunt u het samenvoegen van gebruikers afronden door hier het wachtwoord voor die gebruiker in te geven:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Alleen demonstratiemodus</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Samenvoegen gebruikers is op dit moment beschikbaar in demonstratie- en debugmodus. Het samenvoegen van gebruikers is op dit moment dus niet mogelijk.',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Lees meer over '''samengevoegd aanmelden''']]...''",
	'centralauth-list-home-title'          => 'Thuiswiki',
	'centralauth-list-home-dryrun'         => "Het wachtwoord en e-mailadres dat voor deze wiki is ingesteld wordt gebruikt voor uw samengevoegde gebruiker. Uw gebruikerspagina op die wiki wordt automatisch gelinkt vanuit andere wiki's. Later kunt u instellen welke wiki uw thuiswiki is.",
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
	'centralauth-merge-attempt'            => "'''Bezig met het controleren van de opgegeven wachtwoorden voor de nog niet samengevoegde gebruikers...'''",
	'centralauth-attach-list-attached'     => 'De samengevoegde gebruiker "$1" bestaat uit de volgende gebruikers:',
	'centralauth-attach-title'             => 'Gebruiker bevestigen',
	'centralauth-attach-text'              => 'Deze gebruiker is nog niet gemigreerd naar een samengevoegde gebruiker. Als de overkoepelende gebruiker ook van u is, dan kunt u deze gebruiker samenvoegen als u het wachtwoord voor de overkoepelende gebruiker ingeeft:',
	'centralauth-attach-submit'            => 'Gebruiker migreren',
	'centralauth-attach-success'           => 'De gebruiker is gemigreerd naar de samengevoegde gebruiker.',
	'centralauth'                          => 'Samengevoegd aanmelden beheren',
	'centralauth-admin-manage'             => 'Gebruikersgegevens beheren',
	'centralauth-admin-username'           => 'Gebruikersnaam:',
	'centralauth-admin-lookup'             => 'Gebruikersgegevens bekijken of bewerken',
	'centralauth-admin-permission'         => 'Alleen stewards kunnen gebruikers van anderen samenvoegen.',
	'centralauth-admin-unmerge'            => 'Geselecteerde gebruikers splitsen',
	'centralauth-admin-merge'              => 'Geselecteerde gebruikers samenvoegen',
	'centralauth-admin-bad-input'          => 'Onjuiste samenvoegselectie',
	'centralauth-admin-none-selected'      => 'Er zijn geen gebruikers geselecteerd om te wijzigen',
	'centralauth-prefs-status'             => 'Globale gebruikerstatus',
	'centralauth-prefs-not-managed'        => 'Gebruikt geen samengevoegde gebruiker',
	'centralauth-prefs-unattached'         => 'Niet bevestigd',
	'centralauth-prefs-complete'           => 'Alles in orde!',
	'centralauth-prefs-migration'          => 'Bezig met migreren',
	'centralauth-prefs-count-attached'     => 'Uw gebruikers is actief in {{PLURAL:$1|één projectsite|$1 projectsites}}.',
	'centralauth-prefs-count-unattached'   => 'Niet-bevestigde gebruikers met uw naam zijn nog aanwezig op {{PLURAL:$1|één project|$1 projecten}}.',
	'centralauth-prefs-detail-unattached'  => 'Deze projectsite is niet bevestigd als behorende bij de globale gebruiker.',
	'centralauth-prefs-manage'             => 'Uw globale gebruiker beheren',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Gebruiker $1 kan plaatselijk niet hernoemd worden omdat deze gebruiker gemigreerd is naar het systeem van samengevoegde gebruikers.</div>',
);

/** Norwegian (‪Norsk (bokmål)‬)
 * @author Jon Harald Søby
 */
$messages['no'] = array(
	'mergeaccount'                         => 'Kontosammensmeltingsstatus',
	'centralauth-desc'                     => 'Slå sammen kontoer på wikier tilhørende Wikimedia Foundation',
	'centralauth-merge-notlogged'          => 'Vennligst <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special:MergeAccount}} logg inn]</span> for å sjekke om kontoene dine har blitt fullstendig sammensmeltet.',
	'centralauth-merge-welcome'            => "'''Din brukerkonto har ennå ikke blitt flyttet til Wikimedias enhetlige innlogginssystem.''' Om du velger å flytte kontoene dine kan du bruke samme brukernavn og passord for å logge inn på alle Wikimedias prosjekter på alle språk. Dette gjør det raskere å arbeide med delte prosjekter, som opplasting til [http://commons.wikimedia.org/ Wikimedia Commons], og unngår forvirringene og konfliktene som kan oppstå dersom to personer på forskjellige prosjekter bruker samme brukernavn. Dersom noen allerede har tatt ditt brukernavn på et annet prosjekt vil ikke dette forstyrre dem, men gi deg muligheten til å finne ut av sakene med dem eller en administrator senere.",
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
	'centralauth-notice-dryrun'            => "<div class='successbox'>Kun demonstrasjonsmodus</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Kontosammenslåing er foreløpig i en demonstrasjonsmodus, så faktisk sammenslåing er ikke mulig. Beklager!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Les mer om '''kontosammensmelting''']]…''",
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
	'centralauth-merge-attempt'            => "'''Sjekker det angitte passordet mot gjenværende kontoer…'''",
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

/** Northern Sotho (Sesotho sa Leboa)
 * @author Mohau
 */
$messages['nso'] = array(
	'centralauth-foreign-link'    => 'Moošomiši $1 go $2',
	'centralauth-finish-password' => 'Ditlhaka tša siphiri:',
	'centralauth-admin-username'  => 'Leina la mošomiši:',
);

/** Occitan (Occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'mergeaccount'                         => "Estatut d’unificacion del compte d'utilizaire",
	'centralauth-desc'                     => "Fusiona los comptes d'utilizaires de projèctes wikis de la Wikimedia Fondation",
	'centralauth-merge-notlogged'          => 'Mercé de plan voler <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} vos connectar]</span> per verificar que vòstres comptes son plan estats acampats.',
	'centralauth-merge-welcome'            => "'''Vòstres comptes d'utilizaire son pas encara estats migrats vèrs lo sistèma de compte unic de Wikimedia''' Se causissètz de far migrer vòstres comptes, poiretz utilizar lo meteis nom d’utilizaire e lo meteis senhal sus totes los projèctes Wikimedia dins totas las lengas. Atal, lo trabalh inter-projèctes serà mai aisit, e mai, per exemple, l’impòrt d’imatges sus [http://commons.wikimedia.org/ Wikimedia Commons] ; aquò evitarà tanben la confusion susvenent quand doas personas utilizant lo meteis nom d’utilizaire sus dos projèctes diferents. Se avètz ja lo meteis nom d’utilizaire sus totes los projèctes, deurià pas i aver de problèma. Se una autra persona a lo meteis nom d’utilizaire que vos sus un autre projècte, aurètz l'occasion de dintrar en contacte amb aquesta persona o amb un administrator mai tard.",
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
	'centralauth-not-owner-text'           => "Lo compte d'utilizaire « $1 » es estat automaticament assignat al proprietari del compte sus $2.

Se es vos, poirètz acabar lo procediment d’unificacion de compte en picant lo senhal mèstre per aqueste compte sus :",
	'centralauth-notice-dryrun'            => "<div class='successbox'>Mòde de demonstracion solament</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'La fusion dels comptes es actualament en mòde de demonstracion o de tèst, se pòt doncas pas encara vertadièrament fusionar los comptes. O planhem !',
	'centralauth-readmore-text'            => ":''[[w:oc:Wikipèdia:Login unic|Ne saber mai sul '''compte unificat''']]...''",
	'centralauth-list-home-title'          => 'Projècte principal',
	'centralauth-list-home-dryrun'         => "Lo senhal e l’adreça e-mail del projècte principal çaijos seràn utilizats per vòstre compte unic, e vòstra pagina d'utilizaire sul projècte principal serà automaticament ligada dempuèi los autres projèctes. Poirètz modificar vòstre projècte principal mai tard.",
	'centralauth-list-attached-title'      => 'Comptes ratachats',
	'centralauth-list-attached'            => "Los comptes d'utilizaires nomenats « $1 » son estats acampats pels sites seguents :",
	'centralauth-list-attached-dryrun'     => 'Lo compte nomenat « $1 » sus cadun dels sites seguents serà automaticament ratachat al compte unic :',
	'centralauth-list-unattached-title'    => 'Comptes non ratachats',
	'centralauth-list-unattached'          => "Lo compte d'utilizaire « $1 » pòt èsser confirmat automaticament pels sites que seguisson ; an probablament un senhal diferent de vòstre compte mèstre :",
	'centralauth-foreign-link'             => 'Utilizaire $1 sus $2',
	'centralauth-finish-title'             => 'Completar l’unificacion',
	'centralauth-finish-text'              => 'Se aquestes comptes vos apartenon, podètz acabar lor unificacion en picant los senhals çaijós :',
	'centralauth-finish-password'          => 'Senhal:',
	'centralauth-finish-login'             => "Compte d'utilizaire:",
	'centralauth-finish-send-confirmation' => 'Mandar lo senhal per corrièr electronic',
	'centralauth-finish-problems'          => 'En cas de problèma o se possedissètz pas aquestes autres comptes, vejatz la pagina [[meta:Help:Unified login problems|Problèmas]] (en anglés)...',
	'centralauth-merge-attempt'            => "'''Verificacion del senhal provesit pels comptes non acampats...'''",
	'centralauth-attach-list-attached'     => 'Lo compte unificat nomenat "$1" inclutz los comptes seguents :',
	'centralauth-attach-title'             => 'Confirmar lo compte',
	'centralauth-attach-text'              => 'Aqueste compte a pas encara estat migrat en un compte unificat. Se lo compte global tanben vos aparten, podètz fusionar aqueste compte se picatz lo senhal del compte global :',
	'centralauth-attach-submit'            => 'Migrar los comptes',
	'centralauth-attach-success'           => 'Lo compte es estat migrat en un compte unificat.',
	'centralauth'                          => 'Administracion dels comptes unificats',
	'centralauth-admin-manage'             => "Administrar las donadas d'utilizaire",
	'centralauth-admin-username'           => "Nom d'utilizaire:",
	'centralauth-admin-lookup'             => "Veire o modificar las donadas d'utilizaire",
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

/** Polish (Polski)
 * @author Sp5uhe
 * @author Derbeth
 */
$messages['pl'] = array(
	'mergeaccount'                         => 'Status tworzenia konta globalnego',
	'centralauth-desc'                     => 'Tworzenie konta globalnego - jednego dla wszystkich projektów Fundacji Wikimedia',
	'centralauth-merge-notlogged'          => '<span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} Zaloguj się]</span> by sprawdzić, czy wszystkie Twoje konta zostały przyłączone do konta globalnego.',
	'centralauth-merge-welcome'            => "'''Twoje konto użytkownika nie zostało jeszcze przekształcone na konto globalne.'''

Funkcjonalność konta globalnego, czyli współdzielonego między projektami, pozwala na korzystanie z tej samej nazwy użytkownika i hasła do logowania we wszystkich projektach Wikimedia; we wszystkich wersjach językowych. Ułatwia to np. ładowanie plików na [http://commons.wikimedia.org/ Wikimedia Commons] i pozwala unikać nieporozumień lub nawet konfliktów, które mogą powstać, gdy dwie osoby wybiorą tą samą nazwę użytkownika na różnych projektach.

Jeśli ktoś inny wykorzystuje na innym projekcie identyczną do Twojej nazwę użytkownika, przekształcenie Twojego konta na globalne nie stanie się dla niego problemem, lecz da Ci szansę na późniejsze znalezienie rozwiązania we współpracy bezpośrednio z tą osobą lub z wykorzystaniem pomocy administratorów.",
	'centralauth-merge-step1-title'        => 'Przekształć na globalne',
	'centralauth-merge-step1-detail'       => 'Twoje hasło i zarejestrowany adres e-mail zostaną porównane z kontami na innych wiki, w celu potwierdzenia ich zbieżności. Żadne zmiany nie zostaną wykonane, zanim ich nie zaakceptujesz.',
	'centralauth-merge-step1-submit'       => 'Sprawdzenie informacji o logowaniu',
	'centralauth-merge-step2-title'        => 'Potwierdź więcej kont',
	'centralauth-merge-step2-detail'       => 'Niektóre z kont nie mogły zostać automatycznie przyłączone do konta globalnego. Jeśli te konta należą do Ciebie, możesz potwierdzić, że są Twoje przez podanie haseł do nich.',
	'centralauth-merge-step2-submit'       => 'Potwierdź informację o logowaniu',
	'centralauth-merge-step3-title'        => 'Utwórz konto globalne',
	'centralauth-merge-step3-detail'       => 'System jest gotowy do utworzenia konta globalnego, z dołączonymi następującymi wiki:',
	'centralauth-merge-step3-submit'       => 'Utwórz konto globalne',
	'centralauth-complete'                 => 'Tworzenie konta globalnego zakończone!',
	'centralauth-incomplete'               => 'Tworzenie konta globalnego niekompletne!',
	'centralauth-complete-text'            => 'Możesz teraz logować się na każde wiki Fundacji Wikimedia bez tworzenia nowego konta; ta sama nazwa użytkownika i hasło będzie działać na Wikipedii, Wikisłowniku, Wikipedii i ich projektach siostrzanych we wszystkich językach.',
	'centralauth-incomplete-text'          => 'Kiedy twój login zostanie ujednolicony, będziesz mógł zalogować się do każdego wiki Fundacji Wikimedia bez tworzenia nowego konta; ta sama nazwa użytkownika i hasło będzie działać na Wikipedii, Wikisłowniku, Wikibooks i ich projektach siostrzanych we wszystkich językach.',
	'centralauth-not-owner-text'           => 'Nazwa użytkownika "$1" została automatycznie przypisana właścicielowi konta globalnego na $2.

Jeśli chcesz przyłączyć konto użytkownika "$1" do konta globalnego podaj hasło konta na $2:',
	'centralauth-notice-dryrun'            => '<div class="successbox">Tylko tryb demonstracyjny</div><br style="clear:both" />',
	'centralauth-disabled-dryrun'          => 'Tworzenie konta globalnego jest dostępne tylko w trybie demonstracyjnym/debugującym. Właściwe operacje łączenia kont są wyłączone. Przepraszamy!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Czytaj więcej o '''tworzeniu konta globalnego''']]...''",
	'centralauth-list-home-title'          => 'Macierzysta wiki',
	'centralauth-list-home-dryrun'         => 'Ustawione na tej wiki hasło oraz adres e-mail będą wykorzystywane dla Twojego konta globalnego, a Twoja strona użytkownika zostanie automatycznie podłączona do innych wiki. W przyszłości będziesz jednak mógł zmienić swoją macierzystą wiki.',
	'centralauth-list-attached-title'      => 'Powiązane konta użytkownika',
	'centralauth-list-attached'            => 'Konto o nazwie "$1" we wszystkich tych witrynach zostało automatycznie przypisane do konta globalnego:',
	'centralauth-list-attached-dryrun'     => 'Konto o nazwie "$1" we wszystkich tych witrynach zostanie automatycznie przypisane do konta globalnego:',
	'centralauth-list-unattached-title'    => 'Niepowiązane konta',
	'centralauth-list-unattached'          => 'Konto o nazwie "$1" nie może zostać automatycznie przypisane do konta globalnego dla następujących witryn; najbardziej prawdopodobną przyczyną jest ustawienie dla tych witryn innego hasła niż dla konta macierzystego:',
	'centralauth-foreign-link'             => 'Użytkownik $1 na $2',
	'centralauth-finish-title'             => 'Zakończono tworzenie konta globalnego',
	'centralauth-finish-text'              => 'Jeżeli te konta należą do Ciebie, możesz je przyłączyć do konta globalnego wpisując tutaj hasło dla tych kont:',
	'centralauth-finish-password'          => 'Hasło:',
	'centralauth-finish-login'             => 'Zaloguj',
	'centralauth-finish-send-confirmation' => 'Hasło e-mail',
	'centralauth-finish-problems'          => 'Masz kłopoty lub nie jesteś właścicielem tych innych kont? [[meta:Help:Unified login problems|Jak znaleźć pomoc]]...',
	'centralauth-merge-attempt'            => "'''Sprawdzenie hasła dla pozostałych kont, nieprzyłączonych do konta globalnego...'''",
	'centralauth-attach-list-attached'     => 'Konto globalne "$1" obejmuje następujące konta:',
	'centralauth-attach-title'             => 'Potwierdź konto',
	'centralauth-attach-text'              => 'To konto nie zostało jeszcze przyłączone do konta globalnego. Jeżeli konto globalne należy również do Ciebie, podaj jego hasło, a nastąpi przyłączenie:',
	'centralauth-attach-submit'            => 'Przyłącz konto',
	'centralauth-attach-success'           => 'Konto zostało przyłączone do konta globalnego',
	'centralauth'                          => 'Zarządzanie kontem globalnym',
	'centralauth-admin-manage'             => 'Zarządzanie danymi użytkownika',
	'centralauth-admin-username'           => 'Nazwa użytkownika:',
	'centralauth-admin-lookup'             => 'Podgląd i edycja danych użytkownika',
	'centralauth-admin-permission'         => 'Tylko stewardzi mogą łączyć cudze konta w imieniu tych osób.',
	'centralauth-admin-unmerge'            => 'Odłącz zaznaczone',
	'centralauth-admin-merge'              => 'Przyłącz zaznaczone',
	'centralauth-admin-bad-input'          => 'Nieprawidłowe zaznaczenia dla wykonania przyłączenia',
	'centralauth-admin-none-selected'      => 'Nie zaznaczono kont do modyfikacji.',
	'centralauth-prefs-status'             => 'Status globalnego konta:',
	'centralauth-prefs-not-managed'        => 'nie używasz globalnego konta',
	'centralauth-prefs-unattached'         => 'Niepotwierdzone',
	'centralauth-prefs-complete'           => 'Wszystko w porządku!',
	'centralauth-prefs-migration'          => 'W trakcie przyłączania',
	'centralauth-prefs-count-attached'     => 'Twoje konto jest aktywne na $1 {{plural:$1|projekcie|projektach}}.',
	'centralauth-prefs-count-unattached'   => 'Nieprzyłączone konta o nazwie zbieżnej z Twoją są na $1 {{PLURAL:$1|projekcie|projektach}}.',
	'centralauth-prefs-detail-unattached'  => 'Konto na tej witrynie nie zostało przyłączone do konta globalnego.',
	'centralauth-prefs-manage'             => 'zarządzaj globalnym kontem',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Nie można lokalnie przemianować użytkownika $1, ponieważ to konto zostało przeniesione do globalnego systemu logowania.</div>',
);

$messages['pms'] = array(
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

/** Pashto (پښتو)
 * @author Ahmed-Najib-Biabani-Ibrahimkhel
 */
$messages['ps'] = array(
	'centralauth-finish-password'          => 'پټنوم:',
	'centralauth-finish-login'             => 'ننوتنه',
	'centralauth-finish-send-confirmation' => 'د برېښناليک پټنوم',
	'centralauth-admin-username'           => 'کارن-نوم:',
);

/** Portuguese (Português)
 * @author Malafaya
 * @author Smeira
 */
$messages['pt'] = array(
	'mergeaccount'                         => 'Estado da unificação de contas',
	'centralauth-desc'                     => 'Fundir conta através dos wikis da Fundação Wikimedia',
	'centralauth-merge-notlogged'          => 'Por favor, <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} efectue login]</span> para verificar se as suas contas foram correctamente fundidas.',
	'centralauth-merge-welcome'            => "'''A sua conta de utilizador ainda não foi migrada para o sistema de autenticação (login) unificado da Wikimedia.'''

Caso decida migrar as suas contas, será possível utilizar o mesmo nome de utilizador e palavra-chave para se autenticar em todos os wikis da Wikimedia (em todos os projectos e em todos os idiomas disponíveis).
Isto torna mais fácil trabalhar em projectos partilhados, tal como carregar um ficheiro no [http://commons.wikimedia.org/ Wikimedia Commons], e evita confusões ou conflitos que podem ocorrer quando duas pessoas escolhem o mesmo nome de utilizador em diferentes projectos.

Caso alguém já tenha um nome de utilizador idêntico ao seu em algum outro wiki, tal pessoa não será importunada. No entanto, será possível que dialogue com a mesma ou com um administrador posteriormente.",
	'centralauth-merge-step1-title'        => 'Iniciar a unificação de logins',
	'centralauth-merge-step1-detail'       => 'A sua palavra-chave (senha) e endereço de e-mail serão comparados com os de contas de outros wikis, para confirmar se coincidem. Não serão feitas alterações até que confime que está tudo correcto.',
	'centralauth-merge-step1-submit'       => 'Confirmar informações de login',
	'centralauth-merge-step2-title'        => 'Confirmar contas adicionais',
	'centralauth-merge-step2-detail'       => 'Algumas das contas não coincidem com os dados da residência wiki fornecida. Caso tais contas pertençam a você, será possível confirmar de que são suas fornecendo a palavra-chave (senha) das mesmas.',
	'centralauth-merge-step2-submit'       => 'Confirmar informações de login',
	'centralauth-merge-step3-title'        => 'Criar conta unificada',
	'centralauth-merge-step3-detail'       => 'Tudo pronto para que a sua conta unificada, com os seguintes wikis a ela relacionados, seja criada:',
	'centralauth-merge-step3-submit'       => 'Unificar contas',
	'centralauth-complete'                 => 'Unificação de logins completa!',
	'centralauth-incomplete'               => 'Unificação de logins incompleta!',
	'centralauth-complete-text'            => 'Agora você poderá efectuar login em qualquer wiki da Wikimedia sem ter de criar uma nova conta; o mesmo nome de utilizador e senha funcionarão na Wikipédia, no Wikcionário, no Wikibooks e demais projectos, em todos os idiomas.',
	'centralauth-incomplete-text'          => 'Uma vez estando o seu login unificado, poderá efectuar login em qualquer wiki da Wikimedia sem ter de criar novo cadastro; o mesmo nome de utilizador e senha funcionarão na Wikipédia, no Wikcionário, no Wikibooks e demais projectos, em todos os idiomas.',
	'centralauth-not-owner-text'           => 'O nome de utilizador "$1" foi automaticamente relacionado ao proprietário da conta em $2.

Se este for você, você poderá concluir o procedimento de unificação de login simplesmente digitando a senha principal de tal conta aqui:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Modo de demonstração</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'A unificação de contas se encontra no momento em modo exclusivamente de demonstração/testes. Lamentamos, mas as mesmas ainda não foram unificadas.',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Leia mais sobre o '''login unificado''']]...''",
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
	'centralauth-finish-login'             => 'Utilizador',
	'centralauth-finish-send-confirmation' => 'Enviar senha por e-mail',
	'centralauth-finish-problems'          => 'Está com problemas ou estas outras contas não são suas? [[meta:Help:Unified login problems|Como procurar por ajuda]]...',
	'centralauth-merge-attempt'            => "'''Verificando a senha fornecida para encontrar as demais contas ainda não fundidas...'''",
	'centralauth-attach-list-attached'     => 'A conta unificada com o nome "$1" inclui as seguintes contas:',
	'centralauth-attach-title'             => 'Confirmar conta',
	'centralauth-attach-text'              => 'Esta conta não foi ainda migrada para a conta unificada. Se a conta global é sua também, pode fundir esta conta se introduzir a sua palavra-chave da conta global:',
	'centralauth-attach-submit'            => 'Migrar conta',
	'centralauth-attach-success'           => 'A conta foi migrada para a conta unificada.',
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
	'centralauth-prefs-count-attached'     => 'A sua conta encontra-se activa em $1 {{plural:$1|sítio|sítios}} de projecto.',
	'centralauth-prefs-count-unattached'   => 'Ainda existem contas não confirmadas com seu nome de utilizador em $1 {{plural:$1|projecto|projectos}}.',
	'centralauth-prefs-detail-unattached'  => 'Este sítio não foi confirmado como fazendo parte da conta unificada.',
	'centralauth-prefs-manage'             => 'Manusear sua conta unificada',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Não foi possível renomear localmente o utilizador $1 uma vez que a conta do mesmo foi migrada para o sistema de login universal.</div>',
);


$messages['ro'] = array(
	'centralauth-finish-password'          => 'Parolă:',
);

/** Russian (Русский)
 * @author .:Ajvol:.
 */
$messages['ru'] = array(
	'mergeaccount'                         => 'Состояние объединения учётных записей',
	'centralauth-desc'                     => 'Объединение учётных записей на вики-проектах «Фода Викимедиа»',
	'centralauth-merge-notlogged'          => 'Пожалуйста, <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} представьтесь]</span>, чтобы проверить, были ли ваши учётные записи объединены.',
	'centralauth-merge-welcome'            => "'''Ваша учётная запись ещё не была переведена на единую систему входа Викимедии'''

Если вы решите перенести свои учётные записи, вы сможете использовать одни и те же имя участника и пароль для входа на все языковые разделы всех вики-проектов Викимедиа.
Это сделает проще работу с общими проектами, например, загрузку изображений на [http://commons.wikimedia.org/ Викисклад], позволит избежать путаницы и проблем, которые могут возникнуть, когда разные люди используют в различных проектах одинаковые имена.

Если кто-то уже занял ваше имя в другом проекте, данный переход не затронет их, можно будет разрешить эту проблему с ними или с одним из администраторов позже.",
	'centralauth-merge-step1-title'        => 'Начать объединение учётных имён',
	'centralauth-merge-step1-detail'       => 'Ваш пароль и адрес электронной почты будут сравнены с данными учётных записей других вики-проектов. Изменения не будут сделаны до тех пор, пока вы не подтвердите правильность соответствия.',
	'centralauth-merge-step1-submit'       => 'Подтвердить информацию об учётном имени',
	'centralauth-merge-step2-title'        => 'Подтвердить дополнительные учётные записи',
	'centralauth-merge-step2-detail'       => 'Некоторые учётные записи не могут быть автоматически привязаны к обозначенной домашней вики. Если эти учётные записи принадлежат вам, вы можете подтвердить это, указав соответствующие пароли.',
	'centralauth-merge-step2-submit'       => 'Подтвердить информацию об учётном имени',
	'centralauth-merge-step3-title'        => 'Создание единой учётной записи',
	'centralauth-merge-step3-detail'       => 'Вы готовы создать единую учётную запись с присоединением следующих вики-проектов:',
	'centralauth-merge-step3-submit'       => 'Объединить учётные записи',
	'centralauth-complete'                 => 'Объединение учётных записей завершено!',
	'centralauth-incomplete'               => 'Объединение учётных записей не завершено!',
	'centralauth-complete-text'            => 'Вы можете сейчас представляться любому сайту Викимедиа, без создания новой учётной записи. Одни и те же имя участника и пароль будут работать в Википедии, Викисловаре, Викиучебнике и других проектах на всех языках.',
	'centralauth-incomplete-text'          => 'Как только ваша учётная запись будет объединена, вы сможете представляться на любых проектах Викимедии не создавая новых учётных записей. Одни и те же имя участника и пароль будут работать в Википедии, Викисловаре, Викиучебнике и других проектах на всех языках.',
	'centralauth-not-owner-text'           => 'Имя «$1» было автоматически передано владельцу учётной записи «$2».

Если это вы, то вы можете завершить процесс объединения учётных записей введя здесь основной пароль этой учётной записи:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Демонстрационный режим</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Объединение учётных записей сейчас работает в демонстрационном (отладочном) режиме, реальные операции объединения отключены. Извините.',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Информация об '''объединении учётных записей''']]...''",
	'centralauth-list-home-title'          => 'Домашняя вики',
	'centralauth-list-home-dryrun'         => 'Значения пароля и адреса электронной почты в этой вики будут использованы для вашей единой учётной записи, на страницу участника в этом проекте будут автоматически проставлены ссылки из других вики-проектов. В дальнейшем вы сможете сменить вашу домашнюю вики.',
	'centralauth-list-attached-title'      => 'Присоединённые учётные записи',
	'centralauth-list-attached'            => 'Учётная запись «$1» на следующих сайтах была автоматически объединена:',
	'centralauth-list-attached-dryrun'     => 'Учётное имя «$1» на каждом из перечисленных ниже сайтов будет автоматически присоединено к единой учётной записи:',
	'centralauth-list-unattached-title'    => 'Неприсоединённые учётные записи',
	'centralauth-list-unattached'          => 'Принадлежность вам учётной записи «$1» не может быть автоматически подтверждено на указанных ниже сайтах; вероятно, пароль на них не совдает с паролем вашей основной учётной записи:',
	'centralauth-foreign-link'             => 'Пользователь $1 на $2',
	'centralauth-finish-title'             => 'Окончание объединения',
	'centralauth-finish-text'              => 'Если эти учётные записи принадлежат вам, то вы можете завершить процесс объединения, введя здесь пароли  для других учётных записей:',
	'centralauth-finish-password'          => 'Пароль:',
	'centralauth-finish-login'             => 'Имя пользователя',
	'centralauth-finish-send-confirmation' => 'Выслать пароль по эл. почте',
	'centralauth-finish-problems'          => 'Если возникли проблемы, или вы не являетесь владельцем указанных учётных записей [[meta:Help:Unified login problems|обратитель к справочной информации]]...',
	'centralauth-merge-attempt'            => "'''Проверка введённого пароля на оставшихся необъединённых учётных записях...'''",
	'centralauth-attach-list-attached'     => 'Единая учётная запись «$1» включает следующие учётные записи:',
	'centralauth-attach-title'             => 'Подтверждение учётной записи',
	'centralauth-attach-text'              => 'Эта учётная запись ещё не была перемещена в систему единой учётной записи. Если глобальная учётная запись также принадлежит вам, вы можете присоединить данную учётную запись, указав пароль глобальной учётной записи:',
	'centralauth-attach-submit'            => 'Перенести учётную запись',
	'centralauth-attach-success'           => 'Учётная запись была перенесена в систему единой учётной записи.',
	'centralauth'                          => 'Администрирование объединения имён',
	'centralauth-admin-manage'             => 'Управление информацией об участниках',
	'centralauth-admin-username'           => 'Имя участника:',
	'centralauth-admin-lookup'             => 'Просмотр или редактирование информации об участнике',
	'centralauth-admin-permission'         => 'Только стюарды могут объединять учётные записи других людей.',
	'centralauth-admin-unmerge'            => 'Разделить выбранные',
	'centralauth-admin-merge'              => 'Объединить выбранные',
	'centralauth-admin-bad-input'          => 'Ошибочный выбор объединения',
	'centralauth-admin-none-selected'      => 'Не были выбраны учётные записи для изменения.',
	'centralauth-prefs-status'             => 'Состояние глобальной учётной записи:',
	'centralauth-prefs-not-managed'        => 'Не используется единая учётная запись',
	'centralauth-prefs-unattached'         => 'Неподтверждена',
	'centralauth-prefs-complete'           => 'Всё в порядке!',
	'centralauth-prefs-migration'          => 'Переносится',
	'centralauth-prefs-count-attached'     => 'Ваша учётная запись активна в $1 {{plural:$1|проекте|проектах|проектах}}.',
	'centralauth-prefs-count-unattached'   => 'Неподтверждённые учётные записи с вашим именем остаются в $1 {{plural:$1|проекте|проектах|проектах}}.',
	'centralauth-prefs-detail-unattached'  => 'Этот проект не был подтверждён как относящийся к вашей глобальной учётной записи.',
	'centralauth-prefs-manage'             => 'Управления глобальной учётной записью',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Невозможно переименовать участника $1 в данном проекте, так как это имя участника было перенесено в систему единой учётной записи.</div>',
);

/** Yakut (Саха тыла)
 * @author HalanTul
 */
$messages['sah'] = array(
	'mergeaccount'                         => 'Ааттары холбооһун туруга',
	'centralauth-desc'                     => '"Викимедиа" бырайыактарын хос ааттарын холбооһун',
	'centralauth-merge-notlogged'          => 'Бука диэн, <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} ааккын эт]</span>, оччоҕо ааттарыҥ холбонууларын туругун көрүөҥ.',
	'centralauth-merge-welcome'            => "'''Эн аатыҥ Викимедияҕа киирэр биир аат систиэмэтигэр өссө киирэ илик'''

Ааттаргын онно киллэрэр түгэҥҥэр, биир аатынан уонна киирии тылынан ханнык баҕарар тылларынан суруллубут ханнык баҕарар Викимедиа пуондатын саайтарыгар киирэр кыахтаныаҥ. 
Бу уопсай бырайыактарга үлэлииргэр көмөлөһүө, холобур, [http://commons.wikimedia.org/ Вики ыскылаакка] ойууну киллэргэ атын бырайыактартан биир ааттаах дьон киирэн булкууру таһаараллара суох буолуо.

Өскө ким эрэ эн ааккын атын бырайыакка ылбыт буоллаҕына бу дьайыы кинини таарыйыа суоҕа. Кэлин бу түгэни бэйэтин кытта эбэтэр администраторы кытта быһаарсар кыахтаныаҥ.",
	'centralauth-merge-step1-title'        => 'Бэлиэтэммит ааттары холбууру саҕалаа',
	'centralauth-merge-step1-detail'       => 'Эн киирии тылыҥ уонна электроннай буостаҥ аадырыһа атын вики бырайыактар ааттарын кытта тэҥнэниллиэ. Ханнык да уларытыы бэйэҥ бигэргэтэ иликкинэ олоххо киириэ суоҕа.',
	'centralauth-merge-step1-submit'       => 'Аатым туһунан сибидиэнньэни бигэргэт',
	'centralauth-merge-step2-title'        => 'Атын ааттары бигэргэт',
	'centralauth-merge-step2-detail'       => 'Сорох ааттар аптамаатынан талыллыбыт аакка холбоммотулар. Ол ааттар эйиэннэрэ буоллаҕына тустаах киирии тылларын киллэрэн ону бигэргэт.',
	'centralauth-merge-step2-submit'       => 'Аат туһунан сибидиэнньэни бигэргэт',
	'centralauth-merge-step3-title'        => 'Соҕотох ааты оҥоруу',
	'centralauth-merge-step3-detail'       => 'Соҕотох ааты оҥорорго бэлэм буолла. Маннык вики-бырайыактар холбоһуохтара:',
	'centralauth-merge-step3-submit'       => 'Ааттары холбуурга',
	'centralauth-complete'                 => 'Ааттар этэҥҥэ холбостулар!',
	'centralauth-incomplete'               => 'Ааттары холбооһун кыайан түмүктэммэтэ!',
	'centralauth-complete-text'            => 'Билигин ханнык баҕара Викимедиа саайтыгар саҥа ааты оҥорбокко эрэ киирэр кыахтанныҥ. Соҕотох аатынан уонна киирии тылгынан Википедияҕа да, Викитекаҕа да, атын да бырайыактарга ханнык баҕарар тылынан киирэр кыахтааххын.',
	'centralauth-incomplete-text'          => 'Ааттарыҥ холбостохторуна Викимедиа ханнык баҕарар бырайыактарыгар атын ааты оҥорбокко эрэ киирэр кыахтаныаҥ. Соҕотох аат уонна киирии тыл Википедия да, Викитека да, атын да бырайыактар ханнык баҕарар тылынан салааларыгар киирдэххинэ үлэлиэхтэрэ.',
	'centralauth-not-owner-text'           => '«$1» аат аптамаатынан бу аакка «$2» холбонно.

Ити эн буоллаххына ааттары холбооһуну сүрүн аат киирии тылын киллэрэн түмүктүөххүн сөп:',
	'centralauth-notice-dryrun'            => 'Көрдөрөр (демо) режим',
	'centralauth-disabled-dryrun'          => 'Ааттары холбооһун билиһиннэрэр (демо) эрэсиимҥэ үлэлии турар, онон дьиҥнээх холбонуу дьайыылара арахсан тураллар. Баалама.',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|'''Ааттары холбооһун''' туһунан аах]]...''",
	'centralauth-list-home-title'          => 'Сүрүн ("Дьиэ") Биики',
	'centralauth-list-home-dryrun'         => 'Бу аат киирии тыла уонна элэктроннай аадырыһа холбоммут соҕотох аакка туттуллуо. Бу кыттааччы сирэйигэр атын бырайыактарга баар ааттар сигэниэхтэрэ. Кэлин сүрүн (олохтонор) биикигин уларытыаххын сөп.',
	'centralauth-list-attached-title'      => 'Холбоммут ааттар',
	'centralauth-list-attached'            => 'Маннык аат "$1" бу саайтарга холбоммут:',
	'centralauth-list-attached-dryrun'     => 'Бу аат «$1» аллара бэриллибит саайтарга барыларыгар биир аакка холбонуо:',
	'centralauth-list-unattached-title'    => 'Холбоспотох ааттар',
	'centralauth-list-unattached'          => 'Манна көрдөрүллүбүт саайтарга баар "$1" эйиэнэ буолара аптамаатынан бигэргэтиллибэтэ; арааһа киирии тыллара сүрүн ааи киирии тылыттан атыннар быһыылаах:',
	'centralauth-foreign-link'             => '$1 кыттааччы манна: $2',
	'centralauth-finish-title'             => 'Холбооһун түмүктэннэ/түмүктэниитэ',
	'centralauth-finish-text'              => 'Бу ааттар эйиэннэрэ буоллаҕына холбооһуну атын ааттарга киирии тылларын суруйан түмүктүөххүн сөп:',
	'centralauth-finish-password'          => 'Киирии тыл:',
	'centralauth-finish-login'             => 'Кыттааччы',
	'centralauth-finish-send-confirmation' => 'Киирии тылы почтаннан ыыт',
	'centralauth-finish-problems'          => 'Туох эрэ сатамматаҕына, эбэтэр бу ааттар эйиэннэрэ буолбатах буоллаҕына [[meta:Help:Unified login problems|көмөнү көр]]...',
	'centralauth-merge-attempt'            => "'''Холбоммотох ааттар киирии тылларын бэрэбиэркэтэ...'''",
	'centralauth-attach-list-attached'     => 'Соҕотох «$1» аат маннык ааттары холбуур:',
	'centralauth-attach-title'             => 'Бэлиэтэниини бигэргэтии',
	'centralauth-attach-text'              => 'Бу аат соҕотох аат тиһигэр киирэ илик. Если глобальная учётная запись также принадлежит вам, вы можете присоединить данную учётную запись, указав пароль глобальной учётной записи:',
	'centralauth-attach-submit'            => 'Ааты көһөрөргө',
	'centralauth-attach-success'           => 'Бу аат сүрүн аакка холбосто.',
	'centralauth'                          => 'Ааттары холбооһуну салайыы',
	'centralauth-admin-manage'             => 'Кыттааччылар тустарынан сибидиэнньэлэри көрүү/уларытыы',
	'centralauth-admin-username'           => 'Кыттааччы аата:',
	'centralauth-admin-lookup'             => 'Кыттааччы туһунан информацияны уларытыы эбэтэр көрүү',
	'centralauth-admin-permission'         => 'Стюардар эрэ араас дьон ааттарын биир аакка холбуохтарын сөп.',
	'centralauth-admin-unmerge'            => 'Талыллыбыты араарарга',
	'centralauth-admin-merge'              => 'Талыллыбыты холбуурга',
	'centralauth-admin-bad-input'          => 'Холбооһуну сыыһа талбыккын',
	'centralauth-admin-none-selected'      => 'Уларытыллар ааттары талбатаххын.',
	'centralauth-prefs-status'             => 'Сүрүн аат туруга:',
	'centralauth-prefs-not-managed'        => 'Сүрүн (соҕотох) аат туһаныллыбат',
	'centralauth-prefs-unattached'         => 'Бигэргэтиллибэтэх',
	'centralauth-prefs-complete'           => 'Барыта сатанна!',
	'centralauth-prefs-migration'          => 'Көһөрүллэр',
	'centralauth-prefs-count-attached'     => 'Бу аат $1 {{plural:$1|бырайыакка|бырайыактарга}} туһаныллар.',
	'centralauth-prefs-count-unattached'   => 'Эн бигэргэтиллибэтэх {{plural:$1|аатыҥ|ааттарыҥ}} $1 бырайыакка {{plural:$1|хаалла|хааллылар}}.',
	'centralauth-prefs-detail-unattached'  => 'Бу бырайыакка эн сүрүн аатыҥ бигэргэтиллибэтэх.',
	'centralauth-prefs-manage'             => 'Сүрүн ааты салайыы',
	'centralauth-renameuser-abort'         => "<div class=\"errorbox\">\$1 ааты бу бырайыакка уларытар кыах суох, тоҕо диэтэххэ бу аат ''Сүрүн ааты'' кытта холбоно сылдьар.</div>",
);

/** Slovak (Slovenčina)
 * @author Helix84
 * @author Michawiki
 */
$messages['sk'] = array(
	'mergeaccount'                         => 'Stav zjednotenia prihlasovacích účtov',
	'centralauth-desc'                     => 'Zlúčenie účtov na jednotlivých wiki nadácie Wikimedia',
	'centralauth-merge-notlogged'          => 'Prosím, <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} prihláste sa]</span>, aby ste mohli skontrolovať, či sú vaše účty celkom zjednotené.',
	'centralauth-merge-welcome'            => "'''Váš používateľský účet ešte nebol migrovaný na zjednotený prihlasovací systém Wikimedia.'''

Ak si zvolíte, aby vaše účty boli migrované, budete môcť používať rovnaké používateľské meno a heslo na prihlasovanie do každého z wiki projektov nadácie Wikimedia vo všetkých dostupných jazykoch.
To zjednopduší prácu so zdieľanými projektami ako nahrávanie na [http://commons.wikimedia.org/Hlavná_stránka Wikimedia Commons] a zamedzí zmätkom v prípade, že dvaja ľudia majú rovnaké používateľské meno na rôznych projektoch.

Ak niekto iný už zabral vaše používateľské meno na inom projekte, toto ich nenaruší, ale dá vám možnosť dohodnúť sa s ním alebo s administrátorom neskôr.

== Čo sa stane ďalej? ==

Keď si zvolíte, že chcete migráciu na zjednotené prihlasovanie, systém sa pozrie na každý z projektov, ktorý prevádzkujeme -- Wikipedia, Wikinews, Commons, atď. -- a vypíše každý, kde bolo vaše používateľské meno zaregistrované.

Jedna z týchto wiki bude zvolená za „domovskú wiki“ vášho účtu, zvyčajne tá, ktorá je najviac používaná. Ak to nie je wiki, do ktorej sa momentálne prihlasujete, môžete byť predtým, než proces bude pokračovať požiadaný o potvrdenie, že poznáte heslo k danému účtu.

Informácie účtu na domovskej wiki budú porovnané s každým s ostatných účtov a tie, ktorých heslo alebo emailová adresa sa zhodujú alebo neboli použité budú automaticky pripojené k vášmu novému globálnemu účtu.

Tie, ktoré sa nezhodujú budú vynechané, pretože systém nemôže s istotou určiť, či sú vaše účty. Pre tieto účty, ak patria vám, môžete dokončiť pripojenie zadaním správneho prihlasovacieho hesla; ak ich zaregistroval niekto iný, budete mať možnosť zanechať im správu a uvidíte, či niečo vymyslíte.

Nie je ''povinné'' spojiť všetky účty; niektoré môžete nechať oddelené, a budú tak označené.",
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
	'centralauth-not-owner-text'           => 'Používateľské meno „$1“ bolo automaticky priradené vlastníkovi účtu na projekte $2.

Ak ste to vy, môžete dokončiť proces zjednotenia účtov jednoducho napísaním hesla pre uvedený účet sem:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Toto je iba demonštračný režim</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Zjednotenie účtov prebieha momentálne iba v demonštračnom / ladiacom režime, takže samotné operácie spojenia sú vypnuté. Prepáčte!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Prečítajte si viac o '''zjednotení prihlasovacích účtov''']]...''",
	'centralauth-list-home-title'          => 'Domovská wiki',
	'centralauth-list-home-dryrun'         => 'Heslo a emailová adresa nastavená na tejto wiki sa použije pre váš zjednotený účet a na vašu používateľskú stránku tu budú automaticky odkazovať ostatné wiki. Tiež bude možné zmeniť vašu domovskú wiki neskôr.',
	'centralauth-list-attached-title'      => 'Pripojené účty',
	'centralauth-list-attached'            => 'Účty z názvom „$1“ na nasledujúcich projektoch boli automaticaticky zjednotené:',
	'centralauth-list-attached-dryrun'     => 'Účet s názvom „$1“ na kažom z nasledovných projektov bude automaticky pripojený k zjednotenému účtu:',
	'centralauth-list-unattached-title'    => 'Nepripojené účty',
	'centralauth-list-unattached'          => 'Nebolo možné automaticky potvrdiť, že účet „$1“ na nasledujúcich projektoch patrí vám; pravdepodobne má odlišné heslo ako váš primárny účet:',
	'centralauth-foreign-link'             => 'Užívateľ $1 na $2',
	'centralauth-finish-title'             => 'Dokončiť zjednotenie',
	'centralauth-finish-text'              => 'Ak tieto účty naozaj patria vám, môžete skončiť proces zjednotenia jednoducho napísaním hesiel dotyčných účtov:',
	'centralauth-finish-password'          => 'Heslo:',
	'centralauth-finish-login'             => 'Prihlasovacie meno',
	'centralauth-finish-send-confirmation' => 'Zaslať heslo emailom',
	'centralauth-finish-problems'          => 'Máte problém alebo nie ste vlastníkom týchto účtov? [[meta:Help:Unified login problems|Ako hľadať pomoc]]...',
	'centralauth-merge-attempt'            => "'''Kontrolujem poskytnuté heslá voči zostávajúcim zatiaľ nezjednoteným účtom...'''",
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
	'centralauth-prefs-count-attached'     => 'Váš účet je aktívny na $1 {{PLURAL:$1|projekte|projektoch}}.',
	'centralauth-prefs-count-unattached'   => 'Nepotvrdené účty s vašim menom zostávajú na $1 {{PLURAL:$1|projekte|projektoch}}.',
	'centralauth-prefs-detail-unattached'  => 'Nebolo potvrdené, že účet na tomto projekte patrí ku globálnemu účtu.',
	'centralauth-prefs-manage'             => 'Spravovať váš globálny účet',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Nie je možné lokálne premenovať používateľa $1, keďže toto používateľské meno bolo migrované na zjednotený prihlasovací systém.</div>',
);

/** ћирилица (ћирилица)
 * @author Sasa Stefanovic
 */
$messages['sr-ec'] = array(
	'mergeaccount'                         => 'Статус уједињења налога',
	'centralauth-merge-notlogged'          => 'Молимо вас да се <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} пријавите]</span> како бисте проверили да ли је ваш налог спојен успешно.',
	'centralauth-complete'                 => 'Спајање налога завршено!',
	'centralauth-incomplete'               => 'Спајање налога није завршено!',
	'centralauth-complete-text'            => 'Сада се можете пријавити на било који Викимедијин вики сајт без прављењановог налога; исто корисничко име и лозинка ће свугде радити Википедија, Викиречник, Викикњиге, и њихови остали братски пројекти на свим језицима.',
	'centralauth-incomplete-text'          => 'Када једном спојите налог, можете се пријавити на било који Викимедијин вики сајт без прављења; the same username and password will work on Википедија, Викиречник, Викикњиге, и њихови остали братски пројекти на свим језицима.',
	'centralauth-not-owner-text'           => 'Корисничко име "$1" је аутоматски додељено власнику налога на $2.

Уколико сте ово ви, можете једноставно завршити процес спајања уписујући лозинку за налог овде::',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Прочитајте више о '''спајању налога''']]...''",
	'centralauth-list-attached-title'      => 'Приложени налози',
	'centralauth-list-attached'            => 'Налог "$1" на следећим сајтовима је аутоматски спојен:',
	'centralauth-list-unattached'          => 'Налог "$1" се не може аутоматски потврдити да припада вама на следећим сајтовима; највероватније имају различите лозинке него ваш примаран налог:',
	'centralauth-foreign-link'             => 'Корисник $1 на $2',
	'centralauth-finish-title'             => 'Заврши спајање',
	'centralauth-finish-text'              => 'Уколико ови налози припадају вама, можете завршити процес спајања налога уписујући лозинку за остале налоге овде:',
	'centralauth-finish-password'          => 'Лозинка:',
	'centralauth-finish-login'             => 'Пријава',
	'centralauth-finish-send-confirmation' => 'Пошаљи лозинку на е-пошту',
	'centralauth-finish-problems'          => 'Имате проблем, или ви нисте власник осталих налога? [[meta:Help:Unified login problems|Помоћ]]...',
	'centralauth-merge-attempt'            => "'''Провера унете лозинке наспрам осталих налога који још нису спојени......'''",
	'centralauth'                          => 'Администрација спајања налога',
	'centralauth-admin-manage'             => 'Надгледање корисничких података',
	'centralauth-admin-username'           => 'Корисничко име:',
	'centralauth-admin-lookup'             => 'Преглед или измена корисничких података',
	'centralauth-admin-permission'         => 'Само стјуарди могу да споје остале корисничке налоге за њих.',
	'centralauth-admin-unmerge'            => 'Одвоји селектоване',
	'centralauth-admin-merge'              => 'Споји селектоване',
);

$messages['sr-el'] = array(
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

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages['stq'] = array(
	'mergeaccount'                         => 'Stoatus fon ju Benutserkonten-Touhoopefierenge',
	'centralauth-desc'                     => 'Benutserkonten in Wikis fon ju Wikimedia Foundation touhoopefiere',
	'centralauth-merge-notlogged'          => 'Jädden <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} mäldje die an], </span> uum tou wröigjen, of dien Benutserkonten fulboodich touhoopefierd wuuden.',
	'centralauth-merge-welcome'            => "'''Dien Benutserkonto wuude noch nit in dät globoale Wikimedia-Anmälde-System uurfierd.'''
In dän Fal du die foar ne Migration fon dien Benutserkonto äntschatst, wäd et die muugelk, die mäd n gemeensoamen Benutsernoome un Paaswoud in aal Wikimedia-Projekte in aal ferföigboare Sproaken antoumäldjen.
Dit moaket ju Oarbaid in gemeensoam nutsede Projekte eenfacher, t.B. dät Hoochleeden fon Doatäie ätter [http://commons.wikimedia.org/ Wikimedia Commons] un fermit Fersjoon un Konflikte, do der äntstounde konnen, wan two Moanskene dän sälwen Benutsernoome in ferscheedene Projekte benutsje.

Wan uurswäl din Benutsernoome al in n uur Projekt benutset, so be-ienfloudet et dissen nit, man du hääst ju Muugelkhaid, leeter mäd dissen uur Benutser of in Touhoopeoarbaid mäd n Administrator ätter ne Löösenge tou säiken.",
	'centralauth-merge-step1-title'        => 'Ounfang fon ju Benutserkonten-Touhoopefierenge',
	'centralauth-merge-step1-detail'       => 'Dien Paaswoud un dien iendraine E-Mail-Adresse wäd mäd Benutserkonten in do uur Wikis ouglieked, uum Uureenstämmengen tou fienden. Der wäide neen Annerengen foarnuumen, bit du bestäätigest, dät aal gjucht is.',
	'centralauth-merge-step1-submit'       => 'Anmälde-Informatione bestäätigje',
	'centralauth-merge-step2-title'        => 'Bestätigje moor Benutserkonten',
	'centralauth-merge-step2-detail'       => 'Wäkke fon do Benutserkonten kuuden nit automatisk dien Heemat-Wiki toureekend wäide. Wan disse Konton die heere, koast du dät bestäätigje, deertruch dät du dät Paaswoud tou disse Konten ienrakst.',
	'centralauth-merge-step2-submit'       => 'Anmälde-Informatione bestäätigje',
	'centralauth-merge-step3-title'        => 'Moakje globoal Benutserkonto',
	'centralauth-merge-step3-detail'       => 'Du koast nu n globoal Benutserkonto foar do foulgjende Wikis moakje.',
	'centralauth-merge-step3-submit'       => 'Benutserkonten touhoopefiere',
	'centralauth-complete'                 => 'Ju Touhoopefierenge fon do Benutserkonten is fulboodich.',
	'centralauth-incomplete'               => 'Ju Touhoopefierenge fon do Benutserkonten is nit kompläit!',
	'centralauth-complete-text'            => 'Du koast die nu ap älke Wikimedia-Websiede anmäldje sunner n näi Benutserkonto antoulääsen; diesälge Benutsernoome un dätsälge Paaswoud is foar Wikipedia, Wiktionary, Wikibooks un aal Susterprojekte in aal Sproaken gultich.',
	'centralauth-incomplete-text'          => 'Sogau dien Benutserkonten touhoopefierd sunt, koast du die ap älke Wikimedia-Websiede anmäldje sunner n näi Benutserkonto antoulääsen; diesäalge Benutsernoome un dätsälge Paaswoud is foar Wikipedia, Wiktionary, Wikibooks un aal Susterprojekte gultich.',
	'centralauth-not-owner-text'           => 'Die Benutsernoome „$1“ wuude automatisk dän Oaindummer fon dät Benutserkonto ap $2 touwiesd.

Wan dit din Benutsernoome is, koast du ju Touhoopefoatenge fon do Benutserkonten truch Iengoawe fon dät Haud-Paaswoud fon dit Benutserkonto be-eendje.',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Demonstrationsmodus</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Ju Benutserkonto-Touhoopefierenge befint sik apstuuns in n Demonstrations/Failersäik-Modus. Touhoopefierengs-Aktione sunt deaktivierd.',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Informatione uur ju '''Touhoopefierenge fon do Benutserkonten''']] …''",
	'centralauth-list-home-title'          => 'Heemat-Wiki',
	'centralauth-list-home-dryrun'         => 'Dät Paaswoud un ju E-Mail-Adresse, do du in dissen Wiki iendrain hääst, wäd foar ju Touhoopefierenge fon do Benutserkonten ferwoand un ätter dien Benutsersiede wäide automatisk fon do uur Wikis Ferbiendengen moaked. Du koast leeter din Heemat-Wiki noch annerje.',
	'centralauth-list-attached-title'      => 'Touhoopefierde Benutserkonten',
	'centralauth-list-attached'            => 'Do Benutserkonten mäd dän Noome „$1“ ap do foulgjende Projekte wäide automatisk touhoopefierd:',
	'centralauth-list-attached-dryrun'     => 'Do Benutserkonten mäd dän Noome „$1“ ap do foulgjende Projekte wäide automatisk touhoopefierd:',
	'centralauth-list-unattached-title'    => 'Nit touhoopefierde Benutserkonten',
	'centralauth-list-unattached'          => 'Dät Benutserkonto „$1“ kuude foar do foulgjende Projekte nit automatisk as tou die heerend bestäätiged wäide; fermoudelk häd dät n uur Paaswoud as dien primäre Benutserkonto:',
	'centralauth-foreign-link'             => 'Benutser $1 ap $2',
	'centralauth-finish-title'             => 'Touhoopefierenge kloor be-eendje',
	'centralauth-finish-text'              => 'Wan disse Benutserkonten die heere, koast du hier dän Prozess fon ju Benutserkonten-Touhoopefierenge truch ju Iengoawe fon dät Paaswoud foar do uur Benutserkonten kloor be-eendje":',
	'centralauth-finish-password'          => 'Paaswoud:',
	'centralauth-finish-login'             => 'Anmäldenge',
	'centralauth-finish-send-confirmation' => 'Paaswoud uur E-Mail touseende',
	'centralauth-finish-problems'          => 'Hääst du Probleme of heere die disse uur Benutserkonten neit?
[[meta:Help:Unified login problems|Hier finst du Hälpe]] ...',
	'centralauth-merge-attempt'            => "'''Wröigje dät ienroate Paaswoud mäd do uurblieuwene Benutserkonten...'''",
	'centralauth-attach-list-attached'     => 'Dät globoale Benutserkonto mäd dän Noome „$1“ änthaalt do foulgjende Benutserkonten:',
	'centralauth-attach-title'             => 'Benutserkonto bestäätigje',
	'centralauth-attach-text'              => 'Dit Benutserkonto wuude noch nit in n globoal Benutserkonto integrierd. 
	Wan dät globoale Benutserkonto uk fon die is, koast du ju Touhoopefierenge moakje, truch dät du hier dät Paaswoud fon dät globoale Benutserkonto ienrakst:',
	'centralauth-attach-submit'            => 'Benutserkonto integrierje',
	'centralauth-attach-success'           => 'Dät Benutserkonto wuude in dät globoale Benutserkonto integrierd.',
	'centralauth'                          => 'Ferwaltenge fon ju Benutserkonten-Touhoopefierenge.',
	'centralauth-admin-manage'             => 'Benutserdoaten ferwaltje',
	'centralauth-admin-username'           => 'Benutsernoome:',
	'centralauth-admin-lookup'             => 'Benutserdoaten ankiekje of beoarbaidje',
	'centralauth-admin-permission'         => 'Ju Touhoopefierenge fon Benutserkonten fon uur Benutsere kon bloot truch Stewarde geböäre.',
	'centralauth-admin-unmerge'            => 'Uutwoalde Benutserkonten tränne',
	'centralauth-admin-merge'              => 'Uutwoalde Benutserkonten touhoopefiere',
	'centralauth-admin-bad-input'          => 'Uungultige Uutwoal',
	'centralauth-admin-none-selected'      => 'Der wuuden neen tou annerjende Benutserkonten uutwääld.',
	'centralauth-prefs-status'             => 'Benutserkonten-Stoatus:',
	'centralauth-prefs-not-managed'        => 'Der wäd neen touhoopefierd Benutserkonto bruukt.',
	'centralauth-prefs-unattached'         => 'Nit bestäätiged',
	'centralauth-prefs-complete'           => 'Kloor!',
	'centralauth-prefs-migration'          => 'Touhoopefierenge in Oarbaid',
	'centralauth-prefs-count-attached'     => 'Dien Benutserkonto is in $1 {{PLURAL:$1|Projekt|Projekte}} aktiv.',
	'centralauth-prefs-count-unattached'   => 'Dät rakt in $1 {{PLURAL:$1|Projekt|Projekte}} uunbestäätigede Benutserkonten mäd din Noome.',
	'centralauth-prefs-detail-unattached'  => 'Foar dit Projekt lait neen Bestäätigenge foar dät touhoopefierde Benutserkonto foar.',
	'centralauth-prefs-manage'             => 'Beoarbaidje dien touhoopefierd Benutserkonto',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Benutser $1 kon nit lokoal uumebenaamd wäide, deer hie al in dät globoale Benutserkonten-System uurnuumen wuude.</div>',
);

/** Sundanese (Basa Sunda)
 * @author Kandar
 */
$messages['su'] = array(
	'mergeaccount'                         => 'Status ngahijikeun log asup',
	'centralauth-merge-notlogged'          => 'Mangga <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} lebet log]</span> pikeun mariksa anggeus/henteuna rekening anjeun dihijieun.',
	'centralauth-complete'                 => 'Ngahijikeun log asup geus réngsé!',
	'centralauth-incomplete'               => 'Ngahijikeun log asup can anggeus!',
	'centralauth-complete-text'            => 'Ayeuna anjeun bisa asup log ka loka wiki Wikimédia tanpa kudu nyieun rekening anyar; ladihan pamaké katut sandina bisa dipaké dina Wikipédia, Wikikamus, Wikipustaka, sarta proyék sawargina dina basa séjén.',
	'centralauth-incomplete-text'          => 'Mun log asupna geus dihijikeun, anjeun bakal bisa asup log ka loka wiki Wikimédia mana waé tanpa kudu nyieun rekening anyar; landihan pamaké katut sandina bakal bisa dipaké dina Wikipédia, Wikikamus, Wikipustaka, sarta proyék sawargina dina basa séjén.',
	'centralauth-not-owner-text'           => 'Landihan pamaké "$1" geus diajangkeun ka rekening di $2.

Mun éta téh anjeun, anjeun bisa nganggeuskeun prosés ngahijikeun log asup ku cara ngetikkeun sandi master pikeun éta rekening di dieu:',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Baca lengkepna ngeunaan '''log asup nu dihijikeun''']]...''",
	'centralauth-foreign-link'             => 'Pamaké $1 di $2',
	'centralauth-finish-title'             => 'Réngsé ngahijikeun',
	'centralauth-finish-text'              => 'Mun rekening ieu bener boga anjeun, mangga réngsékeun prosés ngahijikeun log asup ku cara ngasupkeun sandi rekening lianna di dieu:',
	'centralauth-finish-password'          => 'Sandi:',
	'centralauth-finish-login'             => 'Asup log',
	'centralauth-finish-send-confirmation' => 'Kirimkeun sandi kana surélék',
	'centralauth-finish-problems'          => 'Aya masalah? Teu boga rekening lianna ieu? [[meta:Help:Unified login problems|Ménta pitulung]]...',
	'centralauth-merge-attempt'            => "'''Ngakurkeun sandi nu disadiakeun jeung rekening nu can dihijikeun...'''",
	'centralauth'                          => 'Administrasi log asup nu dihijikeun',
	'centralauth-admin-manage'             => 'Kokolakeun data pamaké',
	'centralauth-admin-username'           => 'Landihan pamaké:',
	'centralauth-admin-lookup'             => 'Témbongkeun atawa robah data pamaké',
	'centralauth-admin-permission'         => 'Nu bisa ngahijikeun rekening batur mah ngan steward.',
	'centralauth-admin-unmerge'            => 'Pisahkeun nu dipilih',
	'centralauth-admin-merge'              => 'Hijikeun nu dipilih',
);

/** Swedish (Svenska)
 * @author Lejonel
 */
$messages['sv'] = array(
	'mergeaccount'                         => 'Status för förenad inloggning',
	'centralauth-desc'                     => 'Sammanfogar användarkonton på Wikimedia Foundations olika wikier till ett konto',
	'centralauth-merge-notlogged'          => 'Du måste <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} logga in]</span> för att se om dina konton har blivit fullständigt förenade.',
	'centralauth-merge-welcome'            => "'''Ditt konto har ännu inte flyttats över till Wikimedias förenade inloggningssystem.'''

Om du väljer att förena dina konton, så kommer du att kunna använda samma användarnamn och lösenord för att logga in på alla språkversioner av alla Wikimedias projekt.
På så sätt blir det enklare att arbeta på gemensamma projekt, till exempel att ladda upp filer till [http://commons.wikimedia.org/ Wikimedia Commons]. Det undviker också förvirring och andra problem som kan uppstå när olika personer har samma användarnamn på olika projekt.

Om någon annan redan har tagit ditt användarnamn på en annan sajt så påverkar det här inte henne nu, men du kommer att få möjlighet att reda ut det med henne eller med en administratör senare.",
	'centralauth-merge-step1-title'        => 'Påbörja förening av konton',
	'centralauth-merge-step1-detail'       => 'Ditt lösenord och din e-postadress kommer kontrolleras mot användarkonton på andra wikis för att bekräfta att de stämmer överens. Inga ändringar kommer genomföras innan du bekräftar att allting ser riktigt ut.',
	'centralauth-merge-step1-submit'       => 'Bekräfta lösenord',
	'centralauth-merge-step2-title'        => 'Bekräfta fler konton',
	'centralauth-merge-step2-detail'       => 'Några av konton kunde inte automatiskt matchas med kontot på den wiki som utsetts till hemwiki. Om dessa konton tillhör dig, så kan du bekräfta det genom att ange lösenorden för dem.',
	'centralauth-merge-step2-submit'       => 'Bekräfta lösenord',
	'centralauth-merge-step3-title'        => 'Skapa förenat konto',
	'centralauth-merge-step3-detail'       => 'Du kan nu skapa ditt förenade användarkonto, med följande wikis anslutna:',
	'centralauth-merge-step3-submit'       => 'Förena konton',
	'centralauth-complete'                 => 'Föreningen av inlggoning är fullständig!',
	'centralauth-incomplete'               => 'Föreningen av inloggning är inte fullständig!',
	'centralauth-complete-text'            => 'Du kan nu logga in på alla Wikimedias wikis utan att skapa nya konton. Samma användarnamn och lösenord kommer fungera på alla språkversioner av Wikipedia, Wiktionary, Wikibooks och deras systerprojekt.',
	'centralauth-incomplete-text'          => 'När din inloggning är förenad kommer du kunna logga in på alla Wikimedias wikis utan att skapa nya konton. Samma användarnamn och lösenord kommer fungera på alla språkversioner av Wikipedia, Wiktionary, Wikibooks och deras systerprojekt.',
	'centralauth-not-owner-text'           => 'Användarnamnet "$1" tilldelades automatiskt ägaren av kontot på $2.

Om du är ägaren av det kontot, så kan du slutföra föreningsprocessen genom att ange lösenordet för det kontot här:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Endast demonstration</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Förening av konton körs för närvarande i demonstrations- eller debugläge, så funktionerna som gör kontosammanslagningar är avaktiverade.',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Läs mer om '''förenad inloggning''']]...''",
	'centralauth-list-home-title'          => 'Hemwiki',
	'centralauth-list-home-dryrun'         => 'Ditt lösenord och din e-postadress på denna wiki kommer att användas för ditt förenade konto. Din användarsida på den wikin kommer automatiskt att länkas till från andra wikis. Du kommer senare kunna ändra wiki som är din hemwiki.',
	'centralauth-list-attached-title'      => 'Anslutna konton',
	'centralauth-list-attached'            => 'Konton med namnet "$1" på följade sajter har automatiskt anslutits till det förenade kontot:',
	'centralauth-list-attached-dryrun'     => 'Konton med namnet "$1" på följande sajter kommer automatiskt anslutas till det förenade kontot:',
	'centralauth-list-unattached-title'    => 'Ej anslutna konton',
	'centralauth-list-unattached'          => 'På följande sajter kunde det inte automatiskt bekräftas att konton "$1" tillhör dig; det beror troligen på att de har ett annat lösenord en ditt huvudkonto:',
	'centralauth-foreign-link'             => 'Användare $1 på $2',
	'centralauth-finish-title'             => 'Slutför förening',
	'centralauth-finish-text'              => 'Om dessa konton tillhör dig, så kan du slutföra föreningsprocessen genom att ange lösenorden för de andra kontona här:',
	'centralauth-finish-password'          => 'Lösenord:',
	'centralauth-finish-login'             => 'Logga in',
	'centralauth-finish-send-confirmation' => 'Skicka lösenord via e-post',
	'centralauth-finish-problems'          => 'Har du problem, eller är du inte är ägare till de andra kontona? Se [[meta:Help:Unified login problems|hjälpsidan]]...',
	'centralauth-merge-attempt'            => "'''Kontrollerar det angivna lösenordet mot återstående oanslutna konton...'''",
	'centralauth-attach-list-attached'     => 'Det förenade kontot med namnet "$1" innhåller följande konton:',
	'centralauth-attach-title'             => 'Bekräfta konto',
	'centralauth-attach-text'              => 'Detta konto har ännu inte anslutits till det förenade kontot. Om det globala kontot är ditt, så kan du ansluta det här kontot genom att ange det globala kontots lösenord:',
	'centralauth-attach-submit'            => 'Anslut konto',
	'centralauth-attach-success'           => 'Kontot har anslutits till det förenade kontot.',
	'centralauth'                          => 'Administration av förenad inloggning',
	'centralauth-admin-manage'             => 'Hantera användardata',
	'centralauth-admin-username'           => 'Användarnamn:',
	'centralauth-admin-lookup'             => 'Visa eller redigera användardata',
	'centralauth-admin-permission'         => 'Endast stewarder kan sammanfoga andra användares konton åt dem.',
	'centralauth-admin-unmerge'            => 'Åtskilj valda',
	'centralauth-admin-merge'              => 'Sammanfoga valda',
	'centralauth-admin-bad-input'          => 'Ogiltig val för sammanfogning',
	'centralauth-admin-none-selected'      => 'Har inte valt några konton att modifiera.',
	'centralauth-prefs-status'             => 'Status för globalt konto:',
	'centralauth-prefs-not-managed'        => 'Använder inte förenat konto',
	'centralauth-prefs-unattached'         => 'Obekräftat',
	'centralauth-prefs-complete'           => 'Allt är i ordning!',
	'centralauth-prefs-migration'          => 'Anslutning pågår',
	'centralauth-prefs-count-attached'     => 'Ditt konto är aktivt på $1 projekt.',
	'centralauth-prefs-count-unattached'   => 'Obekräftade konton med ditt namn finns fortfarande på $1 projekt.',
	'centralauth-prefs-detail-unattached'  => 'Det är inte bekräftat att det här kontot tillhör det globala kontot.',
	'centralauth-prefs-manage'             => 'Hantera ditt globala konto',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Det går inte att döpa om användare $1 lokalt eftersom användarnamnet har anslutits till det förenade inloggningssystemet.</div>',
);

/** Telugu (తెలుగు)
 * @author Veeven
 * @author Chaduvari
 * @author వైజాసత్య
 * @author Mpradeep
 */
$messages['te'] = array(
	'mergeaccount'                         => 'ప్రవేశపు ఏకీకరణ స్థితి',
	'centralauth-desc'                     => 'వికీమీడియా ఫౌండేషన్ వికీలన్నింటిలో ఖాతాని విలీనం చేయి',
	'centralauth-merge-notlogged'          => 'మీ ఖాతాలు పూర్తిగా విలీనమయినవని సరిచూసుకునేందుకు, దయచేసి <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} లోనికి ప్రవేశించండి]</span>.',
	'centralauth-merge-welcome'            => "'''మీ వాడుకరి ఖాతా ఇంకా వికీమీడియా యొక్క ఏకీకృత ప్రవేశపు వ్యవస్థ లోనికి విలీనం కాలేదు.'''

మీ ఖాతాలని విలీనం చేస్తే, వికీమీడియా యొక్క అన్ని ప్రాజెక్టు వికీలలోనికి అందుబాటులో ఉన్న అన్ని భాషలలోనికి ఒకే వాడుకరి పేరు మరియు సంకేతపదం ఉపయోగించి మీరు ప్రవేశించవచ్చు.

అందువల్ల [http://commons.wikimedia.org/ వికీమీడియా కామన్స్]లోనికి ఎగుమతి చేయడం లాంటి బహుళ ప్రాజెక్టులలో పనులు సులువౌతాయు, మరియు ఇది ఇద్దరు వ్యక్తులు వేర్వేరు ప్రాజెక్టులలో ఒకే వాడుకరి పేరు ఎంచుకోవడం వల్ల వచ్చే అయోమయాన్ని మరియు సంఘర్షణని నివారిస్తుంది.

మీ వాడుకరి పేరుని ఇతర సైట్లో వేరెవరైనా ఇప్పటికే తీసేసుకునివుంటే ఇది వారిని కదపదు, కానీ తర్వాత వారితోనూ లేదా నిర్వాహకులతోనూ పరిష్కరించుకోవడానికి అవకాశమిస్తుంది.",
	'centralauth-merge-step1-title'        => 'ప్రవేశ ఏకీకరణని మొదలుపెట్టు',
	'centralauth-merge-step1-detail'       => 'మీ సంకేతపదం మరియు నమోదైన ఈ-మెయిల్ చిరునామాలు సరిపోతున్నాయని నిర్ధారించడానికి ఇతర వికీలలో ఉన్న ఖాతాలతో పోల్చిచూస్తాం. అన్నీ సరిగానే ఉన్నాయని మీరు నిర్ధారించే వరకు మార్పులేమీ చెయ్యబోము.',
	'centralauth-merge-step1-submit'       => 'ప్రవేశపు సమాచారాన్ని నిర్ధారించండి',
	'centralauth-merge-step2-title'        => 'మరిన్ని ఖాతాలను నిర్ధారించండి',
	'centralauth-merge-step2-detail'       => 'కొన్ని ఖాతాలను లక్ష్యిత ప్రధాన వికీతో ఆటోమేటిగ్గా సరిపోల్చలేకపోయాం. ఈ ఖాతాలు మీకు సంబంధించినవైతే, వాటి సంకేతపదాలను ఇవ్వడం ద్వారా అవి మీవే అని నిర్ధారించవచ్చు.',
	'centralauth-merge-step2-submit'       => 'ప్రవేశపు సమాచారాన్ని నిర్ధారించండి',
	'centralauth-merge-step3-title'        => 'ఏకీకృత ఖాతాని సృష్టించండి',
	'centralauth-merge-step3-detail'       => 'ఈ క్రింద పేర్కొన్న వికీల జోడింపుతో, మీ ఏకీకృత ఖాతాని సృష్టించడానికి సిద్ధంగా ఉన్నారు.',
	'centralauth-merge-step3-submit'       => 'ఖాతాలను ఏకీకరించు',
	'centralauth-complete'                 => 'ప్రవేశపు ఏకీకరణ పూర్తయ్యింది!',
	'centralauth-incomplete'               => 'ప్రవేశపు ఏకీకరణ పూర్తి కాలేదు!',
	'centralauth-complete-text'            => 'కొత్త ఖాతా సృష్టించుకోకుండానే మీరిప్పుడు ఏదైనా వికీమీడియా వికీ లోనికి ప్రవేశించవచ్చు; అదే వాడుకరిపేరు మరియు సంకేతపదం వికీపీడియా, విక్షనరీ, వికీపుస్తకాలు, మరియు అన్ని భాషలలోని వాటి సోదర ప్రాజెక్టులలోనూ పనిచేస్తాయి.',
	'centralauth-incomplete-text'          => 'ఒక్కసారి ప్రవేశం ఏకీకృతమయ్యాక, మీరు ఏదైనా వికీమీడియా వికీ సైటు లోనికి కొత్త ఖాతా సృష్టించనవసరం లేకుండానే ప్రవేశించగల్గుతారు; వికీపీడియా, విక్షనరీ, వికీపుస్తకాలు, మరియు అన్ని భాషలలోనూ వాటి సోదర ప్రాజెక్టులలోనూ ఒకే వాడుకరి పేరు మరియు సంకేతపదం పనిచేస్తాయి.',
	'centralauth-not-owner-text'           => '"$1" అన్న వాడుకరి పేరు $2లోని ఖాతా యజమానికి ఆటోమేటిగ్గా ఆపాదించివుంది.

అది మీరే అయితే, ఆ ఖాతాకి ప్రధాన సంకేతపదాన్ని ఇక్కడ ఇవ్వడం ద్వారా తేలిగ్గా ప్రవేశపు ఏకీకృత ప్రక్రియని ముగించవచ్చు:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>డెమో మాత్రమే</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'ఖాతా ఏకీకరణ ప్రస్తుతం డెమో / పరీక్షా స్థితిలో ఉంది, కనుక వాస్తవ విలీన కలాపాలని అచేతనం చేసాము. క్షమించండి!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|'''ఏకీకృత ప్రవేశం''' గురించి మరింత తెలుసుకోండి]]...''",
	'centralauth-list-home-title'          => 'ప్రధాన వికీ',
	'centralauth-list-home-dryrun'         => 'ఈ వికీలోని మీ సంకేతపదం మరియు ఈ-మెయిల్ చిరునామాని మీ ఏకీకృత ఖాతాకి ఉపయోగిస్తాం, మరియు అన్ని వికీలనుండి ఇక్కడి మీ వాడుకరి పేజీకి ఆటోమెటిగ్గా లింకువేస్తాం. మీ ప్రధాన వికీ ఏదో మీరు తర్వాత మార్చుకోవచ్చు.',
	'centralauth-list-attached-title'      => 'జోడించిన ఖాతాలు',
	'centralauth-list-attached'            => 'క్రింది సైట్లలోని "$1" అనే పేరుగల ఖాతాని ఏకీకృత ఖాతాకి ఆటోమెటిగ్గా జోడించాం:',
	'centralauth-list-attached-dryrun'     => 'ఈ క్రింద పేర్కొన్న సైట్లలోని "$1" పేరున్న ఖాతాలన్నీ ఆటోమేటిగ్గా ఏకీకృత ఖాతాకి జోడించబడతాయి:',
	'centralauth-list-unattached-title'    => 'జోడించని ఖాతాలు',
	'centralauth-list-unattached'          => 'ఈ క్రింద పేర్కొన్న సైట్లలో "$1" అనే ఖాతా మీదే అని నిర్ధారించలేకున్నాం; బహుశా వీటిల్లో సంకేతపదం మీ ప్రధాన ఖాతాది కాక వేరేది అయివుండవచ్చు:',
	'centralauth-foreign-link'             => '$2లో వాడుకరి $1',
	'centralauth-finish-title'             => 'విలీనం ముగించు',
	'centralauth-finish-text'              => 'ఈ ఖాతాలు మీవే అయితే, వాటి సంకేతపదాలను ఇక్కడ ఇవ్వడం ద్వారా ప్రవేశపు ఏకీకరణ ప్రక్రియని ముగించవచ్చు:',
	'centralauth-finish-password'          => 'సంకేతపదం:',
	'centralauth-finish-login'             => 'ప్రవేశించు',
	'centralauth-finish-send-confirmation' => 'సంకేతపదాన్ని ఈ-మెయిల్లో పంపించు',
	'centralauth-finish-problems'          => 'సమస్య ఉందా, లేదా ఈ ఇతర ఖాతాలు మీవి కాదా? [[meta:Help:Unified login problems|సహాయం పొందడం ఎలా]]...',
	'centralauth-merge-attempt'            => "'''మీరిచ్చిన సంకేతపదాన్ని ఇంకా విలీనంకాని ఖాతాలలో సరిచూస్తున్నాం...'''",
	'centralauth-attach-list-attached'     => '"$1" అనే పేరుగల ఏకీకృత ఖాతా ఈ క్రింద పేర్కొన్న ఖాతాలను కలిగివుంది:',
	'centralauth-attach-title'             => 'ఖాతాని నిర్ధారించు',
	'centralauth-attach-text'              => 'ఈ ఖాతా ఇంకా ఏకీకృత ఖాతాలో విలీనం కాలేదు. క్రింద పేర్కొన్న ఏకీకృత ఖాతా మీదే అయితే, దాని సంకేతపదాన్ని ఇచ్చి ఈ ఖాతాని విలీనం చేసుకోవచ్చు:',
	'centralauth-attach-submit'            => 'ఖాతాను బదిలీ చెయ్యండి',
	'centralauth-attach-success'           => 'ఈ ఖాతా ఏకీకృత ఖాతాకు బదిలీ చేయబడినది',
	'centralauth'                          => 'ఏకీకృత ప్రవేశపు నిర్వహణ',
	'centralauth-admin-manage'             => 'వాడుకరి డాబాను నిర్వహించు',
	'centralauth-admin-username'           => 'వాడుకరి పేరు:',
	'centralauth-admin-lookup'             => 'వాడుకరి సమాచారాన్ని చూడండి లేదా మార్చండి',
	'centralauth-admin-permission'         => 'స్టీవార్డులు మాత్రమే ఇతరుల ఖాతాలను విలీనం చెయ్యగలరు.',
	'centralauth-admin-unmerge'            => 'ఎంచుకున్నవాటి విలీనాన్ని రద్దుచెయ్యి',
	'centralauth-admin-merge'              => 'ఎంచుకున్నవాటిని విలీనం చేయి',
	'centralauth-admin-bad-input'          => 'తప్పుడు విలీనపు ఎంపిక',
	'centralauth-admin-none-selected'      => 'మార్చడానికి ఖాతాలేమీ ఎంచుకోలేదు.',
	'centralauth-prefs-status'             => 'గ్లోబల్ ఖాతా స్థితి:',
	'centralauth-prefs-not-managed'        => 'ఏకీకృత ఖాతాని వాడటం లేదు',
	'centralauth-prefs-unattached'         => 'నిర్థారణకాలేదు',
	'centralauth-prefs-complete'           => 'అన్నీ సరిపోయాయి!',
	'centralauth-prefs-migration'          => 'బదిలీలో ఉన్నవి',
	'centralauth-prefs-count-attached'     => 'మీ ఖాతా $1 ప్రాజెక్టు {{plural:$1|సైటు|సైట్ల}}లో సచేతనంగా ఉంది.',
	'centralauth-prefs-count-unattached'   => '$1 {{plural:$1|ప్రాజెక్టు|ప్రాజెక్టుల}}లో మీ పేరుతో ఉండి నిర్ధారణకాని ఖాతాలు.',
	'centralauth-prefs-detail-unattached'  => 'ఈ ప్రాజెక్టు సైటు గ్లోబల్ ఖాతాకు చెందినదని నిర్ధారణ కాలేదు.',
	'centralauth-prefs-manage'             => 'మీ గ్లోబల్ ఖాతాను నిర్వహించుకోండి',
	'centralauth-renameuser-abort'         => '<div class="errorbox">$1 అనే వాడుకరి పేరు ఏకీకృత ప్రవేశపు వ్యవస్థలో నిలీనమైనందున, స్థానికంగా ఆ పేరుని మార్చలేరు.</div>',
);

/** Tajik (Тоҷикӣ)
 * @author Ibrahim
 */
$messages['tg'] = array(
	'mergeaccount'                         => 'Вазъияти якка кардани ҳисобҳо',
	'centralauth-desc'                     => 'Якка кардани ҳисобҳои корбарӣ дар викиҳои Бунёди Викимедиа',
	'centralauth-merge-step1-title'        => 'Оғози якка кардани ҳисобҳои корбарӣ',
	'centralauth-merge-step1-submit'       => 'Тасдиқи иттилооти вуруд ба систем',
	'centralauth-merge-step2-title'        => 'Тасдиқи ҳисобҳои корбарии бештар',
	'centralauth-merge-step2-submit'       => 'Тасдиқи иттилооти вуруд ба систем',
	'centralauth-merge-step3-title'        => 'Эҷоди ҳисоби муштарак',
	'centralauth-merge-step3-detail'       => 'Шумо омодаед ҳисоби муштараки худро дар викиҳои зерин эҷод кунед:',
	'centralauth-merge-step3-submit'       => 'Якка кардани ҳисобҳо',
	'centralauth-complete'                 => 'Эҷод кардани ҳисобҳо комил шуд!',
	'centralauth-incomplete'               => 'Эҷод кардани ҳисобҳо комил нашуд!',
	'centralauth-complete-text'            => 'Акнун шумо метавонед дар ҳар як аз викиҳои Викимедиа ворид шавид бидуни сохтани ҳисоби ҷадид; ҳисоби корбарии муштараки шумо дар Википедиа, Викилуғат, Викикитобҳо ва дигар лоиҳаҳои Викимедиа дар тамоми забонҳо кор хоҳад кард.',
	'centralauth-incomplete-text'          => 'Дар ҳоле, ки ҳисоби корбарии шумо якка шавад, шумо қодир хоҳед буд дар ҳар яки аз викиҳои Викимедиа бидуни эҷоди ҳисоби ҷадид вуруд кунед. Як ҳисоб ва калима убур дар Википедиа, Викилуғат, Викикитобҳо ва дигар лоиҳаҳои Википедиа дар тамоми забонҳо кор хоҳад кард.',
	'centralauth-not-owner-text'           => 'Ҳисоби корбарии "$1" ба таври худкор ба соҳиби ҳисоби корбарӣ дар $2 ихтисос дода шуд.

Агар шумо соҳиби ин ҳисоб ҳастед, шумо метавонед раванди якка кардан ҳисобҳои корбариро бо ворид кардани калимаи убури саросарӣ дар ин ҷо поён бирасонед:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Фақат тарзи намоишӣ</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Сомонаи якка кардани ҳисобҳои корбарӣ дар ҳоли ҳозир ба таври озмоишӣ ва барои рафъи эрод фаъол аст, бинобар ин якка кардани ҳисобҳои воқеӣ ҳануз фаъол нест. Бубахшед!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Иттилооти бештар дар бораи '''ҳисоби корбарии муштарак''']]...''",
	'centralauth-list-home-title'          => 'Викии аслӣ',
	'centralauth-list-home-dryrun'         => 'Калимаи убур ва нишонаи почтаи электронии интихоб шуда дар ин вики барои ҳисоби корбарии муштараки шумо мавриди истифода қарор хоҳад гирифт, ва ҳисоби корбарии шумо дар дигар викиҳо ба таври худкор ба ин вики пайванд хоҳад шуд. Баъдан шумо метавонед викии аслии худро тағйир диҳед.',
	'centralauth-list-attached-title'      => 'Ҳисобҳои корбарии пайваста',
	'centralauth-list-attached'            => 'Ҳисоби корбарии "$1" дар ҳар як аз лоиҳаҳои зерин ба таври худкор ба ҳисоби корбарии муштарак пайваста  аст:',
	'centralauth-list-attached-dryrun'     => 'Ҳисоби корбарии "$1" дар ҳар як аз лоиҳаҳои зерин ба таври худкор ба ҳисоби корбарии муштарак шумо пайваста хоҳад шуд:',
	'centralauth-list-unattached-title'    => 'Ҳисобҳои ҷудо карда шуда',
	'centralauth-list-unattached'          => 'Ҳисоби корбарии "$1"ро дар сомонаҳои зерин наметавон ба таври худкор мутаалиқ ба шумо донист; бо эҳтимоли зиёд онҳо калимаи убури дигар аз ҳисоби корбарии аслии шумо доранд:',
	'centralauth-foreign-link'             => 'Ҳисоби корбари $1 дар $2',
	'centralauth-finish-title'             => 'Хотимаи идғом',
	'centralauth-finish-text'              => 'Агар шумо соҳиби ин ҳисобҳо ҳастед, метавонед раванди якка кардани ҳисобҳои корбариро бо ворид кардани калимаи убури саросарӣ дар инҷо ба поён бирасонед:',
	'centralauth-finish-password'          => 'Калимаи убур:',
	'centralauth-finish-login'             => 'Вуруд ба систем',
	'centralauth-finish-send-confirmation' => 'Калимаи убур ба почтаи электронӣ фиристода шавад',
	'centralauth-finish-problems'          => 'Дучори мушкили шудаед ё соҳиби ҳисобҳои корбарии дигаре, ки феҳрист шудаанд нестед? [[meta:Help:Unified login problems|Роҳнаморо бихонед]]...',
	'centralauth-merge-attempt'            => "'''Мутобиқат додани калимаи убури ворид шуда бо ҳисобҳои идғом нашудаи боқимонда...'''",
	'centralauth-attach-list-attached'     => 'Ҳисоби корбарии муштарак "$1" шомили ҳисобҳои корбарии зерин мешавад:',
	'centralauth-attach-title'             => 'Тасдиқи ҳисоби корбарӣ',
	'centralauth-attach-text'              => 'Ин ҳисоби корбарӣ ҳанӯз ба ҳисоби корбарии муштарак интиқол дода нашудааст. Агар ҳисоби корбарии муштарак мутаалиқ ба шумо аст, ин ҳисобро ҳам метавонед бо ворид кардани калимаи убури он ба ҳисоби корбарии муштарак якка кунед:',
	'centralauth-attach-submit'            => 'Интиқоли ҳисоби корбарӣ',
	'centralauth-attach-success'           => 'Ҳисоби корбарӣ ба ҳисоби корбарии муштарак интиқол дода шуд.',
	'centralauth'                          => 'Мудирияти ҳисоби корбарии муштарак',
	'centralauth-admin-manage'             => 'Мудирияти иттилооти корбар',
	'centralauth-admin-username'           => 'Номи корбарӣ:',
	'centralauth-admin-lookup'             => 'Мушоҳида ё тағйири иттилооти корбарӣ',
	'centralauth-admin-unmerge'            => 'Аз идғом озод кардани маводи интихобшуда',
	'centralauth-admin-merge'              => 'Интихоби идғом',
	'centralauth-admin-bad-input'          => 'Интихоби ғайри миҷози идғом',
	'centralauth-admin-none-selected'      => 'Ҳеҷ ҳисоби корбари барои тағйир интихоб нашудааст.',
	'centralauth-prefs-status'             => 'Вазъияти ҳисоби корбари муштарак:',
	'centralauth-prefs-not-managed'        => 'Ҳисоби корбарии муштарак мавриди истифода нест',
	'centralauth-prefs-unattached'         => 'Тасдиқнашуда',
	'centralauth-prefs-complete'           => 'Ҳама чиз мураттаб аст!',
	'centralauth-prefs-migration'          => 'Дар ҳоли интиқол',
	'centralauth-prefs-count-attached'     => 'Ҳисоби корбарии шумо дар $1 {{plural:$1|лоиҳа|лоиҳаҳо}} фаъол аст.',
	'centralauth-prefs-manage'             => 'Мудирияти ҳисоби корбарии муштарак',
);

/** Turkish (Türkçe)
 * @author Karduelis
 * @author Srhat
 * @author Erkan Yilmaz
 */
$messages['tr'] = array(
	'centralauth-finish-password'          => 'Parola:',
	'centralauth-finish-login'             => 'Oturum açma',
	'centralauth-finish-send-confirmation' => 'E-posta parolası',
	'centralauth-attach-title'             => 'Hesabı doğrula',
	'centralauth-admin-username'           => 'Kullanıcı:',
	'centralauth-prefs-unattached'         => 'Doğrulanmamış',
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 */
$messages['vi'] = array(
	'mergeaccount'                         => 'Tình trạng hợp nhất tài khoản',
	'centralauth-desc'                     => 'Hợp nhất tài khoản tại các wiki của Quỹ Wikimedia',
	'centralauth-merge-notlogged'          => 'Xin hãy <span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special:MergeAccount}} đăng nhập]</span> để kiểm tra các tài khoản của bạn được hợp nhất hay không.',
	'centralauth-merge-welcome'            => "'''Tài khoản của bạn chưa được đổi qua hệ thống tài khoản hợp nhất của Wikimedia.'''

Sau khi chọn hợp nhất các tài khoản, bạn có thể đăng nhập vào các phiên bản ngôn ngữ của các dự án Wikimedia dùng cùng một tài khoản. Làm vầy thì dễ đóng góp vào các dự án dùng chung, thí dụ tải lên [http://commons.wikimedia.org/ Wikimedia Commons], và tránh sự nhầm lẫn hay mâu thuẫn trong trường hợp hai người chọn cùng tên hiệu ở hai dự án khác nhau.

Nếu ai đã lấy tên hiệu của bạn tại website khác, việc hợp nhất các tài khoản không sẽ thay đổi tài khoản họ có hiện nay, nhưng sẽ dẫn đến cơ hội để giải quyết sự mâu thuẫn với họ hay một quản lý viên về sau.",
	'centralauth-merge-step1-title'        => 'Bắt đầu hợp nhất tài khoản',
	'centralauth-merge-step1-submit'       => 'Xác nhận thông tin tài khoản',
	'centralauth-merge-step2-title'        => 'Xác nhận thêm tài khoản',
	'centralauth-merge-step2-submit'       => 'Xác nhận thông tin tài khoản',
	'centralauth-merge-step3-title'        => 'Mở tài khoản hợp nhất',
	'centralauth-merge-step3-detail'       => 'Bạn sẵn sàng mở tài khoản hợp nhất, bao gồm các wiki sau:',
	'centralauth-merge-step3-submit'       => 'Hợp nhất các tài khoản',
	'centralauth-complete'                 => 'Xong hợp nhất các tài khoản!',
	'centralauth-incomplete'               => 'Chưa hợp nhất tài khoản xong!',
	'centralauth-complete-text'            => 'Bây giờ bạn có thể đăng nhập vào các website trực thuộc Wikimedia, không cần mở tài khoản riêng; các phiên bản ngôn ngữ của Wikipedia, Wiktionary, Wikibooks, và các dự án liên quan đều nhận tên hiệu và mật khẩu của bạn.',
	'centralauth-incomplete-text'          => 'Sau khi hợp nhất các tài khoản, có thể đăng nhập vào các website trực thuộc Wikimedia, không cần mở tài khoản riêng; các phiên bản ngôn ngữ của Wikipedia, Wiktionary, Wikibooks, và các dự án liên quan đều nhận tên hiệu và mật khẩu của bạn.',
	'centralauth-not-owner-text'           => 'Phần mềm đã tự động chỉ định tên hiệu “$1” cho người cùng tên hiệu ở $2.

Nếu bạn là “$1”, có thể hợp nhất các tài khoản xong bằng cách đưa mật khẩu chủ của tài khoản đó vào đây:',
	'centralauth-notice-dryrun'            => "<div class='successbox'>Mới chỉ đang thử</div><br clear='all'/>",
	'centralauth-disabled-dryrun'          => 'Rất tiếc, mới chỉ đang thử chức năng hợp nhất tài khoản, nên thực sự chưa có thể hợp nhất.',
	'centralauth-readmore-text'            => ":''Đọc thêm về '''[[m:Help:Unified login|tài khoản hợp nhất]]'''…''",
	'centralauth-list-home-title'          => 'Wiki chính',
	'centralauth-list-attached-title'      => 'Các tài khoản được gắn',
	'centralauth-list-attached'            => 'Tài khoản “$1” ở các website sau được tự động gắn vào tài khoản hợp nhất:',
	'centralauth-list-attached-dryrun'     => 'Tài khoản “$1” ở các website sau sẽ được tự động gắn vào tài khoản hợp nhất:',
	'centralauth-list-unattached-title'    => 'Các tài khoản chưa gắn',
	'centralauth-foreign-link'             => 'Thành viên $1 tại $2',
	'centralauth-finish-title'             => 'Hợp nhất xong',
	'centralauth-finish-text'              => 'Nếu các tài khoản này của bạn, chỉ việc đưa vào mật khẩu của các tài khoản ở dưới để hợp nhất tài khoản:',
	'centralauth-finish-password'          => 'Mật khẩu:',
	'centralauth-finish-login'             => 'Đăng nhập',
	'centralauth-finish-send-confirmation' => 'Gửi mật khẩu bằng thư điện tử',
	'centralauth-finish-problems'          => "Trục trặc khi đăng nhập, hay những tài khoản kia không của bạn? '''[[meta:Help:Unified login problems|Trợ giúp…]]'''",
	'centralauth-merge-attempt'            => "'''Đang so sánh những mật khẩu mà bạn đưa vào với các tài khoản chưa được hợp nhất…'''",
	'centralauth-attach-list-attached'     => 'Tài khoản hợp nhất dưới tên “$1” bao gồm các tài khoản sau:',
	'centralauth-attach-title'             => 'Xác nhận tài khoản',
	'centralauth-attach-submit'            => 'Chuyển tài khoản',
	'centralauth-attach-success'           => 'Tài khoản này được chuyển qua tài khoản hợp nhất.',
	'centralauth'                          => 'Quản lý các tài khoản hợp nhất',
	'centralauth-admin-manage'             => 'Quản lý dữ liệu người dùng',
	'centralauth-admin-username'           => 'Tên hiệu:',
	'centralauth-admin-lookup'             => 'Xem hay sửa đổi dữ liệu thành viên',
	'centralauth-admin-permission'         => 'Chỉ các tiếp viên có quyền hợp nhất tài khoản của người khác.',
	'centralauth-admin-unmerge'            => 'Chia ra lựa chọn',
	'centralauth-admin-merge'              => 'Hợp nhất lựa chọn',
	'centralauth-admin-bad-input'          => 'Lựa chọn không hợp lệ',
	'centralauth-admin-none-selected'      => 'Chưa chọn tài khoản để sửa đổi.',
	'centralauth-prefs-status'             => 'Tình trạng của tài khoản toàn cầu:',
	'centralauth-prefs-not-managed'        => 'Không sử dụng tài khoản hợp nhất',
	'centralauth-prefs-unattached'         => 'Chưa xác nhận',
	'centralauth-prefs-migration'          => 'Đang hợp nhất',
	'centralauth-prefs-count-attached'     => 'Tài khoản của bạn có hiệu lực tại $1 dự án.',
	'centralauth-prefs-count-unattached'   => '$1 dự án vẫn có tài khoản cùng tên bạn chưa được xác nhận.',
	'centralauth-prefs-detail-unattached'  => 'Chưa xác nhận rằng tài khoản toàn cầu bao gồm dự án này.',
	'centralauth-prefs-manage'             => 'Quản lý tài khoản toàn cầu',
	'centralauth-renameuser-abort'         => '<div class="errorbox">Không có thể đổi tên $1 tại đây vì tên hiệu đã được chuyển của hệ thống tài khoản hợp nhất.</div>',
);

/** Volapük (Volapük)
 * @author Smeira
 * @author Malafaya
 */
$messages['vo'] = array(
	'mergeaccount'                         => 'Stad balama kalas',
	'centralauth-desc'                     => 'Balön kali da vüks Fünoda: Wikimedia',
	'centralauth-merge-notlogged'          => '<span class="plainlinks">[{{fullurl:Special:Userlogin|returnto=Special%3AMergeAccount}} Nunädolös oli, begö!]</span> ad logön, va kals olik pebalons lölöfiko.',
	'centralauth-merge-welcome'            => "'''Gebanakal olik no nog petopätükon ini nunädamasit balik ela Wikimedia.'''

If vilol topätükön kalis olik, okanol gebön gebananami ot e letavödi ot ad nunädön oli pö proyegs (vüks) valiks in püks gebidik valik. Atos fasilükon vobi me proyegs difik, soäs löpükam ragivas nulik ini [http://commons.wikimedia.org/Cifapad Kobädikos ela Wikimedia], e viton kofudis u konflitis (a.s. ven pösods difik välons gebananemi ot pö proyegds difik).

If ek ya labon gebananemi olik pö proyeg votik, kal balik ola no otupon oni; okanol bespikön atosi poso ko on u ko guvan.",
	'centralauth-merge-step1-title'        => 'Primön ad balön kalis',
	'centralauth-merge-step1-detail'       => 'Letavöd e ladet leäktronik peregistaröl oliks poleigodons ko uts kalas vükas votik, ad fümedön, das valiks leigons. Nos povotükon jüs efümedol, das valikos binon verätik.',
	'centralauth-merge-step1-submit'       => 'Fümedolös nunädamanünis',
	'centralauth-merge-step2-title'        => 'Fümedolös kalis pluik',
	'centralauth-merge-step2-detail'       => 'Kals anik no ekanons payümön itjäfidiko ad ut lomavüka olik. Kanol fümedön, das kals at binons oliks, medü letavöd(s) onsik.',
	'centralauth-merge-step2-submit'       => 'Fümedolös nunädamanünis',
	'centralauth-merge-step3-title'        => 'Jafön kali balik',
	'centralauth-merge-step3-detail'       => 'Kanol anu jafön kali balik ola, labü vüks sököl:',
	'centralauth-merge-step3-submit'       => 'Balön kalis',
	'centralauth-complete'                 => 'Kals olik pebalons!',
	'centralauth-incomplete'               => 'Kals olik no pebalons!',
	'centralauth-complete-text'            => 'Kanol anu nunädön oli in proyegs valik ela Wikimedia nes jafön kali nulik. Gebananem e letavöd ots lonöfons pro Vükiped, Vükivödabuk, Vükibuks äsi svistaproyegs onsik in püks valik.',
	'centralauth-incomplete-text'          => 'Posä kals olik pubalons, okanol nunädön oli pö proyegs valik ela Wikimedia nes jafön kali nulik. Gebananem e letavöd ots lonöfons pro Vükiped, Vükivödabuk, Vükibuks äsi svistaproyegs onsik in püks valik.',
	'centralauth-not-owner-text'           => 'Gebananem: „$1“ pegevon itjäfidiko dalabane kala in $2.

If ol binol dalaban, kanol finükön kalibalami medä penol letavöd kala at:',
	'centralauth-disabled-dryrun'          => 'Kalibalam binon atimo nog proyeg no pefinüköl e no nog pedälon. Pidö!',
	'centralauth-readmore-text'            => ":''[[meta:Help:Unified login|Reidolös mödikumosi tefü '''kals balik''']]...''",
	'centralauth-list-home-title'          => 'Lomavük',
	'centralauth-list-home-dryrun'         => 'Letavöd e ladet leäktronik in vük at pogebons in kal balik ola, e gebanapads olik in vüks votik poyümons itjäfidiko isio. Okanol votükön lomavüki olik poso.',
	'centralauth-list-attached-title'      => 'Kals peyümöl',
	'centralauth-list-attached'            => 'Kal labü nem: „$1“ pö vüks sököl peyümons itjäfidiko lü kal balik:',
	'centralauth-list-attached-dryrun'     => 'Kal labü nem: „$1“ su vüks sököl poyümon itjäfidiko lü kal balik:',
	'centralauth-list-unattached-title'    => 'Kals no peyümöl',
	'centralauth-list-unattached'          => 'No eplöpos ad fümedön itjäfidiko kali: „$1“ as ledutöl lü ol su vüks sököl (mögos, das no labons letavödi ot kala lomavüka olik).',
	'centralauth-foreign-link'             => 'Geban $1 in $2',
	'centralauth-finish-title'             => 'Finükön balami',
	'centralauth-finish-text'              => 'If kals at ledutons lü ol, kanol finükön kalibalami medä penol letavödis kalas at:',
	'centralauth-finish-password'          => 'Letavöd:',
	'centralauth-finish-login'             => 'Gebananem',
	'centralauth-finish-send-confirmation' => 'letavöd pota leäktronik',
	'centralauth-finish-problems'          => 'Labol-li fikulis, u no dalabol-li kalis votik at? [[meta:Help:Unified login problems|Ekö! yuf tefik]]...',
	'centralauth-merge-attempt'            => "'''Letävod pegivöl pafümedon leigodü kals no nog peyümöls...'''",
	'centralauth-attach-list-attached'     => 'Kal balik labü nem: „$1“ keninükon kalis sököl:',
	'centralauth-attach-title'             => 'Fümedolös kali',
	'centralauth-attach-text'              => 'Kal at no nog petopätükon lü kal balik. If kal at leduton lü ol, kanol yümön oni if penol letavöd onik:',
	'centralauth-attach-submit'            => 'Topätükön kali',
	'centralauth-attach-success'           => 'Kal at petopätükon lü kal balik.',
	'centralauth'                          => 'Kaliguvam balik',
	'centralauth-admin-manage'             => 'Guvam gebananünas',
	'centralauth-admin-username'           => 'Gebananem:',
	'centralauth-admin-lookup'             => 'Logön u votükön gebananünis',
	'centralauth-admin-unmerge'            => 'Pevälos ad no balön',
	'centralauth-admin-merge'              => 'Pevälos ad balön',
	'centralauth-admin-bad-input'          => 'Pevälos negidetiko ad balön',
	'centralauth-admin-none-selected'      => 'Kals nonik pevälons ad pevotükön.',
	'centralauth-prefs-status'             => 'Stad kala valemik:',
	'centralauth-prefs-not-managed'        => 'Kal balik no pagebon',
	'centralauth-prefs-unattached'         => 'No pefümedöl',
	'centralauth-prefs-complete'           => 'Valikos veräton!',
	'centralauth-prefs-migration'          => 'Patopätükon',
	'centralauth-prefs-count-attached'     => 'Kal olik lonöfon pö {{plural:$1|proyegatopäd|proyegatopäds}} $1.',
	'centralauth-prefs-count-unattached'   => 'Kals no pefümedöls labü nem olik dabinon nog pö {{plural:$1|proyeg|proyegs}} $1.',
	'centralauth-prefs-detail-unattached'  => 'Proyegatopäd at no pefümedon as dutöl lü kal valemik.',
	'centralauth-prefs-manage'             => 'Guvön kali valemik ola',
	'centralauth-renameuser-abort'         => '<div class="errorbox">No mögos ad votanemön gebani: $1 is bi gebananem at petopätükon lü nunädamasit balik.</div>',
);

$messages['yue'] = array(
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

$messages['zh-hans'] = array(
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

$messages['zh-hant'] = array(
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

$messages['zh-tw'] = array(
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

