<?php
/**
 * Aliases for special pages of CentralAuth  extension.
 *
 * @file
 * @ingroup Extensions
 */

$aliases = array();

/** English
 * @author Jon Harald Søby
 */
$aliases['en'] = array(
	'CentralAuth'            => array( 'CentralAuth' ),
	'AutoLogin'              => array( 'AutoLogin' ),
	'MergeAccount'           => array( 'MergeAccount' ),
	'GlobalGroupMembership'  => array( 'GlobalUserRights', 'GlobalGroupMembership' ),
	'GlobalGroupPermissions' => array( 'GlobalGroupPermissions' ),
	'WikiSets'               => array( 'WikiSets', 'EditWikiSets' ),
	'GlobalUsers'            => array( 'GlobalUsers' ),
);

$aliases['af'] = array(
	'GlobalUsers'              => array( 'GlobaleGebruikers' ),
);

$aliases['ar'] = array(
	'CentralAuth'              => array( 'تحقق_مركزي' ),
	'AutoLogin'                => array( 'دخول_تلقائي' ),
	'MergeAccount'             => array( 'دمج_حساب' ),
	'GlobalGroupMembership'    => array( 'صلاحيات_المستخدم_العامة', 'عضوية_المجموعة_العامة' ),
	'GlobalGroupPermissions'   => array( 'سماحات_المجموعة_العامة' ),
	'WikiSets'                 => array( 'تعديل_مجموعات_الويكي' ),
	'GlobalUsers'              => array( 'مستخدمون_عامون' ),
);

$aliases['arz'] = array(
	'CentralAuth'              => array( 'تحقيق_مركزى' ),
	'AutoLogin'                => array( 'دخول_اوتوماتيكى' ),
	'MergeAccount'             => array( 'دمج_حساب' ),
	'GlobalGroupMembership'    => array( 'حقوق_اليوزر_العامه', 'عضوية_الجروپ_العامه' ),
	'GlobalGroupPermissions'   => array( 'اذن_الجروپ_العامه' ),
	'WikiSets'                 => array( 'تعديل_مجموعات_الويكى' ),
	'GlobalUsers'              => array( 'يوزرات_عامين' ),
);

$aliases['bg'] = array(
	'CentralAuth'              => array( 'Управление на единните сметки' ),
	'AutoLogin'                => array( 'Автоматично влизане' ),
	'MergeAccount'             => array( 'Обединяване на сметки' ),
	'GlobalGroupMembership'    => array( 'Глобални потребителски права' ),
	'GlobalUsers'              => array( 'Списък на глобалните сметки' ),
);

$aliases['bs'] = array(
	'CentralAuth'              => array( 'SredisnjaAutent' ),
	'AutoLogin'                => array( 'AutoPrijava' ),
	'MergeAccount'             => array( 'UjediniRacune' ),
	'GlobalGroupMembership'    => array( 'GlobalnaKorisnicnaPrava' ),
	'GlobalGroupPermissions'   => array( 'GlobalneDozvoleGrupa' ),
	'WikiSets'                 => array( 'UrediWikiSetove' ),
	'GlobalUsers'              => array( 'GlobalniKorisnici' ),
);

$aliases['ca'] = array(
	'MergeAccount'             => array( 'Fusió de comptes' ),
	'GlobalUsers'              => array( 'Usuaris globals' ),
);

$aliases['de'] = array(
	'CentralAuth'              => array( 'Verwaltung_Benutzerkonten-Zusammenführung' ),
	'AutoLogin'                => array( 'Automatische_Anmeldung' ),
	'MergeAccount'             => array( 'Benutzerkonten_zusammenführen' ),
	'GlobalGroupMembership'    => array( 'Globale_Benutzerzugehörigkeit' ),
	'GlobalGroupPermissions'   => array( 'Globale_Gruppenrechte' ),
	'WikiSets'                 => array( 'Wikigruppen', 'Wikigruppen_bearbeiten', 'Wikisets_bearbeiten' ),
	'GlobalUsers'              => array( 'Globale_Benutzerliste' ),
);

$aliases['dsb'] = array(
	'CentralAuth'              => array( 'Zjadnośenje_kontow' ),
	'AutoLogin'                => array( 'Awtomatiske_pśizjawjenje' ),
	'MergeAccount'             => array( 'Konta_zjadnośiś' ),
	'GlobalGroupMembership'    => array( 'Cłonkojstwo_w_globalnej_kupce' ),
	'GlobalGroupPermissions'   => array( 'Globalne_kupkowe_pšawa' ),
	'WikiSets'                 => array( 'Wikisajźby_wobźěłaś' ),
	'GlobalUsers'              => array( 'Globalne_wužywarje' ),
);

$aliases['el'] = array(
	'AutoLogin'                => array( 'ΑυτόματηΣύνδεση' ),
	'MergeAccount'             => array( 'ΣυγχώνευσηΛογαριασμού' ),
	'GlobalGroupMembership'    => array( 'ΚαθολικάΔικαιώματαΧρηστών' ),
	'GlobalGroupPermissions'   => array( 'ΚαθολικέςΆδειεςΧρηστών' ),
	'GlobalUsers'              => array( 'ΚαθολικοίΧρήστες' ),
);

$aliases['eo'] = array(
	'AutoLogin'                => array( 'Aŭtomata_ensaluto' ),
	'MergeAccount'             => array( 'Unuigi_konton' ),
	'GlobalUsers'              => array( 'Ĉieaj_uzantoj' ),
);

$aliases['es'] = array(
	'AutoLogin'                => array( 'Entrada_automática', 'Inicio_automático' ),
	'MergeAccount'             => array( 'Fusionar_cuenta_global', 'FusionarCuentaGlobal' ),
	'GlobalGroupMembership'    => array( 'Permisos_de_usuario_global', 'PermisosUsuarioGlobal' ),
	'GlobalGroupPermissions'   => array( 'Permisos_de_grupo_global', 'PermisosGrupoGlobal' ),
	'GlobalUsers'              => array( 'Usuarios_globales' ),
);

$aliases['et'] = array(
	'CentralAuth'              => array( 'Kontode_ühendamine' ),
	'AutoLogin'                => array( 'Automaatne_sisselogimine' ),
	'MergeAccount'             => array( 'Kontode_ühendamise_seis' ),
	'GlobalGroupMembership'    => array( 'Globaalse_kasutaja_õigused' ),
	'GlobalGroupPermissions'   => array( 'Globaalse_rühma_haldamine' ),
	'GlobalUsers'              => array( 'Globaalsed_kasutajad' ),
);

$aliases['fa'] = array(
	'CentralAuth'              => array( 'ورود_متمرکز' ),
	'AutoLogin'                => array( 'ورود_خودکار' ),
	'MergeAccount'             => array( 'ادغام_حساب' ),
	'GlobalGroupMembership'    => array( 'اختیارات_سراسری_کاربر' ),
	'GlobalGroupPermissions'   => array( 'اختیارات_سراسری_گروه' ),
	'WikiSets'                 => array( 'ویرایش_مجموعه‌های_ویکی' ),
	'GlobalUsers'              => array( 'کاربران_سراسری' ),
);

$aliases['fi'] = array(
	'CentralAuth'              => array( 'Keskitetty_varmennus' ),
	'AutoLogin'                => array( 'Automaattikirjautuminen' ),
	'MergeAccount'             => array( 'Yhdistä_tunnus' ),
	'GlobalUsers'              => array( 'Yhdistetyt_tunnukset' ),
);

$aliases['fr'] = array(
	'AutoLogin'                => array( 'Connexion_automatique', 'ConnexionAutomatique', 'ConnexionAuto', 'Login_automatique', 'LoginAutomatique', 'LoginAuto' ),
	'MergeAccount'             => array( 'Fusionner_le_compte', 'FusionnerLeCompte' ),
	'GlobalGroupMembership'    => array( 'Permissions_globales', 'PermissionGlobales' ),
	'GlobalGroupPermissions'   => array( 'Droits_des_groupes_globaux', 'DroitsDesGroupesGlobaux' ),
	'WikiSets'                 => array( 'Modifier_les_sets_de_wikis', 'ModifierLesSetsDeWiki' ),
	'GlobalUsers'              => array( 'Utilisateurs_globaux', 'UtilisateursGlobaux' ),
);

$aliases['frp'] = array(
	'CentralAuth'              => array( 'Administracion_des_comptos_fusionâs', 'AdministracionDesComptosFusionâs' ),
	'AutoLogin'                => array( 'Branchement_ôtomatico', 'BranchementÔtomatico' ),
	'MergeAccount'             => array( 'Fusionar_los_comptos', 'FusionarLosComptos' ),
	'GlobalGroupMembership'    => array( 'Pèrmissions_globâles', 'PèrmissionsGlobâles' ),
	'GlobalGroupPermissions'   => array( 'Drêts_a_les_tropes_globâles', 'DrêtsALesTropesGlobâles' ),
	'WikiSets'                 => array( 'Changiér_los_sèts_de_vouiquis', 'ChangiérLosSètsDeVouiquis' ),
	'GlobalUsers'              => array( 'Utilisators_globâls', 'UtilisatorsGlobâls' ),
);

$aliases['gl'] = array(
	'MergeAccount'             => array( 'Fusionar contas' ),
	'GlobalGroupMembership'    => array( 'Dereitos de usuario globais' ),
	'GlobalUsers'              => array( 'Usuarios globais' ),
);

$aliases['gsw'] = array(
	'CentralAuth'              => array( 'Verwaltig Benutzerchonte-Zämmefierig' ),
	'AutoLogin'                => array( 'Automatischi Aamäldig' ),
	'MergeAccount'             => array( 'Benutzerchonte zämmefiere' ),
	'GlobalGroupMembership'    => array( 'Wältwyti Benutzerrächt' ),
	'GlobalGroupPermissions'   => array( 'Wältwyti Grupperächt' ),
	'WikiSets'                 => array( 'Wikisets bearbeite' ),
	'GlobalUsers'              => array( 'Wältwyti Benutzerlischt' ),
);

$aliases['gu'] = array(
	'CentralAuth'              => array( 'કેન્દ્રીયશપથ' ),
	'AutoLogin'                => array( 'સ્વયંભૂલોગીન' ),
	'GlobalUsers'              => array( 'વૈશ્વીકસભ્ય' ),
);

$aliases['he'] = array(
	'CentralAuth'              => array( 'חשבון_משתמש_מאוחד' ),
	'AutoLogin'                => array( 'כניסה_אוטומטית' ),
	'MergeAccount'             => array( 'מיזוג_חשבונות' ),
	'GlobalGroupMembership'    => array( 'הרשאות_משתמש_כלליות', 'חברות_בקבוצות_כלליות' ),
	'GlobalGroupPermissions'   => array( 'הרשאות_קבוצות_כלליות' ),
	'WikiSets'                 => array( 'עריכת_קבוצות_אתרי_ויקי' ),
	'GlobalUsers'              => array( 'משתמשים_כלליים' ),
);

$aliases['hr'] = array(
	'CentralAuth'              => array( 'Središnja_prijava' ),
	'AutoLogin'                => array( 'AutoPrijava' ),
	'MergeAccount'             => array( 'Spoji_račun' ),
	'GlobalGroupMembership'    => array( 'Globalna_suradnička_prava' ),
	'GlobalGroupPermissions'   => array( 'Globalna_prava_skupina' ),
	'WikiSets'                 => array( 'Uredi_wikiset' ),
	'GlobalUsers'              => array( 'Globalni_suradnici' ),
);

$aliases['hsb'] = array(
	'CentralAuth'              => array( 'Zjednoćenje_kontow' ),
	'AutoLogin'                => array( 'Awtomatiske_přizjewjenje' ),
	'MergeAccount'             => array( 'Konta_zjednoćić' ),
	'GlobalGroupMembership'    => array( 'Globalne_wužiwarske_prawa' ),
	'GlobalGroupPermissions'   => array( 'Globalne_skupinske_prawa' ),
	'WikiSets'                 => array( 'Wikisadźby_wobdźěłać' ),
	'GlobalUsers'              => array( 'Globalni_wužiwarjo' ),
);

$aliases['hu'] = array(
	'CentralAuth'              => array( 'Központi_azonosítás' ),
	'AutoLogin'                => array( 'Automatikus_bejelentkezés' ),
	'MergeAccount'             => array( 'Szerkesztői_fiókok_egyesítése', 'Felhasználói_fiókok_egyesítése' ),
	'GlobalGroupMembership'    => array( 'Globális_szerkesztői_jogok', 'Globális_felhasználói_jogok' ),
	'GlobalGroupPermissions'   => array( 'Globális_szerkesztői_engedélyek', 'Globális_felhasználói_engedélyek' ),
	'WikiSets'                 => array( 'Wikicsoportok_szerkesztése' ),
	'GlobalUsers'              => array( 'Globális_szerkesztőlista', 'Globális_felhasználólista', 'Felhasználók_globális_listája' ),
);

$aliases['ia'] = array(
	'CentralAuth'              => array( 'Auth_central' ),
	'AutoLogin'                => array( 'Autosession', 'AutoSession' ),
	'MergeAccount'             => array( 'Fusionar_conto' ),
	'GlobalGroupMembership'    => array( 'Membrato_global_de_gruppos' ),
	'GlobalGroupPermissions'   => array( 'Permissiones_global_de_gruppos' ),
	'WikiSets'                 => array( 'Modificar_sets_de_wikis' ),
	'GlobalUsers'              => array( 'Usatores_global' ),
);

$aliases['id'] = array(
	'CentralAuth'              => array( 'Otoritas_pusat', 'OtoritasPusat' ),
	'AutoLogin'                => array( 'Masuk_log_otomatis', 'MasukLogOtomatis' ),
	'MergeAccount'             => array( 'Gabungkan_akun', 'GabungkanAkun' ),
	'GlobalGroupMembership'    => array( 'Hak_pengguna_global', 'HakPenggunaGlobal' ),
	'GlobalGroupPermissions'   => array( 'Hak_kelompok_global', 'HakKelompokGlobal' ),
	'WikiSets'                 => array( 'Sunting_kelompok_wiki', 'SuntingKelompokWiki' ),
	'GlobalUsers'              => array( 'Pengguna_global', 'PenggunaGlobal' ),
);

$aliases['it'] = array(
	'MergeAccount'             => array( 'UnificaUtenze' ),
	'GlobalGroupMembership'    => array( 'PermessiUtenteGlobale' ),
	'GlobalGroupPermissions'   => array( 'PermessiGruppoGlobale' ),
	'GlobalUsers'              => array( 'UtentiGlobali' ),
);

$aliases['ja'] = array(
	'CentralAuth'              => array( 'アカウント統一管理', '統一ログインの管理' ),
	'AutoLogin'                => array( '自動ログイン' ),
	'MergeAccount'             => array( 'アカウント統合' ),
	'GlobalGroupMembership'    => array( 'グローバルグループへの所属' ),
	'GlobalGroupPermissions'   => array( 'グローバルグループ権限', 'グローバルグループパーミッション' ),
	'WikiSets'                 => array( 'ウィキ集合', 'ウィキ群の編集' ),
	'GlobalUsers'              => array( 'グローバル利用者' ),
);

$aliases['km'] = array(
	'AutoLogin'                => array( 'ឡុកអ៊ីនដោយស្វ័យប្រវត្តិ' ),
	'MergeAccount'             => array( 'ច្របាច់បញ្ចូលគណនី' ),
);

$aliases['ko'] = array(
	'CentralAuth'              => array( '통합계정관리' ),
	'AutoLogin'                => array( '자동로그인' ),
	'MergeAccount'             => array( '계정합치기', '사용자합치기' ),
	'GlobalGroupMembership'    => array( '공통권한조정' ),
	'GlobalGroupPermissions'   => array( '전체 그룹 권한' ),
	'GlobalUsers'              => array( '통합계정목록', '공통계정목록' ),
);

$aliases['ksh'] = array(
	'AutoLogin'                => array( 'AutomatteschEnlogge' ),
	'GlobalGroupMembership'    => array( 'JemeinsamMetmaacherJroppeRääschte' ),
	'GlobalGroupPermissions'   => array( 'JemeinsamJroppe' ),
	'WikiSets'                 => array( 'WikiJroppe' ),
	'GlobalUsers'              => array( 'Jemeinsam_Metmaacher', 'JemeinsamMetmaacher', 'Jemeinsam_Medmaacher', 'JemeinsamMedmaacher' ),
);

$aliases['lad'] = array(
	'CentralAuth'              => array( 'CentralOtan' ),
	'AutoLogin'                => array( 'EntradaOtomatika' ),
	'MergeAccount'             => array( 'AjuntarCuentoGlobbal' ),
	'GlobalGroupMembership'    => array( 'Permessos_de_usador_globbal' ),
	'GlobalGroupPermissions'   => array( 'Permessos_de_grupo_globbal' ),
	'WikiSets'                 => array( 'ArreglarVikiSiras' ),
	'GlobalUsers'              => array( 'UsadoresGlobbales' ),
);

$aliases['lb'] = array(
	'CentralAuth'              => array( 'Verwaltung_vun_der_Benotzerkonten-Zesummeféierung' ),
	'AutoLogin'                => array( 'Automatesch_Umeldung' ),
	'MergeAccount'             => array( 'Benotzerkonten_zesummeféieren' ),
	'GlobalGroupMembership'    => array( 'Member_vu_globale_Benotzerrechter' ),
	'GlobalGroupPermissions'   => array( 'Global_Grupperechter' ),
	'WikiSets'                 => array( 'Wiki-Seten_änneren' ),
	'GlobalUsers'              => array( 'Global_Benotzer' ),
);

$aliases['lt'] = array(
	'AutoLogin'                => array( 'Automatinis_prisijungimas' ),
	'MergeAccount'             => array( 'Sujungti_sąskaitas' ),
);

$aliases['mk'] = array(
	'CentralAuth'              => array( 'ЦентралнаАвтентикација' ),
	'AutoLogin'                => array( 'АвтоматскоНајавување' ),
	'MergeAccount'             => array( 'СпојувањеНаСметки' ),
	'GlobalGroupMembership'    => array( 'ПраваНаГлобаленКорисник', 'ЧленствоВоГлобалнаГрупа' ),
	'GlobalGroupPermissions'   => array( 'ДозволиНаГлобалнаГрупа' ),
	'GlobalUsers'              => array( 'ГлобалниКорисници' ),
);

$aliases['ml'] = array(
	'CentralAuth'              => array( 'കേന്ദ്രീകൃത_അംഗീകാരം' ),
	'AutoLogin'                => array( 'സ്വയംപ്രവേശനം' ),
	'MergeAccount'             => array( 'അംഗത്വസം‌യോജനം' ),
	'GlobalGroupMembership'    => array( 'ആഗോള_ഉപയോക്തൃ_അവകാശങ്ങൾ', 'ആഗോള_ഉപയോക്തൃ_അംഗത്വം' ),
	'GlobalGroupPermissions'   => array( 'ആഗോള_അംഗത്വാനുമതികൾ' ),
	'WikiSets'                 => array( 'വിക്കിഗണങ്ങൾ_തിരുത്തുക' ),
	'GlobalUsers'              => array( 'ആഗോള_ഉപയോക്താക്കൾ' ),
);

$aliases['mr'] = array(
	'CentralAuth'              => array( 'मध्यवर्तीअधिकारी' ),
	'AutoLogin'                => array( 'स्वयंप्रवेश' ),
	'MergeAccount'             => array( 'खातेविलीनीकरण' ),
	'GlobalGroupMembership'    => array( 'वैश्विकसदस्याधिकार', 'वैश्विकगटसदस्यता' ),
	'GlobalGroupPermissions'   => array( 'वैश्विकगटपरवानग्या' ),
	'WikiSets'                 => array( 'विकिसंचसंपादा' ),
	'GlobalUsers'              => array( 'वैश्विकसदस्य' ),
);

$aliases['ms'] = array(
	'MergeAccount'             => array( 'Gabungkan akaun' ),
	'GlobalGroupMembership'    => array( 'Hak kumpulan sejagat' ),
	'GlobalGroupPermissions'   => array( 'Keizinan kumpulan sejagat' ),
	'WikiSets'                 => array( 'Ubah set wiki' ),
	'GlobalUsers'              => array( 'Pengguna sejagat' ),
);

$aliases['mt'] = array(
	'AutoLogin'                => array( 'LoginAwtomatiku', 'DħulAwtomatiku' ),
	'MergeAccount'             => array( 'WaħħadKont' ),
	'GlobalUsers'              => array( 'UtentiGlobali' ),
);

$aliases['myv'] = array(
	'MergeAccount'             => array( 'ВейтьсэндямсСовамоТарка' ),
);

$aliases['nds-nl'] = array(
	'CentralAuth'              => array( 'Centraal_anmelden' ),
	'AutoLogin'                => array( 'Autematisch_anmelden' ),
	'MergeAccount'             => array( 'Gebruker_samenvoegen' ),
	'GlobalGroupMembership'    => array( 'Globale_gebrukersrechen' ),
	'GlobalGroupPermissions'   => array( 'Globale_groepsrechen' ),
	'WikiSets'                 => array( 'Wikigroepen_bewarken' ),
	'GlobalUsers'              => array( 'Globale_gebrukers' ),
);

$aliases['nl'] = array(
	'CentralAuth'              => array( 'CentraalAanmelden' ),
	'AutoLogin'                => array( 'AutomatischAanmelden', 'AutoAanmelden' ),
	'MergeAccount'             => array( 'GebruikerSamenvoegen' ),
	'GlobalGroupMembership'    => array( 'GlobaalGroepslidmaatschap' ),
	'GlobalGroupPermissions'   => array( 'GlobaleGroepsrechten' ),
	'WikiSets'                 => array( 'WikigroepenBewerken' ),
	'GlobalUsers'              => array( 'GlobaleGebruikers' ),
);

$aliases['nn'] = array(
	'MergeAccount'             => array( 'Kontosamanslåing' ),
	'GlobalGroupMembership'    => array( 'Globale_brukarrettar' ),
	'GlobalUsers'              => array( 'Globale_brukarar' ),
);

$aliases['no'] = array(
	'CentralAuth'              => array( 'Enhetlig_innlogging' ),
	'AutoLogin'                => array( 'Automatisk_innlogging' ),
	'MergeAccount'             => array( 'Kontosammenslåing' ),
	'GlobalGroupMembership'    => array( 'Globale_brukerrettigheter' ),
	'GlobalGroupPermissions'   => array( 'Globale_gruppetillatelser' ),
	'WikiSets'                 => array( 'Rediger_wikisett' ),
	'GlobalUsers'              => array( 'Globale_brukere' ),
);

$aliases['oc'] = array(
	'AutoLogin'                => array( 'Login_Automatic', 'LoginAutomatic', 'LoginAuto' ),
	'MergeAccount'             => array( 'Fusionar_lo_compte', 'FusionarLoCompte' ),
	'GlobalGroupMembership'    => array( 'Permissions_globalas', 'PermissionGlobalas' ),
	'GlobalGroupPermissions'   => array( 'Dreches_dels_gropes_globals', 'DrechesDelsGropesGlobals' ),
	'WikiSets'                 => array( 'Modificar_los_sets_de_wikis', 'ModificarLosSetsDeWiki' ),
	'GlobalUsers'              => array( 'Utilizaires_globals', 'UtilizairesGlobals' ),
);

$aliases['pl'] = array(
	'CentralAuth'              => array( 'Zarządzanie_kontem_uniwersalnym' ),
	'AutoLogin'                => array( 'Automatyczne_logowanie' ),
	'MergeAccount'             => array( 'Łączenie_kont', 'Konto_uniwersalne' ),
	'GlobalGroupMembership'    => array( 'Globalne_uprawnienia' ),
	'GlobalGroupPermissions'   => array( 'Globalne_uprawnienia_grup' ),
	'GlobalUsers'              => array( 'Spis_kont_uniwersalnych' ),
);

$aliases['ps'] = array(
	'GlobalUsers'              => array( 'نړېوال_کارنان' ),
);

$aliases['pt'] = array(
	'AutoLogin'                => array( 'Autenticação_automática' ),
	'MergeAccount'             => array( 'Fundir_conta' ),
	'GlobalGroupMembership'    => array( 'Grupos_globais' ),
	'GlobalGroupPermissions'   => array( 'Privilégios_globais_de_grupo' ),
	'GlobalUsers'              => array( 'Utilizadores_globais' ),
);

$aliases['pt-br'] = array(
	'AutoLogin'                => array( 'Login_automático' ),
	'MergeAccount'             => array( 'Mesclar_conta' ),
	'GlobalUsers'              => array( 'Usuários_globais' ),
);

$aliases['ro'] = array(
	'CentralAuth'              => array( 'Autentificare_centrală' ),
	'AutoLogin'                => array( 'Autentificare_automată' ),
	'MergeAccount'             => array( 'Unește_conturi' ),
	'GlobalGroupMembership'    => array( 'Drepturi_globale_utilizator', 'Membru_global_grup' ),
	'GlobalGroupPermissions'   => array( 'Permisiuni_grup_globale' ),
	'WikiSets'                 => array( 'Setări_modificare_Wiki' ),
	'GlobalUsers'              => array( 'Utilizatori_globali' ),
);

$aliases['sa'] = array(
	'CentralAuth'              => array( 'मध्यवर्तीप्रामान्य' ),
	'AutoLogin'                => array( 'स्वयमेवप्रवेश' ),
	'MergeAccount'             => array( 'उपयोजकसंज्ञासंयोग' ),
	'GlobalGroupMembership'    => array( 'वैश्विकसदस्याधिकार' ),
	'GlobalGroupPermissions'   => array( 'वैश्विकगटसंमती' ),
	'WikiSets'                 => array( 'सम्पादनविकिगट' ),
	'GlobalUsers'              => array( 'वैश्विकयोजक' ),
);

$aliases['si'] = array(
	'CentralAuth'              => array( 'මධ්‍යඅවසර' ),
	'AutoLogin'                => array( 'ස්වයංක්‍රීයපිවිසුම' ),
	'MergeAccount'             => array( 'ගිණුමඑක්කරන්න' ),
);

$aliases['sk'] = array(
	'CentralAuth'              => array( 'CentrálneOverenie' ),
	'AutoLogin'                => array( 'AutomatickéPrihlasovanie' ),
	'MergeAccount'             => array( 'ZlúčenieÚčtov' ),
	'GlobalGroupMembership'    => array( 'GlobálnePrávaPoužívateľa' ),
	'GlobalGroupPermissions'   => array( 'GlobálneSkupinovéOprávnenia' ),
	'WikiSets'                 => array( 'UpraviťWikiMnožiny' ),
	'GlobalUsers'              => array( 'GlobálniPoužívatelia' ),
);

$aliases['su'] = array(
	'MergeAccount'             => array( 'GabungRekening' ),
);

$aliases['sv'] = array(
	'CentralAuth'              => array( 'Gemensam_inloggning' ),
	'AutoLogin'                => array( 'Automatisk_inloggning' ),
	'MergeAccount'             => array( 'Slå_ihop_konton' ),
	'GlobalUsers'              => array( 'Globala_användare' ),
);

$aliases['sw'] = array(
	'AutoLogin'                => array( 'IngiaEFnyewe' ),
	'MergeAccount'             => array( 'KusanyaAkaunti' ),
);

$aliases['tl'] = array(
	'CentralAuth'              => array( 'Lundayan ng pahintulot' ),
	'AutoLogin'                => array( 'Kusang paglagda' ),
	'MergeAccount'             => array( 'Pagsanibin ang akawnt' ),
	'GlobalGroupMembership'    => array( 'Mga karapatan ng pandaigdigang tagagamit', 'Kasapian sa pandaigdigang pangkat' ),
	'GlobalGroupPermissions'   => array( 'Mga kapahintulutan ng pandaigdigang pangkat' ),
	'WikiSets'                 => array( 'Mga pangkat ng pamamatnugot ng wiki' ),
	'GlobalUsers'              => array( 'Pandaigdigang mga tagagamit' ),
);

$aliases['tr'] = array(
	'AutoLogin'                => array( 'OtomatikOturumAçma' ),
	'MergeAccount'             => array( 'HesapBirleştirmeDurumu' ),
	'GlobalGroupMembership'    => array( 'KüreselGrupÜyeliği' ),
	'GlobalGroupPermissions'   => array( 'KüreselGrupİzinleri' ),
	'WikiSets'                 => array( 'VikiDizileriniDüzenle' ),
	'GlobalUsers'              => array( 'KüreselKullanıcılar' ),
);

$aliases['vec'] = array(
	'MergeAccount'             => array( 'UnissiUtense' ),
	'GlobalGroupMembership'    => array( 'DiritiUtenteGlobali' ),
	'GlobalGroupPermissions'   => array( 'ParmessiUtentiGlobali' ),
	'GlobalUsers'              => array( 'UtentiGlobali' ),
);

$aliases['zh-hans'] = array(
	'CentralAuth'              => array( '中央认证' ),
	'AutoLogin'                => array( '自动登录' ),
	'MergeAccount'             => array( '整合账户' ),
	'GlobalGroupMembership'    => array( '全域用户权利', '全域组成员资格' ),
	'GlobalGroupPermissions'   => array( '全域组权限' ),
	'WikiSets'                 => array( '编辑wiki组' ),
	'GlobalUsers'              => array( '全域用户' ),
);

$aliases['zh-hant'] = array(
	'CentralAuth'              => array( '中央認證' ),
	'AutoLogin'                => array( '自動登錄' ),
	'MergeAccount'             => array( '整合賬戶' ),
	'GlobalGroupMembership'    => array( '全域用戶權利', '全域組成員資格', '全域用戶權限' ),
	'GlobalGroupPermissions'   => array( '全域組權限' ),
	'WikiSets'                 => array( '編輯Wiki組' ),
	'GlobalUsers'              => array( '全域用戶' ),
);

$aliases['zh-hk'] = array(
	'GlobalGroupMembership'    => array( '全域用戶權限' ),
);
