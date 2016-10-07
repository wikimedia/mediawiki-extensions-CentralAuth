<?php

class UsersToRenameDatabaseUpdates {

	/**
	 * Notified via talk apge
	 */
	const NOTIFIED = 4;
	/**
	 * A redirect, temporarily skipped
	 */
	const REDIRECT = 5;
	/**
	 * Renamed!
	 */
	const RENAMED = 8;

	/**
	 * @var Database
	 */
	private $db;

	public function __construct( Database $db ) {
		$this->db = $db;
	}

	protected function updateStatus( $name, $wiki, $status ) {
		$this->db->update(
			'users_to_rename',
			array( 'utr_status' => $status ),
			array( 'utr_wiki' => $wiki, 'utr_name' => $name ),
			__METHOD__
		);
	}

	public function markNotified( $name, $wiki ) {
		$this->updateStatus( $name, $wiki, self::NOTIFIED );
	}

	public function markRenamed( $name, $wiki ) {
		$this->updateStatus( $name, $wiki, self::RENAMED );
	}

	public function markRedirectSkipped( $name, $wiki ) {
		$this->updateStatus( $name, $wiki, self::REDIRECT );
	}

	public function remove( $name, $wiki ) {
		$this->db->delete(
			'users_to_rename',
			array( 'utr_wiki' => $wiki, 'utr_name' => $name ),
			__METHOD__
		);
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

	public function findUsers( $wiki, $status, $limit ) {
		$rows = $this->db->select(
			'users_to_rename',
			array( 'utr_name', 'utr_wiki' ),
			array( 'utr_status' => $status, 'utr_wiki' => $wiki ),
			__METHOD__,
			array( 'LIMIT' => $limit )
		);

		return $rows; // @todo this shouldn't return prefixed field names
	}
}
