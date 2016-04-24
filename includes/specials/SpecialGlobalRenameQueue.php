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
 * Process account rename requests made via [[Special:GlobalRenameRequest]].
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2014 Bryan Davis and Wikimedia Foundation.
 * @ingroup SpecialPage
 */
class SpecialGlobalRenameQueue extends SpecialPage {

	const PAGE_OPEN_QUEUE = 'open';
	const PAGE_PROCESS_REQUEST = 'request';
	const PAGE_CLOSED_QUEUE = 'closed';
	const ACTION_CANCEL = 'cancel';
	const ACTION_VIEW = 'view';

	/**
	 * @var string $par Request subpage string
	 */
	protected $par;

	public function __construct() {
		parent::__construct( 'GlobalRenameQueue', 'centralauth-rename' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @param string $par Subpage string if one was specified
	 */
	public function execute( $par ) {
		$this->par = $par;

		$navigation = explode( '/', $par );
		$action = array_shift( $navigation );

		$this->outputHeader();

		switch ( $action ) {
			case self::PAGE_OPEN_QUEUE:
				$this->handleOpenQueue();
				break;

			case self::PAGE_CLOSED_QUEUE:
				$this->handleClosedQueue();
				break;

			case self::PAGE_PROCESS_REQUEST:
				$this->handleProcessRequest( $navigation );
				break;

			default:
				$this->doRedirectToOpenQueue();
				break;
		}
	}

	/**
	 * @param string $titleMessage Message name for page title
	 * @param array $titleParams Params for page title
	 */
	protected function commonPreamble( $titleMessage, $titleParams = array() ) {
		$out = $this->getOutput();
		$this->setHeaders();
		$this->checkPermissions();
		$out->setPageTitle( $this->msg( $titleMessage, $titleParams ) );
	}

	/**
	 * @param string $page Active page
	 */
	protected function commonNav( $page ) {
		$html = Html::openElement( 'div', array(
			'class' => 'mw-ui-button-group',
		) );
		$html .= Html::element( 'a',
			array(
				'href' => $this->getPageTitle( self::PAGE_OPEN_QUEUE )->getFullURL(),
				'class' => 'mw-ui-button' . (
					( $page === self::PAGE_OPEN_QUEUE ) ? ' mw-ui-primary' : ''
				),
			),
			$this->msg( 'globalrenamequeue-nav-openqueue' )->text()
		);
		$html .= Html::element( 'a',
			array(
				'href' => $this->getPageTitle( self::PAGE_CLOSED_QUEUE )->getFullURL(),
				'class' => 'mw-ui-button' .
					( ($page === self::PAGE_CLOSED_QUEUE) ? ' mw-ui-primary' : '' ),
			),
			$this->msg( 'globalrenamequeue-nav-closedqueue' )->text()
		);
		$html .= Html::closeElement( 'div' );
		$html .= Html::element( 'div', array( 'style' => 'clear:both' ) );
		$this->getOutput()->addHtml( $html );
	}

	/**
	 * Get an array of fields for use by the HTMLForm shown above the pager.
	 *
	 * @return array
	 */
	private function getCommonFormFieldsArray() {
		$lang = $this->getLanguage();
		return array(
			'username' => array(
				'type' => 'text',
				'name' => 'username',
				'label-message' => 'globalrenamequeue-form-username',
				'size' => 30,
			),
			'newname' => array(
				'type' => 'text',
				'name' => 'newname',
				'size' => 30,
				'label-message' => 'globalrenamequeue-form-newname',
			),
			'limit' => array(
				'type' => 'limitselect',
				'name' => 'limit',
				'label-message' => 'table_pager_limit_label',
				'options' => array(
					$lang->formatNum( 25 ) => 25,
					$lang->formatNum( 50 ) => 50,
					$lang->formatNum( 75 ) => 75,
					$lang->formatNum( 100 ) => 100,
				),
			),
		);
	}

	/**
	 * Initialize and output the HTMLForm used for filtering.
	 *
	 * @param array $fields
	 */
	private function outputFilterForm( array $fields ) {
		$form = HTMLForm::factory( 'table', $fields, $this->getContext() );
		$form->setMethod( 'get' );
		$form->setWrapperLegendMsg( 'search' );
		$form->prepareForm()->displayForm( false );
	}

	/**
	 * Handle requests to display the open request queue
	 */
	protected function handleOpenQueue() {
		$this->commonPreamble( 'globalrenamequeue' );
		$this->commonNav( self::PAGE_OPEN_QUEUE );
		$this->outputFilterForm( $this->getCommonFormFieldsArray() );

		$pager = new RenameQueueTablePager( $this, self::PAGE_OPEN_QUEUE );
		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
	}

	/**
	 * Handle requests to display the closed request queue
	 */
	protected function handleClosedQueue() {
		$this->commonPreamble( 'globalrenamequeue' );
		$this->commonNav( self::PAGE_CLOSED_QUEUE );
		$fields = array_merge(
			$this->getCommonFormFieldsArray(),
			array(
				'status' => array(
					'type' => 'select',
					'name' => 'status',
					'label-message' => 'globalrenamequeue-form-status',
					'options-messages' => array(
						'globalrenamequeue-form-status-all' => 'all',
						'globalrenamequeue-view-approved' => GlobalRenameRequest::APPROVED,
						'globalrenamequeue-view-rejected' => GlobalRenameRequest::REJECTED,
					),
					'default' => 'all',
				)
			)
		);
		$this->outputFilterForm( $fields );

		$pager = new RenameQueueTablePager( $this, self::PAGE_CLOSED_QUEUE );
		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
	}

	/**
	 * Handle requests related to processing a request.
	 *
	 * @param array $pathArgs Extra path arguments
	 */
	protected function handleProcessRequest( array $pathArgs ) {
		if ( !$pathArgs ) {
			$this->doRedirectToOpenQueue();
			return;
		}

		$rqId = array_shift( $pathArgs );
		$req = GlobalRenameRequest::newFromId( $rqId );
		if ( !$req->exists() ) {
			$this->commonPreamble( 'globalrenamequeue-request-unknown-title' );
			$this->getOutput()->addWikiMsg(
				'globalrenamequeue-request-unknown-body'
			);
			return;
		}

		$action = array_shift( $pathArgs );
		if ( !$req->isPending() ) {
			$action = self::ACTION_VIEW;
		}

		switch ( $action ) {
			case self::ACTION_CANCEL:
				$this->doRedirectToOpenQueue();
				break;
			case self::ACTION_VIEW:
				$this->doViewRequest( $req );
				break;
			default:
				$this->doShowProcessForm( $req );
				break;
		}
	}

	protected function doRedirectToOpenQueue() {
		$this->getOutput()->redirect(
			$this->getPageTitle( self::PAGE_OPEN_QUEUE )->getFullURL()
		);
	}

	/**
	 * Display a request.
	 *
	 * @param GlobalRenameRequest $req
	 */
	protected function doViewRequest( GlobalRenameRequest $req ) {
		$this->commonPreamble( 'globalrenamequeue-request-status-title',
			array( $req->getName(), $req->getNewName() )
		);
		$this->commonNav( self::PAGE_PROCESS_REQUEST );

		$reason = $req->getReason() ?: $this->msg(
			'globalrenamequeue-request-reason-sul'
		)->parseAsBlock();

		$steward = CentralAuthUser::newFromId( $req->getPerformer() );
		if ( $steward === false ) {
			throw new Exception(
				"The perfomer's global user id ({$req->getPerformer()}) does not exist in the database"
			);
		}
		if ( $steward->isAttached() ) {
			$stewardLink = Title::makeTitleSafe( NS_USER, $steward->getName() )->getFullURL();
		} else {
			$stewardLink = WikiMap::getForeignURL(
				$steward->getHomeWiki(), "User:{$steward->getName()}"
			);
		}

		// Done as one big message so that stewards can create a local
		// translation to customize the output as they see fit.
		// @TODO: Do that actually in here... this is not how we do interfaces in 2015.
		$viewMsg = $this->msg( 'globalrenamequeue-view',
			$req->getName(),
			$req->getNewName(),
			$reason,
			$this->msg( 'globalrenamequeue-view-' . $req->getStatus() )->text(),
			$this->getLanguage()->userTimeAndDate(
				$req->getRequested(), $this->getUser()
			),
			$this->getLanguage()->userTimeAndDate(
				$req->getCompleted(), $this->getUser()
			),
			$stewardLink,
			$steward->getName(),
			$req->getComments()
		)->parseAsBlock();
		$this->getOutput()->addHtml( '<div class="plainlinks">' . $viewMsg . '</div>' );
	}

	/**
	 * Display form for approving/denying request or process form submission.
	 *
	 * @param GlobalRenameRequest $req Pending request
	 */
	protected function doShowProcessForm( GlobalRenameRequest $req ) {
		$this->commonPreamble(
			'globalrenamequeue-request-title', array( $req->getName() )
		);

		$form = HTMLForm::factory( 'vform',
			array(
				'rid' => array(
					'default' => $req->getId(),
					'name'    => 'rid',
					'type'    => 'hidden',
					),
				'comments' => array(
					'default'       => $this->getRequest()->getVal( 'comments' ),
					'id'            => 'mw-renamequeue-comments',
					'label-message' => 'globalrenamequeue-request-comments-label',
					'name'          => 'comments',
					'type'          => 'textarea',
					'rows'          => 5,
				),
				// The following fields need to have their names stay in
				// sync with the expectations of GlobalRenameUser::rename()
				'reason' => array(
					'id'            => 'mw-renamequeue-reason',
					'label-message' => 'globalrenamequeue-request-reason-label',
					'name'          => 'reason',
					'type'          => 'text',
				),
				'movepages' => array(
					'id'            => 'mw-renamequeue-movepages',
					'name'          => 'movepages',
					'label-message' => 'globalrenamequeue-request-movepages',
					'type'          => 'check',
					'default'       => 1,
				),
				'suppressredirects' => array(
					'id'            => 'mw-renamequeue-suppressredirects',
					'name'          => 'suppressredirects',
					'label-message' => 'globalrenamequeue-request-suppressredirects',
					'type'          => 'check',
				),
			),
			$this->getContext(),
			'globalrenamequeue'
		);

		$form->suppressDefaultSubmit();
		$form->addButton( 'approve',
			$this->msg( 'globalrenamequeue-request-approve-text' )->text(),
			'mw-renamequeue-approve',
			array(
				'class' => 'mw-ui-constructive mw-ui-flush-right',
			)
		);
		$form->addButton( 'deny',
			$this->msg( 'globalrenamequeue-request-deny-text' )->text(),
			'mw-renamequeue-deny',
			array(
				'class' => 'mw-ui-destructive mw-ui-flush-right',
			)
		);
		$form->addButton( 'cancel',
			$this->msg( 'globalrenamequeue-request-cancel-text' )->text(),
			'mw-renamequeue-cancel',
			array(
				'class' => 'mw-ui-quiet mw-ui-flush-left',
			)
		);

		$form->setId( 'mw-globalrenamequeue-request' );

		if ( $req->userIsGlobal() ) {
			$globalUser = CentralAuthUser::getInstanceByName( $req->getName() );
			$homeWiki = $globalUser->getHomeWiki();
			$infoMsgKey = 'globalrenamequeue-request-userinfo-global';
		} else {
			$homeWiki = $req->getWiki();
			$infoMsgKey = 'globalrenamequeue-request-userinfo-local';
		}

		$headerMsg = $this->msg( 'globalrenamequeue-request-header',
			WikiMap::getForeignURL( $homeWiki, "User:{$req->getName()}" ),
			$req->getName(),
			$req->getNewName()
		);
		$form->addHeaderText( '<span class="plainlinks">' . $headerMsg->parseAsBlock() . '</span>' );

		$homeWikiWiki = WikiMap::getWiki( $homeWiki );
		$infoMsg = $this->msg( $infoMsgKey,
			$req->getName(),
			// homeWikiWiki shouldn't ever be null except in
			// a development/testing environment.
			( $homeWikiWiki ? $homeWikiWiki->getDisplayName() : $homeWiki ),
			$req->getNewName()
		);

		if ( $req->userIsGlobal() ) {
			$infoMsg->numParams( $globalUser->getGlobalEditCount() );
		}

		$form->addHeaderText( $infoMsg->parseAsBlock() );

		if ( class_exists( 'CentralAuthSpoofUser' ) ) {
			$spoofUser = new CentralAuthSpoofUser( $req->getNewName() );
			// @todo move this code somewhere else
			$specialGblRename = new SpecialGlobalRenameUser();
			$specialGblRename->setContext( $this->getContext() );
			$conflicts = $specialGblRename->processAntiSpoofConflicts( $req->getName(), $spoofUser->getConflicts() );
			if ( $conflicts ) {
				$form->addHeaderText(
					$this->msg(
						'globalrenamequeue-request-antispoof-conflicts',
						$this->getLanguage()->commaList( $conflicts )
					)->numParams( count( $conflicts ) )->parseAsBlock()
				);
			}
		}

		// Show a message if the new username matches the title blacklist.
		if ( class_exists( 'TitleBlacklist' ) ) {
			$titleBlacklist = TitleBlacklist::singleton()->isBlacklisted(
				Title::makeTitleSafe( NS_USER, $req->getNewName() ),
				'new-account'
			);
			if ( $titleBlacklist instanceof TitleBlacklistEntry ) {
				$form->addHeaderText(
					$this->msg( 'globalrenamequeue-request-titleblacklist' )
						->rawParams( $titleBlacklist->getRegex() )->parseAsBlock()
				);
			}
		}

		// Show a log entry of previous renames under the requesting user's username
		$caTitle = Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $req->getName() );
		$extract = '';
		$extractCount = LogEventsList::showLogExtract( $extract, 'gblrename', $caTitle, '', array(
			'showIfEmpty' => false,
		) );
		if ( $extractCount ) {
			$form->addHeaderText(
				Xml::fieldset( $this->msg( 'globalrenamequeue-request-previous-renames' )
					->numParams( $extractCount )
					->text(), $extract )
			);
		}

		$reason = $req->getReason() ?: $this->msg(
			'globalrenamequeue-request-reason-sul'
		)->parseAsBlock();
		$form->addHeaderText( $this->msg( 'globalrenamequeue-request-reason',
			$reason
		)->parseAsBlock() );

		$form->setSubmitCallback( array( $this, 'onProcessSubmit' ) );

		$out = $this->getOutput();
		$out->addModuleStyles( array(
			'mediawiki.ui',
			'mediawiki.ui.button',
			'mediawiki.ui.input',
			'ext.centralauth.globalrenamequeue.styles',
		) );
		$out->addModules( 'ext.centralauth.globalrenamequeue' );

		$status = $form->show();
		if ( $status instanceof Status && $status->isOk() ) {
			$this->getOutput()->redirect(
				$this->getPageTitle(
					self::PAGE_PROCESS_REQUEST . "/{$req->getId()}/{$status->value}"
				)->getFullURL()
			);
		}
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onProcessSubmit( array $data ) {
		$request = $this->getContext()->getRequest();
		$status = new Status;
		if ( $request->getCheck( 'approve' ) ) {
			$status = $this->doResolveRequest( true, $data );
		} elseif ( $request->getCheck( 'deny' ) ) {
			$status = $this->doResolveRequest( false, $data );
		} else {
			$status->setResult( true, 'cancel' );
		}
		return $status;
	}

	protected function doResolveRequest( $approved, $data ) {
		$request = GlobalRenameRequest::newFromId( $data['rid'] );
		$oldUser = User::newFromName( $request->getName() );
		if ( $request->userIsGlobal() || $request->getWiki() === wfWikiId() ) {
			$notifyEmail = MailAddress::newFromUser( $oldUser );
		} else {
			$notifyEmail = $this->getRemoteUserMailAddress(
				$request->getWiki(), $request->getName()
			);
		}
		$newUser = User::newFromName( $request->getNewName(), 'creatable' );
		$status = new Status;
		$session = $this->getContext()->exportSession();
		if ( $approved ) {
			if ( $request->userIsGlobal() ) {
				// Trigger a global rename job

				$globalRenameUser = new GlobalRenameUser(
					$this->getUser(),
					$oldUser,
					CentralAuthUser::getInstance( $oldUser ),
					$newUser,
					CentralAuthUser::getInstance( $newUser ),
					new GlobalRenameUserStatus( $newUser->getName() ),
					'JobQueueGroup::singleton',
					new GlobalRenameUserDatabaseUpdates(),
					new GlobalRenameUserLogger( $this->getUser() ),
					$session
				);

				$status = $globalRenameUser->rename( $data );
			} else {
				// If the user is local-only:
				// * rename the local user using LocalRenameUserJob
				// * create a global user attached only to the local wiki
				$job = new LocalRenameUserJob(
					Title::newFromText( 'Global rename job' ),
					array(
						'from' => $oldUser->getName(),
						'to' => $newUser->getName(),
						'renamer' => $this->getUser()->getName(),
						'movepages' => true,
						'suppressredirects' => true,
						'promotetoglobal' => true,
						'reason' => $data['reason'],
						'session' => $session,
					)
				);
				JobQueueGroup::singleton( $request->getWiki() )->push( $job );
				// Now log it
				$this->logPromotionRename(
					$oldUser->getName(),
					$request->getWiki(),
					$newUser->getName(),
					$data['reason']
				);
				$status = Status::newGood();
			}
		}

		if ( $status->isGood() ) {
			$request->setStatus(
				$approved ? GlobalRenameRequest::APPROVED : GlobalRenameRequest::REJECTED
			);
			$request->setCompleted( wfTimestampNow() );
			$request->setPerformer(
				CentralAuthUser::getInstance( $this->getUser() )->getId()
			);
			$request->setComments( $data['comments'] );

			if ( $request->save() ) {
				// Send email to the user about the change in status.
				if ( $approved )  {
					$subject = $this->msg(
						'globalrenamequeue-email-subject-approved'
					)->inContentLanguage()->text();
					$body = $this->msg(
						'globalrenamequeue-email-body-approved',
						array(
							$oldUser->getName(),
							$newUser->getName(),
						)
					)->inContentLanguage()->text();
				} else {
					$subject = $this->msg(
						'globalrenamequeue-email-subject-rejected'
					)->inContentLanguage()->text();
					$body = $this->msg(
						'globalrenamequeue-email-body-rejected',
						array(
							$oldUser->getName(),
							$newUser->getName(),
							$request->getComments(),
						)
					)->inContentLanguage()->text();
				}

				if ( $notifyEmail !== null && $notifyEmail->address ) {
					$type = $approved ? 'approval' : 'rejection';
					wfDebugLog(
						'CentralAuthRename',
						"Sending $type email to User:{$oldUser->getName()}/{$notifyEmail->address}"
					);
					$this->sendNotificationEmail( $notifyEmail, $subject, $body );
				}
			} else {
				$status->fatal( 'globalrenamequeue-request-savefailed' );
			}
		}
		return $status;
	}

	/**
	 * Log a promotion to global rename in the global rename log
	 *
	 * @param string $oldName
	 * @param string $wiki
	 * @param string $newName
	 * @param string $reason
	 */
	protected function logPromotionRename( $oldName, $wiki, $newName, $reason ) {
		$logger = new GlobalRenameUserLogger( $this->getUser() );
		$logger->logPromotion( $oldName, $wiki, $newName, $reason );
	}

	/**
	 * Get a MailAddress for a user on a remote wiki
	 *
	 * @param string $wiki
	 * @param string $username
	 * @return MailAddress|null
	 */
	protected function getRemoteUserMailAddress( $wiki, $username ) {
		$lb = wfGetLB( $wiki );
		$remoteDB = $lb->getConnection( DB_SLAVE, array(), $wiki );
		$row = $remoteDB->selectRow(
			'user',
			array( 'user_email', 'user_name', 'user_real_name' ),
			array(
				'user_name' => User::getCanonicalName( $username ),
			),
			__METHOD__
		);
		if ( $row === false ) {
			$address = null;
		} else {
			$address = new MailAddress(
				$row->user_email, $row->user_name, $row->user_real_name
			);
		}
		$lb->reuseConnection( $remoteDB );
		return $address;
	}

	/**
	 * Send an email notifying the user of the result of their request.
	 *
	 * @param MailAddress $to
	 * @param string $subject
	 * @param string $body
	 * @return Status
	 */
	protected function sendNotificationEmail( MailAddress $to, $subject, $body ) {
		global $wgPasswordSender;
		$from = new MailAddress(
			$wgPasswordSender,
			wfMessage( 'emailsender' )->inContentLanguage()->text()
		);
		return UserMailer::send( $to, $from, $subject, $body );
	}

	protected function getGroupName() {
		return 'users';
	}

	public function getSubpagesForPrefixSearch() {
		return array(
			self::PAGE_OPEN_QUEUE,
			self::PAGE_PROCESS_REQUEST,
			self::PAGE_CLOSED_QUEUE
		);
	}
}


/**
 * Paginated table of search results.
 * @ingroup Pager
 */
class RenameQueueTablePager extends TablePager {

	/**
	 * @var SpecialPage $mOwner
	 */
	protected $mOwner;

	/**
	 * @var string $mPage
	 */
	protected $mPage;

	/**
	 * @var mFieldNames array
	 */
	protected $mFieldNames;

	/**
	 * @param SpecialPage $owner Containing page
	 * @param string $page Subpage
	 * @param IContextSource $context
	 */
	public function __construct(
		SpecialPage $owner, $page, IContextSource $context = null
	) {
		$this->mOwner = $owner;
		$this->mPage = $page;
		$this->mDb = CentralAuthUtils::getCentralSlaveDB();

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
		return array(
			'tables' => 'renameuser_queue',
			'fields' => array(
				'rq_id',
				'rq_name',
				'rq_wiki',
				'rq_newname',
				'rq_reason',
				'rq_requested_ts',
				'rq_status',
				'rq_completed_ts',
				#'rq_deleted', not implemented yet
				'rq_performer',
				'rq_comments',
			),
			'conds' => $this->getQueryInfoConds(),
		);
	}

	protected function getQueryInfoConds() {
		$conds = array();

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
			$closedStatuses = array( GlobalRenameRequest::APPROVED, GlobalRenameRequest::REJECTED );
			if ( in_array( $status, $closedStatuses ) ) {
				// User requested closed status - either approved or rejected
				$conds['rq_status'] = $status;
			} else {
				$dbr = wfGetDB( DB_SLAVE );
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
		return array( 'rq_id' );
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
				$title = SpecialPage::getTitleFor( 'CentralAuth' , $value );
				$formatted = Linker::link( $title, htmlspecialchars( $value ) );
				break;
			case 'rq_performer':
				$steward = CentralAuthUser::newFromId( $value );
				$formatted = '<span class="plainlinks">' .
					WikiMap::foreignUserLink(
					$steward->getHomeWiki(),
					$steward->getName(),
					$steward->getName()
				) . '</span>';
				break;
			case 'row_actions':
				$formatted = $this->formatActionValue( $this->mCurrentRow );
				break;
		}
		return $formatted;
	}

	/**
	 * @return string Formatted table cell contents
	 */
	protected function formatDateTime( $value ) {
		return htmlspecialchars(
			$this->getLanguage()->userTimeAndDate( $value, $this->getUser() )
		);
	}

	/**
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
			array(
				'href' => $this->mOwner->getPageTitle( $target )->getFullURL(),
				'class' => 'mw-ui-progressive',
			),
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
	 * @return array
	 */
	public function getFieldNames() {
		if ( $this->mFieldNames === null ) {
			$this->mFieldNames = array(
				'rq_name' => $this->msg( 'globalrenamequeue-column-rq-name' )->text(),
				'rq_newname' => $this->msg( 'globalrenamequeue-column-rq-newname' )->text(),
				'rq_wiki' => $this->msg( 'globalrenamequeue-column-rq-wiki' )->text(),
				'rq_requested_ts' => $this->msg( 'globalrenamequeue-column-rq-requested-ts' )->text(),
				'row_actions' => $this->msg( 'globalrenamequeue-column-row-actions' )->text(),
			);

			if ( $this->showClosedRequests() ) {
				// Remove action column
				array_pop( $this->mFieldNames );

				$this->mFieldNames += array(
					'rq_completed_ts' => $this->msg( 'globalrenamequeue-column-rq-completed-ts' )->text(),
					'rq_status' => $this->msg( 'globalrenamequeue-column-rq-status' )->text(),
					'rq_performer' => $this->msg( 'globalrenamequeue-column-rq-performer' )->text(),
					'row_actions' => $this->msg( 'globalrenamequeue-column-row-actions' )->text(),
				);
			}
		}
		return $this->mFieldNames;
	}
}
