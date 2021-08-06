<?php

use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupPermissions;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupPermissions
 */
class SpecialGlobalGroupPermissionsTest extends MediaWikiTestCase {

	/** @var SpecialGlobalGroupPermissions|TestingAccessWrapper */
	private $special;

	protected function setUp(): void {
		parent::setUp();

		$this->special = TestingAccessWrapper::newFromObject( new SpecialGlobalGroupPermissions() );
	}

	/**
	 * @covers ::validateGroupName
	 * @dataProvider provideValidateGroupName
	 */
	public function testValidateGroupName( $name, $result ) {
		$status = $this->special->validateGroupName( $name );

		if ( $result ) {
			$this->assertSame( $result, $status->getMessage()->getKey() );
		} else {
			$this->assertTrue( $status->isGood() );
		}
	}

	public static function provideValidateGroupName() {
		return [
			'Valid' => [ 'valid-name', false ],
			'Contains uppercase' => [ 'UpperCase', 'centralauth-editgroup-invalid-name-lowercase' ],
		];
	}
}
