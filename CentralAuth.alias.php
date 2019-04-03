<?php
/**
 * Aliases for special pages of CentralAuth  extension.
 * See also CentralAuth.notranslate-alias.php
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = [];

/** English (English) */
$specialPageAliases['en'] = [
	'CentralAuth' => [ 'CentralAuth', 'GlobalAccount' ],
	'MergeAccount' => [ 'MergeAccount' ],
	'GlobalGroupMembership' => [ 'GlobalUserRights', 'GlobalGroupMembership' ],
	'GlobalGroupPermissions' => [ 'GlobalGroupPermissions' ],
	'WikiSets' => [ 'WikiSets', 'EditWikiSets' ],
	'GlobalUsers' => [ 'GlobalUsers' ],
	'MultiLock' => [ 'MultiLock' ],
	'GlobalRenameUser' => [ 'GlobalRenameUser' ],
	'GlobalRenameProgress' => [ 'GlobalRenameProgress' ],
	'GlobalUserMerge' => [ 'GlobalUserMerge' ],
	'GlobalRenameRequest' => [ 'GlobalRenameRequest' ],
	'GlobalRenameQueue' => [ 'GlobalRenameQueue' ],
	'SulRenameWarning' => [ 'SulRenameWarning' ],
	'UsersWhoWillBeRenamed' => [ 'UsersWhoWillBeRenamed' ],
];

/** Afrikaans (Afrikaans) */
$specialPageAliases['af'] = [
	'GlobalUsers' => [ 'GlobaleGebruikers' ],
];

/** Arabic (العربية) */
$specialPageAliases['ar'] = [
	'CentralAuth' => [ 'تحقق_مركزي' ],
	'MergeAccount' => [ 'دمج_حساب' ],
	'GlobalGroupMembership' => [ 'صلاحيات_المستخدم_العامة', 'عضوية_المجموعة_العامة' ],
	'GlobalGroupPermissions' => [ 'سماحات_المجموعة_العامة' ],
	'WikiSets' => [ 'تعديل_مجموعات_الويكي' ],
	'GlobalUsers' => [ 'مستخدمون_عامون' ],
	'MultiLock' => [ 'قفل_متعدد' ],
	'GlobalRenameUser' => [ 'إعادة_تسمية_مستخدم_عامة' ],
	'GlobalRenameProgress' => [ 'تطور_إعادة_تسمية_عامة' ],
	'GlobalUserMerge' => [ 'دمج_مستخدم_عام' ],
	'GlobalRenameRequest' => [ 'طلب_إعادة_تسمية_عام' ],
	'GlobalRenameQueue' => [ 'طابور|_إعادة_تسمية_عام' ],
	'SulRenameWarning' => [ 'تحذير_إعادة_تسمية_إس_يو_إل' ],
	'UsersWhoWillBeRenamed' => [ 'المستخدمون_الذين_ستتم_إعادة_تسميتهم' ],
];

/** Egyptian Arabic (مصرى) */
$specialPageAliases['arz'] = [
	'CentralAuth' => [ 'تحقيق_مركزى' ],
	'MergeAccount' => [ 'دمج_حساب' ],
	'GlobalGroupMembership' => [ 'حقوق_اليوزر_العامه', 'عضوية_الجروپ_العامه' ],
	'GlobalGroupPermissions' => [ 'اذن_الجروپ_العامه' ],
	'WikiSets' => [ 'تعديل_مجموعات_الويكى' ],
	'GlobalUsers' => [ 'يوزرات_عامين' ],
	'MultiLock' => [ 'قفل_متعدد' ],
	'GlobalRenameUser' => [ 'سمي_تاني_يوزر_عام' ],
	'GlobalRenameProgress' => [ 'تطور_سمي_يوزر_عام' ],
];

/** Assamese (অসমীয়া) */
$specialPageAliases['as'] = [
	'MergeAccount' => [ 'একাউণ্ট_একত্ৰীকৰণ' ],
	'GlobalGroupMembership' => [ 'গোলকীয়_সদস্যৰ_অধিকাৰসমূহ', 'গোলকীয়_গোটৰ_সদস্য' ],
	'GlobalGroupPermissions' => [ 'গোলকীয়_গোটৰ_অনুমতি' ],
	'WikiSets' => [ 'ৱিকিসংহতিসমূহ', 'ৱিকিসংহতিসমূহ_সম্পাদনা' ],
	'GlobalUsers' => [ 'গোলকীয়_ব্যৱহাৰকাৰী' ],
];

/** Avaric (авар) */
$specialPageAliases['av'] = [
	'MergeAccount' => [ 'Учёталъулал_хъвай-хъвагІаязул_цолъи' ],
	'GlobalGroupMembership' => [ 'Глобальные_права_участника', 'Глобальное_членство' ],
	'GlobalGroupPermissions' => [ 'Права_глобальных_групп' ],
	'WikiSets' => [ 'Наборы_вики' ],
	'GlobalUsers' => [ 'Глобальные_участники' ],
];

/** Bashkir (башҡортса) */
$specialPageAliases['ba'] = [
	'GlobalGroupMembership' => [ 'GlobalUserRights' ],
	'WikiSets' => [ 'WikiSets' ],
	'GlobalUsers' => [ 'GlobalUsers' ],
];

/** Bengali (বাংলা) */
$specialPageAliases['bn'] = [
	'CentralAuth' => [ 'কেন্দ্রীয়_প্রমাণী' ],
	'MergeAccount' => [ 'অ্যাকাউন্ট_একত্রীকরণ' ],
	'GlobalGroupMembership' => [ 'বৈশ্বিক_ব্যবহারকারী_অধিকার', 'বৈশ্বিক_দলের_সদস্যপদ' ],
	'GlobalGroupPermissions' => [ 'বৈশ্বিক_দলের_অনুমতি' ],
	'WikiSets' => [ 'উইকিসেট', 'উইকিসেট_সম্পাদনা' ],
	'GlobalUsers' => [ 'বৈশ্বিক_ব্যবহারকারী' ],
	'MultiLock' => [ 'বহুবাধা' ],
	'GlobalRenameUser' => [ 'বৈশ্বিক_ব্যবহারকারী_নামান্তর', 'বৈশ্বিক_ব্যবহারকারী_পুনঃনামকরণ' ],
	'GlobalRenameProgress' => [ 'বৈশ্বিক_নামান্তরের_অগ্রগতি' ],
	'GlobalUserMerge' => [ 'বৈশ্বিক_ব্যবহারকারী_একত্রীকরণ' ],
	'GlobalRenameRequest' => [ 'বৈশ্বিক_নামান্তরের_অনুরোধ' ],
	'GlobalRenameQueue' => [ 'বৈশ্বিক_নামান্তরের_সারি' ],
	'SulRenameWarning' => [ 'SUL_নামান্তরের_সতর্কবার্তা' ],
	'UsersWhoWillBeRenamed' => [ 'ব্যবহারকারী_যারা_নামান্তরিত_হবেন' ],
];

/** Bulgarian (български) */
$specialPageAliases['bg'] = [
	'CentralAuth' => [ 'Управление_на_единните_сметки' ],
	'MergeAccount' => [ 'Обединяване_на_сметки' ],
	'GlobalGroupMembership' => [ 'Глобални_потребителски_права' ],
	'GlobalUsers' => [ 'Списък_на_глобалните_сметки' ],
];

/** Western Balochi (بلوچی رخشانی) */
$specialPageAliases['bgn'] = [
	'CentralAuth' => [ 'متمرکیزین_داخل_بوتین' ],
	'MergeAccount' => [ 'هیسابی_ادغام_کورتین' ],
	'GlobalGroupMembership' => [ 'کارمرزوکی_سراسرین_اختیاران' ],
	'GlobalGroupPermissions' => [ 'گروپی_سراسرین_اختیاران' ],
	'WikiSets' => [ 'ویکی_ئی_مجموئه_ئی_دستکاری_کورتین' ],
	'GlobalUsers' => [ 'سراسرین_کارمرزوکان' ],
	'MultiLock' => [ 'چینکه_قُلپی' ],
	'GlobalRenameUser' => [ 'کارمرزوکی_نامی_سراسرین_تغیر' ],
	'GlobalRenameProgress' => [ 'سراسرین_نامی_تغیری_پیشرپت' ],
	'GlobalUserMerge' => [ 'سراسرین_کارمرزوکی_ادغام' ],
	'GlobalRenameRequest' => [ 'درخواست_په_سراسرین_کامرزوکی_ادغاما' ],
	'GlobalRenameQueue' => [ 'سراسرین_نامی_سپ_ئی_تغیر' ],
	'SulRenameWarning' => [ 'سراسرین_نامی_تغیر_ئی_هشدار' ],
	'UsersWhoWillBeRenamed' => [ 'کار_مرزوکان_که_تغیر_نام_ئه_به_ینت' ],
];

/** Banjar (Bahasa Banjar) */
$specialPageAliases['bjn'] = [
	'CentralAuth' => [ 'Uturitas_pusat' ],
	'MergeAccount' => [ 'Gabungakan_akun' ],
	'GlobalGroupMembership' => [ 'Hak_pamakai_global' ],
	'GlobalGroupPermissions' => [ 'Hak_galambang_global' ],
	'WikiSets' => [ 'Babak_galambang_wiki' ],
	'GlobalUsers' => [ 'Pamakai_global' ],
];

/** Breton (brezhoneg) */
$specialPageAliases['br'] = [
	'MergeAccount' => [ 'KendeuzKont' ],
	'GlobalUsers' => [ 'ImplijerienHollek' ],
];

/** Bosnian (bosanski) */
$specialPageAliases['bs'] = [
	'CentralAuth' => [ 'SredisnjaAutent' ],
	'MergeAccount' => [ 'UjediniRacune' ],
	'GlobalGroupMembership' => [ 'GlobalnaKorisnicnaPrava' ],
	'GlobalGroupPermissions' => [ 'GlobalneDozvoleGrupa' ],
	'WikiSets' => [ 'UrediWikiSetove' ],
	'GlobalUsers' => [ 'GlobalniKorisnici' ],
];

/** Catalan (català) */
$specialPageAliases['ca'] = [
	'MergeAccount' => [ 'Fusió_de_comptes' ],
	'GlobalUsers' => [ 'Usuaris_globals' ],
];

/** Min Dong Chinese (Mìng-dĕ̤ng-ngṳ̄) */
$specialPageAliases['cdo'] = [
	'CentralAuth' => [ '中央授權' ],
	'MergeAccount' => [ '合併賬戶' ],
	'GlobalGroupMembership' => [ '全局用戶權限' ],
	'GlobalGroupPermissions' => [ '全局組權限' ],
	'WikiSets' => [ '維基百科設置' ],
	'GlobalUsers' => [ '全局用戶' ],
	'MultiLock' => [ '多重鎖' ],
];

/** Chechen (нохчийн) */
$specialPageAliases['ce'] = [
	'MergeAccount' => [ 'Декъашхочун_дӀаяздарш_цхьаьнатохар' ],
	'GlobalGroupMembership' => [ 'Декъашхочун_глобальни_бакъонаш' ],
	'GlobalGroupPermissions' => [ 'Глобальни_тобанийн_бакъонаш' ],
	'WikiSets' => [ 'Вики_гӀирсийн_гулам' ],
	'GlobalUsers' => [ 'Глобальни_декъашхой' ],
];

/** Czech (čeština) */
$specialPageAliases['cs'] = [
	'CentralAuth' => [ 'Centrální_ověření' ],
	'MergeAccount' => [ 'Sloučení_účtů' ],
	'GlobalGroupMembership' => [ 'Globální_práva_uživatele' ],
	'GlobalGroupPermissions' => [ 'Práva_globálních_skupin' ],
	'WikiSets' => [ 'Sady_wiki' ],
	'GlobalUsers' => [ 'Globální_uživatelé' ],
	'MultiLock' => [ 'Hromadné_zamknutí' ],
	'GlobalRenameUser' => [ 'Globální_přejmenování_uživatele' ],
	'GlobalRenameProgress' => [ 'Stav_globálního_přejmenování' ],
	'GlobalUserMerge' => [ 'Sloučení_globálního_uživatele' ],
	'GlobalRenameRequest' => [ 'Žádost_o_globální_přejmenování' ],
	'GlobalRenameQueue' => [ 'Fronta_globálních_přejmenování' ],
];

/** German (Deutsch) */
$specialPageAliases['de'] = [
	'CentralAuth' => [ 'Verwaltung_Benutzerkonten-Zusammenführung' ],
	'MergeAccount' => [ 'Benutzerkonten_zusammenführen' ],
	'GlobalGroupMembership' => [ 'Globale_Benutzerzugehörigkeit' ],
	'GlobalGroupPermissions' => [ 'Globale_Gruppenrechte' ],
	'WikiSets' => [ 'Wikigruppen', 'Wikigruppen_bearbeiten', 'Wikisets_bearbeiten' ],
	'GlobalUsers' => [ 'Globale_Benutzerliste' ],
	'MultiLock' => [ 'Mehrfachsperre' ],
	'GlobalRenameUser' => [ 'Globale_Benutzerumbenennung' ],
	'GlobalRenameProgress' => [ 'Globaler_Umbenennungsfortschritt' ],
	'GlobalUserMerge' => [ 'Globale_Benutzerzusammenführung' ],
	'GlobalRenameRequest' => [ 'Globale_Umbenennungsanfrage' ],
	'GlobalRenameQueue' => [ 'Globale_Umbenennungs-Warteschlange' ],
	'SulRenameWarning' => [ 'SUL-Umbenennungswarnung' ],
	'UsersWhoWillBeRenamed' => [ 'Umzubenennende_Benutzer' ],
];

/** Zazaki (Zazaki) */
$specialPageAliases['diq'] = [
	'CentralAuth' => [ 'MerkeziKimlıgAraştkerdış' ],
	'MergeAccount' => [ 'HesabYewkerdış' ],
	'GlobalGroupMembership' => [ 'HeqêEzayanêGlobali', 'GrubaEzayanêGlobalan' ],
	'GlobalGroupPermissions' => [ 'İcazetêGrubaGlobali' ],
	'WikiSets' => [ 'SazkerdışêWikiBıvurne' ],
	'GlobalUsers' => [ 'KarberêGıloveri' ],
	'MultiLock' => [ 'KılitêZêdeyi' ],
];

/** Lower Sorbian (dolnoserbski) */
$specialPageAliases['dsb'] = [
	'CentralAuth' => [ 'Zjadnośenje_kontow' ],
	'MergeAccount' => [ 'Konta_zjadnośiś' ],
	'GlobalGroupMembership' => [ 'Cłonkojstwo_w_globalnej_kupce' ],
	'GlobalGroupPermissions' => [ 'Globalne_kupkowe_pšawa' ],
	'WikiSets' => [ 'Wikisajźby_wobźěłaś' ],
	'GlobalUsers' => [ 'Globalne_wužywarje' ],
];

/** Greek (Ελληνικά) */
$specialPageAliases['el'] = [
	'MergeAccount' => [ 'ΣυγχώνευσηΛογαριασμού' ],
	'GlobalGroupMembership' => [ 'ΚαθολικάΔικαιώματαΧρηστών' ],
	'GlobalGroupPermissions' => [ 'ΚαθολικέςΆδειεςΧρηστών' ],
	'GlobalUsers' => [ 'ΚαθολικοίΧρήστες' ],
];

/** Esperanto (Esperanto) */
$specialPageAliases['eo'] = [
	'CentralAuth' => [ 'Centra_aŭtentigo' ],
	'MergeAccount' => [ 'Unuigi_konton', 'Kunigi_konton' ],
	'GlobalGroupMembership' => [ 'Ĝeneralaj_uzantorajtoj' ],
	'GlobalGroupPermissions' => [ 'Ĝeneralaj_gruprajtoj' ],
	'GlobalUsers' => [ 'Ĉieaj_uzantoj' ],
];

/** Spanish (español) */
$specialPageAliases['es'] = [
	'CentralAuth' => [ 'Información_de_la_cuenta_global' ],
	'MergeAccount' => [ 'Fusionar_cuenta_global', 'FusionarCuentaGlobal' ],
	'GlobalGroupMembership' => [ 'Permisos_de_usuario_global', 'PermisosUsuarioGlobal' ],
	'GlobalGroupPermissions' => [ 'Permisos_de_grupo_global', 'PermisosGrupoGlobal' ],
	'WikiSets' => [ 'AjustesWiki', 'EditarAjustesWiki' ],
	'GlobalUsers' => [ 'Usuarios_globales' ],
	'GlobalRenameRequest' => [ 'Solicitud_de_renombrado_global' ],
	'UsersWhoWillBeRenamed' => [ 'Usuarios_que_cambiarán_de_nombre', 'Usuarias_que_cambiarán_de_nombre' ],
];

/** Estonian (eesti) */
$specialPageAliases['et'] = [
	'CentralAuth' => [ 'Kontode_ühendamine' ],
	'MergeAccount' => [ 'Kontode_ühendamise_seis' ],
	'GlobalGroupMembership' => [ 'Globaalse_kasutaja_õigused' ],
	'GlobalGroupPermissions' => [ 'Globaalse_rühma_haldamine' ],
	'WikiSets' => [ 'Vikikomplektid' ],
	'GlobalUsers' => [ 'Globaalsed_kasutajad' ],
	'GlobalRenameUser' => [ 'Globaalne_kasutajanime_muutmine' ],
	'GlobalRenameProgress' => [ 'Globaalse_ümbernimetamise_seis' ],
	'GlobalRenameRequest' => [ 'Globaalse_ümbernimetamise_taotlus' ],
];

/** Persian (فارسی) */
$specialPageAliases['fa'] = [
	'CentralAuth' => [ 'ورود_متمرکز' ],
	'MergeAccount' => [ 'ادغام_حساب' ],
	'GlobalGroupMembership' => [ 'اختیارات_سراسری_کاربر' ],
	'GlobalGroupPermissions' => [ 'اختیارات_سراسری_گروه' ],
	'WikiSets' => [ 'ویرایش_مجموعه‌های_ویکی' ],
	'GlobalUsers' => [ 'کاربران_سراسری' ],
	'MultiLock' => [ 'چندقفلی' ],
	'GlobalRenameUser' => [ 'تغییر_سراسری_نام_کاربر' ],
	'GlobalRenameProgress' => [ 'پیشرفت_تغییر_نام_سراسری' ],
	'GlobalUserMerge' => [ 'ادغام_کاربر_سراسری' ],
	'GlobalRenameRequest' => [ 'درخواست_ادغام_کاربر_سراسری' ],
	'GlobalRenameQueue' => [ 'صف_تغییر_نام_سراسری' ],
	'SulRenameWarning' => [ 'هشدار_تغییر_نام_سراسری' ],
	'UsersWhoWillBeRenamed' => [ 'کاربرانی_که_تغییر_نام_خواهند_یافت' ],
];

/** Finnish (suomi) */
$specialPageAliases['fi'] = [
	'CentralAuth' => [ 'Keskitetty_varmennus' ],
	'MergeAccount' => [ 'Yhdistä_tunnus' ],
	'GlobalUsers' => [ 'Globaalit_tunnukset' ],
];

/** French (français) */
$specialPageAliases['fr'] = [
	'MergeAccount' => [ 'Fusionner_le_compte', 'FusionnerLeCompte' ],
	'GlobalGroupMembership' => [ 'Permissions_globales', 'PermissionGlobales' ],
	'GlobalGroupPermissions' => [ 'Droits_des_groupes_globaux', 'DroitsDesGroupesGlobaux' ],
	'WikiSets' => [ 'Modifier_les_sets_de_wikis', 'ModifierLesSetsDeWiki' ],
	'GlobalUsers' => [ 'Utilisateurs_globaux', 'UtilisateursGlobaux' ],
];

/** Arpitan (arpetan) */
$specialPageAliases['frp'] = [
	'CentralAuth' => [ 'Administracion_des_comptos_fusionâs', 'AdministracionDesComptosFusionâs' ],
	'MergeAccount' => [ 'Fusionar_los_comptos', 'FusionarLosComptos' ],
	'GlobalGroupMembership' => [ 'Pèrmissions_globâles', 'PèrmissionsGlobâles' ],
	'GlobalGroupPermissions' => [ 'Drêts_a_les_tropes_globâles', 'DrêtsALesTropesGlobâles' ],
	'WikiSets' => [ 'Changiér_los_sèts_de_vouiquis', 'ChangiérLosSètsDeVouiquis' ],
	'GlobalUsers' => [ 'Usanciérs_globâls', 'UsanciérsGlobâls' ],
];

/** Galician (galego) */
$specialPageAliases['gl'] = [
	'CentralAuth' => [ 'Autenticación_central' ],
	'MergeAccount' => [ 'Fusionar_contas' ],
	'GlobalGroupMembership' => [ 'Dereitos_globais' ],
	'GlobalGroupPermissions' => [ 'Permisos_de_grupo_globais' ],
	'WikiSets' => [ 'Configuracións_do_wiki' ],
	'GlobalUsers' => [ 'Usuarios_globais' ],
];

/** Swiss German (Alemannisch) */
$specialPageAliases['gsw'] = [
	'CentralAuth' => [ 'Verwaltig_Benutzerchonte-Zämmefierig' ],
	'MergeAccount' => [ 'Benutzerchonte_zämmefiere' ],
	'GlobalGroupMembership' => [ 'Wältwyti_Benutzerrächt' ],
	'GlobalGroupPermissions' => [ 'Wältwyti_Grupperächt' ],
	'WikiSets' => [ 'Wikisets_bearbeite' ],
	'GlobalUsers' => [ 'Wältwyti_Benutzerlischt' ],
];

/** Gujarati (ગુજરાતી) */
$specialPageAliases['gu'] = [
	'CentralAuth' => [ 'કેન્દ્રીયશપથ' ],
	'GlobalGroupMembership' => [ 'વૈશ્વિકસભ્યહક્કો' ],
	'GlobalGroupPermissions' => [ 'વૈશ્વિકસમૂહપરવાનગી' ],
	'GlobalUsers' => [ 'વૈશ્વિકસભ્ય' ],
];

/** Hebrew (עברית) */
$specialPageAliases['he'] = [
	'CentralAuth' => [ 'חשבון_משתמש_מאוחד' ],
	'MergeAccount' => [ 'מיזוג_חשבונות' ],
	'GlobalGroupMembership' => [ 'הרשאות_משתמש_כלליות', 'חברות_בקבוצות_כלליות' ],
	'GlobalGroupPermissions' => [ 'הרשאות_קבוצות_כלליות' ],
	'WikiSets' => [ 'עריכת_קבוצות_אתרי_ויקי' ],
	'GlobalUsers' => [ 'משתמשים_כלליים' ],
	'MultiLock' => [ 'נעילה_מרובה' ],
	'GlobalRenameUser' => [ 'שינוי_שם_משתמש_גלובלי' ],
	'GlobalRenameProgress' => [ 'התקדמות_שינוי_שם_משתמש_גלובלי' ],
	'GlobalUserMerge' => [ 'מיזוג_חשבון_גלובלי' ],
	'GlobalRenameRequest' => [ 'בקשת_שינוי_שם_גלובלית' ],
	'GlobalRenameQueue' => [ 'תור_שינויי_שם_גלובלי' ],
	'SulRenameWarning' => [ 'אזהרה_שינוי_שם_של_SUL' ],
	'UsersWhoWillBeRenamed' => [ 'משתמשים_ששמם_ישונה' ],
];

/** Hindi (हिन्दी) */
$specialPageAliases['hi'] = [
	'MergeAccount' => [ 'खाता_विलय' ],
	'GlobalGroupPermissions' => [ 'वैश्विक_समूह_अधिकार', 'केन्द्रीय_समूह_अधिकार', 'केंद्रीय_समूह_अधिकार' ],
	'WikiSets' => [ 'विकिसेट' ],
	'GlobalUsers' => [ 'वैश्विक_सदस्य_सूची' ],
];

/** Croatian (hrvatski) */
$specialPageAliases['hr'] = [
	'CentralAuth' => [ 'Središnja_prijava' ],
	'MergeAccount' => [ 'Spoji_račun' ],
	'GlobalGroupMembership' => [ 'Globalna_suradnička_prava' ],
	'GlobalGroupPermissions' => [ 'Globalna_prava_skupina' ],
	'WikiSets' => [ 'Uredi_wikiset' ],
	'GlobalUsers' => [ 'Globalni_suradnici' ],
];

/** Upper Sorbian (hornjoserbsce) */
$specialPageAliases['hsb'] = [
	'CentralAuth' => [ 'Zjednoćenje_kontow' ],
	'MergeAccount' => [ 'Konta_zjednoćić' ],
	'GlobalGroupMembership' => [ 'Globalne_wužiwarske_prawa' ],
	'GlobalGroupPermissions' => [ 'Globalne_skupinske_prawa' ],
	'WikiSets' => [ 'Wikisadźby_wobdźěłać' ],
	'GlobalUsers' => [ 'Globalni_wužiwarjo' ],
];

/** Xiang Chinese (湘语) */
$specialPageAliases['hsn'] = [
	'CentralAuth' => [ '中心认证' ],
	'MergeAccount' => [ '合并账户' ],
	'GlobalGroupMembership' => [ '全局用户权限' ],
	'GlobalGroupPermissions' => [ '全局群组权限' ],
	'WikiSets' => [ '维基设置', '编辑维基设置' ],
	'GlobalUsers' => [ '全局用户' ],
];

/** Haitian (Kreyòl ayisyen) */
$specialPageAliases['ht'] = [
	'CentralAuth' => [ 'OtoriteSantral' ],
	'MergeAccount' => [ 'FizyoneKont' ],
	'GlobalGroupMembership' => [ 'DwaItilizatèGlobal', 'FèPatiGwoupGlobal' ],
	'GlobalGroupPermissions' => [ 'PèmisyonGwoupGlobal' ],
	'WikiSets' => [ 'AnsanmWiki', 'ModifyeAnsanmWiki' ],
	'GlobalUsers' => [ 'ItilizatèGlobal' ],
];

/** Hungarian (magyar) */
$specialPageAliases['hu'] = [
	'CentralAuth' => [ 'Központi_azonosítás' ],
	'MergeAccount' => [ 'Szerkesztői_fiókok_egyesítése', 'Felhasználói_fiókok_egyesítése' ],
	'GlobalGroupMembership' => [ 'Globális_szerkesztői_jogok', 'Globális_felhasználói_jogok' ],
	'GlobalGroupPermissions' => [ 'Globális_szerkesztői_engedélyek', 'Globális_felhasználói_engedélyek' ],
	'WikiSets' => [ 'Wikicsoportok', 'Wikicsoportok_szerkesztése' ],
	'GlobalUsers' => [ 'Globális_szerkesztőlista', 'Globális_felhasználólista', 'Felhasználók_globális_listája' ],
];

/** Interlingua (interlingua) */
$specialPageAliases['ia'] = [
	'CentralAuth' => [ 'Auth_central' ],
	'MergeAccount' => [ 'Fusionar_conto' ],
	'GlobalGroupMembership' => [ 'Membrato_global_de_gruppos' ],
	'GlobalGroupPermissions' => [ 'Permissiones_global_de_gruppos' ],
	'WikiSets' => [ 'Modificar_sets_de_wikis' ],
	'GlobalUsers' => [ 'Usatores_global' ],
];

/** Indonesian (Bahasa Indonesia) */
$specialPageAliases['id'] = [
	'CentralAuth' => [ 'Otoritas_pusat', 'OtoritasPusat' ],
	'MergeAccount' => [ 'Gabungkan_akun', 'GabungkanAkun' ],
	'GlobalGroupMembership' => [ 'Hak_pengguna_global', 'HakPenggunaGlobal' ],
	'GlobalGroupPermissions' => [ 'Hak_kelompok_global', 'HakKelompokGlobal' ],
	'WikiSets' => [ 'Sunting_kelompok_wiki', 'SuntingKelompokWiki' ],
	'GlobalUsers' => [ 'Pengguna_global', 'PenggunaGlobal' ],
];

/** Italian (italiano) */
$specialPageAliases['it'] = [
	'CentralAuth' => [ 'UtenzaGlobale', 'LoginUnificato' ],
	'MergeAccount' => [ 'UnificaUtenze' ],
	'GlobalGroupMembership' => [ 'PermessiUtenteGlobale' ],
	'GlobalGroupPermissions' => [ 'PermessiGruppoGlobale' ],
	'WikiSets' => [ 'WikiSet', 'ModificaWikiSets' ],
	'GlobalUsers' => [ 'UtentiGlobali' ],
];

/** Japanese (日本語) */
$specialPageAliases['ja'] = [
	'CentralAuth' => [ 'アカウント統一管理', '統一ログインの管理' ],
	'MergeAccount' => [ 'アカウント統合' ],
	'GlobalGroupMembership' => [ 'グローバル利用者権限', 'グローバルグループへの所属' ],
	'GlobalGroupPermissions' => [ 'グローバルグループ権限', 'グローバルグループパーミッション' ],
	'WikiSets' => [ 'ウィキ集合', 'ウィキ集合の編集' ],
	'GlobalUsers' => [ 'グローバル利用者' ],
	'MultiLock' => [ '複数利用者ロック' ],
	'GlobalRenameUser' => [ 'グローバル利用者名変更', 'グローバル改名', 'グローバル利用者名の変更' ],
	'GlobalRenameProgress' => [ 'グローバル利用者名変更状況', 'グローバル利用者名変更の進捗' ],
	'GlobalUserMerge' => [ 'グローバル利用者統合' ],
	'GlobalRenameRequest' => [ 'グローバル利用者名変更依頼', 'グローバル利用者アカウントの改名依頼' ],
	'GlobalRenameQueue' => [ 'グローバル利用者名変更依頼待ち', 'グローバル利用者名変更依頼の対応待ち' ],
];

/** Georgian (ქართული) */
$specialPageAliases['ka'] = [
	'GlobalGroupMembership' => [ 'გლობალურ_მომხმარებელთა_უფლებები' ],
	'GlobalUsers' => [ 'გლობალური_მომხმარებლები' ],
];

/** Khmer (ភាសាខ្មែរ) */
$specialPageAliases['km'] = [
	'MergeAccount' => [ 'ច្របាច់បញ្ចូលគណនី' ],
];

/** Korean (한국어) */
$specialPageAliases['ko'] = [
	'CentralAuth' => [ '통합계정관리' ],
	'MergeAccount' => [ '계정합치기' ],
	'GlobalGroupMembership' => [ '공통권한조정', '공통권한그룹구성원' ],
	'GlobalGroupPermissions' => [ '전역그룹권한' ],
	'WikiSets' => [ '위키집합', '위키집합편집' ],
	'GlobalUsers' => [ '통합계정목록', '공통계정목록' ],
	'MultiLock' => [ '다중잠금' ],
	'GlobalRenameUser' => [ '전역사용자이름바꾸기' ],
	'GlobalRenameProgress' => [ '전역이름바꾸기진행' ],
	'GlobalUserMerge' => [ '전역사용자병합' ],
	'GlobalRenameRequest' => [ '전역이름바꾸기요청' ],
];

/** Colognian (Ripoarisch) */
$specialPageAliases['ksh'] = [
	'GlobalGroupMembership' => [ 'JemeinsamMetmaacherJroppeRääschte' ],
	'GlobalGroupPermissions' => [ 'JemeinsamJroppe' ],
	'WikiSets' => [ 'WikiJroppe' ],
	'GlobalUsers' => [ 'Jemeinsam_Metmaacher', 'JemeinsamMetmaacher', 'Jemeinsam_Medmaacher', 'JemeinsamMedmaacher' ],
];

/** Cornish (kernowek) */
$specialPageAliases['kw'] = [
	'MergeAccount' => [ 'KesunyaAkont' ],
	'GlobalGroupMembership' => [ 'GwiryowDevnydhyoryonOllvysel' ],
	'GlobalGroupPermissions' => [ 'KumyasowBagasowOllvysel' ],
	'GlobalUsers' => [ 'DevnydhyoryonOllvysel' ],
];

/** Ladino (Ladino) */
$specialPageAliases['lad'] = [
	'CentralAuth' => [ 'CentralOtan' ],
	'MergeAccount' => [ 'AjuntarCuentoGlobbal' ],
	'GlobalGroupMembership' => [ 'Permessos_de_usador_globbal' ],
	'GlobalGroupPermissions' => [ 'Permessos_de_grupo_globbal' ],
	'WikiSets' => [ 'ArreglarVikiSiras' ],
	'GlobalUsers' => [ 'UsadoresGlobbales' ],
];

/** Luxembourgish (Lëtzebuergesch) */
$specialPageAliases['lb'] = [
	'CentralAuth' => [ 'Verwaltung_vun_der_Benotzerkonten-Zesummeféierung' ],
	'MergeAccount' => [ 'Benotzerkonten_zesummeféieren' ],
	'GlobalGroupMembership' => [ 'Member_vu_globale_Benotzerrechter' ],
	'GlobalGroupPermissions' => [ 'Global_Grupperechter' ],
	'WikiSets' => [ 'Wiki-Seten_änneren' ],
	'GlobalUsers' => [ 'Global_Benotzer' ],
	'MultiLock' => [ 'Méifach_Spär' ],
	'GlobalRenameUser' => [ 'Global_Benotzerëmbenennung' ],
	'GlobalRenameProgress' => [ 'Progrès_vun_der_globaler_Ëmbenennung' ],
	'GlobalUserMerge' => [ 'Global_Benotzer_Fusioun' ],
	'GlobalRenameRequest' => [ 'Global_Ufro_fir_Benotzer_ëmzebenennen' ],
	'GlobalRenameQueue' => [ 'Lëscht_vun_de_globalen_Ëmbenennungen_déi_am_Gaang_sinn' ],
	'SulRenameWarning' => [ 'Warnung_bei_der_SUL_Ëmbenennung' ],
	'UsersWhoWillBeRenamed' => [ 'Benotzer_déi_wäerten_ëmbenannt_ginn' ],
];

/** Northern Luri (لۊری شومالی) */
$specialPageAliases['lrc'] = [
	'MergeAccount' => [ 'سأریأک_کاری_حئساڤ' ],
	'GlobalGroupMembership' => [ 'حقوق_مین_زایارە_یی_کاریار', 'أندوم_بییئن_جأھوٙنی_جأرغە' ],
	'GlobalGroupPermissions' => [ 'تأصیقیا_جأھوٙنی_جأرغە' ],
	'WikiSets' => [ 'میزوٙنکاری_ڤیکی', 'ڤیرایئشت_میزوٙنکاری_ڤیکی' ],
	'GlobalUsers' => [ 'کاریاریا_جأھوٙنی' ],
	'MultiLock' => [ 'چأن_قولفە' ],
	'GlobalRenameUser' => [ 'د_نۊ_نوم_نیائن_جأھوٙنی_کاریار' ],
	'GlobalRenameProgress' => [ 'پیشکئرد_د_نوٙ_نوٙم_نیائن_کاریار' ],
	'GlobalUserMerge' => [ 'سأریأک_کاری_جأھوٙنی_کاریار' ],
	'GlobalRenameRequest' => [ 'حاست_د_نۊ_نوٙم_نیائن_جأھوٙنی' ],
	'GlobalRenameQueue' => [ 'گئی_بأنی_د_نۊ_نوٙم_نیائن_جأھوٙنی' ],
	'SulRenameWarning' => [ 'ھشدار_تأک_کاری_د_نۊ_نوٙم_نیائن' ],
	'UsersWhoWillBeRenamed' => [ 'کاریاری_کئ_د_نۊ_نوٙم_نیائە_بوئە' ],
];

/** Lithuanian (lietuvių) */
$specialPageAliases['lt'] = [
	'MergeAccount' => [ 'Sujungti_sąskaitas' ],
];

/** Malagasy (Malagasy) */
$specialPageAliases['mg'] = [
	'MergeAccount' => [ 'Hampiray_ny_kaonty', 'HampirayKaonty' ],
	'GlobalGroupMembership' => [ 'Zom-pikambana_maneran-tsehatra', 'ZompikambanaManerantsehatra' ],
	'GlobalGroupPermissions' => [ 'Zom-bondrona_maneran-tsehatra', 'ZombondronaManerantsehatra' ],
	'GlobalUsers' => [ 'Mpikambana_maneran-tsehatra', 'MpikambanaManerantsehatra' ],
	'GlobalRenameUser' => [ 'Fanovan\'anaram-pikambana_maneran-tsehatra' ],
	'GlobalRenameProgress' => [ 'Fandrosoan\'ny_fiovan\'anarana_maneran-tsehatra' ],
	'GlobalUserMerge' => [ 'Fampitsoniham-pikambana_maneran-tsehatra' ],
];

/** Minangkabau (Baso Minangkabau) */
$specialPageAliases['min'] = [
	'CentralAuth' => [ 'OtoritehPusek' ],
	'MergeAccount' => [ 'GabuangAkun' ],
	'GlobalGroupMembership' => [ 'HakPanggunoGlobal' ],
	'GlobalGroupPermissions' => [ 'HakKalompokGlobal' ],
	'GlobalUsers' => [ 'PanggunoGlobal' ],
];

/** Macedonian (македонски) */
$specialPageAliases['mk'] = [
	'CentralAuth' => [ 'ЦентралноПотврдување' ],
	'MergeAccount' => [ 'СпојувањеНаСметки' ],
	'GlobalGroupMembership' => [ 'ПраваНаГлобаленКорисник', 'ЧленствоВоГлобалнаГрупа' ],
	'GlobalGroupPermissions' => [ 'ДозволиНаГлобалнаГрупа' ],
	'WikiSets' => [ 'ВикиКомплети' ],
	'GlobalUsers' => [ 'ГлобалниКорисници' ],
	'MultiLock' => [ 'ПовеќекратноЗаклучување' ],
	'GlobalRenameUser' => [ 'ГлобалноПреименувањеКорисник' ],
	'GlobalRenameProgress' => [ 'ГлобалноПреименувањеНапредок' ],
	'GlobalUserMerge' => [ 'ГлобалноСпојувањеКорисник' ],
	'GlobalRenameRequest' => [ 'ГлобалноСпојувањеБарање' ],
	'GlobalRenameQueue' => [ 'РедицаГлобалноПреименување' ],
	'SulRenameWarning' => [ 'ПредупредувањеПреименувањеSUL' ],
	'UsersWhoWillBeRenamed' => [ 'КориснициКоиЌеБидатПреименувани' ],
];

/** Malayalam (മലയാളം) */
$specialPageAliases['ml'] = [
	'CentralAuth' => [ 'കേന്ദ്രീകൃത_അംഗീകാരം' ],
	'MergeAccount' => [ 'അംഗത്വസം‌യോജനം' ],
	'GlobalGroupMembership' => [ 'ആഗോള_ഉപയോക്തൃ_അവകാശങ്ങൾ', 'ആഗോള_ഉപയോക്തൃ_അംഗത്വം' ],
	'GlobalGroupPermissions' => [ 'ആഗോള_അംഗത്വാനുമതികൾ' ],
	'WikiSets' => [ 'വിക്കിഗണങ്ങൾ_തിരുത്തുക' ],
	'GlobalUsers' => [ 'ആഗോള_ഉപയോക്താക്കൾ' ],
	'MultiLock' => [ 'അനവധിബന്ധിക്കൽ' ],
	'GlobalRenameUser' => [ 'ആഗോളഉപയോക്തൃപുനർനാമകരണം' ],
	'GlobalRenameProgress' => [ 'ആഗോളപുനർനാമകരണപുരോഗതി' ],
];

/** Marathi (मराठी) */
$specialPageAliases['mr'] = [
	'CentralAuth' => [ 'मध्यवर्तीअधिकारी' ],
	'MergeAccount' => [ 'खातेविलीनीकरण' ],
	'GlobalGroupMembership' => [ 'वैश्विकसदस्याधिकार', 'वैश्विकगटसदस्यता' ],
	'GlobalGroupPermissions' => [ 'वैश्विकगटपरवानग्या' ],
	'WikiSets' => [ 'विकिसंचसंपादा' ],
	'GlobalUsers' => [ 'वैश्विकसदस्य' ],
];

/** Malay (Bahasa Melayu) */
$specialPageAliases['ms'] = [
	'CentralAuth' => [ 'Pusat_pengesahan' ],
	'MergeAccount' => [ 'Gabungkan_akaun' ],
	'GlobalGroupMembership' => [ 'Hak_kumpulan_sejagat' ],
	'GlobalGroupPermissions' => [ 'Keizinan_kumpulan_sejagat' ],
	'WikiSets' => [ 'Ubah_set_wiki' ],
	'GlobalUsers' => [ 'Pengguna_sejagat' ],
];

/** Maltese (Malti) */
$specialPageAliases['mt'] = [
	'MergeAccount' => [ 'WaħħadKont' ],
	'GlobalUsers' => [ 'UtentiGlobali' ],
];

/** Erzya (эрзянь) */
$specialPageAliases['myv'] = [
	'MergeAccount' => [ 'ВейтьсэндямсСовамоТарка' ],
];

/** Norwegian Bokmål (norsk bokmål) */
$specialPageAliases['nb'] = [
	'CentralAuth' => [ 'Enhetlig_innlogging' ],
	'MergeAccount' => [ 'Kontosammenslåing' ],
	'GlobalGroupMembership' => [ 'Globale_brukerrettigheter' ],
	'GlobalGroupPermissions' => [ 'Globale_gruppetillatelser' ],
	'WikiSets' => [ 'Rediger_wikisett' ],
	'GlobalUsers' => [ 'Globale_brukere' ],
];

/** Low Saxon (Netherlands) (Nedersaksies) */
$specialPageAliases['nds-nl'] = [
	'CentralAuth' => [ 'Sentraal_anmelden' ],
	'MergeAccount' => [ 'Gebruker_samenvoegen' ],
	'GlobalGroupMembership' => [ 'Globale_gebrukersrechten' ],
	'GlobalGroupPermissions' => [ 'Globale_groepsrechten' ],
	'WikiSets' => [ 'Wikigroepen_bewarken' ],
	'GlobalUsers' => [ 'Globale_gebrukers' ],
	'MultiLock' => [ 'Meervoudig_aofsluten' ],
];

/** Dutch (Nederlands) */
$specialPageAliases['nl'] = [
	'CentralAuth' => [ 'CentraalAanmelden' ],
	'MergeAccount' => [ 'GebruikerSamenvoegen' ],
	'GlobalGroupMembership' => [ 'GlobaalGroepslidmaatschap' ],
	'GlobalGroupPermissions' => [ 'GlobaleGroepsrechten' ],
	'WikiSets' => [ 'WikigroepenBewerken' ],
	'GlobalUsers' => [ 'GlobaleGebruikers' ],
	'MultiLock' => [ 'MeervoudigAfsluiten' ],
	'GlobalRenameUser' => [ 'GlobaalGebruikerhernoemen' ],
	'GlobalRenameProgress' => [ 'VoortgangGlobaalHernoemen' ],
	'GlobalUserMerge' => [ 'GlobaalGebruikerSamenvoegen' ],
	'GlobalRenameRequest' => [ 'GlobaalHernoemingsverzoek' ],
	'GlobalRenameQueue' => [ 'WachtrijGlobaalHernoemen' ],
	'SulRenameWarning' => [ 'SulHernoemingswaarschuwing' ],
];

/** Norwegian Nynorsk (norsk nynorsk) */
$specialPageAliases['nn'] = [
	'MergeAccount' => [ 'Kontosamanslåing' ],
	'GlobalGroupMembership' => [ 'Globale_brukarrettar' ],
	'GlobalUsers' => [ 'Globale_brukarar' ],
];

/** Occitan (occitan) */
$specialPageAliases['oc'] = [
	'MergeAccount' => [ 'Fusionar_lo_compte', 'FusionarLoCompte' ],
	'GlobalGroupMembership' => [ 'Permissions_globalas', 'PermissionGlobalas' ],
	'GlobalGroupPermissions' => [ 'Dreches_dels_gropes_globals', 'DrechesDelsGropesGlobals' ],
	'WikiSets' => [ 'Modificar_los_sets_de_wikis', 'ModificarLosSetsDeWiki' ],
	'GlobalUsers' => [ 'Utilizaires_globals', 'UtilizairesGlobals' ],
];

/** Punjabi (ਪੰਜਾਬੀ) */
$specialPageAliases['pa'] = [
	'MergeAccount' => [ 'ਖਾਤਾ_ਰਲਾਓ' ],
	'GlobalGroupMembership' => [ 'ਗਲੋਬਲ_ਵਰਤੋਂਕਾਰ_ਹੱਕ', 'ਗਲੋਬਲ_ਗਰੁੱਪ_ਮੈਂਬਰਸ਼ਿੱਪ' ],
	'GlobalGroupPermissions' => [ 'ਗਲੋਬਲ_ਗਰੁੱਪ_ਇਜਾਜ਼ਤਾਂ' ],
	'GlobalUsers' => [ 'ਗਲੋਬਲ_ਵਰਤੋਂਕਾਰ' ],
];

/** Polish (polski) */
$specialPageAliases['pl'] = [
	'CentralAuth' => [ 'Zarządzanie_kontem_uniwersalnym' ],
	'MergeAccount' => [ 'Łączenie_kont', 'Konto_uniwersalne' ],
	'GlobalGroupMembership' => [ 'Globalne_uprawnienia' ],
	'GlobalGroupPermissions' => [ 'Globalne_uprawnienia_grup' ],
	'WikiSets' => [ 'Zbiory_wiki' ],
	'GlobalUsers' => [ 'Spis_kont_uniwersalnych' ],
];

/** Pashto (پښتو) */
$specialPageAliases['ps'] = [
	'GlobalUsers' => [ 'نړېوال_کارنان' ],
];

/** Portuguese (português) */
$specialPageAliases['pt'] = [
	'CentralAuth' => [ 'Administração_de_contas_globais', 'Administração_global_de_contas' ],
	'MergeAccount' => [ 'Fundir_conta' ],
	'GlobalGroupMembership' => [ 'Grupos_globais' ],
	'GlobalGroupPermissions' => [ 'Privilégios_globais_de_grupo' ],
	'WikiSets' => [ 'Conjuntos_de_wikis' ],
	'GlobalUsers' => [ 'Utilizadores_globais' ],
];

/** Brazilian Portuguese (português do Brasil) */
$specialPageAliases['pt-br'] = [
	'CentralAuth' => [ 'Administração_global_de_contas', 'Administração_de_contas_globais' ],
	'MergeAccount' => [ 'Mesclar_conta' ],
	'GlobalGroupMembership' => [ 'Grupos_globais' ],
	'GlobalUsers' => [ 'Usuários_globais' ],
];

/** Romanian (română) */
$specialPageAliases['ro'] = [
	'CentralAuth' => [ 'Autentificare_centrală' ],
	'MergeAccount' => [ 'Unește_conturi' ],
	'GlobalGroupMembership' => [ 'Drepturi_globale_utilizator', 'Membru_global_grup' ],
	'GlobalGroupPermissions' => [ 'Permisiuni_grup_globale' ],
	'WikiSets' => [ 'Setări_modificare_Wiki' ],
	'GlobalUsers' => [ 'Utilizatori_globali' ],
];

/** Russian (русский) */
$specialPageAliases['ru'] = [
	'MergeAccount' => [ 'Объединение_учётных_записей' ],
	'GlobalGroupMembership' => [ 'Глобальные_права_участника', 'Глобальное_членство' ],
	'GlobalGroupPermissions' => [ 'Права_глобальных_групп' ],
	'WikiSets' => [ 'Наборы_вики' ],
	'GlobalUsers' => [ 'Глобальные_участники' ],
];

/** Sanskrit (संस्कृतम्) */
$specialPageAliases['sa'] = [
	'CentralAuth' => [ 'मध्यवर्तीप्रामान्य' ],
	'MergeAccount' => [ 'उपयोजकसंज्ञासंयोग' ],
	'GlobalGroupMembership' => [ 'वैश्विकसदस्याधिकार' ],
	'GlobalGroupPermissions' => [ 'वैश्विकगटसंमती' ],
	'WikiSets' => [ 'सम्पादनविकिगट' ],
	'GlobalUsers' => [ 'वैश्विकयोजक' ],
];

/** Sicilian (sicilianu) */
$specialPageAliases['scn'] = [
	'CentralAuth' => [ 'LoginUnificato' ],
	'MergeAccount' => [ 'UnificaUtenze' ],
	'GlobalGroupMembership' => [ 'PermessiUtenteGlobale' ],
	'GlobalGroupPermissions' => [ 'PermessiGruppoGlobale' ],
	'WikiSets' => [ 'WikiSet', 'ModificaWikiSets' ],
	'GlobalUsers' => [ 'UtentiGlobali' ],
];

/** Serbo-Croatian (srpskohrvatski / српскохрватски) */
$specialPageAliases['sh'] = [
	'CentralAuth' => [ 'Centralna_prijava' ],
	'MergeAccount' => [ 'Spoji_račun' ],
	'GlobalGroupMembership' => [ 'Globalna_korisnička_prava' ],
	'GlobalGroupPermissions' => [ 'Globalna_prava_grupa' ],
	'WikiSets' => [ 'Uredi_wikiset' ],
	'GlobalUsers' => [ 'Globalni_korisnici' ],
];

/** Sinhala (සිංහල) */
$specialPageAliases['si'] = [
	'CentralAuth' => [ 'මධ්‍යඅවසර' ],
	'MergeAccount' => [ 'ගිණුමඑක්කරන්න' ],
];

/** Slovak (slovenčina) */
$specialPageAliases['sk'] = [
	'CentralAuth' => [ 'CentrálneOverenie' ],
	'MergeAccount' => [ 'ZlúčenieÚčtov' ],
	'GlobalGroupMembership' => [ 'GlobálnePrávaPoužívateľa' ],
	'GlobalGroupPermissions' => [ 'GlobálneSkupinovéOprávnenia' ],
	'WikiSets' => [ 'UpraviťWikiMnožiny' ],
	'GlobalUsers' => [ 'GlobálniPoužívatelia' ],
];

/** Serbian (Cyrillic script) (српски (ћирилица)‎) */
$specialPageAliases['sr-ec'] = [
	'CentralAuth' => [ 'Централна_пријава', 'Глобални_налог' ],
	'MergeAccount' => [ 'Споји_налог' ],
	'GlobalGroupMembership' => [ 'Глобална_корисничка_права', 'Чланство_у_глобалним_групама' ],
	'GlobalGroupPermissions' => [ 'Глобална_права_група' ],
	'WikiSets' => [ 'Скупови_викија', 'Измени_скупове_викија' ],
	'GlobalUsers' => [ 'Глобални_корисници' ],
	'MultiLock' => [ 'Вишеструко_закључавање' ],
	'GlobalRenameUser' => [ 'Глобално_преименовање_корисника' ],
	'GlobalRenameProgress' => [ 'Напредак_глобалног_преименовања' ],
	'GlobalUserMerge' => [ 'Глобално_спајање_корисника' ],
	'GlobalRenameRequest' => [ 'Глобални_захтев_за_преименовање' ],
	'GlobalRenameQueue' => [ 'Глобални_ред_за_преименовање' ],
	'SulRenameWarning' => [ 'Упозорење_о_преименовању' ],
	'UsersWhoWillBeRenamed' => [ 'Корисници_који_ће_бити_преименовани' ],
];

/** Serbian (Latin script) (srpski (latinica)‎) */
$specialPageAliases['sr-el'] = [
	'CentralAuth' => [ 'Centralna_prijava', 'Globalni_nalog' ],
	'MergeAccount' => [ 'Spoji_nalog' ],
	'GlobalGroupMembership' => [ 'Globalna_korisnička_prava', 'Članstvo_u_globalnim_grupama' ],
	'GlobalGroupPermissions' => [ 'Globalna_prava_grupa' ],
	'WikiSets' => [ 'Skupovi_vikija', 'Izmeni_skupove_vikija' ],
	'GlobalUsers' => [ 'Globalni_korisnici' ],
	'MultiLock' => [ 'Višestruko_zaključavanje' ],
	'GlobalRenameUser' => [ 'Globalno_preimenovanje_korisnika' ],
	'GlobalRenameProgress' => [ 'Napredak_globalnog_preimenovanja' ],
	'GlobalUserMerge' => [ 'Globalno_spajanje_korisnika' ],
	'GlobalRenameRequest' => [ 'Globalni_zahtev_za_preimenovanje' ],
	'GlobalRenameQueue' => [ 'Globalni_red_za_preimenovanje' ],
	'SulRenameWarning' => [ 'Upozorenje_o_preimenovanju' ],
	'UsersWhoWillBeRenamed' => [ 'Korisnici_koji_će_biti_preimenovani' ],
];

/** Sundanese (Basa Sunda) */
$specialPageAliases['su'] = [
	'MergeAccount' => [ 'GabungRekening' ],
];

/** Swedish (svenska) */
$specialPageAliases['sv'] = [
	'CentralAuth' => [ 'Gemensam_inloggning' ],
	'MergeAccount' => [ 'Slå_ihop_konton' ],
	'GlobalGroupMembership' => [ 'Globala_användarrättigheter' ],
	'GlobalGroupPermissions' => [ 'Globala_grupper' ],
	'WikiSets' => [ 'Wikiset', 'Wikigrupp' ],
	'GlobalUsers' => [ 'Globala_användare' ],
	'GlobalRenameUser' => [ 'Globalt_användarnamnsbyte' ],
	'GlobalRenameProgress' => [ 'Globalt_namnbytesförlopp' ],
	'GlobalUserMerge' => [ 'Global_användarsammanslagning' ],
];

/** Swahili (Kiswahili) */
$specialPageAliases['sw'] = [
	'MergeAccount' => [ 'KusanyaAkaunti' ],
];

/** Tagalog (Tagalog) */
$specialPageAliases['tl'] = [
	'CentralAuth' => [ 'Lundayan_ng_pahintulot' ],
	'MergeAccount' => [ 'Pagsanibin_ang_akawnt' ],
	'GlobalGroupMembership' => [ 'Mga_karapatan_ng_pandaigdigang_tagagamit', 'Kasapian_sa_pandaigdigang_pangkat' ],
	'GlobalGroupPermissions' => [ 'Mga_kapahintulutan_ng_pandaigdigang_pangkat' ],
	'WikiSets' => [ 'Mga_pangkat_ng_pamamatnugot_ng_wiki' ],
	'GlobalUsers' => [ 'Pandaigdigang_mga_tagagamit' ],
];

/** Turkish (Türkçe) */
$specialPageAliases['tr'] = [
	'CentralAuth' => [ 'MerkeziKimlikDoğrulama' ],
	'MergeAccount' => [ 'HesapBirleştir', 'HesapBirleştirme' ],
	'GlobalGroupMembership' => [ 'KüreselGrupÜyeliği' ],
	'GlobalGroupPermissions' => [ 'KüreselGrupİzinleri' ],
	'WikiSets' => [ 'VikiDizileriniDüzenle' ],
	'GlobalUsers' => [ 'KüreselKullanıcılar' ],
];

/** Tatar (Cyrillic script) (татарча) */
$specialPageAliases['tt-cyrl'] = [
	'GlobalUsers' => [ 'Глобаль_кулланучылар' ],
];

/** Ukrainian (українська) */
$specialPageAliases['uk'] = [
	'MergeAccount' => [ 'Об\'єднання_облікових_записів', 'Объединение_учётных_записей' ],
	'GlobalGroupMembership' => [ 'Глобальні_права', 'Глобальные_права_участника', 'Глобальное_членство' ],
	'GlobalGroupPermissions' => [ 'Права_глобальних_груп', 'Права_глобальных_групп' ],
	'WikiSets' => [ 'Набори_вікі', 'Наборы_вики' ],
	'GlobalUsers' => [ 'Глобальні_користувачі', 'Глобальные_участники' ],
	'UsersWhoWillBeRenamed' => [ 'Користувачі_що_будуть_перейменовані' ],
];

/** Urdu (اردو) */
$specialPageAliases['ur'] = [
	'CentralAuth' => [ 'اختیار_مرکزی' ],
	'MergeAccount' => [ 'ضم_کھاتہ' ],
	'GlobalGroupMembership' => [ 'عالمی_اختیارات_صارف', 'عالمی_گروہی_رکنیت' ],
	'GlobalGroupPermissions' => [ 'عالمی_گروہی_اجازتیں' ],
	'WikiSets' => [ 'ویکی_مجموعات', 'ترمیم_ویکی_مجموعات' ],
	'GlobalUsers' => [ 'عالمی_صارفین' ],
	'MultiLock' => [ 'متعدد_قفل' ],
	'GlobalRenameUser' => [ 'عالمی_تبدیلی_صارف_نام' ],
	'GlobalRenameProgress' => [ 'پیشرفت_عالمی_تبدیلی_نام' ],
	'GlobalUserMerge' => [ 'ضم_عالمی_صارف' ],
	'GlobalRenameRequest' => [ 'درخواست_تبدیلی_عالمی_نام' ],
	'GlobalRenameQueue' => [ 'قطار_عالمی_تبدیلی_نام' ],
	'SulRenameWarning' => [ 'انتباہ_عالمی_تبدیلی_نام' ],
	'UsersWhoWillBeRenamed' => [ 'صارفین_جن_کے_نام_تبدیل_ہوں_گے' ],
];

/** Venetian (vèneto) */
$specialPageAliases['vec'] = [
	'MergeAccount' => [ 'UnissiUtense' ],
	'GlobalGroupMembership' => [ 'DiritiUtenteGlobali' ],
	'GlobalGroupPermissions' => [ 'ParmessiUtentiGlobali' ],
	'GlobalUsers' => [ 'UtentiGlobali' ],
];

/** Vietnamese (Tiếng Việt) */
$specialPageAliases['vi'] = [
	'CentralAuth' => [ 'Đăng_nhập_trung_ương', 'Thành_viên_toàn_cục', 'Thành_viên_toàn_cầu', 'Người_dùng_toàn_cục', 'Người_dùng_toàn_cầu' ],
	'MergeAccount' => [ 'Hợp_nhất_tài_khoản' ],
	'GlobalGroupMembership' => [ 'Quyền_thành_viên_toàn_cục' ],
	'GlobalGroupPermissions' => [ 'Quyền_nhóm_toàn_cục' ],
	'WikiSets' => [ 'Bộ_wiki' ],
	'GlobalUsers' => [ 'Danh_sách_thành_viên_toàn_cục', 'Danh_sách_thành_viên_toàn_cầu', 'Danh_sách_người_dùng_thống_nhất' ],
	'MultiLock' => [ 'Khóa_tài_khoản_toàn_cục' ],
	'GlobalRenameUser' => [ 'Đổi_tên_thành_viên_toàn_cục', 'Đổi_tên_người_dùng_toàn_cục' ],
	'GlobalRenameProgress' => [ 'Tiến_độ_đổi_tên_toàn_cục' ],
	'GlobalUserMerge' => [ 'Hợp_nhất_người_dùng_toàn_cục', 'Hợp_nhất_thành_viên_toàn_cục' ],
	'GlobalRenameRequest' => [ 'Yêu_cầu_đổi_tên_toàn_cục' ],
	'GlobalRenameQueue' => [ 'Hàng_đợi_đổi_tên_toàn_cục' ],
	'SulRenameWarning' => [ 'Cảnh_báo_đổi_tên_tài_khoản_hợp_nhất' ],
];

/** Yiddish (ייִדיש) */
$specialPageAliases['yi'] = [
	'GlobalUsers' => [ 'גלאבאלע_באניצער' ],
];

/** Simplified Chinese (中文（简体）‎) */
$specialPageAliases['zh-hans'] = [
	'CentralAuth' => [ '中央认证' ],
	'MergeAccount' => [ '合并账户', '整合账户' ],
	'GlobalGroupMembership' => [ '全域用户权限', '全域组成员资格' ],
	'GlobalGroupPermissions' => [ '全域组权限' ],
	'WikiSets' => [ 'Wiki集合', '编辑wiki集合' ],
	'GlobalUsers' => [ '全域用户' ],
	'MultiLock' => [ '批量锁定' ],
	'GlobalRenameUser' => [ '全域重命名用户' ],
	'GlobalRenameProgress' => [ '全域重命名状态' ],
	'GlobalUserMerge' => [ '全域用户合并' ],
	'GlobalRenameRequest' => [ '全域重命名申请' ],
	'GlobalRenameQueue' => [ '全域重命名队列' ],
	'SulRenameWarning' => [ 'SUL重命名警告' ],
	'UsersWhoWillBeRenamed' => [ '将被重命名的用户' ],
];

/** Traditional Chinese (中文（繁體）‎) */
$specialPageAliases['zh-hant'] = [
	'CentralAuth' => [ '中央認證' ],
	'MergeAccount' => [ '合併帳號' ],
	'GlobalGroupMembership' => [ '全域使用者權限' ],
	'GlobalGroupPermissions' => [ '全域群組權限' ],
	'WikiSets' => [ 'Wiki_集合', '編輯Wiki集合' ],
	'GlobalUsers' => [ '全域使用者' ],
	'MultiLock' => [ '批次鎖定' ],
	'GlobalRenameUser' => [ '全域重新命名使用者' ],
	'GlobalRenameProgress' => [ '全域重新命名進度' ],
	'GlobalUserMerge' => [ '全域使用者合併', '全域用户合併' ],
	'GlobalRenameRequest' => [ '全域重命名申請' ],
	'GlobalRenameQueue' => [ '全域重命名佇列' ],
	'UsersWhoWillBeRenamed' => [ '將被重命名的使用者' ],
];

/** Chinese (Hong Kong) (中文（香港）‎) */
$specialPageAliases['zh-hk'] = [
	'GlobalGroupMembership' => [ '全域用戶權限' ],
];
