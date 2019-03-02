<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/AbuseFilter',
		'../../extensions/AntiSpoof',
		'../../extensions/EventLogging',
		'../../extensions/MassMessage',
		'../../extensions/MobileFrontend',
		'../../extensions/Renameuser',
		'../../extensions/TitleBlacklist',
		'../../extensions/UserMerge',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/AbuseFilter',
		'../../extensions/AntiSpoof',
		'../../extensions/EventLogging',
		'../../extensions/MassMessage',
		'../../extensions/MobileFrontend',
		'../../extensions/Renameuser',
		'../../extensions/TitleBlacklist',
		'../../extensions/UserMerge',
	]
);

return $cfg;
