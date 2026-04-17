<?php

namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\Maintenance\LockUser;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\LockUser
 * @group Database
 */
class LockUserTest extends MaintenanceBaseTestCase {
	use TempUserTestTrait;

	/** @inheritDoc */
	protected function getMaintenanceClass(): string {
		return LockUser::class;
	}

	public function testUserDoesNotExist(): void {
		$this->maintenance->setOption( 'username', $this->getMutableTestUser()->getUserIdentity()->getName() );
		$this->expectOutputRegex( '/does not exist/' );
		$this->maintenance->execute();
	}

	public function testUserAlreadyLocked(): void {
		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$centralAuthUser->adminLock();

		$this->maintenance->setOption( 'username', $centralAuthUser->getName() );
		$this->expectOutputRegex( '/is already locked/' );
		$this->maintenance->execute();
	}

	public function testUserNotLocked(): void {
		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();

		$this->maintenance->setOption( 'username', $centralAuthUser->getName() );
		$this->maintenance->setOption( 'unlock', 1 );
		$this->expectOutputRegex( '/is not locked/' );
		$this->maintenance->execute();
	}

	private function commonAssertGlobalStatusChangeLog(
		string $expectedTarget,
		string $expectedPerformerUsername
	): void {
		$this->newSelectQueryBuilder()
			->select( [ 'actor_name', 'log_title', 'log_namespace' ] )
			->from( 'logging' )
			->join( 'actor', null, 'log_actor=actor_id' )
			->where( [ 'log_action' => 'setstatus' ] )
			->caller( __METHOD__ )
			->assertRowValue( [
				$expectedPerformerUsername,
				Title::newFromText( $expectedTarget )->getDBkey() . '@global',
				NS_USER
			] );
	}

	public function testLockUser(): void {
		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();

		$this->assertFalse( $centralAuthUser->isLocked(), 'User should not be locked before the script runs' );

		$this->maintenance->setOption( 'username', $centralAuthUser->getName() );
		$this->maintenance->setOption( 'reason', 'Test lock' );
		$this->maintenance->execute();

		$centralAuthUser->invalidateCache();
		$this->assertTrue( $centralAuthUser->isLocked(), 'User should be locked after the script runs' );
		$this->expectOutputRegex( '/Locked user/' );
		$this->commonAssertGlobalStatusChangeLog( $centralAuthUser->getName(), User::MAINTENANCE_SCRIPT_USER );
	}

	public function testUnlockUser(): void {
		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$centralAuthUser->adminLock();

		$this->maintenance->setOption( 'username', $centralAuthUser->getName() );
		$this->maintenance->setOption( 'unlock', 1 );
		$this->maintenance->execute();

		$centralAuthUser->invalidateCache();
		$this->assertFalse( $centralAuthUser->isLocked(), 'User should be unlocked after the script runs' );
		$this->expectOutputRegex( '/Unlocked user/' );
		$this->commonAssertGlobalStatusChangeLog( $centralAuthUser->getName(), User::MAINTENANCE_SCRIPT_USER );
	}

	public function testLockUserWithActor(): void {
		$actorUser = $this->getTestUser()->getUser();
		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();

		$this->maintenance->setOption( 'username', $centralAuthUser->getName() );
		$this->maintenance->setOption( 'actor', $actorUser->getName() );
		$this->maintenance->execute();

		$centralAuthUser->invalidateCache();
		$this->assertTrue( $centralAuthUser->isLocked(), 'User should be locked after the script runs' );
		$this->expectOutputRegex( '/Locked user/' );
		$this->commonAssertGlobalStatusChangeLog( $centralAuthUser->getName(), $actorUser->getName() );
	}

	public function testInvalidActor(): void {
		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getTestUser() )
			->save( $this->getDb() )
			->getCentralUser();

		$this->maintenance->setOption( 'username', $centralAuthUser->getName() );
		$this->maintenance->setOption( 'actor', 'NonExistentUser12345' );
		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/No user \'NonExistentUser12345\' found/' );
		$this->maintenance->execute();
	}

	public function testLockUsersFromFile(): void {
		$user1 = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$user2 = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$this->assertNotSame( $user1->getName(), $user2->getName(), 'Test users should be different' );

		$tempFilePath = $this->getNewTempFile();
		// Two user names, one on each line, and then a whitespace-only line to test that blank lines are ignored.
		file_put_contents( $tempFilePath, $user1->getName() . "\n" . $user2->getName() . "\n \n" );

		$this->maintenance->setOption( 'file', $tempFilePath );
		$this->maintenance->execute();

		$user1->invalidateCache();
		$this->assertTrue( $user1->isLocked(), 'User1 should be locked after the script runs' );
		$user2->invalidateCache();
		$this->assertTrue( $user2->isLocked(), 'User2 should be locked after the script runs' );
		$this->expectOutputRegex( '/Processing 2 users/' );
		$this->expectOutputRegex( '/Locked user/' );
	}

	public function testLockUsersFromFileWhenFileCannotBeRead(): void {
		// Mock the file() call to return false. Doing it via a mock avoids the
		// PHP warning created when the method returns false
		$this->maintenance = $this->getMockBuilder( LockUser::class )
			->onlyMethods( [ 'readUsersFile' ] )
			->getMock();
		$this->maintenance->method( 'readUsersFile' )
			->willReturn( false );
		TestingAccessWrapper::newFromObject( $this->maintenance )->isTesting = true;

		$this->maintenance->setOption( 'file', 'bad-file.txt' );
		$this->expectCallToFatalError();
		$this->expectOutputRegex( "/Failed to read file 'bad-file\.txt'/" );
		$this->maintenance->execute();
	}

	public function testNeitherUsernameNorFileProvided(): void {
		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/Either --username or --file must be provided/' );
		$this->maintenance->execute();
	}

	public function testBothUsernameAndFileProvided(): void {
		$centralAuthUser = CentralAuthTestUser::newFromTestUser( $this->getMutableTestUser() )
			->save( $this->getDb() )
			->getCentralUser();
		$tempFilePath = $this->getNewTempFile();
		file_put_contents( $tempFilePath, $centralAuthUser->getName() );

		$this->maintenance->setOption( 'username', $centralAuthUser->getName() );
		$this->maintenance->setOption( 'file', $tempFilePath );
		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/Cannot use both --username and --file/' );
		$this->maintenance->execute();
	}

	public function testLockOfTemporaryAccount(): void {
		$this->enableAutoCreateTempUser();

		$centralAuthTestUser = new CentralAuthTestUser(
			'~2025-1',
			'GUPassword@123'
		);
		$centralAuthUser = $centralAuthTestUser->save( $this->getDb() )->getCentralUser();

		$this->maintenance->setOption( 'username', $centralAuthUser->getName() );
		$this->expectOutputRegex( '/You cannot lock temporary accounts/' );
		$this->maintenance->execute();
	}
}
