<?php

use MediaWiki\Extension\CentralAuth\CentralAuthUIService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * @coversDefaultClass MediaWiki\Extension\CentralAuth\CentralAuthUIService
 * @group Database
 */
class CentralAuthUIServiceTest extends CentralAuthUsingDatabaseTestCase {
	/**
	 * @covers ::__construct
	 */
	public function testConstructor() {
		$this->assertInstanceOf(
			CentralAuthUIService::class,
			new CentralAuthUIService(
				$this->createMock( TitleFactory::class )
			)
		);
	}

	/**
	 * @covers ::formatHiddenLevel
	 * @dataProvider provideValidHiddenLevels
	 */
	public function testFormatHiddenLevelValid( int $level, string $msg ) {
		$message = $this->createMock( Message::class );
		$message->expects( $this->once() )
			->method( 'escaped' )
			->willReturn( 'sensible return value' );

		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->expects( $this->once() )
			->method( 'msg' )
			->with( "centralauth-admin-$msg" )
			->willReturn( $message );

		$service = new CentralAuthUIService( $this->createMock( TitleFactory::class ) );

		$service->formatHiddenLevel( $localizer, $level );
	}

	public function provideValidHiddenLevels() {
		yield 'HIDDEN_NORMALIZE_NONE' => [ CentralAuthUser::HIDDEN_LEVEL_NONE, 'no' ];
		yield 'HIDDEN_NORMALIZE_LISTS' => [ CentralAuthUser::HIDDEN_LEVEL_LISTS, 'hidden-list' ];
		yield 'HIDDEN_NORMALIZE_SUPPRESSED' => [ CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED, 'hidden-oversight' ];
	}

	/**
	 * @covers ::formatHiddenLevel
	 */
	public function testFormatHiddenLevelInvalid() {
		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->expects( $this->never() )
			->method( 'msg' );

		$service = new CentralAuthUIService( $this->createMock( TitleFactory::class ) );

		$service->formatHiddenLevel( $localizer, 1337 );
	}

	/**
	 * @covers ::prettyTimespan
	 * @dataProvider providePrettyTimespan
	 */
	public function testPrettyTimespan( int $seconds, int $amount, string $unit ) {
		$message = $this->createMock( Message::class );
		$message->expects( $this->once() )
			->method( 'numParams' )
			->with( $amount )
			->willReturnSelf();
		$message->expects( $this->once() )
			->method( 'text' )
			->willReturn( 'sensible return value' );

		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->expects( $this->once() )
			->method( 'msg' )
			->with( "centralauth-$unit-ago" )
			->willReturn( $message );

		$service = new CentralAuthUIService( $this->createMock( TitleFactory::class ) );

		$service->prettyTimespan( $localizer, $seconds );
	}

	public function providePrettyTimespan(): Generator {
		yield '5 seconds ago' => [ 5, 5, 'seconds' ];
		yield '70 seconds ago' => [ 70, 70, 'seconds' ];
		yield '119 seconds ago' => [ 119, 119, 'seconds' ];
		yield '2 minutes ago' => [ 120, 2, 'minutes' ];
		yield '2 minutes ago (almost 3)' => [ 179, 2, 'minutes' ];
		yield '3 minutes ago' => [ 181, 3, 'minutes' ];
		yield '60 minutes ago' => [ 60 * 60, 60, 'minutes' ];
		yield '119 minutes ago' => [ 119 * 60, 119, 'minutes' ];
		yield '2 hours ago' => [ 140 * 60, 2, 'hours' ];
		yield '24 hours ago' => [ 24 * 60 * 60, 24, 'hours' ];
		yield '47 hours ago' => [ 47 * 60 * 60, 47, 'hours' ];
		yield '2 days ago' => [ 50 * 60 * 60, 2, 'days' ];
		yield '40 days ago' => [ 40 * 24 * 60 * 60, 40, 'days' ];
		yield '2 months ago' => [ 70 * 24 * 60 * 60, 2, 'months' ];
		// 400 days = 1 year and 35 days
		yield '13 months ago' => [ 400 * 24 * 60 * 60, 13, 'months' ];
		yield '2 years ago' => [ 731 * 24 * 60 * 60, 2, 'years' ];
		yield '10 years ago' => [ 3651 * 24 * 60 * 60, 10, 'years' ];
	}

	/**
	 * @covers ::processAntiSpoofConflicts
	 */
	public function testProcessAntiSpoofConflicts() {
		$u = new CentralAuthTestUser(
			'Existing',
			'GUP@ssword',
			[ 'gu_id' => '3001' ],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
			]
		);
		$u->save( $this->db );

		$u = new CentralAuthTestUser(
			'Conflict',
			'GUP@ssword',
			[ 'gu_id' => '3002' ],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
			]
		);
		$u->save( $this->db );

		$u = new CentralAuthTestUser(
			'SuppressedConflict',
			'GUP@ssword',
			[
				'gu_id' => '3003',
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
			],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
			]
		);
		$u->save( $this->db );

		$message = $this->createMock( Message::class );
		$message->expects( $this->once() )
			->method( 'text' )
			->willReturn( 'user suppressed' );

		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->expects( $this->once() )
			->method( 'msg' )
			->with( 'centralauth-rename-conflict-hidden' )
			->willReturn( $message );

		$service = new CentralAuthUIService( $this->createMock( TitleFactory::class ) );

		$conflicts = $service->processAntiSpoofConflicts(
			$localizer,
			'Existing',
			[ 'Existing', 'Conflict', 'SuppressedConflict' ]
		);

		$this->assertNotContains( '[[Special:CentralAuth/Existing|Existing]]', $conflicts );

		$this->assertContains( '[[Special:CentralAuth/Conflict|Conflict]]', $conflicts );

		$this->assertNotContains( '[[Special:CentralAuth/SuppressedConflict|SuppressedConflict]]', $conflicts );
		$this->assertContains( 'user suppressed', $conflicts );
	}
}
