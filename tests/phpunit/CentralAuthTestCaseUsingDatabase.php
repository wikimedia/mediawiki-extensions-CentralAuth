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
		'users_to_rename',
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
		fprintf( STDERR, "Setting up class " . static::class . " with prefix '" . $db->tablePrefix() . "'\n" );
		$tables = [];
		$res = $db->query( "SHOW TABLES", __METHOD__ );
		while ( $row = $res->fetchRow() ) {
			$row = array_values( $row );
			$tables[] = $row[0];
		}
		fprintf( STDERR, "  Tables: " . join(' ', $tables) . "\n" );
		if ( $db->tablePrefix() !== MediaWikiTestCase::DB_PREFIX ) {
			fprintf( STDERR, "Table prefix is " . $db->tablePrefix() . " !== " . MediaWikiTestCase::DB_PREFIX . "\n" );
			$originalPrefix = $db->tablePrefix();
			$db->tablePrefix( MediaWikiTestCase::DB_PREFIX );
			if ( !$db->tableExists( 'globaluser' ) ) {
				fprintf( STDERR, "Creating tables!\n" );
				$db->sourceFile( __DIR__ . '/../../central-auth.sql' );
			} else {
				fprintf( STDERR, "Tables already exist!\n" );
			}
			$db->tablePrefix( $originalPrefix );
		} else {
			if ( !$db->tableExists( 'globaluser' ) ) {
				fprintf( STDERR, "Creating tables!\n" );
				$db->sourceFile( __DIR__ . '/../../central-auth.sql' );
			} else {
				fprintf( STDERR, "Tables already exist!\n" );
			}
		}
	}

	public static function tearDownAfterClass() {
		global $wgCentralAuthDatabase;
		$db = wfGetDB( DB_MASTER );
		fprintf( STDERR, "Tearing down class " . static::class . " with prefix '" . $db->tablePrefix() . "'\n" );
		foreach ( self::$centralauthTables as $table ) {
			$db->dropTable( $table );
		}
		$tables = [];
		$res = $db->query( "SHOW TABLES", __METHOD__ );
		while ( $row = $res->fetchRow() ) {
			$row = array_values( $row );
			$tables[] = $row[0];
		}
		fprintf( STDERR, "  Tables: " . join(' ', $tables) . "\n" );
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
