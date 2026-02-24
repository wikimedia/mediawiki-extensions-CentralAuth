<?php

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration;

use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\CentralAuthHooks;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \MediaWiki\Extension\CentralAuth\CentralAuthHooks
 */
class CentralAuthHooksTest extends MediaWikiIntegrationTestCase {

	private function newCentralAuthHooks( CentralAuthEditCounter $editCounter ): CentralAuthHooks {
		$services = $this->getServiceContainer();
		return new CentralAuthHooks(
			$services->getMainConfig(),
			$services->getUserNameUtils(),
			$services->getUserOptionsLookup(),
			$editCounter
		);
	}

	private function setUpCentralAuthUser( string $name ): CentralAuthUser {
		$caUser = $this->getMockBuilder( CentralAuthUser::class )
			->setConstructorArgs( [ $name, IDBAccessObject::READ_NORMAL ] )
			->getMock();

		$caUser->method( 'getName' )->willReturn( $name );
		$caUser->method( 'exists' )->willReturn( true );
		$caUser->method( 'isAttached' )->willReturn( true );

		CentralAuthServices::getUserCache()->set( $caUser );

		return $caUser;
	}

	public function testGetSecurityLogContextIncludesEditCount() {
		$this->setUpCentralAuthUser( 'SecurityLogTestUser' );

		$editCounter = $this->createMock( CentralAuthEditCounter::class );
		$editCounter->method( 'getCountIfInitialized' )->willReturn( 42 );

		$user = new UserIdentityValue( 1, 'SecurityLogTestUser' );

		$context = [];
		$this->newCentralAuthHooks( $editCounter )
			->onGetSecurityLogContext( [ 'user' => $user ], $context );

		$this->assertSame(
			42,
			$context['user_global_edit_count'],
			'user_global_edit_count should contain the actual edit count'
		);
	}

	public function testGetSecurityLogContextUsesNegativeOneWhenEditCountUnknown() {
		$this->setUpCentralAuthUser( 'SecurityLogTestUser2' );

		$editCounter = $this->createMock( CentralAuthEditCounter::class );
		$editCounter->method( 'getCountIfInitialized' )->willReturn( null );

		$user = new UserIdentityValue( 2, 'SecurityLogTestUser2' );

		$context = [];
		$this->newCentralAuthHooks( $editCounter )
			->onGetSecurityLogContext( [ 'user' => $user ], $context );

		$this->assertSame(
			-1,
			$context['user_global_edit_count'],
			'user_global_edit_count should be -1 when edit count is unknown'
		);
	}
}
