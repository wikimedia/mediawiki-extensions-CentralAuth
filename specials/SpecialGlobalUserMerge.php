<?php

class SpecialGlobalUserMerge extends FormSpecialPage {

	private $users;
	private $newUsername;

	function __construct() {
		parent::__construct( 'GlobalUserMerge', 'centralauth-usermerge' );
	}

	function execute( $par ) {
		$this->getOutput()->addModules( 'ext.centralauth.globalrenameuser' );
		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	function getFormFields() {
		return array(
			'newuser' => array(
				'id' => 'mw-globalusermerge-usernames',
				'name' => 'newuser',
				'label-message' => 'centralauth-usermerge-form-newuser',
				'type' => 'text',
				'required' => true,
			),
			'usernames' => array(
				'id' => 'mw-globalusermerge-usernames',
				'name' => 'usernames',
				'label-message' => 'centralauth-usermerge-form-usernames',
				'type' => 'textarea',
				'required' => true,
				'validation-callback' => array( $this, 'validateUsernames' ),
			),
			'reason' => array(
				'id' => 'mw-globalusermerge-reason',
				'name' => 'reason',
				'label-message' => 'centralauth-usermerge-form-reason',
				'type' => 'text',
			),
		);
	}

	private function validateUsername( $name ) {
		$caUser = new CentralAuthUser( $name );
		return $caUser->exists();
	}

	function validateUsernames( $text ) {
		$users = explode( "\n", $text );
		foreach ( $users as $user ) {
			if ( !$this->validateUsername( $user ) ) {
				return $this->msg( 'centralauth-usermerge-invalid', $user )->escaped();
			}
		}

		// All the users are valid
		$this->users = $users;

		return true;
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	function onSubmit( array $data ) {
		$newUser = User::newFromName( $data['newname'], 'creatable' );
		if ( !$newUser ) {
			return Status::newFatal( 'centralaut-usermerge-invalidname' );
		}

		$this->newUsername = $newUser->getName();

		$status = new Status;
		foreach ( $this->users as $oldUsername ) {
			$oldUser = User::newFromName( $oldUsername );
			$globalRenameUser = new GlobalRenameUser(
				$this->getUser(),
				$oldUser,
				CentralAuthUser::getInstance( $oldUser ),
				$newUser,
				CentralAuthUser::getInstance( $newUser ),
				new GlobalRenameUserStatus( $data['newname'] ),
				'JobQueueGroup::singleton',
				new GlobalRenameUserDatabaseUpdates(),
				new GlobalUserMergeLogger( $this->getUser() )
			);

			$status->merge( $globalRenameUser->merge( $data ) );
		}

		return $status;
	}

	function onSuccess() {
		$lang = $this->getLanguage();
		$globalUsers = array_map( function ( $name ) {
			return "$name@global";
		}, $this->users );
		$userList = $lang->commaList( $globalUsers );

		$msg = $this->msg( 'centralauth-usermerge-queued' )
			->params( $userList, $this->newUsername )
			->parse();
		$this->getOutput()->addHTML( $msg );
	}
}
