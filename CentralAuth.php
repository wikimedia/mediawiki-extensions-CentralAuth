<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CentralAuth' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['SpecialCentralAuth'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['SpecialCentralAuthAliases'] = __DIR__ . '/CentralAuth.alias.php';
	$wgExtensionMessagesFiles['SpecialCentralAuthAliasesNoTranslate'] = __DIR__ . '/CentralAuth.notranslate-alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for CentralAuth extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the CentralAuth extension requires MediaWiki 1.25+' );
}
