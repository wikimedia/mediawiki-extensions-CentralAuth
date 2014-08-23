<?php

class SpecialGlobalUserMergeTest extends CentralAuthTestCaseUsingDatabase {

	public function setUp() {
		parent::setUp();
		$user = new CentralAuthTestUser(
			'GlobalUserBeingRenamed',
			'GUP@ssword',
			array( 'gu_id' => '9001' ),
			array(
				array( wfWikiID(), 'primary' ),
				array( 'enwiki', 'primary' ),
				array( 'dewiki', 'login' ),
				array( 'metawiki', 'password' ),
			)
		);
		$user->setRenameInProgress( 'GlobalUserBeingRenamed', 'ATotallyBetterName', 'enwiki' );
		$user->save( $this->db );
		$user2 = new CentralAuthTestUser(
			'GlobalUserNotBeingRenamed',
			'GUP@ssword',
			array( 'gu_id' => '9002' ),
			array(
				array( wfWikiID(), 'primary' ),
				array( 'enwiki', 'primary' ),
				array( 'dewiki', 'login' ),
				array( 'metawiki', 'password' ),
			)
		);
		$user2->save( $this->db );
	}

	/**
	 * @covers SpecialGlobalUserMerge::validateUsername
	 * @dataProvider provideValidateUsername
	 */
	public function testValidateUsername( $name, $error ) {
		$sp = new SpecialGlobalUserMerge;
		$status = $sp->validateUsername( $name );
		if ( is_string( $error ) ) {
			$errors = $status->getErrorsArray();
			$this->assertEquals( $error, $errors[0][0] );
		} else {
			$this->assertTrue( $status->isOK() );
		}
	}

	public static function provideValidateUsername() {
		return array(
			array( 'A totally invalid username!!![][]||', 'centralauth-usermerge-invalid' ),
			array( 'There is no global account for this', 'centralauth-usermerge-invalid' ),
			array( 'GlobalUserBeingRenamed', 'centralauth-usermerge-already' ),
			array( 'GlobalUserNotBeingRenamed', true ),
		);
	}

	/**
	 * @covers SpecialGlobalUserMerge::onSubmit
	 */
	public function testOnSubmit() {
		$sp = new SpecialGlobalUserMerge;
		$status = $sp->onSubmit( array( 'finaluser' => 'ANotCreatableName|||[][]' ) );
		$this->assertInstanceOf( 'Status', $status );
		$this->assertFalse( $status->isOK() );
		$errors = $status->getErrorsArray();
		$this->assertEquals( 'centralauth-usermerge-invalidname', $errors[0][0] );
	}
}