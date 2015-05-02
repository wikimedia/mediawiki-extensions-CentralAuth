<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CentralAuth' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CentralAuth'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['CentralAuthAliases'] = __DIR__ . '/CentralAuth.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for CentralAuth extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the CentralAuth extension requires MediaWiki 1.25+' );
}
