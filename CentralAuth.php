<?php

$wgSpecialPages['MergeAccount'] = 'SpecialMergeAccount';
$wgAutoloadClasses['SpecialMergeAccount'] =
	dirname( __FILE__ ) . "/SpecialMergeAccount.php";

$wgExtensionFunctions[] = 'wfSetupCentralAuth';

function wfSetupCentralAuth() {
	require dirname( __FILE__ ) . '/CentralAuth.i18n.php';
	global $wgCentralAuthMessages, $wgMessageCache;
	foreach( $wgCentralAuthMessages as $key => $messages ) {
		$wgMessageCache->addMessages( $messages, $key );
	}
}


?>