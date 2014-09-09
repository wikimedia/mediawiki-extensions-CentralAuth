<?php

class UsersToRenameDatabaseUpdates {

	/**
	 * @var DatabaseBase
	 */
	private $db;

	public function __construct( DatabaseBase $db ) {
		$this->db = $db;
	}

	/**
	 * @param string $name
	 * @param string $wiki
	 */
	public function insert( $name, $wiki ) {
		$this->batchInsert( array( array(
			'name' => $name,
			'wiki' => $wiki
		) ) );
	}

	/**
	 * Batch insert rows
	 *
	 * @param array $info Array with array members that have 'name' and 'wiki' keys
	 */
	public function batchInsert( array $info ) {
		$rows = array();
		foreach ( $info as $row ) {
			$rows[] = array(
				'utr_name' => $row['name'],
				'utr_wiki' => $row['wiki'],
			);
		}

		$this->db->insert(
			'users_to_rename',
			$rows,
			__METHOD__,
			array( 'IGNORE' )
		);
	}
}