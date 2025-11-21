<?php

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

/**
 * @covers \MediaWiki\Extension\CentralAuth\LogFormatter\GroupMembershipChangeLogFormatter
 */
class GroupMembershipChangeLogFormatterTest extends LogFormatterTestCase {

	protected function setUp(): void {
		parent::setUp();

		$db = $this->createNoOpMock( IDatabase::class, [ 'getInfinity' ] );
		$db->method( 'getInfinity' )->willReturn( 'infinity' );
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getReplicaDatabase' )->willReturn( $db );
		$this->setService( 'DBLoadBalancerFactory', $lbFactory );
	}

	/**
	 * Provide different rows from the logging table to test
	 * for backward compatibility.
	 * Do not change the existing data, just add a new database row
	 */
	public static function provideGlobalGroupsLogDatabaseRows() {
		return [
			// Current format
			[
				[
					'type' => 'gblrights',
					'action' => 'usergroups',
					'comment' => 'rights comment',
					'user' => 0,
					'user_text' => 'Sysop',
					'namespace' => NS_USER,
					'title' => 'User',
					'params' => [
						'oldGroups' => [],
						'newGroups' => [ 'steward', 'group1' ],
						'oldMetadata' => [],
						'newMetadata' => [
							[ 'expiry' => null ],
							[ 'expiry' => '20160101123456' ]
						],
					],
				],
				[
					'text' => 'Sysop changed global group membership for User: granted group1 '
						. '(temporary, until 12:34, 1 January 2016) and steward',
					'api' => [
						'oldGroups' => [],
						'newGroups' => [ 'steward', 'group1' ],
						'oldMetadata' => [],
						'newMetadata' => [
							[ 'expiry' => null ],
							[ 'expiry' => '20160101123456' ],
						],
					],
				],
			],

			// Legacy format (oldgroups and newgroups as comma-separated strings)
			[
				[
					'type' => 'gblrights',
					'action' => 'usergroups',
					'comment' => 'rights comment',
					'user' => 0,
					'user_text' => 'Sysop',
					'namespace' => NS_USER,
					'title' => 'User',
					'params' => [
						'(none)',
						'steward, group1',
					],
				],
				[
					'legacy' => true,
					'text' => 'Sysop changed global group membership for User: granted '
						. 'steward and group1',
					'api' => [
						'(none)',
						'steward, group1',
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provideGlobalGroupsLogDatabaseRows
	 */
	public function testGlobalGroupsLogDatabaseRows( $row, $extra ) {
		$this->doTestLogFormatter( $row, $extra );
	}
}
