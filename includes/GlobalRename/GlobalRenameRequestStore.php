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

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;

/**
 * Stores and loads GlobalRenameRequest objects in a database.
 *
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class GlobalRenameRequestStore {
	/** @var CentralAuthDatabaseManager */
	private $dbManager;

	/**
	 * @param CentralAuthDatabaseManager $dbManager
	 */
	public function __construct( CentralAuthDatabaseManager $dbManager ) {
		$this->dbManager = $dbManager;
	}

	/**
	 * Persists the given global rename request to the central database.
	 * @param GlobalRenameRequest $request
	 * @return bool
	 */
	public function save( GlobalRenameRequest $request ): bool {
		$dbw = $this->dbManager->getCentralDB( DB_PRIMARY );
		if ( $request->getId() === null ) {
			$request
				->setRequested( wfTimestampNow() )
				->setStatus( GlobalRenameRequest::PENDING );

			$dbw->insert(
				'renameuser_queue',
				[
					'rq_name'         => $request->getName(),
					'rq_wiki'         => $request->getWiki(),
					'rq_newname'      => $request->getNewName(),
					'rq_reason'       => $request->getReason(),
					'rq_requested_ts' => $request->getRequested(),
					'rq_status'       => $request->getStatus(),
				],
				__METHOD__
			);

			$request->setId( $dbw->insertId() );
		} else {
			$dbw->update(
				'renameuser_queue',
				[
					'rq_name'         => $request->getName(),
					'rq_wiki'         => $request->getWiki(),
					'rq_newname'      => $request->getNewName(),
					'rq_reason'       => $request->getReason(),
					'rq_requested_ts' => $request->getRequested(),
					'rq_status'       => $request->getStatus(),
					'rq_completed_ts' => $request->getCompleted(),
					'rq_deleted'      => $request->getDeleted(),
					'rq_performer'    => $request->getPerformer(),
					'rq_comments'     => $request->getComments(),
				],
				[
					'rq_id' => $request->getId()
				],
				__METHOD__
			);
		}

		return $dbw->affectedRows() === 1;
	}
}
