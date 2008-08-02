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
	'SpecialCentralAuth'            => array( 'CentralAuth' ),
        'SpecialEditWikiSets'           => array( 'EditWikiSets' ),
        'SpecialGlobalGroupMembership'  => array( 'GlobalUserRights', 'GlobalGroupMembership' ),
        'SpecialGlobalGroupPermissions' => array( 'GlobalGroupPermissions' ),
        'SpecialGlobalUsers'            => array( 'GlobalUsers' ),
        'SpecialMergeAccount'           => array( 'MergeAccount' ),
);

/** Arabic (العربية)
 * @author Meno25
 */
$aliases['ar'] = array(
	'SpecialCentralAuth'              => array( 'تحقق مركزي' ),
	'SpecialEditWikiSets'             => array( 'تعديل مجموعات الويكي' ),
	'SpecialGlobalGroupMembership'    => array( 'صلاحيات المستخدم العامة', 'عضوية المجموعة العامة' ),
	'SpecialGlobalGroupPermissions'   => array( 'سماحات المجموعة العامة' ),
	'SpecialGlobalUsers'              => array( 'مستخدمون عامون' ),
	'SpecialMergeAccount'             => array( 'دمج حساب' ),
);
