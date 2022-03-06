<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\User\CentralAuthForcedLocalCreationService;
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

	/**
	 * @param ContainerInterface|null $services Service container to use. If null, global
	 * MediaWikiServices::getInstance() will be used instead.
	 * @return GlobalRenameRequestStore
	 */
	public static function getGlobalRenameRequestStore(
		ContainerInterface $services = null
	): GlobalRenameRequestStore {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'CentralAuth.GlobalRenameRequestStore' );
	}
}

class_alias( CentralAuthServices::class, 'CentralAuthServices' );
