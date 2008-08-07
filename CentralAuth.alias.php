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
	'CentralAuth'            => array( 'تحقق_مركزي' ),
	'EditWikiSets'           => array( 'تعديل_مجموعات_الويكي' ),
	'GlobalGroupMembership'  => array( 'صلاحيات_المستخدم_العامة', 'عضوية_المجموعة_العامة' ),
	'GlobalGroupPermissions' => array( 'سماحات_المجموعة_العامة' ),
	'GlobalUsers'            => array( 'مستخدمون_عامون' ),
	'MergeAccount'           => array( 'دمج_حساب' ),
);

/** Egyptian Spoken Arabic (مصرى)
 * @author Meno25
 */
$aliases['arz'] = array(
	'CentralAuth'            => array( 'تحقق_مركزى' ),
	'EditWikiSets'           => array( 'تعديل_مجموعات_الويكى' ),
	'GlobalGroupMembership'  => array( 'صلاحيات_المستخدم_العامة', 'عضوية_المجموعة_العامة' ),
	'GlobalGroupPermissions' => array( 'سماحات_المجموعة_العامة' ),
	'GlobalUsers'            => array( 'مستخدمون_عامون' ),
	'MergeAccount'           => array( 'دمج_حساب' ),
);

/** Hebrew (עברית)
 * @author Rotem Liss
 */
$aliases['he'] = array(
	'CentralAuth'            => array( 'חשבון_משתמש_מאוחד' ),
	'EditWikiSets'           => array( 'עריכת_קבוצות_אתרי_ויקי' ),
	'GlobalGroupMembership'  => array( 'הרשאות_משתמש_כלליות', 'חברות_בקבוצות_כלליות' ),
	'GlobalGroupPermissions' => array( 'הרשאות_קבוצות_כלליות' ),
	'GlobalUsers'            => array( 'משתמשים_כלליים' ),
	'MergeAccount'           => array( 'מיזוג_חשבונות' ),
);

/** Malay (Bahasa Melayu) */
$aliases['ms'] = array(
	'EditWikiSets'           => array( 'Ubah_set_wiki' ),
	'GlobalGroupMembership'  => array( 'Hak_kumpulan_sejagat' ),
	'GlobalGroupPermissions' => array( 'Keizinan_kumpulan_sejagat' ),
	'GlobalUsers'            => array( 'Pengguna_sejagat' ),
	'MergeAccount'           => array( 'Gabungkan_akaun' ),
);

/** Dutch (Nederlands) */
$aliases['nl'] = array(
	'CentralAuth'            => array( 'CentraalAanmelden' ),
	'EditWikiSets'           => array( 'WikigroepenBewerken' ),
	'GlobalGroupMembership'  => array( 'GlobaleGebruikersrechten' ),
	'GlobalGroupPermissions' => array( 'GlobaleGroepsrechten' ),
	'GlobalUsers'            => array( 'GlobaleGebruikers' ),
	'MergeAccount'           => array( 'GebruikerSamenvoegen' ),
);

/** Norwegian (bokmål)‬ (‪Norsk (bokmål)‬) */
$aliases['no'] = array(
	'EditWikiSets'           => array( 'Rediger wikisett' ),
	'GlobalGroupMembership'  => array( 'Globale brukerrettigheter' ),
	'GlobalGroupPermissions' => array( 'Globale gruppetillatelser' ),
	'GlobalUsers'            => array( 'Globale brukere' ),
	'MergeAccount'           => array( 'Slå sammen kontoer' ),
);

/** Swedish (Svenska) */
$aliases['sv'] = array(
	'CentralAuth' => array( 'Gemensam inloggning' ),
	'GlobalUsers' => array( 'Globala användare' ),
);

