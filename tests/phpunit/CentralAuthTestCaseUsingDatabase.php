<?php
/**
 * Setup database tests for centralauth.
 *
 * @group Database
 */
abstract class CentralAuthTestCaseUsingDatabase extends MediaWikiTestCase {

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
		'renameuser_queue',
	);

	// Keep track of the original db name
	protected static $centralAuthDatabase = null;

	/**
	 * Setup the centralauth tables in the current db, so we don't have
	 * to worry about rights on another database. The first time it's called
	 * we have to set the DB prefix ourselves, and reset it back to the original
	 * so that CloneDatabase will work. On subsequent runs, the prefix is already
	 * setup for us.
	 */
	public static function setUpBeforeClass() {
		global $wgCentralAuthDatabase;
		parent::setUpBeforeClass();

		if ( is_null( self::$centralAuthDatabase ) ) {
			self::$centralAuthDatabase = $wgCentralAuthDatabase;
		}
		$wgCentralAuthDatabase = false; // use the current wiki db

		$db = wfGetDB( DB_MASTER );
		if ( $db->tablePrefix() !== MediaWikiTestCase::DB_PREFIX ) {
			$originalPrefix = $db->tablePrefix();
			$db->tablePrefix( MediaWikiTestCase::DB_PREFIX );
			if ( !$db->tableExists( 'globaluser' ) ) {
				$db->sourceFile( __DIR__ . '/../../central-auth.sql' );
			}
			$db->tablePrefix( $originalPrefix );
		} else {
			$db->sourceFile( __DIR__ . '/../../central-auth.sql' );
		}
	}

	public static function tearDownAfterClass() {
		global $wgCentralAuthDatabase;
		$db = wfGetDB( DB_MASTER );
		foreach ( self::$centralauthTables as $table ) {
			$db->dropTable( $table );
		}
		if ( !is_null( self::$centralAuthDatabase ) ) {
			$wgCentralAuthDatabase = self::$centralAuthDatabase;
		}
		parent::tearDownAfterClass();
	}

	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		$this->tablesUsed = array_merge( $this->tablesUsed, self::$centralauthTables );
		parent::__construct( $name, $data, $dataName );
	}
}
