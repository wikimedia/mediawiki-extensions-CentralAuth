<?php

namespace MediaWiki\CentralAuth\Tests\Phpunit\Integration\Maintenance;

use CentralAuthTestUser;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\CentralAuth\Maintenance\CentralAuthExpireTemporaryAccounts;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Maintenance\CentralAuthExpireTemporaryAccounts
 * @group Database
 */
class CentralAuthExpireTemporaryAccountsTest extends MaintenanceBaseTestCase {
	use TempUserTestTrait;

	private static User $expiredTempUser;

	protected function getMaintenanceClass() {
		return CentralAuthExpireTemporaryAccounts::class;
	}

	public function addDBDataOnce() {
		parent::addDBDataOnce();
		// Set an explicit name pattern for temporary accounts to not have to go through
		// TempUserCreator when creating test users.
		$this->enableAutoCreateTempUser( [
			'genPattern' => '~$1',
			'reservedPattern' => '~$1',
		] );

		ConvertibleTimestamp::setFakeTime( '20240901020000' );

		self::$expiredTempUser = $this->getMutableTestUser( [], '~' )->getUser();
		$unattachedTempUser = $this->getMutableTestUser( [], '~' )->getUser();

		ConvertibleTimestamp::setFakeTime( '20241201000000' );

		$liveTempUser = $this->getMutableTestUser( [], '~' )->getUser();

		ConvertibleTimestamp::setFakeTime( false );

		$nextCentralId = 1_001;
		/** @var User $localUser */
		foreach ( [ self::$expiredTempUser, $unattachedTempUser, $liveTempUser ] as $localUser ) {
			$wikis = [];

			if ( !$localUser->equals( $unattachedTempUser ) ) {
				$wikis[] = [ WikiMap::getCurrentWikiId(), 'primary' ];
			}

			$centralUser = new CentralAuthTestUser(
				$localUser->getName(),
				'GUP@ssword',
				[
					'gu_id' => $nextCentralId++,
					'gu_registration' => $localUser->getRegistration(),
				],
				$wikis
			);
			$centralUser->save( $this->getDb() );

			// Set an appropriate local user ID mapping for this user.
			$this->getDb()->newUpdateQueryBuilder()
				->update( 'localuser' )
				->set( [ 'lu_local_id' => $localUser->getId() ] )
				->where( [
					'lu_name' => $localUser->getName(),
					'lu_wiki' => WikiMap::getCurrentWikiId(),
				] )
				->caller( __METHOD__ )
				->execute();
		}
	}

	public function testExecuteWhenTemporaryAccountsNotKnown() {
		$this->disableAutoCreateTempUser();
		$this->expectOutputRegex( '/Temporary accounts are disabled/' );
		$this->maintenance->execute();
	}

	public function testExecuteWhenTemporaryAccountsNeverExpire() {
		$this->enableAutoCreateTempUser( [
			'expireAfterDays' => null,
			'notifyBeforeExpirationDays' => null,
			'genPattern' => '~$1',
			'reservedPattern' => '~$1',
		] );
		$this->expectOutputRegex( '/Temporary account expiry is not enabled/' );
		$this->maintenance->execute();
	}

	public function testExecuteWithNoExistingTemporaryAccounts() {
		// Create a no-op mock AuthManager, as no accounts should be expired by the script.
		$this->setService( 'AuthManager', $this->createNoOpMock( AuthManager::class ) );
		$this->enableAutoCreateTempUser( [
			'genPattern' => '~$1',
			'reservedPattern' => '~$1',
		] );
		$this->expectOutputRegex( '/Revoked access for 0 temporary users/' );

		$this->maintenance->loadParamsAndArgs( null, [ 'frequency' => 1, 'expiry' => 90 ] );
		$this->maintenance->execute();
	}

	public function testExecuteWithExistingTemporaryAccounts() {
		ConvertibleTimestamp::setFakeTime( '20241201000000' );

		$authManager = $this->createMock( AuthManager::class );
		$authManager->expects( $this->once() )
			->method( 'revokeAccessForUser' )
			->with( self::$expiredTempUser->getName() );

		$this->setService( 'AuthManager', $authManager );
		$this->enableAutoCreateTempUser( [
			'genPattern' => '~$1',
			'reservedPattern' => '~$1',
		] );

		$this->expectOutputRegex( '/Revoked access for 1 temporary users/' );

		$this->maintenance->loadParamsAndArgs( null, [ 'frequency' => 1, 'expiry' => 90 ] );
		$this->maintenance->execute();
	}
}
