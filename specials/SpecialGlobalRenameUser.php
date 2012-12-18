<?php
/**
 * Created on Dec 17, 2012
 *
 * CentralAuth extension
 *
 * Copyright (C) 2012 Alex Monk (krenair@gmail.com)
 *
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
 */

class SpecialGlobalRenameUser extends SpecialPage {
	function __construct() {
		parent::__construct( 'GlobalRenameUser', 'centralauth-globalrename' );
	}

	function execute( $subpage ) {
		global $wgDBname;
		$this->getOutput()->setPageTitle( $this->msg( 'globalrenameuser' ) );

        if ( !$this->getUser()->isAllowed( 'centralauth-globalrename' ) ) {
			$this->displayRestrictionError();
			return;
        }

		// TODO: Add a form to input the username, reason, etc. - maybe borrow from another special page

		$globalUser = new CentralAuthUser( $this->getRequest()->getText( 'target' ) );

		if ( !$globalUser->exists() ) {
			$this->getOutput()->addWikiMsg( "centralauth-globalrename-nonexistent", $globalUser->getName() );
			return;
		}

		$currentName = $globalUser->getName();
		$newName = $this->getRequest()->getText( 'newname' );

		$db = CentralAuthUser::getCentralDB();
		$targetUsernameUses = $db->select(
			'localnames',
			'ln_wiki',
			array( 'ln_name' => $newName ),
			__METHOD__
		);

		if ( $targetUsernameUses->numRows() != 0 ) {
			$this->getOutput()->addWikiMsg( 'centralauth-globalrename-username-exists', $newName ); // TODO: Siebrand suggested that maybe we should show which wikis
			return;
		}

		$startedLocked = $globalUser->isLocked();
		// Lock the user before we start changing anything.
		if ( !$startedLocked ) {
			$globalUser->adminLock();
		}

		// Update the globaluser and localuser tables. The existing hooks should deal with globalnames/localnames...
		$tables = array( 'globaluser' => 'gu', 'localuser' => 'lu' );
		foreach ( $tables as $table => $prefix ) {
			$db->update(
				$table,
				array( $prefix . '_name' => $newName ),
				array( $prefix . '_name' => $currentName ),
				__METHOD__
			);
		}

		// Create the job
		$job = Job::factory(
			'startLocalRenaming',
			Title::makeTitleSafe( NS_USER, $globalUser->getName() ),
			array(
				'from' => $globalUser->getName(),
				'to' => $newName,
				'reason' => $this->getRequest()->getText( 'reason' ),
				'startedLocked' => $startedLocked,
				'startedFrom' => $wgDBname,
				'startedByName' => $this->getUser()->getName(),
				'startedById' => $this->getUser()->getID(),
				'startedByIP' => $this->getRequest()->getIP()
			)
		);

		global $wgMemc;
		$wgMemc->set( CentralAuthUser::memcKey( 'globalrename', $currentName ), $globalUser->listAttached() );

		// Put the job into the queue on each wiki it needs to run on
		foreach ( $globalUser->listAttached() as $wiki ) {
			JobQueue::factory(
				array(
					'wiki' => $wiki,
					'class' => 'JobQueueDB',
					'type' => 'startLocalRenaming'
				)
			)->batchPush( array( $job ) );
		}

		$this->getOutput()->addWikiMsg( "centralauth-globalrename-complete" );
	}
}
