<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Extension\CentralAuth\User\CentralAuthUserStatusLookupService;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthUserStatusLookupService
 * @group Database
 */
class CentralAuthUserStatusLookupServiceTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;

	/** @dataProvider provideMatch */
	public function testMatch( array $transition, array $rights, ?string $expectedTimestamp ): void {
		$service = new CentralAuthUserStatusLookupService(
			$this->getServiceContainer()->getConnectionProvider()
		);

		$logEntry = $service->getLogForUserStatusTransition(
			'TestUser',
			$transition,
			$this->mockRegisteredAuthorityWithPermissions( $rights )
		);

		if ( $expectedTimestamp !== null ) {
			$this->assertInstanceOf( DatabaseLogEntry::class, $logEntry );
			$this->assertSame( $expectedTimestamp, $logEntry->getTimestamp() );
		} else {
			$this->assertNull( $logEntry );
		}
	}

	public static function provideMatch(): array {
		return [
			// Happy paths - advanced rights
			'Find add locked (in globalauth)' => [
				'transition' => [ 'added' => [ 'locked' ], 'removed' => [] ],
				'rights' => [ 'deletedhistory', 'viewsuppressed', 'suppressionlog' ],
				'expectedTimestamp' => '20250102030405'
			],
			'Find add hidden (in suppress)' => [
				'transition' => [ 'added' => [ 'hidden' ], 'removed' => [] ],
				'rights' => [ 'deletedhistory', 'viewsuppressed', 'suppressionlog' ],
				'expectedTimestamp' => '20250203040506'
			],
			'Find remove hidden in globalauth (subset match)' => [
				'transition' => [ 'added' => [], 'removed' => [ 'hidden' ] ],
				'rights' => [ 'deletedhistory', 'viewsuppressed', 'suppressionlog' ],
				'expectedTimestamp' => '20250304050607'
			],
			'Find add hidden+locked (no match)' => [
				'transition' => [ 'added' => [ 'hidden', 'locked' ], 'removed' => [] ],
				'rights' => [ 'deletedhistory', 'viewsuppressed', 'suppressionlog' ],
				'expectedTimestamp' => null
			],

			// Unhappy paths - insufficient rights
			'Find add hidden in suppress (not enough rights)' => [
				'transition' => [ 'added' => [ 'hidden' ], 'removed' => [] ],
				'rights' => [],
				'expectedTimestamp' => null
			],
			'Find remove hidden+locked (not enough rights)' => [
				'transition' => [ 'added' => [], 'removed' => [ 'hidden' ] ],
				'rights' => [],
				'expectedTimestamp' => null
			]
		];
	}

	public function testLegacyLogEntry(): void {
		$service = new CentralAuthUserStatusLookupService(
			$this->getServiceContainer()->getConnectionProvider()
		);

		$logEntry = $service->getLogForUserStatusTransition(
			'TestUser2',
			[ 'added' => [], 'removed' => [] ],
			null
		);

		$this->assertInstanceOf( DatabaseLogEntry::class, $logEntry );
		$this->assertSame( '20200102030405', $logEntry->getTimestamp() );
	}

	public function addDBDataOnce(): void {
		$targetTitle = Title::makeTitle( NS_USER, 'TestUser@global' );

		$log = new ManualLogEntry( 'globalauth', 'setstatus' );
		$log->setDeleted( 0 );
		$log->setTarget( $targetTitle );
		$log->setTimestamp( '20250102030405' );
		$log->setParameters( [ 'added' => [ 'locked' ], 'removed' => [] ] );
		$log->setPerformer( $this->getTestUser()->getUser() );
		$log->insert( $this->getDb() );

		$log = new ManualLogEntry( 'suppress', 'setstatus' );
		$log->setDeleted( 0 );
		$log->setTarget( $targetTitle );
		$log->setParameters( [ 'added' => [ 'hidden' ], 'removed' => [] ] );
		$log->setTimestamp( '20250203040506' );
		$log->setPerformer( $this->getTestUser()->getUser() );
		$log->insert( $this->getDb() );

		$log = new ManualLogEntry( 'globalauth', 'setstatus' );
		$log->setDeleted( LogPage::DELETED_ACTION );
		$log->setTarget( $targetTitle );
		$log->setTimestamp( '20250304050607' );
		$log->setParameters( [ 'added' => [], 'removed' => [ 'hidden', 'locked' ] ] );
		$log->setPerformer( $this->getTestUser()->getUser() );
		$log->insert( $this->getDb() );

		$this->getDb()->newInsertQueryBuilder()
			->insert( 'logging' )
			->row( [
				'log_type' => 'globalauth',
				'log_action' => 'setstatus',
				'log_namespace' => NS_USER,
				'log_title' => 'TestUser2@global',
				'log_timestamp' => '20200102030405',
				'log_actor' => 1,
				'log_comment_id' => 1,
				'log_deleted' => 0,
				'log_params' => "locked\n(none)",
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
