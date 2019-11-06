<?php

/**
 * @group API
 * @group Database
 * @group medium
 */
class ApiQueryGlobalUserinfoTest extends ApiTestCase {
	private $user = null;

	protected function setUp() : void {
		parent::setUp();
		$this->user = $this->getTestUser()->getUser();
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
		$res = $this->doApiRequest(
			$this->getParams( [
				'guiuser' => $this->user->getName()
			] )
		);
		var_dump( $res );
		$centralUser = CentralAuthUser::getInstance( $this->user );
		$data = $res['query']['globaluserinfo'][$this->user->getName()];
		$this->assertSame( $centralUser->getHomeWiki(), $data['home'] );
		$this->assertSame( $centralUser->getId(), $data['id'] );
		$this->assertSame( $centralUser->getRegistration(), $data['registration'] );
		$this->assertSame( $centralUser->getGlobalEditCount(), $data['editcount'] );
	}
}
