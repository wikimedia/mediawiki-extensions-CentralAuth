<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MediaWikiServices;
use Wikimedia\ScopedCallback;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthEditCounter
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\UserEditCountUpdateHookHandler
 * @group Database
 */
class CentralAuthEditCounterTest extends CentralAuthUsingDatabaseTestCase {
	public function testEdit() {
		$this->tablesUsed[] = 'user';
		$this->tablesUsed[] = 'page';

		$testUser = new CentralAuthTestUser(
			'CentralAuthEditCounterTest',
			bin2hex( random_bytes( 6 ) ),
			[],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$testUser->save( $this->db );
		$user = MediaWikiServices::getInstance()->getUserFactory()
			->newFromName( 'CentralAuthEditCounterTest' );
		$caUser = CentralAuthUser::getInstance( $user );
		$editCounter = CentralAuthServices::getEditCounter();
		$this->assertSame( 0, $editCounter->getCount( $caUser ) );
		$scope = DeferredUpdates::preventOpportunisticUpdates();
		$this->editPage( 'Test', 'test edit', '', NS_MAIN, $user );
		DeferredUpdates::doUpdates();
		ScopedCallback::consume( $scope );
		$this->assertSame( 1, $editCounter->getCount( $caUser ) );
	}
}
