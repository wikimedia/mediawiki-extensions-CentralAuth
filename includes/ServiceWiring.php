<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUIService;
use MediaWiki\Extension\CentralAuth\CentralAuthWikiListService;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserValidator;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

// PHPUnit does not understand coverage for this file.
// It is covered though, see CentralAuthServiceWiringTest.
// @codeCoverageIgnoreStart

return [
	'CentralAuth.CentralAuthDatabaseManager' => static function (
		MediaWikiServices $services
	): CentralAuthDatabaseManager {
		return new CentralAuthDatabaseManager(
			new ServiceOptions( CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getDBLoadBalancerFactory(),
			$services->getReadOnlyMode()
		);
	},
	'CentralAuth.CentralAuthForcedLocalCreationService' => static function (
		MediaWikiServices $services
	): CentralAuthForcedLocalCreationService {
		return new CentralAuthForcedLocalCreationService(
			$services->getUserFactory(),
			CentralAuthServices::getUtilityService( $services )
		);
	},
	'CentralAuth.CentralAuthSessionManager' => static function (
		MediaWikiServices $services
	): CentralAuthSessionManager {
		return new CentralAuthSessionManager(
			new ServiceOptions(
				CentralAuthSessionManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getStatsdDataFactory()
		);
	},
	'CentralAuth.CentralAuthUIService' => static function (
		MediaWikiServices $services
	): CentralAuthUIService {
		return new CentralAuthUIService(
			$services->getTitleFactory()
		);
	},
	'CentralAuth.CentralAuthUtilityService' => static function (
		MediaWikiServices $services
	): CentralAuthUtilityService {
		return new CentralAuthUtilityService(
			$services->getMainConfig(),
			$services->getAuthManager(),
			$services->getTitleFactory()
		);
	},
	'CentralAuth.CentralAuthWikiListService' => static function (
		MediaWikiServices $services
	): CentralAuthWikiListService {
		return new CentralAuthWikiListService(
			new ServiceOptions( CentralAuthWikiListService::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getHookContainer()
		);
	},
	'CentralAuth.GlobalRenameDenylist' => static function ( MediaWikiServices $services ): GlobalRenameDenylist {
		$config = $services->getMainConfig();
		return new GlobalRenameDenylist(
			LoggerFactory::getInstance( 'CentralAuthRename' ),
			$services->getHttpRequestFactory(),
			$services->getWikiPageFactory(),
			$config->get( 'GlobalRenameDenylist' ),
			$config->get( 'GlobalRenameDenylistRegex' )
		);
	},
	'CentralAuth.GlobalRenameRequestStore' => static function (
		MediaWikiServices $services
	): GlobalRenameRequestStore {
		return new GlobalRenameRequestStore(
			CentralAuthServices::getDatabaseManager( $services ),
			$services->getUserNameUtils()
		);
	},
	'CentralAuth.GlobalRenameUserValidator' => static function (
		MediaWikiServices $services
	): GlobalRenameUserValidator {
		return new GlobalRenameUserValidator(
			$services->getUserNameUtils()
		);
	},
	'CentralAuth.GlobalGroupLookup' => static function ( MediaWikiServices $services ): GlobalGroupLookup {
		return new GlobalGroupLookup(
			CentralAuthServices::getDatabaseManager( $services )
		);
	},
];

// @codeCoverageIgnoreEnd
