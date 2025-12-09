<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\User\CentralAuthAntiSpoofManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthForcedLocalCreationService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUserHelper;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUserStatusLookupFactory;
use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilderFactory;
use MediaWiki\MediaWikiServices;
use Psr\Container\ContainerInterface;

/**
 * Provide access to CentralAuth provided services in static contexts.
 *
 * @since 1.36
 * @author Taavi "Majavah" Väänänen
 */
class CentralAuthServices {

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 * @since 1.41
	 */
	public static function getAntiSpoofManager( ?ContainerInterface $services = null ): CentralAuthAntiSpoofManager {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthAntiSpoofManager' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 * @since 1.44
	 */
	public static function getApiTokenGenerator(
		?ContainerInterface $services = null
	): CentralAuthApiTokenGenerator {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthApiTokenGenerator' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 * @since 1.44
	 */
	public static function getAutomaticGlobalGroupManager(
		?ContainerInterface $services = null
	): CentralAuthAutomaticGlobalGroupManager {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthAutomaticGlobalGroupManager' );
	}

	public static function getCentralDomainUtils(
		?ContainerInterface $services = null
	): CentralDomainUtils {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralDomainUtils' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 * @since 1.37
	 */
	public static function getDatabaseManager( ?ContainerInterface $services = null ): CentralAuthDatabaseManager {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthDatabaseManager' );
	}

	public static function getEditCounter(
		?ContainerInterface $services = null
	): CentralAuthEditCounter {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthEditCounter' );
	}

	public static function getFilteredRequestTracker(
		?ContainerInterface $services = null
	): FilteredRequestTracker {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.FilteredRequestTracker' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getForcedLocalCreationService(
		?ContainerInterface $services = null
	): CentralAuthForcedLocalCreationService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthForcedLocalCreationService' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getSessionManager( ?ContainerInterface $services = null ): CentralAuthSessionManager {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthSessionManager' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getSharedDomainUtils( ?ContainerInterface $services = null ): SharedDomainUtils {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.SharedDomainUtils' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getTokenManager( ?ContainerInterface $services = null ): CentralAuthTokenManager {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthTokenManager' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getUIService( ?ContainerInterface $services = null ): CentralAuthUIService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthUIService' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getUserCache( ?ContainerInterface $services = null ): CentralAuthUserCache {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthUserCache' );
	}

	public static function getUserHelper( ?ContainerInterface $services = null ): CentralAuthUserHelper {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthUserHelper' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getUtilityService( ?ContainerInterface $services = null ): CentralAuthUtilityService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthUtilityService' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 * @since 1.37
	 */
	public static function getWikiListService( ?ContainerInterface $services = null ): CentralAuthWikiListService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.CentralAuthWikiListService' );
	}

	public static function getGlobalGroupAssignmentService(
		?ContainerInterface $services = null
	): GlobalGroupAssignmentService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.GlobalGroupAssignmentService' );
	}

	public static function getGlobalGroupLookup( ?ContainerInterface $services = null ): GlobalGroupLookup {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.GlobalGroupLookup' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getGlobalRenameFactory(
		?ContainerInterface $services = null
	): GlobalRenameFactory {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.GlobalRenameFactory' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getGlobalRenameRequestStore(
		?ContainerInterface $services = null
	): GlobalRenameRequestStore {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.GlobalRenameRequestStore' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 */
	public static function getGlobalUserSelectQueryBuilderFactory(
		?ContainerInterface $services = null
	): GlobalUserSelectQueryBuilderFactory {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.GlobalUserSelectQueryBuilderFactory' );
	}

	public static function getUserStatusLookupFactory(
		?ContainerInterface $services = null
	): CentralAuthUserStatusLookupFactory {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.CentralAuthUserStatusLookupFactory' );
	}
}
