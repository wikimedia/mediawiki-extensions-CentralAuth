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

		$language = $this->createMock( \Language::class );

		$db = $this->createMock( DBConnRef::class );
		$db->method( 'selectRow' )
			->willReturn( (object)[
				'user_id' => 0,
				'user_name' => 'Test',
				'user_email' => 'test@example.com',
				'user_email_authenticated' => '2010-01-01',
				'user_registration' => '2010-01-01',
			] );
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
			] );

		$commentStore = $this->createMock( \CommentStore::class );
		$commentStore->method( 'getJoin' )
			->willReturn( [
				'tables' => [],
				'fields' => [],
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
	}
}
