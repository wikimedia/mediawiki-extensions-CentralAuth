<?php

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupPermissions;
use MediaWiki\Permissions\PermissionManager;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupPermissions
 */
class SpecialGlobalGroupPermissionsTest extends MediaWikiUnitTestCase {

	/** @var SpecialGlobalGroupPermissions|TestingAccessWrapper */
	private $special;

	protected function setUp(): void {
		parent::setUp();

		$this->special = TestingAccessWrapper::newFromObject(
			new SpecialGlobalGroupPermissions(
				$this->createNoOpMock( PermissionManager::class ),
				$this->createNoOpMock( CentralAuthDatabaseManager::class ),
				$this->createNoOpMock( GlobalGroupLookup::class )
			)
		);
	}

	/**
	 * @dataProvider provideValidateGroupName
	 */
	public function testValidateGroupName( $name, $result ) {
		$status = $this->special->validateGroupName( $name );

		if ( $result ) {
			$this->assertStatusError( $result, $status );
		} else {
			$this->assertStatusGood( $status );
		}
	}

	public static function provideValidateGroupName() {
		return [
			'Valid' => [ 'valid-name', false ],
			'Contains uppercase' => [ 'UpperCase', 'centralauth-editgroup-invalid-name-lowercase' ],
		];
	}
}
