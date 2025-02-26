<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserValidator;
use MediaWiki\Extension\CentralAuth\User\CentralAuthAntiSpoofManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthForcedLocalCreationService;
use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilderFactory;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\Options\UserOptionsManager;

// PHPUnit does not understand coverage for this file.
// It is covered though, see CentralAuthServiceWiringTest.
// @codeCoverageIgnoreStart

return [

	'CentralAuth.FilteredRequestTracker' => static function (
		MediaWikiServices $services
	): FilteredRequestTracker {
		return new FilteredRequestTracker();
	},

	'CentralAuth.CentralAuthAntiSpoofManager' => static function (
		MediaWikiServices $services
	): CentralAuthAntiSpoofManager {
		return new CentralAuthAntiSpoofManager(
			new ServiceOptions( CentralAuthAntiSpoofManager::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			LoggerFactory::getInstance( 'antispoof' ),
			$services->getDBLoadBalancerFactory(),
			CentralAuthServices::getDatabaseManager( $services )
		);
	},

	'CentralAuth.CentralAuthApiTokenGenerator' => static function (
		MediaWikiServices $services
	): CentralAuthApiTokenGenerator {
		return new CentralAuthApiTokenGenerator(
			CentralAuthServices::getTokenManager( $services )
		);
	},

	'CentralAuth.CentralAuthAutomaticGlobalGroupManager' => static function (
		MediaWikiServices $services
	): CentralAuthAutomaticGlobalGroupManager {
		return new CentralAuthAutomaticGlobalGroupManager(
			new ServiceOptions(
				CentralAuthAutomaticGlobalGroupManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
		);
	},

	'CentralAuth.CentralAuthDatabaseManager' => static function (
		MediaWikiServices $services
	): CentralAuthDatabaseManager {
		return new CentralAuthDatabaseManager(
			new ServiceOptions( CentralAuthDatabaseManager::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getDBLoadBalancerFactory(),
			$services->getReadOnlyMode()
		);
	},

	'CentralAuth.CentralAuthEditCounter' => static function (
		MediaWikiServices $services
	): CentralAuthEditCounter {
		return new CentralAuthEditCounter(
			CentralAuthServices::getDatabaseManager( $services ),
			$services->getMainWANObjectCache()
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
			$services->getStatsdDataFactory(),
			$services->getStatsFactory()
		);
	},

	'CentralAuth.CentralAuthTokenManager' => static function (
		MediaWikiServices $services
	): CentralAuthTokenManager {
		return new CentralAuthTokenManager(
			$services->getMicroStash(),
			LoggerFactory::getInstance( 'CentralAuth' )
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
			$services->getTitleFactory(),
			$services->getJobQueueGroupFactory(),
			$services->getJobFactory()
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
			LoggerFactory::getInstance( 'CentralAuth' ),
			$services->getHttpRequestFactory(),
			$services->getWikiPageFactory(),
			$config->get( CAMainConfigNames::GlobalRenameDenylist )
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

	'CentralAuth.GlobalRenameFactory' => static function ( MediaWikiServices $services ): GlobalRenameFactory {
		return new GlobalRenameFactory(
			$services->getJobQueueGroupFactory(),
			$services->getUserFactory(),
			CentralAuthServices::getAntiSpoofManager( $services ),
			CentralAuthServices::getDatabaseManager( $services )
		);
	},

	'CentralAuth.GlobalUserSelectQueryBuilderFactory' => static function (
		MediaWikiServices $services
	): GlobalUserSelectQueryBuilderFactory {
		return new GlobalUserSelectQueryBuilderFactory(
			CentralAuthServices::getDatabaseManager( $services )->getCentralReplicaDB(),
			$services->getActorStore(),
			$services->getUserNameUtils(),
			$services->getTempUserConfig()
		);
	},

	'CentralAuth.CentralDomainUtils' => static function ( MediaWikiServices $services ): CentralDomainUtils {
		return new CentralDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			$services->get( 'CentralAuth.SharedDomainUtils' )
		);
	},

	'CentralAuth.SharedDomainUtils' => static function ( MediaWikiServices $services ): SharedDomainUtils {
		// work around early access of UserOptionsManager perturbing service
		// instantiation order and breaking Wikibase (T386836)
		$userOptionsManagerCallback = static function () use ( $services ): UserOptionsManager {
			return $services->getUserOptionsManager();
		};
		return new SharedDomainUtils(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			$userOptionsManagerCallback,
			new HookRunner( $services->getHookContainer() ),
			$services->has( "MobileFrontend.Context" ) ? $services->get( "MobileFrontend.Context" ) : null,
			defined( 'MW_API' ) || defined( 'MW_REST_API' ),
			$services->getTempUserConfig()
		);
	},

	];

// @codeCoverageIgnoreEnd
