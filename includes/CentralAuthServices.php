<?php

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthSessionManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUIService;
use MediaWiki\Extension\CentralAuth\CentralAuthWikiListService;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
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
	 *
	 * @return CentralAuthDatabaseManager
	 * @since 1.37
	 */
	public static function getDatabaseManager( ContainerInterface $services = null ): CentralAuthDatabaseManager {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthDatabaseManager' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 *
	 * @return CentralAuthForcedLocalCreationService
	 */
	public static function getForcedLocalCreationService(
		ContainerInterface $services = null
	): CentralAuthForcedLocalCreationService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthForcedLocalCreationService' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 *
	 * @return CentralAuthSessionManager
	 */
	public static function getSessionManager( ContainerInterface $services = null ): CentralAuthSessionManager {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthSessionManager' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 *
	 * @return CentralAuthUIService
	 */
	public static function getUIService( ContainerInterface $services = null ): CentralAuthUIService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthUIService' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 * @return CentralAuthUtilityService
	 */
	public static function getUtilityService( ContainerInterface $services = null ): CentralAuthUtilityService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->getService( 'CentralAuth.CentralAuthUtilityService' );
	}

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 * @return CentralAuthWikiListService
	 * @since 1.37
	 */
	public static function getWikiListService( ContainerInterface $services = null ): CentralAuthWikiListService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.CentralAuthWikiListService' );
	}

	public static function getGlobalGroupLookup( ContainerInterface $services = null ): GlobalGroupLookup {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.GlobalGroupLookup' );
	}
}
