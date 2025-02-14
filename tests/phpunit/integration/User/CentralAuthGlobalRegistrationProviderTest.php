<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthGlobalRegistrationProvider;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthGlobalRegistrationProvider
 * @group Database
 */
class CentralAuthGlobalRegistrationProviderTest extends MediaWikiIntegrationTestCase {

	public function testUnregistered() {
		$this->assertFalse(
			$this->getServiceContainer()->getUserRegistrationLookup()->getRegistration(
				new UserIdentityValue( 0, '127.0.0.1' ),
				CentralAuthGlobalRegistrationProvider::TYPE
			)
		);
	}

	public function testValid() {
		$user = new CentralAuthTestUser(
			'GlobalUser',
			'GUP@ssword',
			[ 'gu_id' => 1001 ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$user->save( $this->getDb() );

		$this->assertSame(
			'20130627183537',
			$this->getServiceContainer()->getUserRegistrationLookup()->getRegistration(
				new UserIdentityValue( 1, 'GlobalUser' ),
				CentralAuthGlobalRegistrationProvider::TYPE
			)
		);
	}

	public function testFetchRegistrationBatch(): void {
		$this->markTestSkipped( 'Skipped until IUserRegistrationProvider::fetchRegistrationBatch exists in core' );

		// Use a consistent timestamp for local registrations.
		$curTime = '20250101000000';
		ConvertibleTimestamp::setFakeTime( $curTime );

		$localUsers = [
			new UserIdentityValue( 1, 'TestUser' ),
			new UserIdentityValue( 2, 'OtherUser' ),
		];

		$expectedRegistrationTimestamps = [
			1 => '20130627183537',
			2 => '20240627183537',
			// Unattached users should fall back to the local registration timestamp.
			3 => $curTime,
			4 => null,
			0 => null,
		];

		$nextCentralId = 1_001;
		foreach ( $localUsers as $localUser ) {
			$centralUser = new CentralAuthTestUser(
				$localUser->getName(),
				'GUP@ssword',
				[
					'gu_id' => $nextCentralId++,
					'gu_registration' => $expectedRegistrationTimestamps[$localUser->getId()]
				],
				[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
			);
			$centralUser->save( $this->getDb() );
		}

		$unattachedLocalUser = new UserIdentityValue( 3, 'UnattachedUser' );
		$localUsers[] = $unattachedLocalUser;

		$centralUser = new CentralAuthTestUser(
			$unattachedLocalUser->getName(),
			'GUP@ssword',
			[
				'gu_id' => $nextCentralId++,
				'gu_registration' => '20230101000000'
			],
		);
		$centralUser->save( $this->getDb() );

		$localUsers[] = new UserIdentityValue( 4, 'MissingUser' );
		$localUsers[] = new UserIdentityValue( 0, '127.0.0.1' );

		$timestampsByLocalId = $this->getServiceContainer()
			->getUserRegistrationLookup()
			->getFirstRegistrationBatch( $localUsers );

		$this->assertSame( $expectedRegistrationTimestamps, $timestampsByLocalId );
	}
}
