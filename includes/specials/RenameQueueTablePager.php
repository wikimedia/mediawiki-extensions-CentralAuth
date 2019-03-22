<?php
/**
 * @section LICENSE
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
 * @ingroup SpecialPage
 */

/**
 * Paginated table of search results.
 * @ingroup Pager
 */
class RenameQueueTablePager extends TablePager {

	/**
	 * @var SpecialPage
	 */
	protected $mOwner;

	/**
	 * @var string
	 */
	protected $mPage;

	/**
	 * @var string[]|null
	 */
	protected $mFieldNames;

	/**
	 * @param SpecialPage $owner Containing page
	 * @param string $page Subpage
	 * @param IContextSource|null $context
	 */
	public function __construct(
		SpecialPage $owner, $page, IContextSource $context = null
	) {
		$this->mOwner = $owner;
		$this->mPage = $page;
		$this->mDb = CentralAuthUtils::getCentralReplicaDB();

		$limit = $this->getRequest()->getInt( 'limit', 25 );
		// Override default cap of 5000
		$this->setLimit( min( 100, $limit ) );

		if ( $this->showOpenRequests() ) {
			$this->mDefaultDirection = self::DIR_ASCENDING;
		} else {
			$this->mDefaultDirection = self::DIR_DESCENDING;
		}
		parent::__construct( $context );
	}

	protected function showOpenRequests() {
		return $this->mPage === SpecialGlobalRenameQueue::PAGE_OPEN_QUEUE;
	}

	protected function showClosedRequests() {
		return $this->mPage === SpecialGlobalRenameQueue::PAGE_CLOSED_QUEUE;
	}

	public function getQueryInfo() {
		return [
			'tables' => 'renameuser_queue',
			'fields' => [
				'rq_id',
				'rq_name',
				'rq_wiki',
				'rq_newname',
				'rq_reason',
				'rq_requested_ts',
				'rq_status',
				'rq_completed_ts',
				# 'rq_deleted', not implemented yet
				'rq_performer',
				'rq_comments',
			],
			'conds' => $this->getQueryInfoConds(),
		];
	}

	protected function getQueryInfoConds() {
		$conds = [];

		$username = $this->getRequest()->getText( 'username' );
		$username = User::getCanonicalName( $username );
		if ( $username ) {
			$conds['rq_name'] = $username;
		}

		$newname = $this->getRequest()->getText( 'newname' );
		$newname = User::getCanonicalName( $newname );
		if ( $newname ) {
			$conds['rq_newname'] = $newname;
		}

		if ( $this->showOpenRequests() ) {
			$conds['rq_status'] = GlobalRenameRequest::PENDING;
		} else {
			$status = $this->getRequest()->getVal( 'status', 'all' );
			$closedStatuses = [ GlobalRenameRequest::APPROVED, GlobalRenameRequest::REJECTED ];
			if ( in_array( $status, $closedStatuses ) ) {
				// User requested closed status - either approved or rejected
				$conds['rq_status'] = $status;
			} else {
				$dbr = wfGetDB( DB_REPLICA );
				// All closed requests
				$conds[] = 'rq_status <> ' . $dbr->addQuotes( GlobalRenameRequest::PENDING );
			}
		}

		return $conds;
	}

	/**
	 * @return array
	 */
	protected function getExtraSortFields() {
		// Break order ties based on the unique id
		return [ 'rq_id' ];
	}

	/**
	 * @param string $field
	 * @return bool
	 */
	public function isFieldSortable( $field ) {
		$sortable = false;
		switch ( $field ) {
			case 'rq_name':
			case 'rq_wiki':
			case 'rq_newname':
			case 'rq_reason':
			case 'rq_requested_ts':
			case 'rq_status':
			case 'rq_completed_ts':
			case 'rq_performer':
				$sortable = true;
		}
		return $sortable;
	}

	/**
	 * @param string $name The database field name
	 * @param string $value The value retrieved from the database
	 * @return string HTML to place inside table cell
	 */
	public function formatValue( $name, $value ) {
		$formatted = htmlspecialchars( $value );
		switch ( $name ) {
			case 'rq_requested_ts':
			case 'rq_completed_ts':
				$formatted = $this->formatDateTime( $value );
				break;
			case 'rq_name':
			case 'rq_newname':
				$title = SpecialPage::getTitleFor( 'CentralAuth', $value );
				$formatted = $this->mOwner->getLinkRenderer()->makeLink( $title, $value );
				break;
			case 'rq_performer':
				$renamer = CentralAuthUser::newFromId( $value );
				$formatted = '<span class="plainlinks">' .
					WikiMap::foreignUserLink(
					$renamer->getHomeWiki(),
					$renamer->getName(),
					$renamer->getName()
				) . '</span>';
				break;
			case 'row_actions':
				$formatted = $this->formatActionValue( $this->mCurrentRow );
				break;
		}
		return $formatted;
	}

	/**
	 * @param string $value
	 * @return string Formatted table cell contents
	 */
	protected function formatDateTime( $value ) {
		return htmlspecialchars(
			$this->getLanguage()->userTimeAndDate( $value, $this->getUser() )
		);
	}

	/**
	 * @param stdClass $row
	 * @return string Formatted table cell contents
	 */
	protected function formatActionValue( $row ) {
		$target = SpecialGlobalRenameQueue::PAGE_PROCESS_REQUEST . '/' . $row->rq_id;
		if ( $this->showOpenRequests() ) {
			$label = 'globalrenamequeue-action-address';
		} else {
			$target .= '/' . SpecialGlobalRenameQueue::ACTION_VIEW;
			$label = 'globalrenamequeue-action-view';
		}
		return Html::element( 'a',
			[
				'href' => $this->mOwner->getPageTitle( $target )->getFullURL(),
				'class' => 'mw-ui-progressive',
			],
			$this->msg( $label )->text()
		);
	}

	/**
	 * @return string
	 */
	public function getDefaultSort() {
		if ( $this->showOpenRequests() ) {
			return 'rq_requested_ts';
		} else {
			return 'rq_completed_ts';
		}
	}

	/**
	 * @return string[]
	 */
	public function getFieldNames() {
		if ( $this->mFieldNames === null ) {
			$this->mFieldNames = [
				'rq_name' => $this->msg( 'globalrenamequeue-column-rq-name' )->text(),
				'rq_newname' => $this->msg( 'globalrenamequeue-column-rq-newname' )->text(),
				'rq_wiki' => $this->msg( 'globalrenamequeue-column-rq-wiki' )->text(),
				'rq_requested_ts' =>
					$this->msg( 'globalrenamequeue-column-rq-requested-ts' )->text(),
				'row_actions' => $this->msg( 'globalrenamequeue-column-row-actions' )->text(),
			];

			if ( $this->showClosedRequests() ) {
				// Remove action column
				array_pop( $this->mFieldNames );

				$this->mFieldNames += [
					'rq_completed_ts' =>
						$this->msg( 'globalrenamequeue-column-rq-completed-ts' )->text(),
					'rq_status' => $this->msg( 'globalrenamequeue-column-rq-status' )->text(),
					'rq_performer' => $this->msg( 'globalrenamequeue-column-rq-performer' )->text(),
					'row_actions' => $this->msg( 'globalrenamequeue-column-row-actions' )->text(),
				];
			}
		}
		return $this->mFieldNames;
	}
}
