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
// @codingStandardsIgnoreFile

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	// Localizing Special:CentralAutoLogin causes issues (bug 54195) and is of
	// miniscule benefit to users, so don't do so.
	'CentralAutoLogin' => array( 'CentralAutoLogin' ),
	'CentralLogin' => array( 'CentralLogin' ),
);
