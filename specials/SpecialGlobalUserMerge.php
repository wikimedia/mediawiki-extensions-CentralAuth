<?php

class SpecialGlobalUserMerge extends FormSpecialPage {

	/**
	 * @var string[]
	 */
	private $users;

	/**
	 * @var string
	 */
	private $newUsername;

	function __construct() {
		parent::__construct( 'GlobalUserMerge', 'centralauth-usermerge' );
	}

	function execute( $par ) {
		if ( !class_exists( 'SpecialUserMerge' ) ) {
			$this->setHeaders();
			throw new ErrorPageError( 'error', 'centralauth-usermerge-notinstalled' );
		}
		$this->getOutput()->addModules( 'ext.centralauth.globalrenameuser' );
		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	function getFormFields() {
		$us = $this;
		return array(
			'finaluser' => array(
				'id' => 'mw-globalusermerge-usernames',
				'name' => 'newuser',
				'label-message' => 'centralauth-usermerge-form-newuser',
				'type' => 'text',
				'required' => true,
				'validation-callback' => function( $name ) use ( $us ) {
						$valid = $us->validateUsername( $name );
						if ( $valid instanceof Message ) {
							return $valid->escaped();
						}

						return true;
					}
			),
			'usernames' => array(
				'id' => 'mw-globalusermerge-usernames',
				'name' => 'usernames',
				'label-message' => 'centralauth-usermerge-form-usernames',
				'type' => 'textarea',
				'required' => true,
				'validation-callback' => array( $this, 'validateUsernames' ),
				'help-message' => 'centralauth-usermerge-form-usernames-help',
			),
			'reason' => array(
				'id' => 'mw-globalusermerge-reason',
				'name' => 'reason',
				'label-message' => 'centralauth-usermerge-form-reason',
				'type' => 'text',
			),
		);
	}

	/**
	 * @param $name
	 * @return bool|Message if not valid
	 */
	function validateUsername( $name ) {
		$name = User::getCanonicalName( $name, 'usable' );
		if ( !$name ) {
			return $this->msg( 'centralauth-usermerge-invalid', $name );
		}
		$caUser = new CentralAuthUser( $name );
		if ( !$caUser->exists() ) {
			return $this->msg( 'centralauth-usermerge-invalid', $name );
		}

		if ( $caUser->renameInProgress() ) {
			return $this->msg( 'centralauth-usermerge-already', $name );
		}

		return true;
	}

	function validateUsernames( $text ) {
		$users = explode( "\n", trim( $text ) );
		foreach ( $users as $user ) {
			$valid = $this->validateUsername( $user );
			if ( $valid instanceof Message ) { // not valid
				return $valid->escaped();
			}
		}

		if ( !$users ) {
			return $this->msg( 'centralauth-usermerge-nousers' )->escaped();
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
		$newUser = User::newFromName( $data['finaluser'], 'creatable' );
		if ( !$newUser ) {
			return Status::newFatal( 'centralaut-usermerge-invalidname' );
		}

		$this->newUsername = $newUser->getName();

		$oldCAUsers = array_map( function( $name ) {
			return new CentralAuthUser( $name );
		}, $this->users );
		$globalUserMerge = new GlobalUserMerge(
			$this->getUser(),
			$oldCAUsers,
			CentralAuthUser::getInstance( $newUser ),
			 new GlobalRenameUserStatus( $newUser->getName() ),
			'JobQueueGroup::singleton',
			new GlobalRenameUserDatabaseUpdates(),
			new GlobalUserMergeLogger( $this->getUser() )
		);
		$status = $globalUserMerge->merge( $data['reason'] );

		return $status;
	}

	/**
	 * Get a HTML link to this user's Special:CentralAuth page
	 *
	 * @param string $name
	 * @return string raw HTML
	 */
	public function getCentralAuthLink( $name ) {
		return Linker::linkKnown(
			SpecialPage::getTitleFor( 'CentralAuth', $name ),
			htmlspecialchars( $name )
		);
	}

	function onSuccess() {
		$lang = $this->getLanguage();
		$us = $this;
		$globalUsers = array_map( function ( $name ) use ( $us ) {
			return $us->getCentralAuthLink( $name );
		}, $this->users );
		$userList = $lang->commaList( $globalUsers );

		$msg = $this->msg( 'centralauth-usermerge-queued' )
			->rawParams( $userList, $this->getCentralAuthLink( $this->newUsername ) )
			->parse();
		$this->getOutput()->addHTML( $msg );
	}
}
