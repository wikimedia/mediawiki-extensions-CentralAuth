<?php
/**
 * Setup database tests for centralauth
 * @group CentralAuthDB
 */

class CentralAuthRemoteUserUsingDatabaseTest extends CentralAuthTestCaseUsingDatabase {

	protected $remoteDB1, $remoteDB2, $remoteDB3;

	protected static $userTables = array( 'user', 'user_groups', 'ipblocks' );


	/**
	 * Add tables emulating remote wikis. TODO: This could all be done in addDBData,
	 * to avoid duplicating the "remote wiki" tables with unittest_ prefix. But it's
	 * ugly no matter where you do it.
	 * Uses the prefixes unittestremote1_, unittestremote2_, unittestremote3_
	 */
	public static function setUpBeforeClass() {
		global $wgDBprefix;

		parent::setUpBeforeClass();

		$db = wfGetDB( DB_MASTER );
		$originalGlobalPrefix = $wgDBprefix;
		$originalPrefix = $db->tablePrefix();
		$cl1 = new CloneDatabase( $db, self::$userTables, 'unittestremote1_', $db->tablePrefix() );
		$cl2 = new CloneDatabase( $db, self::$userTables, 'unittestremote2_', $db->tablePrefix() );
		$cl3 = new CloneDatabase( $db, self::$userTables, 'unittestremote3_', $db->tablePrefix() );
		$cl1->useTemporaryTables( false );
		$cl2->useTemporaryTables( false );
		$cl3->useTemporaryTables( false );
		$cl1->cloneTableStructure();
		$cl2->cloneTableStructure();
		$cl3->cloneTableStructure();
		$db->tablePrefix( $originalPrefix );
		$wgDBprefix = $originalGlobalPrefix; // CloneDatabase is evil and sets the global
	}

	/**
	 * Cleanup the tables emulating remote wikis
	 */
	public static function tearDownAfterClass() {
		$db = wfGetDB( DB_MASTER );
		foreach ( self::$userTables as $tbl ) {
			$db->query( "DROP TABLE `unittestremote1_{$tbl}`" );
			$db->query( "DROP TABLE `unittestremote2_{$tbl}`" );
			$db->query( "DROP TABLE `unittestremote3_{$tbl}`" );
		}
		parent::tearDownAfterClass();
	}

	/**
	 * Setup global and local users with various attachments
	 */
	protected function setUp() {
		global $wgDBname;

		parent::setUp();

		// Setup connections to all the "remote" wikis
		$this->remoteDB1 = wfGetDB( DB_MASTER, array(), "{$wgDBname}-unittestremote1_" );
		$this->remoteDB2 = wfGetDB( DB_MASTER, array(), "{$wgDBname}-unittestremote2_" );
		$this->remoteDB3 = wfGetDB( DB_MASTER, array(), "{$wgDBname}-unittestremote3_" );

		// Setup User GlobalUser, attached on unittestremote1_ and unittestremote2_
		$user = new CentralAuthTestUser(
			'GlobalUser',
			'GUP@ssword',
			array( 'gu_id' => '1001' ),
			array(
				array( $this->remoteDB1->getWikiID(), 'login' ),
				array( $this->remoteDB2->getWikiID(), 'login' ),
			)
		);
		$user->save( $this->db ); // Saves in globaluser, localuser, localnames
		$user->saveRemote( $this->remoteDB1 );
		$user->saveRemote( $this->remoteDB2 );


		// Add an "unattached" user named GlobalUser on unittestremote3_
		$user->saveRemote( $this->remoteDB3 );
		$user->saveLocalName( $this->db, $this->remoteDB3->getWikiID() );

		// Setup global user without localname updates
		$user2 = new CentralAuthTestUser(
			'GlobalUser2',
			'GU2P@ssword',
			array( 'gu_id' => '1002' ),
			array(
				array( $this->remoteDB1->getWikiID(), 'login' ),
			)
		);
		$user2->save( $this->db );
		$user2->saveRemote( $this->remoteDB1 );
		$user2->saveRemote( $this->remoteDB2 );

		// Setup local-only user that can be migrated
		$user3 = new CentralAuthTestUser(
			'LocalUser',
			'LUP@ssword',
			array( 'gu_id' => '1003' ),
			array()
		);
		$user3->createLocalAccount();
		$user3->saveRemote( $this->remoteDB1 );
		$user3->saveRemote( $this->remoteDB2,
			array(
				'user_editcount' => 20,
				'user_email' => '',
				'user_email_authenticated' => false
			)
		);
		$pass = User::getPasswordFactory()->newFromPlaintext( '1234567890' );
		$user3->saveRemote( $this->remoteDB3,
			array(
				'user_editcount' => 19,
				'user_password' => $pass->toString(),
				'user_email' => '',
				'user_email_authenticated' => false
			)
		);

	}

	public function needsDB() {
		return true;
	}


	/**
	 * @covers CentralAuthUser::queryAttached
	 * @covers CentralAuthUser::localUserData
	 */
	public function testQueryAttached() {
		global $wgDBname;
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertSame( true, $caUser->exists() ); // sanity

		$attachments = $caUser->queryAttached();

		$this->assertArrayEquals(
			array( $this->remoteDB1->getWikiID(),
				$this->remoteDB2->getWikiID(),
			),
			array_keys( $attachments ),
			"Global user should be attached on remote wikis 1 & 2"
		);
	}

	/**
	 * @covers CentralAuthUser::queryUnattached
	 * @covers CentralAuthUser::doListUnattached
	 * @covers CentralAuthUser::localUserData
	 */
	public function testQueryUnattached() {
		global $wgDBname;
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertSame( true, $caUser->exists() ); // sanity

		$unattachments = $caUser->queryUnattached();

		$this->assertArrayEquals(
			array( $this->remoteDB3->getWikiID(), ),
			array_keys( $unattachments ),
			"Global user shouldn't be attached on remote wiki 3"
		);
	}

	/**
	 * @covers CentralAuthUser::listAttached
	 * @covers CentralAuthUser::loadAttached
	 */
	public function testListAttached() {
		$caUser = new CentralAuthUser( 'GlobalUser' );
		$this->assertSame( true, $caUser->exists() ); // sanity

		$this->assertArrayEquals(
			array(
				$this->remoteDB1->getWikiID(),
				$this->remoteDB2->getWikiID(),
			),
			$caUser->listAttached(),
			"Global user should be attached on remote wikis 1 & 2"
		);
	}

	/**
	 * @covers CentralAuthUser::getWikiList
	 */
	public function testGetWikiList() {
		$this->setMwGlobals( array(
			'wgLocalDatabases' => array(
				$this->db->getWikiID(),
				$this->remoteDB1->getWikiID(),
				$this->remoteDB2->getWikiID(),
				$this->remoteDB3->getWikiID(),
			),
		) );
		$this->assertArrayEquals(
			array(
				$this->db->getWikiID(),
				$this->remoteDB1->getWikiID(),
				$this->remoteDB2->getWikiID(),
				$this->remoteDB3->getWikiID(),
			),
			CentralAuthUser::getWikiList()
		);
	}

	/**
	 * @dataProvider provideMigrationDryRun
	 * @covers CentralAuthUser::migrationDryRun
	 * @covers CentralAuthUser::attemptAutoMigration
	 * @covers CentralAuthUser::attach
	 */
	public function testMigrationDryRun( $passwords, $good, $attach, $msg ) {
		$caNewUser = new CentralAuthUser( 'LocalUser' );
		$this->assertSame( false, $caNewUser->exists() ); // sanity


		$home = false;
		$attached = array();
		$unattached = array();

		$status = $caNewUser->migrationDryRun(
			$passwords,
			$home,
			$attached,
			$unattached,
			$methods
		);

		$this->assertSame( $good, $status->isGood(), $msg );

		$this->assertArrayEquals(
			$attach,
			$attached,
			$msg
		);
	}

	public function provideMigrationDryRun() {
		global $wgDBname;
		return array(
			array(
				array( 'aaaa' ),
				false,
				array(),
				'User must authenticate local account to merge'
			),
			array(
				array( 'LUP@ssword' ),
				true,
				array(
					"{$wgDBname}-unittest_",
					"{$wgDBname}-unittestremote1_",
					"{$wgDBname}-unittestremote2_",
				),
				'User suplies primary password'
			),
			array(
				array( 'LUP@ssword', '1234567890' ),
				true,
				array(
					"{$wgDBname}-unittest_",
					"{$wgDBname}-unittestremote1_",
					"{$wgDBname}-unittestremote2_",
					"{$wgDBname}-unittestremote3_",
				),
				'User attaches all wikis by password'
			),
		);
	}

	/**
	 * @covers CentralAuthUser::storeAndMigrate
	 * @covers CentralAuthUser::attemptAutoMigration
	 * @covers CentralAuthUser::attach
	 */
	public function testStoreAndMigrate() {
		$caNewUser = new CentralAuthUser( 'LocalUser' );
		$this->assertSame( false, $caNewUser->exists() ); // sanity

		// Suppress the warning from nested transactions in storeAndMigrate and attach()
		wfSuppressWarnings();
		$caNewUser->storeAndMigrate( array( '1234567890', 'LUP@ssword' ), false );
		wfRestoreWarnings();

		$this->assertSame( true, $caNewUser->exists() );

		$this->assertArrayEquals(
			array(
				$this->db->getWikiID(),
				$this->remoteDB1->getWikiID(),
				$this->remoteDB2->getWikiID(),
				$this->remoteDB3->getWikiID(),
			),
			array_keys( $caNewUser->queryAttached() ),
			"Global user should be attached on remote wikis 1 & 2"
		);
	}

	/**
	 * This relies on another CentralAuth change to work
	 * @covers CentralAuthUser::importLocalNames
	 */
/*	public function testImportLocalNames() {
		$caUser = new CentralAuthUser( 'GlobalUser2' );
		$this->assertSame( true, $caUser->exists() ); // sanity

		$this->assertSame(
			true,
			$caUser->importLocalNames()
		);

		$expected = array(
			array( $this->db->getWikiID() ),
			array( $this->remoteDB1->getWikiID() ),
			array( $this->remoteDB2->getWikiID() ),
		);

		$this->assertSelect(
			'localnames',
			array( 'ln_wiki' ),
			array( 'ln_name' => 'GlobalUser2' ),
			$expected
		);
	}*/

}
