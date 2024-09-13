<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\BlockHookHandler
 */
class BlockHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testGetBlock() {
		$u = new CentralAuthTestUser(
			'GloballySuppressedUser',
			'GLUP@ssword',
			[
				'gu_id' => '1004',
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
			],
			[
				[ WikiMap::getCurrentWikiId(), 'primary' ],
			]
		);
		$u->save( $this->getDb() );

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newFromName( 'GloballySuppressedUser' );
		$this->assertTrue( $user->getBlock()->getHideName() );
	}

	public function testGetBlock_noLocalAccount() {
		// This user doesn't exist locally, but we still surface the block.
		$u = new CentralAuthTestUser(
			'GloballySuppressedUser',
			'GLUP@ssword',
			[
				'gu_id' => '1004',
				'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
			],
			[],
			false
		);
		$u->save( $this->getDb() );

		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newFromName( 'GloballySuppressedUser' );
		$this->assertTrue( $user->getBlock()->getHideName() );
	}

	public function testGetBlock_ipRange() {
		$userFactory = $this->getServiceContainer()->getUserFactory();
		$user = $userFactory->newAnonymous();
		// T358112: IP ranges (invalid usernames) should not cause an exception.
		$user->setName( '127.0.0.1/24' );
		$this->assertNull( $user->getBlock() );
	}
}
