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
