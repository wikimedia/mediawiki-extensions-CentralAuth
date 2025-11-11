<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use DateInterval;
use Exception;
use InvalidArgumentException;
use MediaWiki\Block\Restriction\ActionRestriction;
use MediaWiki\Block\Restriction\NamespaceRestriction;
use MediaWiki\Block\Restriction\PageRestriction;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUIService;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\User\CentralAuthGlobalRegistrationProvider;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\Widget\HTMLGlobalUserTextField;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLookup;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserNameUtils;
use MediaWiki\Utils\MWTimestamp;
use MediaWiki\WikiMap\WikiMap;
use MediaWiki\WikiMap\WikiReference;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\PanelLayout;
use OOUI\Widget;
use StatusValue;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

class SpecialCentralAuth extends SpecialPage {

	/** @var string */
	private $mUserName;
	/** @var bool */
	private $mCanUnmerge;
	/** @var bool */
	private $mCanLock;
	/** @var bool */
	private $mCanSuppress;
	/** @var bool */
	private $mCanEdit;
	/** @var bool */
	private $mCanChangeGroups;

	/**
	 * @var CentralAuthUser
	 */
	private $mGlobalUser;

	/**
	 * @var array[]
	 */
	private $mAttachedLocalAccounts;

	/**
	 * @var array
	 */
	private $mUnattachedLocalAccounts;

	/** @var string */
	private $mMethod;

	/** @var bool */
	private $mPosted;

	/** @var string[] */
	private $mWikis;

	private CommentFormatter $commentFormatter;
	private IConnectionProvider $dbProvider;
	private NamespaceInfo $namespaceInfo;
	private TempUserConfig $tempUserConfig;
	private UserFactory $userFactory;
	private UserNameUtils $userNameUtils;
	private UserRegistrationLookup $userRegistrationLookup;
	private CentralAuthDatabaseManager $databaseManager;
	private CentralAuthUIService $uiService;
	private GlobalRenameFactory $globalRenameFactory;

	public function __construct(
		CommentFormatter $commentFormatter,
		IConnectionProvider $dbProvider,
		NamespaceInfo $namespaceInfo,
		TempUserConfig $tempUserConfig,
		UserFactory $userFactory,
		UserNameUtils $userNameUtils,
		UserRegistrationLookup $userRegistrationLookup,
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUIService $uiService,
		GlobalRenameFactory $globalRenameFactory
	) {
		parent::__construct( 'CentralAuth' );
		$this->commentFormatter = $commentFormatter;
		$this->dbProvider = $dbProvider;
		$this->namespaceInfo = $namespaceInfo;
		$this->tempUserConfig = $tempUserConfig;
		$this->userFactory = $userFactory;
		$this->userNameUtils = $userNameUtils;
		$this->userRegistrationLookup = $userRegistrationLookup;
		$this->databaseManager = $databaseManager;
		$this->uiService = $uiService;
		$this->globalRenameFactory = $globalRenameFactory;
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	public function execute( $subpage ) {
		$this->setHeaders();
		$this->addHelpLink( 'Extension:CentralAuth' );

		$authority = $this->getContext()->getAuthority();
		$this->mCanUnmerge = $authority->isAllowed( 'centralauth-unmerge' );
		$this->mCanLock = $authority->isAllowed( 'centralauth-lock' );
		$this->mCanSuppress = $authority->isAllowed( 'centralauth-suppress' );
		$this->mCanEdit = $this->mCanUnmerge || $this->mCanLock || $this->mCanSuppress;
		$this->mCanChangeGroups = $authority->isAllowed( 'globalgroupmembership' );

		$this->getOutput()->setPageTitleMsg(
			$this->msg( $this->mCanEdit ? 'centralauth' : 'centralauth-ro' )
		);
		$this->getOutput()->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
		$this->getOutput()->addModules( 'ext.centralauth' );
		$this->getOutput()->addModuleStyles( 'ext.centralauth.misc.styles' );
		$this->getOutput()->addJsConfigVars(
			'wgMergeMethodDescriptions', $this->getMergeMethodDescriptions()
		);

		$this->mUserName = $this->getRequest()->getText( 'target', $subpage ?? '' );
		if ( $this->mUserName === '' ) {
			# First time through
			$this->getOutput()->addWikiMsg( 'centralauth-admin-intro' );
			$this->showUsernameForm();
			return;
		}

		$canonUsername = $this->userNameUtils->getCanonical( $this->mUserName );
		if ( $canonUsername === false ) {
			$this->showNonexistentError();
			return;
		}
		// Use the canonical username from this point on, so that all links etc. work right (T392340)
		$this->mUserName = $canonUsername;

		$this->mPosted = $this->getRequest()->wasPosted();
		$this->mMethod = $this->getRequest()->getVal( 'wpMethod' );
		$this->mWikis = (array)$this->getRequest()->getArray( 'wpWikis' );

		// If wpReasonList is specified then for backwards compatability with the old format of the admin status form,
		// the value of wpReason needs to be moved to wpReason-other and the value of wpReasonList needs to be moved
		// to wpReason.
		if ( $this->getRequest()->getVal( 'wpReasonList' ) ) {
			$this->getRequest()->setVal( 'wpReason-other', $this->getRequest()->getVal( 'wpReason' ) );
			$this->getRequest()->setVal( 'wpReason', $this->getRequest()->getVal( 'wpReasonList' ) );
			$this->getRequest()->unsetVal( 'wpReasonList' );
		}

		// Possible demo states

		// success, all accounts merged
		// successful login, some accounts merged, others left
		// successful login, others left
		// not account owner, others left

		// is owner / is not owner
		// did / did not merge some accounts
		// do / don't have more accounts to merge

		$userPage = Title::makeTitleSafe( NS_USER, $this->mUserName );
		if ( $userPage ) {
			$localUser = User::newFromName( $this->mUserName, false );
			$this->getSkin()->setRelevantUser( $localUser );
		}

		// per T49991
		$this->getOutput()->setHTMLTitle( $this->msg(
			'pagetitle',
			$this->msg(
				$this->mCanEdit ? 'centralauth-admin-title' : 'centralauth-admin-title-ro',
				$this->mUserName
			)->plain()
		) );

		$globalUser = $this->getRequest()->wasPosted()
			? CentralAuthUser::getPrimaryInstanceByName( $this->mUserName )
			: CentralAuthUser::getInstanceByName( $this->mUserName );
		$this->mGlobalUser = $globalUser;

		if ( ( $globalUser->isSuppressed() || $globalUser->isHidden() ) &&
			!$this->mCanSuppress
		) {
			// Claim that there's nothing if the global account is hidden and the user is not
			// allowed to see it.
			$this->showNonexistentError();
			return;
		}

		$continue = true;
		if ( $this->mCanEdit && $this->mPosted ) {
			$this->databaseManager->assertNotReadOnly();
			$continue = $this->doSubmit();
		}

		// Show just a user friendly message when a rename is in progress
		try {
			$this->mAttachedLocalAccounts = $globalUser->queryAttached();
		} catch ( Exception $e ) {
			if ( $globalUser->renameInProgress() ) {
				$this->showRenameInProgressError();
				return;
			}
			// Rethrow
			throw $e;
		}

		$this->mUnattachedLocalAccounts = $globalUser->queryUnattached();

		if ( !$globalUser->exists() && !count( $this->mUnattachedLocalAccounts ) ) {
			// Nothing to see here
			$this->showNonexistentError();
			return;
		}

		$this->showUsernameForm();
		if ( $continue && $globalUser->exists() ) {
			$this->showInfo();
			$this->showGlobalBlockingExemptWikisList();
			if ( $this->mCanLock ) {
				$this->showStatusForm();
			}
			if ( $this->mCanUnmerge ) {
				$this->showDeleteGlobalAccountForm();
			}
			$this->showLogExtract();
			$this->showWikiLists();
		} elseif ( $continue && !$globalUser->exists() ) {
			// No global account, but we can still list the local ones
			$this->showError( 'centralauth-admin-nonexistent', $this->mUserName );
			$this->showWikiLists();
		}
	}

	private function showNonexistentError() {
		$this->showError( 'centralauth-admin-nonexistent', $this->mUserName );
		$this->showUsernameForm();
	}

	private function showRenameInProgressError() {
		$this->showError( 'centralauth-admin-rename-in-progress', $this->mUserName );
		$renameStatus = $this->globalRenameFactory->newGlobalRenameUserStatus( $this->mUserName );
		$names = $renameStatus->getNames();
		$this->uiService->showRenameLogExtract( $this->getContext(), $names[1] );
	}

	/**
	 * @return bool Returns true if the normal form should be displayed
	 */
	public function doSubmit() {
		$deleted = false;
		$globalUser = $this->mGlobalUser;
		$request = $this->getRequest();

		$givenState = $request->getVal( 'wpUserState' );
		$stateCheck = $givenState === $globalUser->getStateHash( true );

		if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
			$this->showError( 'centralauth-token-mismatch' );
		} elseif ( $this->mMethod == 'unmerge' && $this->mCanUnmerge ) {
			$status = $globalUser->adminUnattach( $this->mWikis );
			if ( !$status->isGood() ) {
				$this->showStatusError( $status );
			} else {
				$this->showSuccess( 'centralauth-admin-unmerge-success',
					$this->getLanguage()->formatNum( $status->successCount ),
					/* deprecated */ $status->successCount );
			}
		} elseif ( $this->mMethod == 'delete' && $this->mCanUnmerge ) {
			$status = $globalUser->adminDelete( $request->getVal( 'reason' ), $this->getUser() );
			if ( !$status->isGood() ) {
				$this->showStatusError( $status );
			} else {
				$this->showSuccess( 'centralauth-admin-delete-success', $this->mUserName );
				$deleted = true;
			}
		} elseif ( $this->mMethod == 'set-status' && !$stateCheck ) {
			$this->showError( 'centralauth-state-mismatch' );
		} elseif ( $this->mMethod == 'set-status' && $this->mCanLock ) {
			$setLocked = $request->getBool( 'wpStatusLocked' );
			$setHidden = $request->getInt( 'wpStatusHidden', -1 );
			$reason = $request->getText( 'wpReason' );
			$reasonDetail = $request->getText( 'wpReason-other' );

			if ( $reason == 'other' ) {
				$reason = $reasonDetail;
			} elseif ( $reasonDetail ) {
				$reason .= $this->msg( 'colon-separator' )->inContentLanguage()->text() .
					$reasonDetail;
			}

			$status = $globalUser->adminLockHide(
				$setLocked,
				$setHidden,
				$reason,
				$this->getContext()
			);

			// Tell the user what happened
			if ( !$status->isGood() ) {
				$this->showStatusError( $status );
			} elseif ( $status->successCount > 0 ) {
				$this->showSuccess( 'centralauth-admin-setstatus-success', wfEscapeWikitext( $this->mUserName ) );
			}
		} else {
			$this->showError( 'centralauth-admin-bad-input' );
		}
		return !$deleted;
	}

	private function showStatusError( StatusValue $status ) {
		foreach ( $status->getMessages() as $msg ) {
			$this->showError( $msg );
		}
	}

	/**
	 * @param string|MessageSpecifier $key
	 * @param mixed ...$params
	 */
	private function showError( $key, ...$params ) {
		$this->getOutput()->addHTML( Html::errorBox( $this->msg( $key, ...$params )->parse() ) );
	}

	/**
	 * @param string|MessageSpecifier $key
	 * @param mixed ...$params
	 */
	private function showSuccess( $key, ...$params ) {
		$this->getOutput()->addHTML( Html::successBox( $this->msg( $key, ...$params )->parse() ) );
	}

	private function showUsernameForm() {
		$lookup = $this->msg(
			$this->mCanEdit ? 'centralauth-admin-lookup-rw' : 'centralauth-admin-lookup-ro'
		)->text();

		$formDescriptor = [
			'user' => [
				'class' => HTMLGlobalUserTextField::class,
				'name' => 'target',
				'label-message' => 'centralauth-admin-username',
				'size' => 25,
				'id' => 'target',
				'default' => $this->mUserName,
				'required' => true
			]
		];

		$legend = $this->msg( $this->mCanEdit ? 'centralauth-admin-manage' : 'centralauth-admin-view' )->text();

		$context = new DerivativeContext( $this->getContext() );
		// Remove subpage
		$context->setTitle( $this->getPageTitle() );
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $context );
		$htmlForm
			->setMethod( 'get' )
			->setSubmitText( $lookup )
			->setSubmitID( 'centralauth-submit-find' )
			->setWrapperLegend( $legend )
			->prepareForm()
			->displayForm( false );
	}

	private function showInfo() {
		$attribs = $this->getInfoFields();

		// Give grep a chance to find the usages:
		// centralauth-admin-info-username, centralauth-admin-info-registered,
		// centralauth-admin-info-editcount, centralauth-admin-info-locked,
		// centralauth-admin-info-hidden, centralauth-admin-info-groups
		$content = Html::openElement( "ul" );
		foreach ( $attribs as $key => [ 'label' => $msg, 'data' => $data ] ) {
			$content .= Html::rawElement(
				'li',
				[ 'id' => "mw-centralauth-admin-info-$key" ],
				$this->msg( 'centralauth-admin-info-line' )
					->params( $this->msg( $msg )->escaped() )
					->rawParams( $data )
					->parse()
			);
		}
		$content .= Html::closeElement( "ul" );

		$this->getOutput()->addHTML( $this->getFramedFieldsetLayout(
			$content, 'centralauth-admin-info-header', 'mw-centralauth-info'
		) );
	}

	/**
	 * @return array Array of arrays where each array has two keys: 'label' containing the message
	 *   key or Message object to be used as the label, and 'data' which is the already HTML escaped
	 *   value that is associated with the label. The first dimension keys are field names, but are currently
	 *   not used in the UI or for generating messages.
	 * @phan-return array<string,array{label:string|Message,data:string}>
	 */
	private function getInfoFields() {
		$globalUser = $this->mGlobalUser;

		$reg = $globalUser->getRegistration();
		$age = $this->uiService->prettyTimespan(
			$this->getContext(),
			(int)wfTimestamp( TS_UNIX ) - (int)wfTimestamp( TS_UNIX, $reg )
		);
		$editCountCached = $globalUser->getGlobalEditCount();
		$attribs = [
			'username' => htmlspecialchars( $globalUser->getName() ),
			'registered' => htmlspecialchars(
				$this->getLanguage()->timeanddate( $reg, true ) . " ($age)" ),
			'editcount' => htmlspecialchars(
				$this->getLanguage()->formatNum( $editCountCached ) ),
		];
		$editCountSummed = $this->evaluateTotalEditcount();
		if ( $editCountCached !== $editCountSummed ) {
			$attribs['editcountsum'] = htmlspecialchars(
				$this->getLanguage()->formatNum( $editCountSummed ) );
		}
		$attribs['attached'] = htmlspecialchars(
			$this->getLanguage()->formatNum( count( $this->mAttachedLocalAccounts ) ) );
		if (
			// Renaming self is not allowed.
			$globalUser->getName() !== $this->getContext()->getUser()->getName()
			&& $this->getContext()->getAuthority()->isAllowed( 'centralauth-rename' )
		) {
			$renameLink = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'GlobalRenameUser', $globalUser->getName() ),
				$this->msg( 'centralauth-admin-info-username-rename' )->text()
			);

			$attribs['username'] .= $this->msg( 'word-separator' )->escaped();
			$attribs['username'] .= $this->msg( 'parentheses' )->rawParams( $renameLink )->escaped();
		}

		if ( count( $this->mUnattachedLocalAccounts ) ) {
			$attribs['unattached'] = htmlspecialchars(
				$this->getLanguage()->formatNum( count( $this->mUnattachedLocalAccounts ) ) );
		}

		if ( $globalUser->isLocked() ) {
			$attribs['locked'] = $this->msg( 'centralauth-admin-yes' )->escaped();
		}

		if ( $this->mCanSuppress ) {
			$attribs['hidden'] = $this->uiService->formatHiddenLevel(
				$this->getContext(),
				$globalUser->getHiddenLevelInt()
			);
		}

		if ( $this->tempUserConfig->isTempName( $globalUser->getName() ) ) {
			$localUser = $this->userFactory->newFromName( $globalUser->getName() );
			// if the central user is valid, the local username is too, but Phan doesn't know that
			'@phan-var User $localUser';
			$registrationDate = $this->userRegistrationLookup
				->getRegistration( $localUser, CentralAuthGlobalRegistrationProvider::TYPE );
			$expirationDays = $this->tempUserConfig->getExpireAfterDays();
			if ( $registrationDate && $expirationDays ) {
				// Add one day to account for the expiration script running daily
				$expirationDate = MWTimestamp::getInstance( $registrationDate )
					->add( new DateInterval( 'P' . ( $expirationDays + 1 ) . 'D' ) );
				if ( $expirationDate->getTimestamp() < MWTimestamp::time() ) {
					$attribs['expired'] = htmlspecialchars( $this->getLanguage()
						->userTimeAndDate( $expirationDate->timestamp, $localUser ) );
				}
			}
		}

		// Convert the values of the existing $attribs array into arrays, where the value is placed in the 'data'
		// key and the 'label' is the message key generated from the associated key.
		$attribsWithMessageKeys = [];
		foreach ( $attribs as $key => $value ) {
			$attribsWithMessageKeys[$key] = [
				'label' => "centralauth-admin-info-$key",
				'data' => $value,
			];
		}

		$groups = $globalUser->getGlobalGroupsWithExpiration();
		if ( $groups ) {
			$groupLinks = [];
			// Ensure temporary groups are displayed first, to avoid ambiguity like
			// "first, second (expires at some point)" (unclear if only second expires or if both expire)
			uasort( $groups, static fn ( $first, $second ) => (bool)$second <=> (bool)$first );

			$uiLanguage = $this->getLanguage();
			$uiUser = $this->getUser();

			foreach ( $groups as $group => $expiry ) {
				$link = $this->getLinkRenderer()->makeLink(
					SpecialPage::getTitleFor( 'GlobalGroupPermissions', $group ),
					$uiLanguage->getGroupName( $group )
				);

				if ( $expiry ) {
					$link = $this->msg( 'group-membership-link-with-expiry' )
						->rawParams( $link )
						->params( $uiLanguage->userTimeAndDate( $expiry, $uiUser ) )
						->escaped();
				}

				$groupLinks[] = $link;
			}

			$attribsWithMessageKeys['groups'] = [
				'label' => $this->msg( 'centralauth-admin-info-groups' )->numParams( count( $groups ) ),
				'data' => $uiLanguage->commaList( $groupLinks ),
			];
		}

		if ( $this->mCanChangeGroups ) {
			if ( !isset( $attribsWithMessageKeys['groups'] ) ) {
				$attribsWithMessageKeys['groups'] = [
					'label' => $this->msg( 'centralauth-admin-info-groups' )->numParams( 0 ),
					'data' => $this->msg( 'rightsnone' )->escaped(),
				];
			}

			$manageGroupsLink = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'GlobalGroupMembership', $globalUser->getName() ),
				$this->msg( 'centralauth-admin-info-groups-manage' )->text()
			);

			$attribsWithMessageKeys['groups']['data'] .= $this->msg( 'word-separator' )->escaped();
			$attribsWithMessageKeys['groups']['data'] .= $this->msg( 'parentheses' )
				->rawParams( $manageGroupsLink )->escaped();
		}

		$caHookRunner = new CentralAuthHookRunner( $this->getHookContainer() );
		$caHookRunner->onCentralAuthInfoFields( $globalUser, $this->getContext(), $attribsWithMessageKeys );

		return $attribsWithMessageKeys;
	}

	private function showGlobalBlockingExemptWikisList() {
		$tableRows = $this->getGlobalBlockingExemptWikiTableRows();
		if ( !$tableRows ) {
			return;
		}

		$header = Html::openElement( 'thead' ) . Html::openElement( 'tr' );
		$header .= Html::element(
			'th',
			[],
			$this->msg( 'centralauth-admin-globalblock-exempt-list-wiki-heading' )->text()
		);
		$header .= Html::element(
			'th',
			[ 'class' => 'unsortable' ],
			$this->msg( 'centralauth-admin-globalblock-exempt-list-reason-heading' )->text()
		);
		$header .= Html::closeElement( 'tr' ) . Html::closeElement( 'thead' );

		$body = Html::rawElement( 'tbody', [], $tableRows );

		$tableHtml = Html::rawElement(
			'table',
			[ 'class' => 'wikitable sortable mw-centralauth-globalblock-exempt-list-table' ],
			$header . $body
		);
		$this->getOutput()->addHTML( $this->getFramedFieldsetLayout(
			$tableHtml, 'centralauth-admin-globalblock-exempt-list',
			'mw-centralauth-globalblock-exempt-list'
		) );
	}

	private function getGlobalBlockingExemptWikiTableRows(): string {
		// There can be no global blocks if the GlobalBlocking extension is not loaded.
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'GlobalBlocking' ) ) {
			return '';
		}

		$globalBlockingServices = GlobalBlockingServices::wrap( MediaWikiServices::getInstance() );
		$globalBlock = $globalBlockingServices->getGlobalBlockLookup()
			->getGlobalBlockingBlock( null, $this->mGlobalUser->getId(), GlobalBlockLookup::SKIP_LOCAL_DISABLE_CHECK );
		// If the user is not globally blocked, then their global block cannot be locally disabled (so there will be
		// no rows to display).
		if ( $globalBlock === null ) {
			return '';
		}

		$html = '';
		$queryWikis = array_merge( $this->mAttachedLocalAccounts, $this->mUnattachedLocalAccounts );
		foreach ( $queryWikis as $wiki ) {
			// Check if the global block is disabled on the given wiki, and if it is then add it to the table HTML.
			$localBlockStatus = $globalBlockingServices->getGlobalBlockLocalStatusLookup()
				->getLocalStatusInfo( $globalBlock->gb_id, $wiki['wiki'] );
			if ( $localBlockStatus === false ) {
				continue;
			}

			$row = Html::rawElement( 'td', [], $this->foreignUserLink( $wiki['wiki'] ) ) .
				Html::element( 'td', [], $localBlockStatus['reason'] );
			$html .= Html::rawElement( 'tr', [], $row );
		}
		return $html;
	}

	/**
	 * Generates a {@link FieldLayout} that can be used in a HTMLFormInfo field instance when 'rawrow' is true.
	 * This is useful in the case that the HTML contains elements which cannot appear inside a label element.
	 *
	 * @param string $html The HTML that we want to use in the 'info' field
	 * @return FieldLayout An instance suitable for use in an 'info' field with 'rawrow' set to true
	 */
	private function getFieldLayoutForHtmlContent( string $html ): FieldLayout {
		return new FieldLayout( new Widget( [ 'content' => new HtmlSnippet( $html ) ] ) );
	}

	private function showWikiLists() {
		$showUnmergeCheckboxes = $this->mCanUnmerge && $this->mGlobalUser->exists();

		$formDescriptor = [
			'wikilist' => [
				'type' => 'info',
				'raw' => true,
				// We need to use a "rawrow" here to prevent the table element being wrapped by a label element.
				'rawrow' => true,
				// When using "rawrow" we need to provide the HTML content via a FieldLayout.
				'default' => $this->getFieldLayoutForHtmlContent( $this->getWikiListsTable( $showUnmergeCheckboxes ) ),
			],
			'Method' => [
				'type' => 'hidden',
				'default' => 'unmerge',
			],
		];

		if ( $showUnmergeCheckboxes ) {
			$formDescriptor['submit'] = [
				'type' => 'submit',
				'buttonlabel-message' => 'centralauth-admin-unmerge',
				'flags' => [ 'progressive' ],
			];
		}

		$context = new DerivativeContext( $this->getContext() );
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $context );
		$htmlForm
			->setAction( $this->getPageTitle()->getFullURL( [ 'target' => $this->mUserName ] ) )
			->suppressDefaultSubmit()
			->setWrapperLegendMsg(
				$showUnmergeCheckboxes ? 'centralauth-admin-list-legend-rw' : 'centralauth-admin-list-legend-ro'
			)
			->setId( 'mw-centralauth-merged' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @param bool $showUnmergeCheckboxes Whether the checkboxes to allow the user to unmerge a local account should
	 *   be shown in the table.
	 * @return string The HTML for the table of local accounts
	 */
	private function getWikiListsTable( bool $showUnmergeCheckboxes ): string {
		$columns = [
			// centralauth-admin-list-localwiki
			"localwiki",
			// centralauth-admin-list-attached-on
			"attached-on",
			// centralauth-admin-list-method
			"method",
			// centralauth-admin-list-blocked
			"blocked",
			// centralauth-admin-list-editcount
			"editcount",
			// centralauth-admin-list-groups
			"groups",
		];

		$header = Html::openElement( 'thead' ) . Html::openElement( 'tr' );
		if ( $showUnmergeCheckboxes ) {
			$header .= Html::element( 'th', [ 'class' => 'unsortable' ] );
		}
		foreach ( $columns as $c ) {
			$header .= Html::element( 'th', [], $this->msg( "centralauth-admin-list-$c" )->text() );
		}
		$header .= Html::closeElement( 'tr' ) . Html::closeElement( 'thead' );

		$body = Html::rawElement(
			'tbody', [],
			$this->listAccounts( $this->mAttachedLocalAccounts, $showUnmergeCheckboxes ) .
			$this->listAccounts( $this->mUnattachedLocalAccounts, $showUnmergeCheckboxes )
		);

		return Html::rawElement(
			'table',
			[ 'class' => 'wikitable sortable mw-centralauth-wikislist' ],
			$header . $body
		);
	}

	/**
	 * @param array[] $list The result of {@link CentralAuthUser::queryAttached} or
	 *   {@link CentralAuthUser::queryUnattached()}
	 * @return string The HTML body table rows
	 */
	private function listAccounts( array $list, bool $showUnmergeCheckboxes ): string {
		ksort( $list );
		return implode( "\n", array_map( function ( $row ) use ( $showUnmergeCheckboxes ) {
			return $this->listWikiItem( $row, $showUnmergeCheckboxes );
		}, $list ) );
	}

	/**
	 * @param array $row The an item from an array returned by either {@link CentralAuthUser::queryAttached} or
	 *    {@link CentralAuthUser::queryUnattached()}
	 * @return string The HTML row that represents the provided array
	 */
	private function listWikiItem( array $row, bool $showUnmergeCheckboxes ): string {
		$html = Html::openElement( 'tr' );

		if ( $showUnmergeCheckboxes ) {
			if ( !empty( $row['attachedMethod'] ) ) {
				$html .= Html::rawElement( 'td', [], $this->adminCheck( $row['wiki'] ) );
			} else {
				// Account is unattached, don't show checkbox to detach
				$html .= Html::element( 'td' );
			}
		}

		$html .= Html::rawElement( 'td', [], $this->foreignUserLink( $row['wiki'] ) );

		$attachedTimestamp = $row['attachedTimestamp'] ?? '';

		$html .= $this->getAttachedTimestampField( $attachedTimestamp );

		if ( empty( $row['attachedMethod'] ) ) {
			$attachedMethod = $this->msg( 'centralauth-admin-unattached' )->parse();
		} else {
			$attachedMethod = $this->formatMergeMethod( $row['attachedMethod'] );
		}
		$html .= Html::rawElement( 'td', [ 'class' => 'mw-centralauth-wikislist-method' ], $attachedMethod );

		$html .= Html::rawElement( 'td', [ 'style' => 'overflow-wrap: anywhere;' ], $this->formatBlockStatus( $row ) ) .
			Html::rawElement(
				'td', [ 'class' => 'mw-centralauth-wikislist-editcount' ], $this->formatEditcount( $row )
			) .
			Html::rawElement( 'td',
				[ 'data-sort-value' => count( $row['groupMemberships'] ) ],
				$this->formatGroups( $row )
			) .
			Html::closeElement( 'tr' );

		return $html;
	}

	/**
	 * @param string|null $attachedTimestamp
	 *
	 * @return string
	 */
	private function getAttachedTimestampField( $attachedTimestamp ) {
		if ( !$attachedTimestamp ) {
			$html = Html::openElement( 'td', [ 'data-sort-value' => '0' ] ) .
				$this->msg( 'centralauth-admin-unattached' )->parse();
		} else {
			$html = Html::openElement( 'td',
				[ 'data-sort-value' => $attachedTimestamp ] ) .
				// visible date and time in users preference
				htmlspecialchars( $this->getLanguage()->timeanddate( $attachedTimestamp, true ) );
		}

		$html .= Html::closeElement( 'td' );
		return $html;
	}

	/**
	 * @param string $method
	 * @return string
	 * @see CentralAuthUser::attach()
	 */
	private function formatMergeMethod( $method ) {
		// Give grep a chance to find the usages:
		// centralauth-merge-method-primary, centralauth-merge-method-empty,
		// centralauth-merge-method-mail, centralauth-merge-method-password,
		// centralauth-merge-method-admin, centralauth-merge-method-new,
		// centralauth-merge-method-login
		$brief = $this->msg( "centralauth-merge-method-{$method}" )->text();
		$html =
			Html::element(
				'img', [
					'src' => $this->getConfig()->get( MainConfigNames::ExtensionAssetsPath )
						. "/CentralAuth/images/icons/merged-{$method}.png",
					'alt' => $brief,
					'title' => $brief,
				]
			)
			. Html::element(
				'span', [
					'class' => 'merge-method-help',
					'title' => $brief,
					'data-centralauth-mergemethod' => $method
				],
				$this->msg( 'centralauth-merge-method-questionmark' )->text()
			);

		return $html;
	}

	/**
	 * @param array $row
	 * @return string
	 */
	private function formatBlockStatus( $row ) {
		$additionalHtml = '';
		if ( isset( $row['blocked'] ) && $row['blocked'] ) {
			$optionMessage = $this->formatBlockParams( $row );
			if ( $row['block-expiry'] == 'infinity' ) {
				$text = $this->msg( 'centralauth-admin-blocked2-indef' )->text();
			} else {
				$expiry = $this->getLanguage()->timeanddate( $row['block-expiry'], true );
				$expiryd = $this->getLanguage()->date( $row['block-expiry'], true );
				$expiryt = $this->getLanguage()->time( $row['block-expiry'], true );

				$text = $this->msg( 'centralauth-admin-blocked2', $expiry, $expiryd, $expiryt )
					->text();
			}

			if ( $row['block-reason'] ) {
				$reason = Sanitizer::escapeHtmlAllowEntities( $row['block-reason'] );
				$reason = $this->commentFormatter->formatLinks(
					$reason,
					null,
					false,
					$row['wiki']
				);

				$msg = $this->msg( 'centralauth-admin-blocked-reason' );
				$msg->rawParams( '<span class="plainlinks">' . $reason . '</span>' );

				$additionalHtml .= Html::rawElement( 'br' ) . $msg->parse();
			}

			$additionalHtml .= ' ' . $optionMessage;

		} else {
			$text = $this->msg( 'centralauth-admin-notblocked' )->text();
		}

		return self::foreignLink(
			$row['wiki'],
			'Special:Log/block',
			$text,
			$this->msg( 'centralauth-admin-blocklog' )->text(),
			'page=User:' . urlencode( $this->mUserName )
		) . $additionalHtml;
	}

	/**
	 * Format a block's parameters.
	 *
	 * @see BlockListPager::formatValue()
	 *
	 * @param array $row
	 * @return string
	 */
	private function formatBlockParams( $row ) {
		global $wgConf;

		// Ensure all the data is loaded before trying to use.
		$wgConf->loadFullData();

		$properties = [];

		if ( $row['block-sitewide'] ) {
			$properties[] = $this->msg( 'blocklist-editing-sitewide' )->escaped();
		}

		if ( !$row['block-sitewide'] && $row['block-restrictions'] ) {
			$list = $this->getRestrictionListHTML( $row );
			if ( $list ) {
				$properties[] = $this->msg( 'blocklist-editing' )->escaped() . $list;
			}
		}

		$options = [
			'anononly' => 'anononlyblock',
			'nocreate' => 'createaccountblock',
			'noautoblock' => 'noautoblockblock',
			'noemail' => 'emailblock',
			'nousertalk' => 'blocklist-nousertalk',
		];
		foreach ( $options as $option => $msg ) {
			if ( $row['block-' . $option] ) {
				$properties[] = $this->msg( $msg )->escaped();
			}
		}

		if ( !$properties ) {
			return '';
		}

		return Html::rawElement(
			'ul',
			[],
			implode( '', array_map( static function ( $prop ) {
				return Html::rawElement(
					'li',
					[],
					$prop
				);
			}, $properties ) )
		);
	}

	/**
	 * @see BlockListPager::getRestrictionListHTML()
	 *
	 * @return string
	 */
	private function getRestrictionListHTML( array $row ) {
		$count = array_reduce( $row['block-restrictions'], static function ( $carry, $restriction ) {
			$carry[$restriction->getType()] += 1;
			return $carry;
		}, [
			PageRestriction::TYPE => 0,
			NamespaceRestriction::TYPE => 0,
			ActionRestriction::TYPE => 0,
		] );

		$restrictions = [];
		foreach ( $count as $type => $value ) {
			if ( $value === 0 ) {
				continue;
			}

			$restrictions[] = Html::rawElement(
				'li',
				[],
				self::foreignLink(
					$row['wiki'],
					'Special:BlockList/' . $row['name'],
					// The following messages are generated here:
					// * centralauth-block-editing-page
					// * centralauth-block-editing-ns
					// * centralauth-block-editing-action
					$this->msg( 'centralauth-block-editing-' . $type, $value )->text()
				)
			);
		}

		if ( count( $restrictions ) === 0 ) {
			return '';
		}

		return Html::rawElement(
			'ul',
			[],
			implode( '', $restrictions )
		);
	}

	/**
	 * @param array $row
	 * @return string
	 * @throws Exception
	 */
	private function formatEditcount( $row ) {
		$wiki = WikiMap::getWiki( $row['wiki'] );
		if ( !$wiki ) {
			throw new InvalidArgumentException( "Invalid wiki: {$row['wiki']}" );
		}
		$wikiname = $wiki->getDisplayName();
		$editCount = $this->getLanguage()->formatNum( intval( $row['editCount'] ) );

		return self::foreignLink(
			$row['wiki'],
			'Special:Contributions/' . $this->mUserName,
			$editCount,
			$this->msg( 'centralauth-foreign-contributions', $editCount, $wikiname )->text()
		);
	}

	/**
	 * @param array $row
	 * @return string
	 */
	private function formatGroups( $row ) {
		if ( !count( $row['groupMemberships'] ) ) {
			return '';
		}

		// We place temporary groups before non-expiring groups in the list.
		// This is to avoid the ambiguity of something like
		// "sysop, bureaucrat (temporary)" -- users might wonder whether the
		// "temporary" indication applies to both groups, or just the last one
		$listTemporary = [];
		$list = [];
		/** @var UserGroupMembership $ugm */
		foreach ( $row['groupMemberships'] as $group => $ugm ) {
			if ( $ugm->getExpiry() ) {
				$listTemporary[] = $this->msg( 'centralauth-admin-group-temporary',
					wfEscapeWikitext( $group ) )->parse();
			} else {
				$list[] = htmlspecialchars( $group );
			}
		}
		return $this->getLanguage()->commaList( array_merge( $listTemporary, $list ) );
	}

	/**
	 * @param string|WikiReference $wikiID
	 * @param string $title
	 * @param string $text not HTML escaped
	 * @param string $hint
	 * @param string $params
	 * @return string
	 * @throws Exception
	 */
	public static function foreignLink( $wikiID, $title, $text, $hint = '', $params = '' ) {
		if ( $wikiID instanceof WikiReference ) {
			$wiki = $wikiID;
		} else {
			$wiki = WikiMap::getWiki( $wikiID );
			if ( !$wiki ) {
				throw new InvalidArgumentException( "Invalid wiki: $wikiID" );
			}
		}

		$url = $wiki->getFullUrl( $title );
		if ( $params ) {
			$url .= '?' . $params;
		}
		return Html::element( 'a',
			[
				'href' => $url,
				'title' => $hint,
			],
			$text );
	}

	/**
	 * @param string $wikiID
	 * @return string
	 * @throws Exception
	 */
	private function foreignUserLink( $wikiID ) {
		$wiki = WikiMap::getWiki( $wikiID );
		if ( !$wiki ) {
			throw new InvalidArgumentException( "Invalid wiki: $wikiID" );
		}

		$wikiname = $wiki->getDisplayName();
		return self::foreignLink(
			$wiki,
			$this->namespaceInfo->getCanonicalName( NS_USER ) . ':' . $this->mUserName,
			$wikiname,
			$this->msg( 'centralauth-foreign-link', $this->mUserName, $wikiname )->text()
		);
	}

	/**
	 * @param string $wikiID
	 * @return string
	 */
	private function adminCheck( $wikiID ) {
		return Html::check( 'wpWikis[]', false, [ 'value' => $wikiID ] );
	}

	/**
	 * Generates a form for managing deleting a global account which contains a description, a reason field
	 * and destructive submit button.
	 */
	private function showDeleteGlobalAccountForm() {
		$formDescriptor = [
			'info' => [
				'type' => 'info',
				'raw' => true,
				'default' => $this->msg( 'centralauth-admin-delete-description' )->parseAsBlock(),
				'cssclass' => 'mw-centralauth-admin-delete-intro',
			],
			'reason' => [
				'type' => 'text',
				'label-message' => 'centralauth-admin-reason',
				'id' => "delete-reason",
				'name' => 'reason',
			],
			'Method' => [
				'type' => 'hidden',
				'default' => 'delete',
			],
			'submit' => [
				'type' => 'submit',
				'buttonlabel-message' => 'centralauth-admin-delete-button',
				'flags' => [ 'progressive', 'destructive' ]
			],
		];

		$context = new DerivativeContext( $this->getContext() );
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $context );
		$htmlForm
			->setAction( $this->getPageTitle()->getFullURL( [ 'target' => $this->mUserName ] ) )
			->suppressDefaultSubmit()
			->setWrapperLegendMsg( 'centralauth-admin-delete-title' )
			->setId( 'mw-centralauth-delete' )
			->prepareForm()
			->displayForm( false );
	}

	private function showStatusForm() {
		// Allows locking, hiding, locking and hiding.
		$formDescriptor = [
			'intro' => [
				'type' => 'info',
				'raw' => true,
				'default' => $this->msg( 'centralauth-admin-status-intro' )->parseAsBlock(),
				'cssclass' => 'mw-centralauth-admin-status-intro',
				'section' => 'intro',
			],
			'StatusLocked' => [
				'type' => 'radio',
				'label-message' => 'centralauth-admin-status-locked',
				'options-messages' => [
					'centralauth-admin-status-locked-no' => 0,
					'centralauth-admin-status-locked-yes' => 1,
				],
				'default' => (int)$this->mGlobalUser->isLocked(),
				'id' => 'mw-centralauth-admin-status-locked',
				'section' => 'lockedhidden',
			],
			'StatusHidden' => [
				'type' => 'radio',
				'label-message' => 'centralauth-admin-status-hidden',
				'options-messages' => [
					'centralauth-admin-status-hidden-no' => CentralAuthUser::HIDDEN_LEVEL_NONE,
				],
				'default' => $this->mGlobalUser->getHiddenLevelInt(),
				'id' => 'mw-centralauth-admin-status-hidden',
				'section' => 'lockedhidden',
			],
			'Reason' => [
				'type' => 'selectandother',
				'maxlength' => CommentStore::COMMENT_CHARACTER_LIMIT,
				'label-message' => 'centralauth-admin-reason',
				'options-message' => 'centralauth-admin-status-reasons',
				'other-message' => 'centralauth-admin-reason-other-select',
				'id' => 'mw-centralauth-admin-reason',
			],
			'Method' => [
				'type' => 'hidden',
				'default' => 'set-status',
			],
			'UserState' => [
				'type' => 'hidden',
				'default' => $this->mGlobalUser->getStateHash( false ),
			],
			'submit' => [
				'type' => 'submit',
				'buttonlabel-message' => 'centralauth-admin-status-submit',
				'flags' => [ 'progressive' ],
			],
		];

		if ( $this->mCanSuppress ) {
			$formDescriptor['StatusHidden']['options-messages'] += [
				'centralauth-admin-status-hidden-list' => CentralAuthUser::HIDDEN_LEVEL_LISTS,
				'centralauth-admin-status-hidden-oversight' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
			];
		}

		$context = new DerivativeContext( $this->getContext() );
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $context );
		$htmlForm
			->setAction( $this->getPageTitle()->getFullURL( [ 'target' => $this->mUserName ] ) )
			->suppressDefaultSubmit()
			->setId( 'mw-centralauth-admin-status' )
			->setWrapperLegendMsg( 'centralauth-admin-status' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Gets a framed and padded fieldset that contains the given HTML.
	 *
	 * Used over HTMLForm as we need to avoid adding hidden fields like "wpEditToken" and form elements to parts of
	 * the page that are not forms.
	 *
	 * @param string $html The HTML to be wrapped with the fieldset
	 * @param string|MessageSpecifier $fieldsetLegendMsg The message to use as the fieldset legend
	 * @param string|null $fieldsetId The ID for the fieldset, or null for no ID
	 * @return string HTML
	 */
	private function getFramedFieldsetLayout( string $html, $fieldsetLegendMsg, ?string $fieldsetId = null ): string {
		$fieldset = new FieldsetLayout( [
			'label' => $this->msg( $fieldsetLegendMsg )->text(),
			'items' => [
				new Widget( [
					'content' => new HtmlSnippet( $html ),
				] ),
			],
			'id' => $fieldsetId,
		] );
		return new PanelLayout( [
			'classes' => [ 'mw-htmlform-ooui-wrapper' ],
			'expanded' => false,
			'padded' => true,
			'framed' => true,
			'content' => $fieldset,
		] );
	}

	private function showLogExtract() {
		$user = $this->mGlobalUser->getName();
		$globalTitle = Title::newFromText( $this->namespaceInfo->getCanonicalName( NS_USER ) . ":{$user}@global" );
		if ( !$globalTitle ) {
			// Don't fatal even if a Title couldn't be generated
			// because we've invalid usernames too :/
			return;
		}

		$localDbr = $this->dbProvider->getReplicaDatabase();

		// Construct the conditions needed to lookup CentralAuth account changes logs
		$logTypes = [ 'globalauth' ];
		if ( $this->mCanSuppress ) {
			$logTypes[] = 'suppress';
		}
		$relevantLogsExpr = $localDbr->expr( 'log_type', '=', $logTypes )
			->and( 'log_namespace', '=', $globalTitle->getNamespace() )
			->and( 'log_title', '=', $globalTitle->getDBkey() );

		// If GlobalBlocking is installed, also show the logs for removing and adding global blocks in
		// the log extract as these are changes to the status of the global account.
		if ( ExtensionRegistry::getInstance()->isLoaded( 'GlobalBlocking' ) ) {
			$globalBlockingLogsTitle = Title::makeTitleSafe( NS_USER, $user );
			if ( $globalBlockingLogsTitle ) {
				$globalBlockingLogsExpr = $localDbr->expr( 'log_type', '=', 'gblblock' )
					->and( 'log_action', '=', [ 'gunblock', 'gblock', 'gblock2', 'modify' ] )
					->and( 'log_namespace', '=', $globalBlockingLogsTitle->getNamespace() )
					->and( 'log_title', '=', $globalBlockingLogsTitle->getDBkey() );

				$relevantLogsExpr = $localDbr->orExpr( [
					$relevantLogsExpr,
					$globalBlockingLogsExpr
				] );
			}
		}

		$html = '';
		$numRows = LogEventsList::showLogExtract(
			$html,
			// Hack for T387178: LogPager::limitType hides suppression logs if no types are filtered.
			// However, we are filtering types using custom conditions which is not recognised and
			// therefore we need to define them again.
			array_merge( $logTypes, [ 'gblblock' ] ),
			'', '', [ 'showIfEmpty' => true, 'conds' => [ $relevantLogsExpr ] ]
		);

		if ( $numRows ) {
			$this->getOutput()->addHTML( $this->getFramedFieldsetLayout(
				$html, 'centralauth-admin-logsnippet', 'mw-centralauth-admin-logsnippet'
			) );

			return;
		}

		if ( $this->mGlobalUser->isLocked() ) {
			$logOtherWikiMsg = $this->msg( 'centralauth-admin-log-otherwiki',
				$this->mGlobalUser->getName() );

			if ( !$logOtherWikiMsg->isDisabled() ) {
				$this->getOutput()->addHTML(
					Html::warningBox(
						$logOtherWikiMsg->parse(),
						'centralauth-admin-log-otherwiki'
					)
				);
			}
		}
	}

	/**
	 * @return int
	 */
	private function evaluateTotalEditcount() {
		$total = 0;
		foreach ( $this->mAttachedLocalAccounts as $acc ) {
			$total += $acc['editCount'];
		}
		return $total;
	}

	/**
	 * @return array[]
	 */
	private function getMergeMethodDescriptions() {
		// Give grep a chance to find the usages:
		// centralauth-merge-method-primary, centralauth-merge-method-new,
		// centralauth-merge-method-empty, centralauth-merge-method-password,
		// centralauth-merge-method-mail, centralauth-merge-method-admin,
		// centralauth-merge-method-login
		// Give grep a chance to find the usages:
		// centralauth-merge-method-primary-desc, centralauth-merge-method-new-desc,
		// centralauth-merge-method-empty-desc, centralauth-merge-method-password-desc,
		// centralauth-merge-method-mail-desc, centralauth-merge-method-admin-desc,
		// centralauth-merge-method-login-desc
		$mergeMethodDescriptions = [];
		foreach ( [ 'primary', 'new', 'empty', 'password', 'mail', 'admin', 'login' ] as $method ) {
			$mergeMethodDescriptions[$method] = [
				'short' => $this->getLanguage()->ucfirst(
					$this->msg( "centralauth-merge-method-{$method}" )->escaped()
				),
				'desc' => $this->msg( "centralauth-merge-method-{$method}-desc" )->escaped()
			];
		}
		return $mergeMethodDescriptions;
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$search = $this->userNameUtils->getCanonical( $search );
		if ( !$search ) {
			// No prefix suggestion for invalid user
			return [];
		}

		$dbr = $this->databaseManager->getCentralReplicaDB();

		// Autocomplete subpage as user list - non-hidden users to allow caching
		return $dbr->newSelectQueryBuilder()
			->select( 'gu_name' )
			->from( 'globaluser' )
			->where( [
				$dbr->expr( 'gu_name', IExpression::LIKE, new LikeValue( $search, $dbr->anyString() ) ),
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE,
			] )
			->orderBy( 'gu_name' )
			->limit( $limit )
			->offset( $offset )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}
}
