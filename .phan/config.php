<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/AbuseFilter',
		'../../extensions/AntiSpoof',
		'../../extensions/CheckUser',
		'../../extensions/Echo',
		'../../extensions/GlobalBlocking',
		'../../extensions/GlobalPreferences',
		'../../extensions/MassMessage',
		'../../extensions/MobileFrontend',
		'../../extensions/SecurePoll',
		'../../extensions/TitleBlacklist',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/AbuseFilter',
		'../../extensions/AntiSpoof',
		'../../extensions/CheckUser',
		'../../extensions/Echo',
		'../../extensions/GlobalBlocking',
		'../../extensions/GlobalPreferences',
		'../../extensions/MassMessage',
		'../../extensions/MobileFrontend',
		'../../extensions/SecurePoll',
		'../../extensions/TitleBlacklist',
	]
);

$cfg['exclude_file_list'] = array_merge(
	$cfg['exclude_file_list'],
	[
		'../../extensions/MassMessage/.phan/stubs/Event.php',
	]
);

$cfg['plugins'] = array_merge( $cfg['plugins'], [
	'PHPDocRedundantPlugin',
] );

return $cfg;
