<?php

/**
 * @group API
 * @group Database
 * @group medium
 */
class ApiQueryGlobalUserinfoTest extends CentralAuthUsingDatabaseApiTestCase {
	private $users = [];

	protected function setUp() : void {
		parent::setUp();
		for ( $i = 0; $i < 5; $i++ ) {
			$username = 'GlobalUser ' . $i;
			$user = new CentralAuthTestUser(
				$username,
				'GUP@ssword',
				[ 'gu_id' => '1001' ],
				[
					[ wfWikiID(), 'primary' ],
					[ 'enwiki', 'primary' ],
					[ 'dewiki', 'login' ],
					[ 'metawiki', 'password' ],
				]
			);
			$user->save( $this->db );
			$this->users[] = CentralAuthUser::getInstanceByName( $username );
		}
	}

	private function getParams( $additional = [] ) {
		$base = [
			'action' => 'query',
			'meta' => 'globaluserinfo',
			'guiprop' => 'editcount|groups|merged|rights|unattached'
		];
		return array_merge( $base, $additional );
	}

	/**
	 * @covers ApiQueryGlobalUserInfo::execute
	 */
	public function testExecuteOneUser() {
		$centralUser = $this->users[0];
		$res = $this->doApiRequest(
			$this->getParams( [
				'guiuser' => $centralUser->getName()
			] )
		);
		$data = $res[0]['query']['globaluserinfo'];
		$this->assertSame( $centralUser->getHomeWiki(), $data['home'] );
		$this->assertSame( $centralUser->getId(), $data['id'] );
		$this->assertSame( $centralUser->getRegistration(), $data['registration'] );
		$this->assertSame( $centralUser->getGlobalEditCount(), $data['editcount'] );
	}
}
