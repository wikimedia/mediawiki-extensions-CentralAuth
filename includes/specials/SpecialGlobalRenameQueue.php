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

use MediaWiki\MediaWikiServices;

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
	protected function commonPreamble( $titleMessage, $titleParams = [] ) {
		$out = $this->getOutput();
		$this->setHeaders();
		$this->checkPermissions();
		$out->setPageTitle( $this->msg( $titleMessage, $titleParams ) );
	}

	/**
	 * @param string $page Active page
	 */
	protected function commonNav( $page ) {
		$html = Html::openElement( 'div', [
			'class' => 'mw-ui-button-group',
		] );
		$html .= Html::element( 'a',
			[
				'href' => $this->getPageTitle( self::PAGE_OPEN_QUEUE )->getFullURL(),
				'class' => 'mw-ui-button' . (
					( $page === self::PAGE_OPEN_QUEUE ) ? ' mw-ui-progressive' : ''
				),
			],
			$this->msg( 'globalrenamequeue-nav-openqueue' )->text()
		);
		$html .= Html::element( 'a',
			[
				'href' => $this->getPageTitle( self::PAGE_CLOSED_QUEUE )->getFullURL(),
				'class' => 'mw-ui-button' .
					( ( $page === self::PAGE_CLOSED_QUEUE ) ? ' mw-ui-progressive' : '' ),
			],
			$this->msg( 'globalrenamequeue-nav-closedqueue' )->text()
		);
		$html .= Html::closeElement( 'div' );
		$html .= Html::element( 'div', [ 'style' => 'clear:both' ] );
		$this->getOutput()->addHtml( $html );
	}

	/**
	 * Get an array of fields for use by the HTMLForm shown above the pager.
	 *
	 * @return array
	 */
	private function getCommonFormFieldsArray() {
		$lang = $this->getLanguage();
		return [
			'username' => [
				'type' => 'text',
				'name' => 'username',
				'label-message' => 'globalrenamequeue-form-username',
				'size' => 30,
			],
			'newname' => [
				'type' => 'text',
				'name' => 'newname',
				'size' => 30,
				'label-message' => 'globalrenamequeue-form-newname',
			],
			'limit' => [
				'type' => 'limitselect',
				'name' => 'limit',
				'label-message' => 'table_pager_limit_label',
				'options' => [
					$lang->formatNum( 25 ) => 25,
					$lang->formatNum( 50 ) => 50,
					$lang->formatNum( 75 ) => 75,
					$lang->formatNum( 100 ) => 100,
				],
			],
		];
	}

	/**
	 * Initialize and output the HTMLForm used for filtering.
	 *
	 * @param array $formDescriptor
	 */
	private function outputFilterForm( array $formDescriptor ) {
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
			->setWrapperLegendMsg( 'search' )
			->prepareForm()->displayForm( false );
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
		$formDescriptor = array_merge(
			$this->getCommonFormFieldsArray(),
			[
				'status' => [
					'type' => 'select',
					'name' => 'status',
					'label-message' => 'globalrenamequeue-form-status',
					'options-messages' => [
						'globalrenamequeue-form-status-all' => 'all',
						'globalrenamequeue-view-approved' => GlobalRenameRequest::APPROVED,
						'globalrenamequeue-view-rejected' => GlobalRenameRequest::REJECTED,
					],
					'default' => 'all',
				]
			]
		);
		$this->outputFilterForm( $formDescriptor );

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
			[ $req->getName(), $req->getNewName() ]
		);
		$this->commonNav( self::PAGE_PROCESS_REQUEST );

		$reason = $req->getReason() ?: $this->msg(
			'globalrenamequeue-request-reason-sul'
		)->parseAsBlock();

		$renamer = CentralAuthUser::newFromId( $req->getPerformer() );
		if ( $renamer === false ) {
			throw new Exception(
				"The perfomer's global user id ({$req->getPerformer()}) " .
					"does not exist in the database"
			);
		}
		if ( $renamer->isAttached() ) {
			$renamerLink = Title::makeTitleSafe( NS_USER, $renamer->getName() )->getFullURL();
		} else {
			$renamerLink = WikiMap::getForeignURL(
				$renamer->getHomeWiki(), "User:{$renamer->getName()}"
			);
		}

		// Done as one big message so that admins can create a local
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
			$renamerLink,
			$renamer->getName(),
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
			'globalrenamequeue-request-title', [ $req->getName() ]
		);

		$htmlForm = HTMLForm::factory( 'ooui',
			[
				'rid' => [
					'default' => $req->getId(),
					'name'    => 'rid',
					'type'    => 'hidden',
					],
				'comments' => [
					'default'       => $this->getRequest()->getVal( 'comments' ),
					'id'            => 'mw-renamequeue-comments',
					'label-message' => 'globalrenamequeue-request-comments-label',
					'name'          => 'comments',
					'type'          => 'textarea',
					'rows'          => 5,
				],
				// The following fields need to have their names stay in
				// sync with the expectations of GlobalRenameUser::rename()
				'reason' => [
					'id'            => 'mw-renamequeue-reason',
					'label-message' => 'globalrenamequeue-request-reason-label',
					'name'          => 'reason',
					'type'          => 'text',
				],
				'movepages' => [
					'id'            => 'mw-renamequeue-movepages',
					'name'          => 'movepages',
					'label-message' => 'globalrenamequeue-request-movepages',
					'type'          => 'check',
					'default'       => 1,
				],
				'suppressredirects' => [
					'id'            => 'mw-renamequeue-suppressredirects',
					'name'          => 'suppressredirects',
					'label-message' => 'globalrenamequeue-request-suppressredirects',
					'type'          => 'check',
				],
			],
			$this->getContext(),
			'globalrenamequeue'
		);

		$htmlForm
			->suppressDefaultSubmit()
			->addButton( [
				'name' => 'approve',
				'value' => $this->msg( 'globalrenamequeue-request-approve-text' )->text(),
				'id' => 'mw-renamequeue-approve',
				'attribs' => [
					'class' => 'mw-ui-flush-right',
				],
				'flags' => [ 'primary', 'progressive' ],
				'framed' => true
			] )
			->addButton( [
				'name' => 'deny',
				'value' => $this->msg( 'globalrenamequeue-request-deny-text' )->text(),
				'id' => 'mw-renamequeue-deny',
				'attribs' => [
					'class' => 'mw-ui-flush-right',
				],
				'flags' => [ 'destructive' ],
				'framed' => true
			] )
			->addButton( [
				'name' => 'cancel',
				'value' => $this->msg( 'globalrenamequeue-request-cancel-text' )->text(),
				'id' => 'mw-renamequeue-cancel',
				'attribs' => [
					'class' => 'mw-ui-flush-left',
				]
			] )
			->setId( 'mw-globalrenamequeue-request' );

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
		$htmlForm->addHeaderText( '<span class="plainlinks">' . $headerMsg->parseAsBlock() .
			'</span>' );

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

		$htmlForm->addHeaderText( $infoMsg->parseAsBlock() );

		if ( class_exists( CentralAuthSpoofUser::class ) ) {
			$spoofUser = new CentralAuthSpoofUser( $req->getNewName() );
			// @todo move this code somewhere else
			$specialGblRename = new SpecialGlobalRenameUser();
			$specialGblRename->setContext( $this->getContext() );
			$conflicts = $specialGblRename->processAntiSpoofConflicts( $req->getName(),
				$spoofUser->getConflicts() );
			if ( $conflicts ) {
				$htmlForm->addHeaderText(
					$this->msg(
						'globalrenamequeue-request-antispoof-conflicts',
						$this->getLanguage()->commaList( $conflicts )
					)->numParams( count( $conflicts ) )->parseAsBlock()
				);
			}
		}

		// Show a message if the new username matches the title blacklist.
		if ( class_exists( TitleBlacklist::class ) ) {
			$titleBlacklist = TitleBlacklist::singleton()->isBlacklisted(
				Title::makeTitleSafe( NS_USER, $req->getNewName() ),
				'new-account'
			);
			if ( $titleBlacklist instanceof TitleBlacklistEntry ) {
				$htmlForm->addHeaderText(
					$this->msg( 'globalrenamequeue-request-titleblacklist' )
						->params( wfEscapeWikiText( $titleBlacklist->getRegex() ) )->parseAsBlock()
				);
			}
		}

		// Show a log entry of previous renames under the requesting user's username
		$caTitle = Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $req->getName() );
		$extract = '';
		$extractCount = LogEventsList::showLogExtract( $extract, 'gblrename', $caTitle, '', [
			'showIfEmpty' => false,
		] );
		if ( $extractCount ) {
			$htmlForm->addHeaderText(
				Xml::fieldset( $this->msg( 'globalrenamequeue-request-previous-renames' )
					->numParams( $extractCount )
					->text(), $extract )
			);
		}

		$reason = $req->getReason() ?: $this->msg(
			'globalrenamequeue-request-reason-sul'
		)->parseAsBlock();
		$htmlForm->addHeaderText( $this->msg( 'globalrenamequeue-request-reason',
			$reason
		)->parseAsBlock() );

		$htmlForm->setSubmitCallback( [ $this, 'onProcessSubmit' ] );

		$out = $this->getOutput();
		$out->addModuleStyles( [
			'mediawiki.ui',
			'mediawiki.ui.button',
			'mediawiki.ui.input',
			'ext.centralauth.globalrenamequeue.styles',
		] );
		$out->addModules( 'ext.centralauth.globalrenamequeue' );

		$status = $htmlForm->show();
		if ( $status instanceof Status && $status->isOK() ) {
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
					[
						'from' => $oldUser->getName(),
						'to' => $newUser->getName(),
						'renamer' => $this->getUser()->getName(),
						'movepages' => true,
						'suppressredirects' => true,
						'promotetoglobal' => true,
						'reason' => $data['reason'],
						'session' => $session,
					]
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
				if ( $approved ) {
					$subject = $this->msg(
						'globalrenamequeue-email-subject-approved'
					)->inContentLanguage()->text();
					$body = $this->msg(
						'globalrenamequeue-email-body-approved',
						[
							$oldUser->getName(),
							$newUser->getName(),
						]
					)->inContentLanguage()->text();
				} else {
					$subject = $this->msg(
						'globalrenamequeue-email-subject-rejected'
					)->inContentLanguage()->text();
					$body = $this->msg(
						'globalrenamequeue-email-body-rejected',
						[
							$oldUser->getName(),
							$newUser->getName(),
							$request->getComments(),
						]
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
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$lb = $lbFactory->getMainLB( $wiki );
		$remoteDB = $lb->getConnection( DB_REPLICA, [], $wiki );
		$row = $remoteDB->selectRow(
			'user',
			[ 'user_email', 'user_name', 'user_real_name' ],
			[
				'user_name' => User::getCanonicalName( $username ),
			],
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
		return [
			self::PAGE_OPEN_QUEUE,
			self::PAGE_PROCESS_REQUEST,
			self::PAGE_CLOSED_QUEUE
		];
	}
}
