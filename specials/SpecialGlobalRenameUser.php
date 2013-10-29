<?php

class SpecialGlobalRenameUser extends FormSpecialPage {

	/** @var array $wikis */
	protected $wikis;

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
		if ( CentralAuthUser::renameInProgress( $old ) ) {
			return Status::newFatal( 'centralauth-renameinprogress' );
		}
		// Check that the new name is a valid name...
		$newUser = User::newFromName( $new, 'creatable' );
		if ( $newUser === false ) {
			return Status::newFatal( 'centralauth-badusername' );
		}

		return Status::newGood();
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

		$user = User::newFromName( $data['oldname'] );
		if ( $user->getName() == $this->getUser()->getName() ) {
			return Status::newFatal( 'centralauth-rename-cannotself' );
		}
		$caUser = CentralAuthUser::getInstance( $user );
		$attached = $caUser->queryAttached();
		$this->wikis = array_keys( $attached );
		$rows = array();
		foreach ( $this->wikis as $wiki ) {
			$rows[] = array(
				'wiki' => $wiki,
				'oldname' => $data['oldname'],
				'status' => 'queued'
			);
		}

		// Update the db status
		$dbw = CentralAuthUser::getCentralDB();
		$dbw->insert(
			'renameuser_status',
			$rows,
			__METHOD__
		);

		// Submit the jobs.
		$params = array( 'from' => $data['oldname'], 'to' => $data['newname'] );
		$title = Title::newMainPage(); // This isn't used anywhere!
		$job = new LocalRenameUserJob( $title, $params );
		foreach( $this->wikis as $wiki ) {
			JobQueueGroup::singleton( $wiki )->push( $job );
		}

		return Status::newGood();
	}

	function onSuccess() {
		// TODO Output some "jobs have been queued on ..$this->wikis"
	}
}
