<?php
// This is inserted into the job queue of each wiki which needs renaming done, and actually just calls RenameuserSQL::rename() which may start a job of it's own...
class CentralAuthLocalRenameUserJob extends Job {
	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'startLocalRenaming', $title, $params, $id );
	}

	public function run() {
		global $wgMemc, $wgDBname;
		if ( !class_exists( 'RenameuserSQL', false ) ) {
			AutoLoader::autoload( 'SpecialRenameuser' ); // RenameuserSQL is in Renameuser_body.php, which it registers as SpecialRenameuser
		}

		$from = $this->params['from'];
		$to = $this->params['to'];
		$rus = new RenameuserSQL(
			$from,
			$to,
			User::newFromName( $from )->getId(),
			array( 'checkIfUserExists' => false ) /* We do this update in SpecialCentralAuth::doSubmit, don't want RenameUser to fail when it notices this */
		);

		if ( $rus->rename() ) {
			// TODO: For renames of more than RENAMEUSER_CONTRIBJOB (default: 5000), we might want to do this in CentralAuthHooks::onRenameUserComplete instead
			$cdb = CentralAuthUser::getCentralDB();
			$wikiListKey = CentralAuthUser::memcKey( 'globalrename', sha1( $to ) );
			$cdb->lock( 'centralauth-globalrename-usingmemcachekey-' . sha1( $to ), __METHOD__ ); // To avoid race condition where multiple wikis might try to edit the key at the same time
			$wikiList = $wgMemc->get( $wikiListKey ); // TODO: Find a way to completely disable global renaming if memcache isn't available on all wikis. We can't just quit here if we see that there's no memcached - other wikis will be waiting for us (with no way for us to cancel) and the next bit of code will never get run.
			$wikiList = array_diff( $wikiList, array( $wgDBname ) ); // Remove this wiki from the list.
			if ( count( $wikiList ) == 0 ) {
				$wgMemc->delete( $wikiListKey );
				$cdb->unlock( 'centralauth-globalrename-usingmemcachekey-' . sha1( $to ), __METHOD__ );

				// Make log entry on the wiki which this rename job was started on.
				$db = wfGetDB( DB_MASTER, array(), $this->params['startedFrom'] );
				$title = Title::newFromText( "User:{$from}@global" );
				$db->insert(
					'logging',
					array(
						'log_type' => 'renameuser',
						'log_action' => 'globalrenameuser',
						'log_timestamp' => $db->timestamp( wfTimestampNow() ),
						'log_user' => $this->params['startedById'],
						'log_user_text' => $this->params['startedByName'],
						'log_namespace' => $title->getNamespace(),
						'log_title' => $title->getDBkey(),
						'log_page' => $title->getArticleID(),
						'log_comment' => $this->params['reason'],
						'log_params' => $to
					),
					__METHOD__
				);
				$db->insert(
					'recentchanges',
					array(
						'rc_timestamp' => $db->timestamp( wfTimestampNow() ),
						'rc_cur_time' => $db->timestamp( wfTimestampNow() ),
						'rc_user' => $this->params['startedById'],
						'rc_user_text' => $this->params['startedByName'],
						'rc_namespace' => $title->getNamespace(),
						'rc_title' => $title->getDBkey(),
						'rc_comment' => $this->params['reason'],
						'rc_type' => RC_LOG,
						'rc_ip' => $this->params['startedByIP'],
						'rc_logid' => $db->insertId(),
						'rc_log_type' => 'renameuser',
						'rc_log_action' => 'globalrenameuser',
						'rc_params' => $to
					),
					__METHOD__
				);
			} else {
				$wgMemc->set( $wikiListKey, $wikiList );
				$cdb->unlock( 'centralauth-globalrename-usingmemcachekey-' . sha1( $to ), __METHOD__ );
			}
			return true;
		}
	}
}
