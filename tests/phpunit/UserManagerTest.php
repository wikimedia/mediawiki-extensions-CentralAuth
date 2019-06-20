<?php

namespace MediaWiki\CentralAuth\Tests\Block;

use MediaWiki\Block\BlockRestrictionStore;
use MediaWiki\CentralAuth\UserManager;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\ILoadBalancer;

class UserManagerTest extends \MediaWikiTestCase {

	/**
	 * @covers MediaWiki\CentralAuth\UserManager::getLocalUserData()
	 */
	public function testGetLocalUserData() {
		$wikiId = 'test';

		$user = (object)[
			'user_id' => 0,
			'user_name' => 'Test',
			'user_email' => 'test@example.com',
			'user_email_authenticated' => '20190308062452',
			'user_registration' => '20190308062452',
			'user_password' => 'password',
			'user_editcount' => 3,
		];

		$language = $this->createMock( \Language::class );

		$db = $this->createMock( DBConnRef::class );
		$db->method( 'selectRow' )
			->willReturn( $user );
		$db->method( 'select' )
			->willReturn( [] );

		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnectionRef' )
			->willReturn( $db );

		$lbFactory = $this->createMock( ILBFactory::class );
		$lbFactory->method( 'getMainLB' )
			->with( $wikiId )
			->willReturn( $loadBalancer );

		$actorMigraiton = $this->createMock( \ActorMigration::class );
		$actorMigraiton->method( 'getWhere' )
			->willReturn( [
				'orconds' => [],
				'tables' => [],
				'joins' => [],
			] );

		$commentStore = $this->createMock( \CommentStore::class );
		$commentStore->method( 'getJoin' )
			->willReturn( [
				'tables' => [],
				'fields' => [],
				'joins' => [],
			] );

		$logger = $this->createMock( LoggerInterface::class );
		$blockRestrictions = $this->createMock( BlockRestrictionStore::class );

		$userManager = new UserManager(
			$language,
			$lbFactory,
			$actorMigraiton,
			$commentStore,
			$logger,
			$blockRestrictions
		);

		$data = $userManager->getLocalUserData( $wikiId, 'Test' );

		$this->assertInternalType( 'array', $data );
		$this->assertArrayHasKey( 'wiki', $data );
		$this->assertSame( $wikiId, $data['wiki'] );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertSame( $user->user_id, $data['id'] );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertSame( $user->user_name, $data['name'] );
		$this->assertArrayHasKey( 'email', $data );
		$this->assertSame( $user->user_email, $data['email'] );
		$this->assertArrayHasKey( 'emailAuthenticated', $data );
		$this->assertSame( $user->user_email_authenticated, $data['emailAuthenticated'] );
		$this->assertArrayHasKey( 'registration', $data );
		$this->assertSame( $user->user_registration, $data['registration'] );
		$this->assertArrayHasKey( 'password', $data );
		$this->assertSame( $user->user_password, $data['password'] );
		$this->assertArrayHasKey( 'editCount', $data );
		$this->assertSame( $user->user_editcount, $data['editCount'] );
		$this->assertArrayHasKey( 'groupMemberships', $data );
		$this->assertEmpty( $data['groupMemberships'] );
		$this->assertArrayHasKey( 'blocked', $data );
		$this->assertFalse( $data['blocked'] );
	}
}
