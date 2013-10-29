<?php

class SpecialGlobalRenameUser extends FormSpecialPage {

	/** @var array $wikis */
	protected $wikis;

	function __construct() {
		parent::__construct( 'GlobalRenameUser', 'centralauth-rename' );
	}

	function getFormFields() {
		return array(
			'oldname' => array(
				'id' => 'mw-globalrenameuser-oldname',
				'name' => 'oldname',
				'label' => 'Current name', // @todo i18n
				'type' => 'text',
				'required' => true
			),
			'newname' => array(
				'id' => 'mw-globalrenameuser-newname',
				'name' => 'newname',
				'label' => 'New name', // @todo i18n
				'type' => 'text',
				'required' => true
			),
			'reason' => array(
				'id' => 'mw-globalrenameuser-reason',
				'name' => 'reason',
				'label' => 'Reason',
			)
		);
	}

	/**
	 * Perform validation on the user submitted data
	 * @param array $data
	 * @return Status
	 */
	function validate( array $data ) {
		$old = $data['oldname'];
		$new = $data['newname'];
		$caOldUser = CentralAuthUser::getInstance( User::newFromName( $old ) );
		if ( !$caOldUser->exists() ) {
			return Status::newFatal( 'centralauth-doesnotexist' );
		}
		$caNewUser = CentralAuthUser::getInstance( User::newFromName( $new ) );
		if ( $caNewUser->exists() ) {
			return Status::newFatal( 'centralauth-alreadyexists' );
		}
		// Check that the new name is a valid name...
		$newUser = User::newFromName( $new, 'creatable' );
		if ( $newUser === false ) {
			return Status::newFatal( 'centralauth-badusername' );
		}

		// @todo figure out if this is necessary
		$user = User::newFromName( $data['oldname'] );
		if ( $user->getName() == $this->getUser()->getName() ) {
			return Status::newFatal( 'centralauth-rename-cannotself' );
		}

		// Check we're not currently renaming the user
		if ( CentralAuthUser::renameInProgress( $old ) ) {
			return Status::newFatal( 'centralauth-renameinprogress' );
		}


		return Status::newGood();
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	function onSubmit( array $data ) {
		global $wgContLang;

		$valid = $this->validate( $data );
		if ( !$valid->isOK() ) {
			return $valid;
		}
		$oldname = $data['oldname'];
		// Force uppercase of newusername, otherwise wikis with wgCapitalLinks=false can create lc usernames
		$newname = $wgContLang->ucfirst( $data['newname'] );

		$user = User::newFromName( $oldname );
		$caUser = CentralAuthUser::getInstance( $user );
		$attached = $caUser->queryAttached();
		$this->wikis = array_keys( $attached );
		$rows = array();
		foreach ( $this->wikis as $wiki ) {
			$rows[] = array(
				'ru_wiki' => $wiki,
				'ru_oldname' => $oldname,
				'ru_newname' => $newname,
				'ru_status' => 'queued'
			);
		}

		// Update the db status
		$dbw = CentralAuthUser::getCentralDB();
		$dbw->insert(
			'renameuser_status',
			$rows,
			__METHOD__
		);

		$this->updateCARow( $oldname, $newname );
		// From this point on all code using CentralAuthUser
		// needs to use the new username, except for
		// the renameInProgress function. Probably.

		// Submit the jobs.
		$params = array(
			'from' => $oldname,
			'to' => $newname,
			'renamer' => $this->getUser()->getName() // @todo do we need to check if the account exists?
		);
		$title = Title::newMainPage(); // This isn't used anywhere!
		$job = new LocalRenameUserJob( $title, $params );
		foreach( $this->wikis as $wiki ) {
			JobQueueGroup::singleton( $wiki )->push( $job );
		}

		return Status::newGood();
	}

	/**
	 * Update rows in the various centralauth tables...
	 * @param string $oldname
	 * @param string $newname
	 */
	function updateCARow( $oldname, $newname ) {
		$cdbw = CentralAuthUser::getCentralDB();
		$cdbw->update(
			'globaluser',
			array( 'gu_name' => $newname ),
			array( 'gu_name' => $oldname ),
			__METHOD__
		);
		// The localnames table will be updated upon rename locally
	}

	function onSuccess() {
		// TODO Output some "jobs have been queued on ..$this->wikis"
	}
}
