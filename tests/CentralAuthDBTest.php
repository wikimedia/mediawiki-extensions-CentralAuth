<?php
/**
 * Setup database tests for centralauth
 */
abstract class CentralAuthDBTest extends MediaWikiTestCase {

	public static $centralauthTables = array(
		'global_group_permissions',
		'global_group_restrictions',
		'global_user_groups',
		'globalnames',
		'globaluser',
		'localnames',
		'localuser',
		'wikiset',
		'renameuser_status',
	);

	/**
	 * Setup the centralauth tables in the current db, so we don't have
	 * to worry about rights on another database. The first time it's called
	 * we have to set the DB prefix ourselves, and reset it back to the original
	 * so that CloneDatabase will work. On subsequent runs, the prefix is already
	 * setup for us.
	 */
	public static function setUpBeforeClass() {
		global $wgCentralAuthDatabase;
		$wgCentralAuthDatabase = false; // use the current wiki db

		$db = wfGetDB( DB_MASTER );
		if ( $db->tablePrefix() !== MediaWikiTestCase::DB_PREFIX ) {
			$originalPrefix = $db->tablePrefix();
			$db->tablePrefix( MediaWikiTestCase::DB_PREFIX );
			$db->sourceFile( __DIR__ . '/../central-auth.sql' );
			$db->tablePrefix( $originalPrefix );
		} else {
			$db->sourceFile( __DIR__ . '/../central-auth.sql' );
		}
	}

	public static function tearDownAfterClass() {
		$db = wfGetDB( DB_MASTER );
		foreach ( self::$centralauthTables as $table ) {
			$db->dropTable( $table );
		}
	}

	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		$this->tablesUsed = array_merge( $this->tablesUsed, self::$centralauthTables );
		parent::__construct( $name, $data, $dataName );
	}

	/**
	 * Add local and global accounts for testing
	 */
	protected function setUp() {
		// Add a fairly standard, attached user
		$user = User::newFromName( 'GlobalUser' );
		if ( $user->idForName() == 0 ) {
			$user->addToDatabase();
			$user->setPassword( 'GUP@ssword' );
			$user->saveSettings();
		}
		$row = array(
			'gu_name' => 'GlobalUser',
			'gu_id' => '1001',
			'gu_salt' => '',
			'gu_password' => '1234567890',
			'gu_auth_token' => '1234',
			'gu_locked' => 0,
			'gu_hidden' => CentralAuthUser::HIDDEN_NONE,
			'gu_registration' => '20130627183537',
			'gu_email' => 'test@localhost',
			'gu_email_authenticated' => '20130801040214',
			'gu_home_db' => wfWikiID(),
		);
		$this->db->upsert(
			'globaluser',
			$row,
			array( 'gu_id' ),
			$row,
			__METHOD__
		);

		// Add a user who will have few attachements
		$user = User::newFromName( 'MetaGlobalUser' );
		if ( $user->idForName() == 0 ) {
			$user->addToDatabase();
			$user->setPassword( 'MGUP@ssword' );
			$user->saveSettings();
		}
		$rowmeta = array(
			'gu_name' => 'MetaGlobalUser',
			'gu_id' => '1002',
			'gu_salt' => '',
			'gu_password' => '1234567890',
			'gu_auth_token' => '1234',
			'gu_locked' => 0,
			'gu_hidden' => CentralAuthUser::HIDDEN_NONE,
			'gu_registration' => '20130627183537',
			'gu_email' => 'testmeta@localhost',
			'gu_email_authenticated' => '20130801040214',
			'gu_home_db' => 'metawiki',
		);
		$this->db->upsert(
			'globaluser',
			$rowmeta,
			array( 'gu_id' ),
			$rowmeta,
			__METHOD__
		);


		// Add a locked user
		$user = User::newFromName( 'GlobalLockedUser' );
		if ( $user->idForName() == 0 ) {
			$user->addToDatabase();
			$user->setPassword( 'GLUP@ssword' );
			$user->saveSettings();
		}
		$rowlocked = array(
			'gu_name' => 'GlobalLockedUser',
			'gu_id' => '1003',
			'gu_salt' => '',
			'gu_password' => '1234567890',
			'gu_auth_token' => '1234',
			'gu_locked' => 1,
			'gu_hidden' => CentralAuthUser::HIDDEN_NONE,
			'gu_registration' => '20130627183537',
			'gu_email' => 'testlocked@localhost',
			'gu_email_authenticated' => '20130801040214',
			'gu_home_db' => 'metawiki',
		);
		$this->db->upsert(
			'globaluser',
			$rowlocked,
			array( 'gu_id' ),
			$rowlocked,
			__METHOD__
		);

		// Add a suppressed user
		$user = User::newFromName( 'GlobalSuppressedUser' );
		if ( $user->idForName() == 0 ) {
			$user->addToDatabase();
			$user->setPassword( 'GSUP@ssword' );
			$user->saveSettings();
		}
		$rowsuppressed = array(
			'gu_name' => 'GlobalSuppressedUser',
			'gu_id' => '1004',
			'gu_salt' => '',
			'gu_password' => '1234567890',
			'gu_auth_token' => '1234',
			'gu_locked' => 1,
			'gu_hidden' => CentralAuthUser::HIDDEN_OVERSIGHT,
			'gu_registration' => '20130627183537',
			'gu_email' => 'testsuppressed@localhost',
			'gu_email_authenticated' => '20130801040214',
			'gu_home_db' => wfWikiID(),
		);
		$this->db->upsert(
			'globaluser',
			$rowsuppressed,
			array( 'gu_id' ),
			$rowsuppressed,
			__METHOD__
		);


		// Attache users
		$attach = array(
			array(
				'lu_wiki' => wfWikiID(),
				'lu_name' => 'GlobalUser',
				'lu_attached_timestamp' => '20130627183537',
				'lu_attached_method' => 'primary',
			),
			array(
				'lu_wiki' => 'enwiki',
				'lu_name' => 'GlobalUser',
				'lu_attached_timestamp' => '20130627183537',
				'lu_attached_method' => 'primary',
			),
			array(
				'lu_wiki' => 'dewiki',
				'lu_name' => 'GlobalUser',
				'lu_attached_timestamp' => '20130627183538',
				'lu_attached_method' => 'login',
			),
			array(
				'lu_wiki' => 'metawiki',
				'lu_name' => 'GlobalUser',
				'lu_attached_timestamp' => '20130627183550',
				'lu_attached_method' => 'password',
			),
			array(
				'lu_wiki' => 'metawiki',
				'lu_name' => 'MetaGlobalUser',
				'lu_attached_timestamp' => '20130627183550',
				'lu_attached_method' => 'primary',
			),
			array(
				'lu_wiki' => 'metawiki',
				'lu_name' => 'GlobalLockedUser',
				'lu_attached_timestamp' => '20130627183550',
				'lu_attached_method' => 'primary',
			),
			array(
				'lu_wiki' => wfWikiID(),
				'lu_name' => 'GlobalSuppressedUser',
				'lu_attached_timestamp' => '20130627183550',
				'lu_attached_method' => 'primary',
			),
		);

		// Attach user
		$this->db->delete( 'localuser', '*', __METHOD__ );
		$this->db->insert( 'localuser', $attach, __METHOD__ );

		parent::setUp();
	}

	protected function tearDown() {
		parent::tearDown();
	}
}
