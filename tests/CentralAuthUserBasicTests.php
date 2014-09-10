<?php
/**
 * Very basic tests for CentralAuthUser
 * @group CentralAuth
 */
class CentralAuthUserTest extends MediaWikiTestCase {

	/**
	 * @var CentralAuthUser
	 */
	protected $caUser;

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgCentralAuthLockedCanEdit' => array(),
		) );

		$row = new stdClass();
		$row->gu_name = 'GlobalUser';
		$row->gu_id = '1001';
		$row->lu_wiki = 'enwiki';
		$row->gu_salt = '';
		$row->gu_password = '1234567890';
		$row->gu_auth_token = '1234';
		$row->gu_locked = '0';
		$row->gu_hidden = '';
		$row->gu_registration = '20130627183537';
		$row->gu_email = 'test@localhost';
		$row->gu_email_authenticated = '20130801040214';
		$row->gu_home_db = 'enwiki';

		$this->caUser = CentralAuthUser::newFromRow( $row, false );
		$this->caUser->mGroups = array(
			'abusefilter',
			'global-ipblock-exempt',
			'Global_sysops'
		);
	}

	protected function tearDown() {
		parent::tearDown();
		unset( $this->caUser );
	}


	public function testGetId() {
		$this->assertEquals( $this->caUser->getId(), 1001 );
	}

	public function testGetHomeWiki() {
		$this->assertEquals( $this->caUser->getHomeWiki(), 'enwiki' );
	}

	public function testIsAttached() {
		$this->assertEquals( $this->caUser->isAttached(), true );
	}

	public function testIsLocked() {
		$this->assertEquals( $this->caUser->isLocked(), false );
		$this->caUser->mLocked = "1";
		$this->assertEquals( $this->caUser->isLocked(), true );
	}

	public function testGetHiddenLevel() {
		$this->assertEquals(
			$this->caUser->getHiddenLevel(),
			CentralAuthUser::HIDDEN_NONE
		);
	}

	public function testExists() {

		$this->assertEquals( $this->caUser->exists(), true );

		$caUserUnattached = CentralAuthUser::newUnattached(
			'UnattachedUser',
			false
		);
		$this->assertEquals( $caUserUnattached->exists(), false );
	}

	public function testGetGlobalGroups() {
		$this->assertArrayEquals(
			$this->caUser->getGlobalGroups(),
			array( 'abusefilter',
				'global-ipblock-exempt',
				'Global_sysops'
			)
		);
	}

	public function testGetStateHash() {
		$this->assertEquals(
			$this->caUser->getStateHash(),
			'2234d7949459185926a50073d174b673'
		);
	}

	public function testGetAuthToken() {
		$caUserUnattached = CentralAuthUser::newUnattached(
			'UnattachedUser',
			false
		);
		$token = $caUserUnattached->getAuthToken();
		$this->assertEquals(
			strlen( $token ),
			32
		);
		$this->assertEquals(
			preg_match( '/[^a-f0-9]/', $token ),
			0
		);
	}

}
