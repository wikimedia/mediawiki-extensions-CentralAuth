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

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\CompositeBlock;
use MediaWiki\Block\Hook\GetUserBlockHook;
use MediaWiki\Block\SystemBlock;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\OtherBlockLogLinkHook;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\IPUtils;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class BlockHookHandler implements
	GetUserBlockHook,
	OtherBlockLogLinkHook
{
	private UserNameUtils $userNameUtils;

	public function __construct( UserNameUtils $userNameUtils ) {
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * Make sure a user is hidden if their global account is hidden.
	 * If a user's global account is hidden (suppressed):
	 * - if locally blocked and hidden, do nothing
	 * - if not blocked, add a system block with a suppression
	 * - if blocked but not hidden, make a new composite block
	 *   containing the existing blocks plus a system block with a
	 *   suppression
	 *
	 * @param User $user
	 * @param string|null $ip
	 * @param AbstractBlock|null &$block
	 * @return bool
	 */
	public function onGetUserBlock( $user, $ip, &$block ) {
		if ( $block && $block->getHideName() ) {
			return false;
		}
		if ( !$this->userNameUtils->isValid( $user->getName() ) ) {
			// Only valid usernames can be handled (and hidden) by CentralAuth.
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( $centralUser->exists()
			&& ( $centralUser->isAttached() || !$user->isRegistered() )
			&& $centralUser->getHiddenLevelInt() === CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED
		) {
			$hideUserBlock = new SystemBlock( [
				'address' => $user,
				'hideName' => true,
				'systemBlock' => 'hideuser',
			] );

			if ( $block === null ) {
				$block = $hideUserBlock;
				return false;
			}

			$blocks = $block->toArray();

			$blocks[] = $hideUserBlock;
			$block = new CompositeBlock( [
				'address' => $ip,
				'reason' => new Message( 'blockedtext-composite-reason' ),
				'originalBlocks' => $blocks,
			] );

			return false;
		}

		return true;
	}

	/**
	 * Creates a link to the global lock log
	 * @param array &$otherBlockLink Message with a link to the global block log
	 * @param string $user The username to be checked
	 * @return bool true
	 */
	public function onOtherBlockLogLink( &$otherBlockLink, $user ) {
		if (
			IPUtils::isIPAddress( $user )
			|| !$this->userNameUtils->isValid( $user )
		) {
			// Only usernames can be locked.
			return true;
		}

		$caUser = CentralAuthUser::getInstanceByName( $user );
		if ( $caUser->isLocked() && in_array( WikiMap::getCurrentWikiId(), $caUser->listAttached() ) ) {
			$otherBlockLink[] = Html::rawElement(
				'span',
				[ 'class' => 'mw-centralauth-lock-loglink plainlinks' ],
				wfMessage( 'centralauth-block-already-locked', $user )->parse()
			);
		}
		return true;
	}
}
