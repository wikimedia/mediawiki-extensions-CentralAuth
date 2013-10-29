<?php

class SpecialGlobalRenameUser extends FormSpecialPage {

	/** @var array $wikis */
	protected $wikis;

	/** @var string $newUsername */
	protected $newUsername;

	/** @var string $oldUsername */
	protected $oldUsername;

	function __construct() {
		parent::__construct( 'GlobalRenameUser', 'centralauth-rename' );
	}

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
	 * @return Status
	 */
	function validate( array $data ) {
		if ( !class_exists( 'RenameuserSQL' ) ) {
			return Status::newFatal( 'centralauth-rename-notinstalled' );
		}

		$status = new Status();

		$old = $data['oldname'];
		$new = $data['newname'];
		$caOldUser = CentralAuthUser::getInstance( User::newFromName( $old ) );
		if ( !$caOldUser->exists() ) {
			$status->fatal( 'centralauth-rename-doesnotexist' );
		}
		$caNewUser = CentralAuthUser::getInstance( User::newFromName( $new ) );
		if ( $caNewUser->exists() ) {
			$status->fatal( 'centralauth-rename-alreadyexists' );
		}
		$unattached = $caNewUser->listUnattached();
		if ( $unattached ) {
			$status->fatal( 'centralauth-rename-unattached-intheway' );
		}

		// Check that the new name is a valid name...
		$newUser = User::newFromName( $new, 'creatable' );
		if ( $newUser === false ) {
			$status->fatal( 'centralauth-rename-badusername' );
		}

		// @todo figure out if this is necessary
		$user = User::newFromName( $data['oldname'] );
		if ( $user->getName() == $this->getUser()->getName() ) {
			$status->fatal( 'centralauth-rename-cannotself' );
		}

		// Check we're not currently renaming the user
		if ( $caOldUser->renameInProgress() ) {
			$status->fatal( 'centralauth-rename-alreadyinprogress' );
		}

		return $status;
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
		$this->oldUsername = $data['oldname'];
		// Force uppercase of newusername, otherwise wikis with wgCapitalLinks=false can create lc usernames
		// Hopefully this is good enough for all other languages...
		$this->newUsername = $wgContLang->ucfirst( $data['newname'] );

		$user = User::newFromName( $this->oldUsername );
		$caUser = CentralAuthUser::getInstance( $user );
		$this->wikis = $caUser->listAttached();

		$rows = array();
		foreach ( $this->wikis as $wiki ) {
			$rows[] = array(
				'ru_wiki' => $wiki,
				'ru_oldname' => $this->oldUsername,
				'ru_newname' => $this->newUsername,
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

		if ( $dbw->affectedRows() === 0 ) {
			// Race condition: Another admin already started the rename!
			return Status::newFatal( 'centralauth-rename-alreadyinprogress' );
		}

		$this->updateCARows( $this->oldUsername, $this->newUsername );
		// From this point on all code using CentralAuthUser
		// needs to use the new username, except for
		// the renameInProgress function. Probably.

		// Clear some caches...
		$caUser->quickInvalidateCache();
		$caUser->clearRenameCache();
		$caNewUser = new CentralAuthUser( $this->newUsername );
		$caNewUser->quickInvalidateCache();
		$caUser->clearRenameCache();

		// Submit the jobs.
		$params = array(
			'from' => $this->oldUsername,
			'to' => $this->newUsername,
			'renamer' => $this->getUser()->getName(),
			'movepages' => $data['movepages'],
			'suppressredirects' => $data['suppressredirects'],
		);
		$title = Title::newMainPage(); // This isn't used anywhere!
		$job = new LocalRenameUserJob( $title, $params );
		foreach( $this->wikis as $wiki ) {
			JobQueueGroup::singleton( $wiki )->push( $job );
		}

		// Log it!
		$logEntry = new ManualLogEntry( 'renameuser', 'global' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $this->newUsername ) );
		$logEntry->setComment( $data['reason'] );
		$logEntry->setParameters( array(
			'4::olduser' => $this->oldUsername,
			'5::newuser' => $this->newUsername,
		) );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );


		return Status::newGood();
	}

	/**
	 * Update rows in the various centralauth tables...
	 * @param string $oldname
	 * @param string $newname
	 */
	function updateCARows( $oldname, $newname ) {
		$cdbw = CentralAuthUser::getCentralDB();
		$cdbw->begin( __METHOD__ );
		$cdbw->update(
			'globaluser',
			array( 'gu_name' => $newname ),
			array( 'gu_name' => $oldname ),
			__METHOD__
		);
		$cdbw->update(
			'localuser',
			array( 'lu_name' => $newname ),
			array( 'lu_name' => $oldname ),
			__METHOD__
		);
		$cdbw->commit( __METHOD__ );

		// The localnames table will be updated upon rename locally
	}

	function onSuccess() {
		$lang = $this->getLanguage();
		$list = $lang->commaList( $this->wikis );
		$msg = $this->msg( 'centralauth-rename-queued' )
			->params( $this->oldUsername, $this->newUsername, $list )
			->parse();
		$this->getOutput()->addHTML( $msg );
	}
}
