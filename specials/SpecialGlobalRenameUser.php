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

	function __construct() {
		parent::__construct( 'GlobalRenameUser', 'centralauth-rename' );
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
				'required' => true
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
			)
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
		if ( $oldUser->getName() == $this->getUser()->getName() ) {
			return Status::newFatal( 'centralauth-rename-cannotself' );
		}

		$newUser = User::newFromName( $data['newname'] );
		if ( !$newUser ) {
			return Status::newFatal( 'centralauth-rename-badusername' );
		}

		$validator = new GlobalRenameUserValidator();
		$status = $validator->validate( $oldUser, $newUser );

		return $status;
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	function onSubmit( array $data ) {
		$valid = $this->validate( $data );
		if ( !$valid->isOK() ) {
			return $valid;
		}

		$this->newUsername = $data['newname'];
		$this->oldUsername = $data['oldname'];

		$globalRenameUser = new GlobalRenameUser(
			$this->getUser(),
			User::newFromName( $this->oldUsername ),
			new CentralAuthUser( $this->oldUsername ),
			User::newFromName( $this->newUsername, 'creatable' ),
			new CentralAuthUser( $this->newUsername ),
			new GlobalRenameUserStatus( $this->newUsername ),
			'JobQueueGroup::singleton',
			new GlobalRenameUserDatabaseUpdates(),
			new GlobalRenameUserLogger( $this->getUser() )
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
}
