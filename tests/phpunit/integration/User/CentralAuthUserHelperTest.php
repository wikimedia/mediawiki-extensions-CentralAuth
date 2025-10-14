<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\WikiMap\WikiMap;

/**
 * @covers \MediaWiki\Extension\CentralAuth\User\CentralAuthUserHelper
 * @group Database
 */
class CentralAuthUserHelperTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;

	private function getRegisteredTestUser(): CentralAuthUser {
		$testUser = $this->getMutableTestUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $testUser->getUser() );
		$caUser->register( $testUser->getPassword(), null );
		$caUser->attach( WikiMap::getCurrentWikiId() );
		$caUser->addToGlobalGroup( 'group-two' );
		$caUser->addToGlobalGroup(
			'group-three',
			// Some time in the future
			time() + 1800
		);
		return $caUser;
	}

	/** @dataProvider provideGetUserByName */
	public function testGetUserByName( callable $usernameToCheck, ?string $expectedError ) {
		$user = $this->getRegisteredTestUser();
		$service = CentralAuthServices::getUserHelper();
		$statusSecondary = $service->getCentralAuthUserByName( $usernameToCheck( $user ) );
		$statusPrimary = $service->getCentralAuthUserByNameFromPrimary( $usernameToCheck( $user ) );

		if ( $expectedError !== null ) {
			$this->assertStatusError( $expectedError, $statusSecondary );
			$this->assertStatusError( $expectedError, $statusPrimary );
			return;
		}
		$this->assertStatusGood( $statusSecondary );
		$valueSecondary = $statusSecondary->getValue();
		$this->assertEquals( $user->getName(), $valueSecondary->getName() );
		$this->assertEquals( $user->getId(), $valueSecondary->getId() );

		$this->assertStatusGood( $statusPrimary );
		$valuePrimary = $statusPrimary->getValue();
		$this->assertEquals( $user->getName(), $valuePrimary->getName() );
		$this->assertEquals( $user->getId(), $valuePrimary->getId() );
	}

	public static function provideGetUserByName(): array {
		return [
			'Registered user' => [
				'usernameToCheck' => static fn ( CentralAuthUser $user ) => $user->getName(),
				'expectedError' => null,
			],
			'Registered user with non-canonical username' => [
				'usernameToCheck' => static fn ( CentralAuthUser $user ) => lcfirst( $user->getName() ),
				'expectedError' => null,
			],
			'Blank username' => [
				'usernameToCheck' => static fn ( CentralAuthUser $user ) => '',
				'expectedError' => 'nouserspecified',
			],
			'Non-existing user' => [
				'usernameToCheck' => static fn ( CentralAuthUser $user ) => 'Not in use',
				'expectedError' => 'nosuchusershort',
			],
			'Invalid username' => [
				'usernameToCheck' => static fn ( CentralAuthUser $user ) => 'Invalid@username',
				'expectedError' => 'nosuchusershort',
			],
		];
	}

	/** @dataProvider provideGetUserById */
	public function testGetUserById_exists( bool $usePrimary ) {
		$user = $this->getRegisteredTestUser();
		$service = CentralAuthServices::getUserHelper();
		$status = $usePrimary ?
			$service->getCentralAuthUserByIdFromPrimary( $user->getId() ) :
			$service->getCentralAuthUserById( $user->getId() );

		$this->assertStatusGood( $status );
		$value = $status->getValue();
		$this->assertEquals( $user->getName(), $value->getName() );
		$this->assertEquals( $user->getId(), $value->getId() );
	}

	/** @dataProvider provideGetUserById */
	public function testGetUserById_doesNotExist( bool $usePrimary ) {
		$service = CentralAuthServices::getUserHelper();
		$status = $usePrimary ?
			$service->getCentralAuthUserByIdFromPrimary( 12345678 ) :
			$service->getCentralAuthUserById( 12345678 );

		$this->assertStatusError( 'noname', $status );
	}

	public static function provideGetUserById(): array {
		return [
			'Using primary database' => [ 'usePrimary' => true ],
			'Using any database' => [ 'usePrimary' => false ],
		];
	}

	/** @dataProvider provideGetUserWithAuthority */
	public function testGetUserByNameWithAuthority( bool $hasSuppressRight, bool $isHidden, bool $expected ) {
		$user = $this->getRegisteredTestUser();
		if ( $isHidden ) {
			$hideStatus = $user->adminSetHidden( true );
			$this->assertStatusGood( $hideStatus );
		}

		$viewer = $this->mockAnonAuthorityWithPermissions( $hasSuppressRight ? [ 'centralauth-suppress' ] : [] );

		$service = CentralAuthServices::getUserHelper();
		$status = $service->getCentralAuthUserByName( $user->getName(), $viewer );

		if ( !$expected ) {
			$this->assertStatusError( 'nosuchusershort', $status );
			return;
		}
		$this->assertStatusGood( $status );
		$value = $status->getValue();
		$this->assertEquals( $user->getName(), $value->getName() );
		$this->assertEquals( $user->getId(), $value->getId() );
	}

	/** @dataProvider provideGetUserWithAuthority */
	public function testGetUserByIdWithAuthority( bool $hasSuppressRight, bool $isHidden, bool $expected ) {
		$user = $this->getRegisteredTestUser();
		if ( $isHidden ) {
			$hideStatus = $user->adminSetHidden( true );
			$this->assertStatusGood( $hideStatus );
		}

		$viewer = $this->mockAnonAuthorityWithPermissions( $hasSuppressRight ? [ 'centralauth-suppress' ] : [] );

		$service = CentralAuthServices::getUserHelper();
		$status = $service->getCentralAuthUserById( $user->getId(), $viewer );

		if ( !$expected ) {
			$this->assertStatusError( 'noname', $status );
			return;
		}
		$this->assertStatusGood( $status );
		$value = $status->getValue();
		$this->assertEquals( $user->getName(), $value->getName() );
		$this->assertEquals( $user->getId(), $value->getId() );
	}

	public static function provideGetUserWithAuthority(): array {
		return [
			'Visible target, without centralauth-suppress right' => [
				'hasSuppressRight' => false,
				'isHidden' => false,
				'expected' => true,
			],
			'Hidden target, without centralauth-suppress right' => [
				'hasSuppressRight' => false,
				'isHidden' => true,
				'expected' => false,
			],
			'Hidden target, with centralauth-suppress right' => [
				'hasSuppressRight' => true,
				'isHidden' => true,
				'expected' => true,
			],
		];
	}
}
