<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthGlobalRegistrationProvider;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;

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
}
