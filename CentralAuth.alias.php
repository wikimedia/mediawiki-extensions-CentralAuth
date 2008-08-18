<?php
/**
 * Aliases for special pages of CentralAuth  extension.
 *
 * @addtogroup Extensions
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
	'EditWikiSets'           => array( 'EditWikiSets' ),
	'GlobalUsers'            => array( 'GlobalUsers' ),
);

/** Arabic (العربية)
 * @author Meno25
 */
$aliases['ar'] = array(
	'CentralAuth' => array( 'تحقق_مركزي' ),
	'AutoLogin' => array( 'دخول_تلقائي' ),
	'MergeAccount' => array( 'دمج_حساب' ),
	'GlobalGroupMembership' => array( 'صلاحيات_المستخدم_العامة', 'عضوية_المجموعة_العامة' ),
	'GlobalGroupPermissions' => array( 'سماحات_المجموعة_العامة' ),
	'EditWikiSets' => array( 'تعديل_مجموعات_الويكي' ),
	'GlobalUsers' => array( 'مستخدمون_عامون' ),
);

/** Egyptian Spoken Arabic (مصرى)
 * @author Meno25
 */
$aliases['arz'] = array(
	'CentralAuth' => array( 'تحقق_مركزى' ),
	'AutoLogin' => array( 'دخول_تلقائى' ),
	'MergeAccount' => array( 'دمج_حساب' ),
	'GlobalGroupMembership' => array( 'صلاحيات_المستخدم_العامة', 'عضوية_المجموعة_العامة' ),
	'GlobalGroupPermissions' => array( 'سماحات_المجموعة_العامة' ),
	'EditWikiSets' => array( 'تعديل_مجموعات_الويكى' ),
	'GlobalUsers' => array( 'مستخدمون_عامون' ),
);

/** German (Deutsch) */
$aliases['de'] = array(
	'CentralAuth' => array( 'Verwaltung Benutzerkonten-Zusammenführung' ),
	'AutoLogin' => array( 'Automatische Anmeldung' ),
	'MergeAccount' => array( 'Benutzerkonten zusammenführen' ),
	'GlobalGroupMembership' => array( 'Globale Benutzerrechte' ),
	'GlobalGroupPermissions' => array( 'Globale Gruppenrechte' ),
	'GlobalUsers' => array( 'Globale Benutzerliste' ),
);

/** Hebrew (עברית)
 * @author Rotem Liss
 */
$aliases['he'] = array(
	'CentralAuth'            => array( 'חשבון_משתמש_מאוחד' ),
	'MergeAccount'           => array( 'מיזוג_חשבונות' ),
	'GlobalGroupMembership'  => array( 'הרשאות_משתמש_כלליות', 'חברות_בקבוצות_כלליות' ),
	'GlobalGroupPermissions' => array( 'הרשאות_קבוצות_כלליות' ),
	'EditWikiSets'           => array( 'עריכת_קבוצות_אתרי_ויקי' ),
	'GlobalUsers'            => array( 'משתמשים_כלליים' ),
);

/** Croatian (Hrvatski) */
$aliases['hr'] = array(
	'CentralAuth' => array( 'Središnja_prijava' ),
	'MergeAccount' => array( 'Spoji_račun' ),
	'GlobalGroupMembership' => array( 'Globalna_suradnička_prava' ),
	'EditWikiSets' => array( 'Uredi_wikiset' ),
	'GlobalUsers' => array( 'Globalni_suradnici' ),
);

/** Hungarian (Magyar) */
$aliases['hu'] = array(
	'CentralAuth' => array( 'Központi azonosítás' ),
	'AutoLogin' => array( 'Automatikus bejelentkezés' ),
	'MergeAccount' => array( 'Felhasználói fiókok egyesítése' ),
	'GlobalUsers' => array( 'Felhasználók globális listája' ),
);

/** Korean (한국어) */
$aliases['ko'] = array(
	'MergeAccount' => array( '계정합치기', '사용자합치기' ),
);

/** Luxembourgish (Lëtzebuergesch) */
$aliases['lb'] = array(
	'AutoLogin' => array( 'Automatesch Umeldung' ),
	'MergeAccount' => array( 'Benotzerkonten zesummeféieren' ),
	'GlobalGroupMembership' => array( 'Global Benotzerrechter' ),
	'GlobalGroupPermissions' => array( 'Global Grupperechter' ),
	'GlobalUsers' => array( 'Global Benotzer' ),
);

/** Malay (Bahasa Melayu) */
$aliases['ms'] = array(
	'MergeAccount' => array( 'Gabungkan_akaun' ),
	'GlobalGroupMembership' => array( 'Hak_kumpulan_sejagat' ),
	'GlobalGroupPermissions' => array( 'Keizinan_kumpulan_sejagat' ),
	'EditWikiSets' => array( 'Ubah_set_wiki' ),
	'GlobalUsers' => array( 'Pengguna_sejagat' ),
);

/** Dutch (Nederlands) */
$aliases['nl'] = array(
	'CentralAuth' => array( 'CentraalAanmelden' ),
	'AutoLogin' => array( 'AutomatischAanmelden', 'AutoAanmelden' ),
	'MergeAccount' => array( 'GebruikerSamenvoegen' ),
	'GlobalGroupMembership' => array( 'GlobaleGebruikersrechten' ),
	'GlobalGroupPermissions' => array( 'GlobaleGroepsrechten' ),
	'EditWikiSets' => array( 'WikigroepenBewerken' ),
	'GlobalUsers' => array( 'GlobaleGebruikers' ),
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬) */
$aliases['no'] = array(
	'CentralAuth' => array( 'Enhetlig innlogging' ),
	'AutoLogin' => array( 'Automatisk innlogging' ),
	'MergeAccount' => array( 'Kontosammenslåing' ),
	'GlobalGroupMembership' => array( 'Globale brukerrettigheter' ),
	'GlobalGroupPermissions' => array( 'Globale gruppetillatelser' ),
	'EditWikiSets' => array( 'Rediger wikisett' ),
	'GlobalUsers' => array( 'Globale brukere' ),
);

/** Polish (Polski) */
$aliases['pl'] = array(
	'CentralAuth' => array( 'Zarządzanie kontem uniwersalnym' ),
	'MergeAccount' => array( 'Łączenie kont', 'Konto uniwersalne' ),
	'GlobalUsers' => array( 'Spis kont uniwersalnych' ),
);

/** Swedish (Svenska) */
$aliases['sv'] = array(
	'CentralAuth' => array( 'Gemensam inloggning' ),
	'GlobalUsers' => array( 'Globala användare' ),
);

