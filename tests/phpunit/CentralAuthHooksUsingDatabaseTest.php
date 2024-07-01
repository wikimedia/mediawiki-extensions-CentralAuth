<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWikiIntegrationTestCase;

/**
 * Basic tests for CentralAuthHooks
 *
 * @group CentralAuthDB
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthHooks
 */
class CentralAuthHooksUsingDatabaseTest extends MediaWikiIntegrationTestCase {
	public function testLockedEmailDisabled() {
		$u = new CentralAuthTestUser(
			'GlobalLockedUser',
			'GLUP@ssword',
			[
				'gu_id' => '1003',
				'gu_locked' => 1,
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE,
				'gu_email' => 'testlocked@localhost',
				'gu_home_db' => 'metawiki',
			],
			[
				[ 'metawiki', 'primary' ],
			]
		);
		$u->save( $this->getDb() );

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newFromName( 'GlobalLockedUser' );
		$this->assertFalse( $user->isEmailConfirmed() );
		$this->assertFalse( $user->canReceiveEmail() );
	}
}
