<?php

/**
 * @group CentralAuth
= */
class GlobalRenameUserValidatorTest extends MediaWikiTestCase {

	private function assertHasMessage( Status $s, $msg ) {
		$this->assertTrue( $s->hasMessage( $msg ) );
	}

	private function getMockCAUser() {
		return $this->getMockBuilder( 'CentralAuthUser' )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @covers GlobalRenameUserValidator
	 */
	public function testValidateNonCreatableName() {
		$this->setMwGlobals( 'wgInvalidUsernameCharacters', '@' );
		$v = new GlobalRenameUserValidator();
		$oldUser = User::newFromName( 'FooBaz' );
		$oldUser->centralAuthObj = $this->getMockCAUser();
		$newUser = User::newFromName( 'Baz@Foo' );
		$newUser->centralAuthObj = $this->getMockCAUser();
		$s = $v->validate( $oldUser, $newUser );
		$this->assertInstanceOf( 'Status', $s );
		$this->assertFalse( $s->isGood() );
		$this->assertHasMessage( $s, 'centralauth-rename-badusername' );
	}

	/**
	 * @covers GlobalRenameUserValidator
	 */
	public function testValidateOldNameDoesntExist() {
		$ca = $this->getMockCAUser();
		$ca->expects( $this->any() )->method( 'exists' )->will( $this->returnValue( false ) );

		$u = User::newFromName( 'FooBaz' );
		$u->centralAuthObj = $ca;

		$v = new GlobalRenameUserValidator();
		$s = $v->validate( $u, $u );
		$this->assertInstanceOf( 'Status', $s );
		$this->assertFalse( $s->isGood() );
		$this->assertHasMessage( $s, 'centralauth-rename-doesnotexist' );
	}

	/**
	 * @covers GlobalRenameUserValidator
	 */
	public function testValidateNewNameExists() {
		$ca = $this->getMockCAUser();
		$ca->expects( $this->any() )->method( 'exists' )->will( $this->returnValue( true ) );

		$u = User::newFromName( 'FooBaz' );
		$u->centralAuthObj = $ca;

		$v = new GlobalRenameUserValidator();
		$s = $v->validate( $u, $u );
		$this->assertInstanceOf( 'Status', $s );
		$this->assertFalse( $s->isGood() );
		$this->assertHasMessage( $s, 'centralauth-rename-alreadyexists' );
	}

	/**
	 * @covers GlobalRenameUserValidator
	 */
	public function testValidateHasUnattached() {
		$ca = $this->getMockCAUser();
		$ca->expects( $this->any() )->method( 'listUnattached' )->will( $this->returnValue( array(
			'foowiki' => array( 'attachedMethod' => 'new' ),
		) ) );

		$u = User::newFromName( 'FooBaz' );
		$u->centralAuthObj = $ca;

		$v = new GlobalRenameUserValidator();
		$s = $v->validate( $u, $u );
		$this->assertInstanceOf( 'Status', $s );
		$this->assertFalse( $s->isGood() );
		$this->assertHasMessage( $s, 'centralauth-rename-unattached-intheway' );
	}

	/**
	 * @covers GlobalRenameUserValidator
	 */
	public function testValidateHasRenameInProgress() {
		$ca = $this->getMockCAUser();
		$ca->expects( $this->any() )->method( 'renameInProgress' )->will(
			$this->returnValue( array( 'FooBaz', 'NewBaz' ) )
		);

		$u = User::newFromName( 'FooBaz' );
		$u->centralAuthObj = $ca;

		$v = new GlobalRenameUserValidator();
		$s = $v->validate( $u, $u );
		$this->assertInstanceOf( 'Status', $s );
		$this->assertFalse( $s->isGood() );
		$this->assertHasMessage( $s, 'centralauth-rename-alreadyinprogress' );
	}

	/**
	 * @covers GlobalRenameUserValidator
	 */
	public function testValidateGoodName() {
		$caNew = $this->getMockCAUser();
		$caOld = $this->getMockCAUser();
		$caOld->expects( $this->any() )->method( 'exists' )->will( $this->returnValue( true ) );
		$uOld = User::newFromName( 'OldName' );
		$uOld->centralAuthObj = $caOld;
		$uNew = User::newFromName( 'NewName' );
		$uNew->centralAuthObj = $caNew;

		$v = new GlobalRenameUserValidator();
		$s = $v->validate( $uOld, $uNew );
		$this->assertInstanceOf( 'Status', $s );
		$this->assertTrue( $s->isGood() );
	}
}