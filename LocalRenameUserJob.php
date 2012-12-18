<?php
// This is inserted into the job queue of each wiki which needs renaming done, and actually just calls RenameuserSQL::rename() which may start a job of it's own...
class CentralAuthLocalRenameUserJob extends Job {
	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'startLocalRenaming', $title, $params, $id );
	}

	public function run() {
		global $wgMemc, $wgDBname, $wgCentralAuthRenameObjectStore;
		if ( !MWInit::classExists( 'RenameuserSQL' ) ) {
			throw new MWException( "Extension Renameuser is required for this to run." );
		}

		$from = $this->params['from'];
		$to = $this->params['to'];
		$contribs = User::newFromName( $from )->getEditCount();

		$renamerName = $this->params['startedByName'];
		$renamerId = (int)$this->params['startedById'];
		if ( $this->params['startedFrom'] != wfWikiID() ) {
			$renamerName = $this->params['startedByName'] . '@' . $this->params['startedFrom'];
			$renamerId = 0;
		}

		$rus = new RenameuserSQL(
			$from,
			$to,
			User::newFromName( $from )->getId(),
			array( 'checkIfUserExists' => false ) /* We do this update in SpecialCentralAuth::doSubmit, don't want RenameUser to fail when it notices this */
		);

		if ( $rus->rename() ) {
			// TODO: For renames of more than RENAMEUSER_CONTRIBJOB (default: 5000), we might want to do this in CentralAuthHooks::onRenameUserComplete instead

			// Do local logging
			$logEntry = new ManualLogEntry( 'renameuser', 'renameuser' );
			$logEntry->setPerformer( User::newFromName( $renamerName ) );
			$logEntry->setTarget( Title::newFromText( "User:{$from}" ) );
			$logEntry->setComment( $this->params['reason'] );
			$logEntry->setParameters( array(
				'4::olduser' => $from,
				'5::newuser' => $to,
				'6::edits' => $contribs
			) );
			$logid = $logEntry->insert();
			$logEntry->publish( $logid );

			$cdb = CentralAuthUser::getCentralDB();
			$wikiListKey = CentralAuthUser::memcKey( 'globalrename', sha1( $to ) );
			$renameCache = ObjectCache::newFromParams( $wgCentralAuthRenameObjectStore );
			$cdb->lock( 'centralauth-globalrename-usingmemcachekey-' . sha1( $to ), __METHOD__ ); // To avoid race condition where multiple wikis might try to edit the key at the same time
			$wikiList = $renameCache->get( $wikiListKey );
			$wikiList = array_diff( $wikiList, array( $wgDBname ) ); // Remove this wiki from the list.
			if ( count( $wikiList ) == 0 ) {
				$renameCache->delete( $wikiListKey );
			} else {
				$renameCache->set( $wikiListKey, $wikiList );
			}
			$cdb->unlock( 'centralauth-globalrename-usingmemcachekey-' . sha1( $to ), __METHOD__ );
			return true;
		}
	}
}
