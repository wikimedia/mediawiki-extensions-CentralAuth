<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use CentralAuthAntiSpoofHooks;
use CentralAuthSpoofUser;
use ExtensionRegistry;
use FormSpecialPage;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUIService;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUser;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserDatabaseUpdates;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserLogger;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserStatus;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserValidator;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\TitleBlacklist\TitleBlacklist;
use MediaWiki\Extension\TitleBlacklist\TitleBlacklistEntry;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use Message;
use Status;
use Title;
use User;

class SpecialGlobalRenameUser extends FormSpecialPage {

	/**
	 * @var string
	 */
	private $newUsername;

	/**
	 * @var string
	 */
	private $oldUsername;

	/**
	 * @var bool
	 */
	private $overrideAntiSpoof = false;

	/**
	 * @var bool
	 */
	private $allowHighEditcount = false;

	/**
	 * @var bool
	 */
	private $overrideTitleBlacklist = false;

	/** @var JobQueueGroupFactory */
	private $jobQueueGroupFactory;

	/** @var CentralAuthDatabaseManager */
	private $databaseManager;

	/** @var CentralAuthUIService */
	private $uiService;

	/** @var GlobalRenameDenylist */
	private $globalRenameDenylist;

	/** @var GlobalRenameUserValidator */
	private $globalRenameUserValidator;

	/**
	 * Require confirmation if olduser has more than this many global edits
	 */
	private const EDITCOUNT_THRESHOLD = 100000;

	/**
	 * @param JobQueueGroupFactory $jobQueueGroupFactory
	 * @param CentralAuthDatabaseManager $databaseManager
	 * @param CentralAuthUIService $uiService
	 * @param GlobalRenameDenylist $globalRenameDenylist
	 * @param GlobalRenameUserValidator $globalRenameUserValidator
	 */
	public function __construct(
		JobQueueGroupFactory $jobQueueGroupFactory,
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUIService $uiService,
		GlobalRenameDenylist $globalRenameDenylist,
		GlobalRenameUserValidator $globalRenameUserValidator
	) {
		parent::__construct( 'GlobalRenameUser', 'centralauth-rename' );
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->databaseManager = $databaseManager;
		$this->uiService = $uiService;
		$this->globalRenameDenylist = $globalRenameDenylist;
		$this->globalRenameUserValidator = $globalRenameUserValidator;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @param string|null $par Subpage string if one was specified
	 */
	public function execute( $par ) {
		parent::execute( $par );
		$this->getOutput()->addModules( 'ext.centralauth.globalrenameuser' );
		$this->getOutput()->addModules( 'ext.centralauth.globaluserautocomplete' );
		$this->getOutput()->addModuleStyles( 'ext.centralauth.misc.styles' );
	}

	/**
	 * @return array[]
	 */
	public function getFormFields() {
		$fields = [
			'oldname' => [
				'id' => 'mw-globalrenameuser-oldname',
				'name' => 'oldname',
				'label-message' => 'centralauth-rename-form-oldname',
				'type' => 'text',
				'required' => true,
				'cssclass' => 'mw-autocomplete-global-user'
			],
			'newname' => [
				'id' => 'mw-globalrenameuser-newname',
				'name' => 'newname',
				'label-message' => 'centralauth-rename-form-newname',
				'type' => 'text',
				'required' => true
			],
			'reason' => [
				'id' => 'mw-globalrenameuser-reason',
				'name' => 'reason',
				'label-message' => 'centralauth-rename-form-reason',
				'type' => 'text',
			],
			'movepages' => [
				'id' => 'mw-globalrenameuser-movepages',
				'name' => 'movepages',
				'label-message' => 'centralauth-rename-form-movepages',
				'type' => 'check',
				'default' => 1,
			],
			'suppressredirects' => [
				'id' => 'mw-globalrenameuser-suppressredirects',
				'name' => 'suppressredirects',
				'label-message' => 'centralauth-rename-form-suppressredirects',
				'type' => 'check',
			],
			'overrideantispoof' => [
				'id' => 'mw-globalrenameuser-overrideantispoof',
				'name' => 'overrideantispoof',
				'label-message' => 'centralauth-rename-form-overrideantispoof',
				'type' => 'check'
			],
			'overridetitleblacklist' => [
				'id' => 'mw-globalrenameuser-overridetitleblacklist',
				'name' => 'overridetitleblacklist',
				'label-message' => 'centralauth-rename-form-overridetitleblacklist',
				'type' => 'check'
			],
			'allowhigheditcount' => [
				'name' => 'allowhigheditcount',
				'type' => 'hidden',
				'default' => '',
			]
		];

		// Ask for confirmation if the user has more than 100k edits globally
		$oldName = trim( $this->getRequest()->getText( 'oldname' ) );
		if ( $oldName !== '' ) {
			$oldUser = User::newFromName( $oldName );
			if ( $oldUser ) {
				$caUser = CentralAuthUser::getInstance( $oldUser );
				if ( $caUser->getGlobalEditCount() > self::EDITCOUNT_THRESHOLD ) {
					$fields['allowhigheditcount'] = [
						'id' => 'mw-globalrenameuser-allowhigheditcount',
						'label-message' => [ 'centralauth-rename-form-allowhigheditcount',
							Message::numParam( self::EDITCOUNT_THRESHOLD ) ],
						'type' => 'check'
					];
				}
			}
		}

		return $fields;
	}

	/**
	 * Perform validation on the user submitted data
	 * and check that we can perform the rename
	 * @param array $data
	 *
	 * @return Status
	 */
	public function validate( array $data ) {
		$oldUser = User::newFromName( $data['oldname'] );
		if ( !$oldUser ) {
			return Status::newFatal( 'centralauth-rename-doesnotexist' );
		}

		if ( $oldUser->getName() === $this->getUser()->getName() ) {
			return Status::newFatal( 'centralauth-rename-cannotself' );
		}

		$newUser = User::newFromName( $data['newname'] );
		if ( !$newUser ) {
			return Status::newFatal( 'centralauth-rename-badusername' );
		}

		if ( !$this->overrideAntiSpoof ) {
			$spoofUser = new CentralAuthSpoofUser( $newUser->getName() );
			$conflicts = $this->uiService->processAntiSpoofConflicts(
				$this->getContext(),
				$oldUser->getName(),
				$spoofUser->getConflicts()
			);

			$renamedUser = CentralAuthAntiSpoofHooks::getOldRenamedUserName( $newUser->getName() );
			if ( $renamedUser !== null ) {
				$conflicts[] = $renamedUser;
			}

			if ( $conflicts ) {
				return Status::newFatal(
					$this->msg( 'centralauth-rename-antispoofconflicts2' )
						->params( $this->getLanguage()->listToText( $conflicts ) )
						->numParams( count( $conflicts ) )
				);
			}
		}

		// Let the performer know that olduser's editcount is more than the
		// sysadmin-intervention-threshold and do the rename only if we've received
		// confirmation that they want to do it.
		$caOldUser = CentralAuthUser::getInstance( $oldUser );
		if ( !$this->allowHighEditcount &&
			$caOldUser->getGlobalEditCount() > self::EDITCOUNT_THRESHOLD
		) {
			return Status::newFatal(
				$this->msg( 'centralauth-rename-globaleditcount-threshold' )
					->numParams( self::EDITCOUNT_THRESHOLD )
			);
		}

		// Ask for confirmation if the new username matches the title blacklist.
		if (
			!$this->overrideTitleBlacklist
			&& ExtensionRegistry::getInstance()->isLoaded( 'TitleBlacklist' )
		) {
				$titleBlacklist = TitleBlacklist::singleton()->isBlacklisted(
					Title::makeTitleSafe( NS_USER, $newUser->getName() ),
					'new-account'
				);
				if ( $titleBlacklist instanceof TitleBlacklistEntry ) {
					return Status::newFatal(
						$this->msg( 'centralauth-rename-titleblacklist-match' )
							->params( wfEscapeWikiText( $titleBlacklist->getRegex() ) )
					);
				}
		}

		// Validate rename deny list
		if ( !$this->globalRenameDenylist->checkUser( $oldUser ) ) {
			return Status::newFatal( 'centralauth-rename-listed-on-denylist' );
		}

		return $this->globalRenameUserValidator->validate( $oldUser, $newUser );
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		if ( $data['overrideantispoof'] ) {
			$this->overrideAntiSpoof = true;
		}

		if ( $data['overridetitleblacklist'] ) {
			$this->overrideTitleBlacklist = true;
		}

		if ( $data['allowhigheditcount'] ) {
			$this->allowHighEditcount = true;
		}

		$valid = $this->validate( $data );
		if ( !$valid->isOK() ) {
			return $valid;
		}

		$this->newUsername = $data['newname'];
		$this->oldUsername = $data['oldname'];
		$oldUser = User::newFromName( $this->oldUsername );
		$newUser = User::newFromName( $this->newUsername, 'creatable' );

		$session = $this->getContext()->exportSession();
		$globalRenameUser = new GlobalRenameUser(
			$this->getUser(),
			$oldUser,
			CentralAuthUser::getInstance( $oldUser ),
			$newUser,
			CentralAuthUser::getInstance( $newUser ),
			new GlobalRenameUserStatus( $newUser->getName() ),
			$this->jobQueueGroupFactory,
			new GlobalRenameUserDatabaseUpdates( $this->databaseManager ),
			new GlobalRenameUserLogger( $this->getUser() ),
			$session
		);

		return $globalRenameUser->rename( $data );
	}

	public function onSuccess() {
		$msg = $this->msg( 'centralauth-rename-queued' )
			->params( $this->oldUsername, $this->newUsername )
			->parse();
		$this->getOutput()->addHTML( $msg );
	}

	protected function getGroupName() {
		return 'users';
	}
}
