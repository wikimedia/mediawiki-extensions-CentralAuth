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

namespace MediaWiki\Extension\CentralAuth\Special;

use ExtensionRegistry;
use LogEventsList;
use MailAddress;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUIService;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserLogger;
use MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob\LocalRenameUserJob;
use MediaWiki\Extension\CentralAuth\User\CentralAuthAntiSpoofManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\TitleBlacklist\TitleBlacklist;
use MediaWiki\Extension\TitleBlacklist\TitleBlacklistEntry;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use OOUI\MessageWidget;
use RuntimeException;
use UserMailer;
use Wikimedia\Rdbms\LBFactory;
use Xml;

/**
 * Process account rename requests made via [[Special:GlobalRenameRequest]].
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2014 Bryan Davis and Wikimedia Foundation.
 * @ingroup SpecialPage
 */
class SpecialGlobalRenameQueue extends SpecialPage {

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var LBFactory */
	private $lbFactory;

	/** @var CentralAuthDatabaseManager */
	private $databaseManager;

	/** @var CentralAuthUIService */
	private $uiService;

	/** @var GlobalRenameRequestStore */
	private $globalRenameRequestStore;

	/** @var JobQueueGroupFactory */
	private $jobQueueGroupFactory;

	private CentralAuthAntiSpoofManager $caAntiSpoofManager;

	private GlobalRenameFactory $globalRenameFactory;

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	public const PAGE_OPEN_QUEUE = 'open';
	public const PAGE_PROCESS_REQUEST = 'request';
	public const PAGE_CLOSED_QUEUE = 'closed';

	private const ACTION_CANCEL = 'cancel';
	public const ACTION_VIEW = 'view';

	public function __construct(
		UserNameUtils $userNameUtils,
		LBFactory $lbFactory,
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUIService $uiService,
		GlobalRenameRequestStore $globalRenameRequestStore,
		JobQueueGroupFactory $jobQueueGroupFactory,
		CentralAuthAntiSpoofManager $caAntiSpoofManager,
		GlobalRenameFactory $globalRenameFactory
	) {
		parent::__construct( 'GlobalRenameQueue', 'centralauth-rename' );
		$this->userNameUtils = $userNameUtils;
		$this->lbFactory = $lbFactory;
		$this->databaseManager = $databaseManager;
		$this->uiService = $uiService;
		$this->globalRenameRequestStore = $globalRenameRequestStore;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->caAntiSpoofManager = $caAntiSpoofManager;
		$this->globalRenameFactory = $globalRenameFactory;
		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @param string|null $par Subpage string if one was specified
	 */
	public function execute( $par ) {
		$navigation = explode( '/', $par );
		$action = array_shift( $navigation );

		$this->outputHeader();
		$this->addSubtitleLinks();

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
		$out->setPageTitleMsg( $this->msg( $titleMessage, $titleParams ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getAssociatedNavigationLinks() {
		return [
			$this->getPageTitle( self::PAGE_OPEN_QUEUE )->getPrefixedText(),
			$this->getPageTitle( self::PAGE_CLOSED_QUEUE )->getPrefixedText(),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getShortDescription( string $path = '' ): string {
		switch ( $path ) {
			case $this->getPageTitle( self::PAGE_OPEN_QUEUE )->getText():
				return $this->msg( 'globalrenamequeue-nav-openqueue' )->text();
			case $this->getPageTitle( self::PAGE_CLOSED_QUEUE )->getText():
				return $this->msg( 'globalrenamequeue-nav-closedqueue' )->text();
			default:
				return '';
		}
	}

	private function addSubtitleLinks() {
		if ( $this->getSkin()->supportsMenu( 'associated-pages' ) ) {
			// Already shown by the skin
			return;
		}
		$links = [];
		foreach ( $this->getAssociatedNavigationLinks() as $titleText ) {
			$title = Title::newFromText( $titleText );
			$links[] = $this->getLinkRenderer()->makeKnownLink(
				$title,
				$this->getShortDescription( $title->getText() )
			);
		}
		$this->getOutput()->addSubtitle( $this->getLanguage()->pipeList( $links ) );
	}

	/**
	 * Get an array of fields for use by the HTMLForm shown above the pager.
	 *
	 * @return array[]
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
			'type' => [
				'type' => 'select',
				'name' => 'type',
				'label-message' => 'globalrenamequeue-form-type',
				'options-messages' => [
					'globalrenamequeue-form-type-all' => 'all',
					'globalrenamequeue-form-type-rename' => GlobalRenameRequest::RENAME,
					'globalrenamequeue-form-type-vanish' => GlobalRenameRequest::VANISH,
				],
				'default' => 'all',
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
		$this->outputFilterForm( $this->getCommonFormFieldsArray() );

		$pager = new RenameQueueTablePager(
			$this->getContext(),
			$this->getLinkRenderer(),
			$this->databaseManager,
			$this->userNameUtils,
			self::PAGE_OPEN_QUEUE
		);
		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
	}

	/**
	 * Handle requests to display the closed request queue
	 */
	protected function handleClosedQueue() {
		$this->commonPreamble( 'globalrenamequeue' );
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

		$pager = new RenameQueueTablePager(
			$this->getContext(),
			$this->getLinkRenderer(),
			$this->databaseManager,
			$this->userNameUtils,
			self::PAGE_CLOSED_QUEUE
		);
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
		if ( !is_numeric( $rqId ) ) {
			$this->showUnkownRequest();
			return;
		}
		$req = $this->globalRenameRequestStore->newFromId( $rqId );
		if ( !$req->exists() ) {
			$this->showUnkownRequest();
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

	private function showUnkownRequest() {
		$this->commonPreamble( 'globalrenamequeue-request-unknown-title' );
		$this->getOutput()->addWikiMsg(
			'globalrenamequeue-request-unknown-body'
		);
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

		$reason = $req->getReason() ?: $this->msg(
			'globalrenamequeue-request-reason-sul'
		)->parseAsBlock();

		$renamer = CentralAuthUser::newFromId( $req->getPerformer() );
		if ( $renamer === false ) {
			throw new RuntimeException(
				"The performer's global user id ({$req->getPerformer()}) " .
					"does not exist in the database"
			);
		}
		$homewiki = $renamer->getHomeWiki();
		if ( $renamer->isAttached() || $homewiki === null ) {
			$renamerLink = Title::makeTitleSafe( NS_USER, $renamer->getName() )->getFullURL();
		} else {
			$renamerLink = WikiMap::getForeignURL( $homewiki, 'User:' . $renamer->getName() );
		}

		if ( strpos( $reason, "\n" ) !== false ) {
			$reason = "<dl><dd>" . str_replace( "\n", "</dd><dd>", $reason ) . "</dd></dl>";
		} else {
			$reason = ': ' . $reason;
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
		$isVanishRequest = $req->getType() === GlobalRenameRequest::VANISH;

		// Set up message keys according to request type (rename/vanish)
		if ( $isVanishRequest ) {
			$commonPreambleMsg = 'globalrenamequeue-request-vanish-title';
			$approveButtonMsg = 'globalrenamequeue-request-vanish-approve-text';
			$denyButtonMsg = 'globalrenamequeue-request-vanish-deny-text';
			$globalUserInfoMsg = 'globalrenamequeue-request-vanish-userinfo';
			$headerMsgKey = 'globalrenamequeue-request-vanish-header';
			$reasonMsg = 'globalrenamequeue-request-vanish-reason';
			$approveConfirmation = 'mw-renamequeue-approve-vanish';
		} else {
			$commonPreambleMsg = 'globalrenamequeue-request-title';
			$approveButtonMsg = 'globalrenamequeue-request-approve-text';
			$denyButtonMsg = 'globalrenamequeue-request-deny-text';
			$globalUserInfoMsg = 'globalrenamequeue-request-userinfo-global';
			$headerMsgKey = 'globalrenamequeue-request-header';
			$reasonMsg = 'globalrenamequeue-request-reason';
			$approveConfirmation = 'mw-renamequeue-approve';
		}

		$this->commonPreamble( $commonPreambleMsg, [ $req->getName() ] );

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
			],
			$this->getContext(),
			'globalrenamequeue'
		);

		// Show tools to approve only when user is not reviewing own request.
		if ( $req->getName() !== $this->getUser()->getName() ) {
			$htmlForm
				->addFields( [
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
						'default'       => $isVanishRequest ? 0 : 1,
					],
					'suppressredirects' => [
						'id'            => 'mw-renamequeue-suppressredirects',
						'name'          => 'suppressredirects',
						'label-message' => 'globalrenamequeue-request-suppressredirects',
						'type'          => 'check',
						'default'       => $isVanishRequest ? 1 : 0,
						'disabled'      => $isVanishRequest ? 1 : 0,
					],
				] )
				->addButton( [
					'name' => 'approve',
					'value' => $this->msg( $approveButtonMsg )->text(),
					'id' => $approveConfirmation,
					'flags' => [ 'primary', 'progressive' ],
					'framed' => true
				] );
		}

		$htmlForm
			->suppressDefaultSubmit()
			->addButton( [
				'name' => 'deny',
				'value' => $this->msg( $denyButtonMsg )->text(),
				'id' => 'mw-renamequeue-deny',
				'flags' => [ 'destructive' ],
				'framed' => true
			] )
			->addButton( [
				'name' => 'cancel',
				'value' => $this->msg( 'globalrenamequeue-request-cancel-text' )->text(),
				'id' => 'mw-renamequeue-cancel',
			] )
			->setId( 'mw-globalrenamequeue-request' );

		if ( $req->userIsGlobal() ) {
			$globalUser = CentralAuthUser::getInstanceByName( $req->getName() );
			$homeWiki = $globalUser->getHomeWiki();
			$infoMsgKey = $globalUserInfoMsg;
		} else {
			$homeWiki = $req->getWiki();
			$infoMsgKey = 'globalrenamequeue-request-userinfo-local';
		}

		if ( $homeWiki === null ) {
			$homeLink = Title::makeTitleSafe( NS_USER, $req->getName() )->getFullURL();
		} else {
			$homeLink = WikiMap::getForeignURL( $homeWiki, 'User:' . $req->getName() );
		}

		$headerMsg = $this->msg(
			$headerMsgKey,
			$homeLink,
			$req->getName(),
			$req->getNewName()
		);
		$htmlForm->addHeaderHtml( '<span class="plainlinks">' . $headerMsg->parseAsBlock() .
			'</span>' );

		$homeWikiWiki = $homeWiki ? WikiMap::getWiki( $homeWiki ) : null;
		$infoMsgArgs = [
			$infoMsgKey,
			$req->getName(),
			// homeWikiWiki shouldn't ever be null except in
			// a development/testing environment.
			( $homeWikiWiki ? $homeWikiWiki->getDisplayName() : $homeWiki ),
		];
		// Rename requests need the new username into the info message
		if ( !$isVanishRequest ) {
			$infoMsgArgs[] = $req->getNewName();
		}
		$infoMsg = $this->msg( ...$infoMsgArgs );

		if ( isset( $globalUser ) ) {
			$infoMsg->numParams( $globalUser->getGlobalEditCount() );
			$infoMsg->params( $this->msg(
				$globalUser->isBlocked() ?
					'globalrenamequeue-request-vanish-user-blocked' :
					'globalrenamequeue-request-vanish-user-not-blocked'
			) );
		}

		$htmlForm->addHeaderHtml( $infoMsg->parseAsBlock() );

		// Handle AntiSpoof integration
		$spoofUser = $this->caAntiSpoofManager->getSpoofUser( $req->getNewName() );
		$conflicts = $this->uiService->processAntiSpoofConflicts(
			$this->getContext(),
			$req->getName(),
			$spoofUser->getConflicts()
		);
		$renamedUser = $this->caAntiSpoofManager->getOldRenamedUserName( $req->getNewName() );
		if ( $renamedUser !== null ) {
			$conflicts[] = $renamedUser;
		}
		if ( $conflicts ) {
			$htmlForm->addHeaderHtml(
				$this->msg(
					'globalrenamequeue-request-antispoof-conflicts',
					$this->getLanguage()->commaList( $conflicts )
				)->numParams( count( $conflicts ) )->parseAsBlock()
			);
		}

		// Show a message if the new username matches the title blacklist.
		if ( ExtensionRegistry::getInstance()->isLoaded( 'TitleBlacklist' ) ) {
			$titleBlacklist = TitleBlacklist::singleton()->isBlacklisted(
				Title::makeTitleSafe( NS_USER, $req->getNewName() ),
				'new-account'
			);
			if ( $titleBlacklist instanceof TitleBlacklistEntry ) {
				$htmlForm->addHeaderHtml(
					$this->msg( 'globalrenamequeue-request-titleblacklist' )
						->params( wfEscapeWikiText( $titleBlacklist->getRegex() ) )->parseAsBlock()
				);
			}
		}

		// Show a log entry of previous renames under the requesting user's username
		$caTitle = SpecialPage::getTitleFor( 'CentralAuth', $req->getName() );
		$extract = '';
		$extractCount = LogEventsList::showLogExtract( $extract, 'gblrename', $caTitle, '', [
			'showIfEmpty' => false,
		] );
		if ( $extractCount ) {
			$htmlForm->addHeaderHtml(
				Xml::fieldset( $this->msg( 'globalrenamequeue-request-previous-renames' )
					->numParams( $extractCount )
					->text(), $extract )
			);
		}

		$reason = $req->getReason() ?: $this->msg(
			'globalrenamequeue-request-reason-sul'
		)->parseAsBlock();
		$htmlForm->addHeaderHtml( $this->msg( $reasonMsg,
			"<dl><dd>" . str_replace( "\n", "</dd><dd>", $reason ) . "</dd></dl>"
		)->parseAsBlock() );

		// Show warning when reviewing own request
		if ( $req->getName() === $this->getUser()->getName() ) {
			$message = new MessageWidget( [
				'label' => $this->msg( 'globalrenamerequest-self-warning' )->text(),
				'type' => 'warning',
				'inline' => true
			] );
			$htmlForm->addHeaderHtml( $message->toString() );
		}

		$htmlForm->setSubmitCallback( [ $this, 'onProcessSubmit' ] );

		$out = $this->getOutput();
		$out->addModuleStyles( 'ext.centralauth.globalrenamequeue.styles' );
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

	/**
	 * @param bool $approved
	 * @param array $data
	 *
	 * @return Status
	 */
	protected function doResolveRequest( $approved, $data ) {
		$request = $this->globalRenameRequestStore->newFromId( $data['rid'] );
		$oldUser = User::newFromName( $request->getName() );

		$newUser = User::newFromName( $request->getNewName(), 'creatable' );
		$status = new Status;
		$session = $this->getContext()->exportSession();
		if ( $approved ) {
			// Disallow self-renaming
			if ( $request->getName() === $this->getUser()->getName() ) {
				return Status::newFatal( 'globalrenamerequest-self-error' );
			}

			if ( $request->userIsGlobal() ) {
				$renameOptions = $data;
				$renameOptions[ 'requestType' ] = $request->getType();

				// Trigger a global rename job
				$status = $this->globalRenameFactory
					->newGlobalRenameUser(
						$this->getUser(),
						CentralAuthUser::getInstanceByName( $request->getName() ),
						$request->getNewName()
					)
					->withSession( $session )
					->rename( $renameOptions );
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
						'requestType' => $request->getType(),
					]
				);
				$this->jobQueueGroupFactory->makeJobQueueGroup( $request->getWiki() )->push( $job );
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

			if ( $this->globalRenameRequestStore->save( $request ) ) {
				if ( $request->getType() === GlobalRenameRequest::VANISH ) {
					$emailSubjectApprovedMsg = 'globalrenamequeue-vanish-email-subject-approved';
					$emailBodyApprovedMsg = 'globalrenamequeue-vanish-email-body-approved';
					$emailBodyApprovedWithNoteMsg = 'globalrenamequeue-vanish-email-body-approved-with-note';
					$emailSubjectRejectedMsg = 'globalrenamequeue-vanish-email-subject-rejected';
					$emailBodyRejectedMsg = 'globalrenamequeue-vanish-email-body-rejected';
					$emailBodyMsgArgs = [
						$oldUser->getName(),
						$request->getComments()
					];
				} else {
					$emailSubjectApprovedMsg = 'globalrenamequeue-email-subject-approved';
					$emailBodyApprovedMsg = 'globalrenamequeue-email-body-approved';
					$emailBodyApprovedWithNoteMsg = 'globalrenamequeue-email-body-approved-with-note';
					$emailSubjectRejectedMsg = 'globalrenamequeue-email-subject-rejected';
					$emailBodyRejectedMsg = 'globalrenamequeue-email-body-rejected';
					$emailBodyMsgArgs = [
						$oldUser->getName(),
						$newUser->getName(),
						$request->getComments(),
					];
				}

				// Send email to the user about the change in status.
				if ( $approved ) {
					$subject = $this->msg(
						$emailSubjectApprovedMsg
					)->inContentLanguage()->text();
					if ( $request->getComments() === '' ) {
						$msgKey = $emailBodyApprovedMsg;
					} else {
						$msgKey = $emailBodyApprovedWithNoteMsg;
					}
					$body = $this->msg(
						$msgKey, $emailBodyMsgArgs
					)->inContentLanguage()->text();
				} else {
					$subject = $this->msg(
						$emailSubjectRejectedMsg
					)->inContentLanguage()->text();
					$body = $this->msg(
						$emailBodyRejectedMsg, $emailBodyMsgArgs
					)->inContentLanguage()->text();
				}

				if ( $request->userIsGlobal() || $request->getWiki() === WikiMap::getCurrentWikiId() ) {
					$notifyEmail = MailAddress::newFromUser( $oldUser );
				} else {
					$notifyEmail = $this->getRemoteUserMailAddress(
						$request->getWiki(), $request->getName()
					);
				}

				if ( $notifyEmail !== null && $notifyEmail->address ) {
					$type = $approved ? 'approval' : 'rejection';
					$this->logger->info( "Send $type email to User:{oldName}", [
						'oldName' => $oldUser->getName(),
						'component' => 'GlobalRename',
					] );
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
		$lb = $this->lbFactory->getMainLB( $wiki );
		$remoteDB = $lb->getConnection( DB_REPLICA, [], $wiki );
		$row = $remoteDB->newSelectQueryBuilder()
			->select( [ 'user_email', 'user_name', 'user_real_name' ] )
			->from( 'user' )
			->where( [
				'user_name' => $this->userNameUtils->getCanonical( $username ),
			] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $row === false ) {
			$address = null;
		} else {
			$address = new MailAddress(
				$row->user_email, $row->user_name, $row->user_real_name
			);
		}
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
		$from = new MailAddress(
			$this->getConfig()->get( 'PasswordSender' ),
			$this->msg( 'emailsender' )->inContentLanguage()->text()
		);
		return UserMailer::send( $to, $from, $subject, $body );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	/** @inheritDoc */
	public function getSubpagesForPrefixSearch() {
		return [
			self::PAGE_OPEN_QUEUE,
			self::PAGE_PROCESS_REQUEST,
			self::PAGE_CLOSED_QUEUE
		];
	}
}
