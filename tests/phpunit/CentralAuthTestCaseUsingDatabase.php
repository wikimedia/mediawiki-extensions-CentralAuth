<?php
/**
 * Setup database tests for centralauth.
 *
 * @group Database
 */
abstract class CentralAuthTestCaseUsingDatabase extends MediaWikiTestCase {

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


	}

	public static function tearDownAfterClass() {
		global $wgCentralAuthDatabase;

		if ( !is_null( self::$centralAuthDatabase ) ) {
			$wgCentralAuthDatabase = self::$centralAuthDatabase;
		}
		parent::tearDownAfterClass();
	}

	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$this->tablesUsed = array_merge( $this->tablesUsed, CentralAuthHooks::$centralauthTables );
		parent::__construct( $name, $data, $dataName );
	}
}
