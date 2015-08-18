<?php

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

	function __construct() {
		parent::__construct( 'GlobalRenameUser', 'centralauth-rename' );
	}

	/**
	 * @param string $par Subpage string if one was specified
	 */
	public function execute( $par ) {
		parent::execute( $par );
		$this->getOutput()->addModules( 'ext.centralauth.globalrenameuser' );
		$this->getOutput()->addModules( 'ext.centralauth.globaluserautocomplete' );
	}

	/**
	 * @return array
	 */
	function getFormFields() {
		return array(
			'oldname' => array(
				'id' => 'mw-globalrenameuser-oldname',
				'name' => 'oldname',
				'label-message' => 'centralauth-rename-form-oldname',
				'type' => 'text',
				'required' => true,
				'cssclass' => 'mw-autocomplete-global-user'
			),
			'newname' => array(
				'id' => 'mw-globalrenameuser-newname',
				'name' => 'newname',
				'label-message' => 'centralauth-rename-form-newname',
				'type' => 'text',
				'required' => true
			),
			'reason' => array(
				'id' => 'mw-globalrenameuser-reason',
				'name' => 'reason',
				'label-message' => 'centralauth-rename-form-reason',
				'type' => 'text',
			),
			'movepages' => array(
				'id' => 'mw-globalrenameuser-movepages',
				'name' => 'movepages',
				'label-message' => 'centralauth-rename-form-movepages',
				'type' => 'check',
				'default' => 1,
			),
			'suppressredirects' => array(
				'id' => 'mw-globalrenameuser-suppressredirects',
				'name' => 'suppressredirects',
				'label-message' => 'centralauth-rename-form-suppressredirects',
				'type' => 'check',
			),
			'overrideantispoof' => array(
				'id' => 'mw-globalrenameuser-overrideantispoof',
				'name' => 'overrideantispoof',
				'label-message' => 'centralauth-rename-form-overrideantispoof',
				'type' => 'check'
			),
		);
	}

	/**
	 * Perform validation on the user submitted data
	 * and check that we can perform the rename
	 * @param array $data
	 *
	 * @return Status
	 */
	function validate( array $data ) {
		if ( !class_exists( 'RenameuserSQL' ) ) {
			return Status::newFatal( 'centralauth-rename-notinstalled' );
		}

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

		if ( !$this->overrideAntiSpoof && class_exists( 'CentralAuthSpoofUser' ) ) {
			$spoofUser = new CentralAuthSpoofUser( $newUser->getName() );
			$conflicts = $this->processAntiSpoofConflicts(
				$oldUser->getName(),
				$spoofUser->getConflicts()
			);
			if ( $conflicts ) {
				return Status::newFatal(
					$this->msg( 'centralauth-rename-antispoofconflicts2' )
						->params( $this->getLanguage()->listToText( $conflicts ) )
						->numParams( count( $conflicts ) )
				);
			}
		}

		$validator = new GlobalRenameUserValidator();
		$status = $validator->validate( $oldUser, $newUser );

		return $status;
	}

	/**
	 * This is also used in SpecialGlobalRenameQueue
	 *
	 * @param string $oldname User's old (current) name
	 * @param array $conflicts Conflicting usernames
	 * @return array Usernames that are safe to display - non-hidden usernames are linked to Special:CA
	 */
	public function processAntiSpoofConflicts( $oldname, array $conflicts ) {
		$display = array();
		foreach ( $conflicts as $name ) {
			if ( $name === $oldname ) {
				// Not a conflict since the old usage will go away
				continue;
			}
			$ca = new CentralAuthUser( $name );
			$ca->setLoadFromMasterFlag();
			if ( $ca->isHidden() ) {
				$display[] = $this->msg( 'centralauth-rename-conflict-hidden' )->text();
			} else {
				$display[] = "[[Special:CentralAuth/$name|$name]]";
			}
		}
		return $display;
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	function onSubmit( array $data ) {
		if ( $data['overrideantispoof'] ) {
			$this->overrideAntiSpoof = true;
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
			'JobQueueGroup::singleton',
			new GlobalRenameUserDatabaseUpdates(),
			new GlobalRenameUserLogger( $this->getUser() ),
			$session
		);

		return $globalRenameUser->rename( $data );
	}

	function onSuccess() {
		$lang = $this->getLanguage();
		$caUser = new CentralAuthUser( $this->newUsername );
		$wikiList = $lang->commaList( $caUser->listAttached() );

		$msg = $this->msg( 'centralauth-rename-queued' )
			->params( $this->oldUsername, $this->newUsername, $wikiList )
			->parse();
		$this->getOutput()->addHTML( $msg );
	}

	protected function getGroupName() {
		return 'users';
	}
}
