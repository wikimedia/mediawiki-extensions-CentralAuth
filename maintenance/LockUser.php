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

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Permissions\UltimateAuthority;

/**
 * Locks a specific global account.
 *
 * @author Taavi Väänänen <taavi@wikimedia.org>
 */
class LockUser extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'CentralAuth' );
		$this->addOption( 'username', 'User to act on', true, true );
		$this->addOption( 'actor', 'Username to log with', false, true );
		$this->addOption( 'reason', 'Reason to use', false, true );
		$this->addOption( 'bot', 'Mark as bot in RC', false, false );
	}

	public function execute() {
		$user = CentralAuthUser::getInstanceByName(
			$this->getServiceContainer()
				->getUserNameUtils()
				->getCanonical( $this->getOption( 'username' ) )
		);
		$username = $user->getName();
		$context = $this->getContext();

		if ( !$user->exists() ) {
			$this->fatalError( "User '$username' does not exist" );
		}

		if ( $user->isLocked() ) {
			$this->output( "User '$username' is already locked" . PHP_EOL );
			return;
		}

		$status = $user->adminLockHide(
			/* setLocked */ true,
			/* setHidden */ null,
			$this->getOption( 'reason', '' ),
			$context,
			$this->getOption( 'bot', false )
		);

		if ( $status->isGood() ) {
			$this->output( "Locked user '$username'" . PHP_EOL );
			return;
		}

		$this->fatalError(
			$this->getServiceContainer()
				->getFormatterFactory()
				->getStatusFormatter( $context )
				->getWikiText( $status )
		);
	}

	private function getContext(): IContextSource {
		$context = RequestContext::newExtraneousContext( Title::makeTitleSafe( NS_SPECIAL, 'Badtitle' ) );

		$username = $this->getOption( 'actor' );
		if ( $username ) {
			$user = $this->getServiceContainer()
				->getUserFactory()
				->newFromName( $username );

			if ( !$user || !$user->isRegistered() ) {
				$this->fatalError( "No user '$username' found!" );
			}

			'@phan-var \MediaWiki\User\User $user';
			$context->setUser( $user );
		} else {
			$context->setUser( User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ) );
		}

		$context->setAuthority( new UltimateAuthority( $context->getUser(), false ) );
		return $context;
	}
}

$maintClass = LockUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
