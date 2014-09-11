<?php

class SpecialGlobalUserMerge extends FormSpecialPage {

	/**
	 * @var CentralAuthUser[]
	 */
	private $oldCAUsers = array();

	/**
	 * @var string
	 */
	private $newUsername;

	public function __construct() {
		parent::__construct( 'GlobalUserMerge', 'centralauth-usermerge' );
	}

	public function execute( $par ) {
		global $wgCentralAuthEnableUserMerge;
		if ( !class_exists( 'SpecialUserMerge' ) ) {
			$this->setHeaders();
			throw new ErrorPageError( 'error', 'centralauth-usermerge-notinstalled' );
		}

		if ( !$wgCentralAuthEnableUserMerge ) {
			$this->setHeaders();
			throw new ErrorPageError( 'error', 'centralauth-usermerge-disabled' );
		}

		$this->getOutput()->addModules( 'ext.centralauth.globalrenameuser' );
		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		$us = $this;
		return array(
			'finaluser' => array(
				'id' => 'mw-globalusermerge-usernames',
				'name' => 'newuser',
				'label-message' => 'centralauth-usermerge-form-newuser',
				'type' => 'text',
				'required' => true,
				'validation-callback' => array( $this, 'validateUsername' ),
			),
			'usernames' => array(
				'id' => 'mw-globalusermerge-usernames',
				'name' => 'usernames',
				'label-message' => 'centralauth-usermerge-form-usernames',
				'type' => 'cloner',
				'format' => 'table',
				'create-button-message' => 'centralauth-usermerge-form-adduser',
				'delete-button-message' => 'centralauth-usermerge-form-deleteuser',
				'fields' => array(
					'name' => array(
						'type' => 'text',
						'validation-callback' => array( $this, 'validateUsername' ),
					),
				),
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
	 * @return string|bool
	 */
	public function validateUsername( $name ) {
		if ( $name === null || $name === '' ) {
			// blank cloner field, bypass.
			return true;
		}

		$name = User::getCanonicalName( $name, 'usable' );
		if ( !$name ) {
			return $this->msg( 'centralauth-usermerge-invalid', $name )->escaped();
		}
		$caUser = new CentralAuthUser( $name );
		if ( !$caUser->exists() ) {
			return $this->msg( 'centralauth-usermerge-invalid', $name )->escaped();
		}

		if ( $caUser->renameInProgress() ) {
			return $this->msg( 'centralauth-usermerge-already', $name )->escaped();
		}

		return true;
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$newUser = User::newFromName( $data['finaluser'], 'creatable' );
		if ( !$newUser ) {
			return Status::newFatal( 'centralauth-usermerge-invalidname' );
		}

		foreach ( $data['usernames'] as $field ) {
			if ( $field['name'] ) {
				$this->oldCAUsers[] = new CentralAuthUser( $field['name'] );
			}
		}

		if ( !$this->oldCAUsers ) {
			return $this->msg( 'centralauth-usermerge-nousers' )->escaped();
		}

		$this->newUsername = $newUser->getName();

		$globalUserMerge = new GlobalUserMerge(
			$this->getUser(),
			$this->oldCAUsers,
			CentralAuthUser::getInstance( $newUser ),
			 new GlobalRenameUserStatus( $newUser->getName() ),
			'JobQueueGroup::singleton',
			new GlobalUserMergeDatabaseUpdates(),
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
	public function getLocalizedCentralAuthLink( $name ) {
		return Linker::linkKnown(
			SpecialPage::getTitleFor( 'CentralAuth', $name ),
			htmlspecialchars( $name )
		);
	}

	public function onSuccess() {
		$lang = $this->getLanguage();
		$us = $this;
		$globalUsers = array_map( function ( CentralAuthUser $user ) use ( $us ) {
			return $us->getLocalizedCentralAuthLink( $user->getName() );
		}, $this->oldCAUsers );
		$userList = $lang->commaList( $globalUsers );

		$msg = $this->msg( 'centralauth-usermerge-queued' )
			->rawParams(
				$userList,
				$this->getLocalizedCentralAuthLink( $this->newUsername )
			)->params( $this->newUsername )->parse();
		$this->getOutput()->addHTML( $msg );
	}
}
