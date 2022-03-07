<?php
/**
 * Aliases for special pages of CentralAuth extension that should not be
 * translated.
 *
 * Do not add this file to translatewiki.
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = [];

/** English (English) */
$specialPageAliases['en'] = [
	// Localizing Special:CentralAutoLogin causes issues (T56195) and is of
	// miniscule benefit to users, so don't do so.
	'CentralAutoLogin' => [ 'CentralAutoLogin' ],
	'CentralLogin' => [ 'CentralLogin' ],
];
