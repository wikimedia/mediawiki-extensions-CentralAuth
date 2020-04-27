<?php

/**
 * @group API
 * @group Database
 * @group medium
 */
class ApiQueryGlobalUserinfoTest extends CentralAuthUsingDatabaseApiTestCase {
	private $usernames = [];

	protected function setUp() : void {
		parent::setUp();
		for ( $i = 0; $i < 5; $i++ ) {
			$username = 'GlobalUser ' . $i;
			$user = new CentralAuthTestUser(
				$username,
				'GUP@ssword',
				[ 'gu_id' => '100' . ( $i + 1 ) ],
				[
					[ wfWikiID(), 'primary' ],
					[ 'enwiki', 'primary' ],
					[ 'dewiki', 'login' ],
					[ 'metawiki', 'password' ],
				]
			);
			$user->save( $this->db );
			$this->usernames[] = $username;
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
		$centralUser = CentralAuthUser::getInstanceByName( $this->usernames[0] );
		$res = $this->doApiRequest(
			$this->getParams( [
				'guiuser' => $centralUser->getName()
			] )
		);
		$data = $res[0]['query']['globaluserinfo'];
		$this->assertSame( $centralUser->getHomeWiki(), $data['home'] );
		$this->assertSame( $centralUser->getId(), $data['id'] );
		$this->assertSame(
			wfTimestamp( TS_ISO_8601, $centralUser->getRegistration() ),
			$data['registration']
		);
		$this->assertSame( $centralUser->getGlobalEditCount(), $data['editcount'] );
	}
}
