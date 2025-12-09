<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\BlockTargetFactory;
use MediaWiki\Block\CompositeBlock;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\Hook\GetUserBlockHook;
use MediaWiki\Block\SystemBlock;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\BlockIpCompleteHook;
use MediaWiki\Hook\OtherBlockLogLinkHook;
use MediaWiki\Hook\UnblockUserCompleteHook;
use MediaWiki\Html\Html;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\IPUtils;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class BlockHookHandler implements
	GetUserBlockHook,
	OtherBlockLogLinkHook,
	BlockIpCompleteHook,
	UnblockUserCompleteHook
{

	public function __construct(
		private BlockTargetFactory $blockTargetFactory,
		private WANObjectCache $wanCache,
		private StatsFactory $statsFactory,
		private UserNameUtils $userNameUtils
	) {
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
				'target' => $this->blockTargetFactory->newFromUser( $user ),
				'hideName' => true,
				'systemBlock' => 'hideuser',
			] );

			if ( $block === null ) {
				$block = $hideUserBlock;
				return false;
			}

			$blocks = $block->toArray();

			$blocks[] = $hideUserBlock;
			$block = CompositeBlock::createFromBlocks( ...$blocks );

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

	/**
	 * Expire user's blocks cache; next instance of CentralAuthUser::getBlocks will reinstantiate
	 *
	 * @inheritDoc
	 */
	public function onBlockIpComplete( $block, $user, $priorBlock ) {
		$this->invalidateCentralAuthUserGetBlocksCache( $block );
	}

	/**
	 * Expire user's blocks cache; next instance of CentralAuthUser::getBlocks will reinstantiate
	 *
	 * @inheritDoc
	 */
	public function onUnblockUserComplete( $block, $user ) {
		$this->invalidateCentralAuthUserGetBlocksCache( $block );
	}

	/**
	 * Given a block passed from a block changing hook (onBlockIpComplete, onUnblockUserComplete),
	 * Check against it to see if the associated user could have a cached CentralAuth::getBlocks()
	 * and if so, invalidate it as something about their blocked status has changed.
	 *
	 * @param DatabaseBlock $block
	 */
	private function invalidateCentralAuthUserGetBlocksCache( $block ): void {
		// Return early if the block target doesn't yield a central id because
		// there won't be a cache object associated with the target either
		$userIdentity = $block->getTargetUserIdentity();
		if ( !( $userIdentity instanceof UserIdentity ) ) {
			return;
		}
		$centralUserId = CentralAuthUser::getInstance( $userIdentity )->getId();
		if ( !$centralUserId ) {
			return;
		}

		$cacheKey = $this->wanCache->makeGlobalKey(
			'centralauthuser-getblocks',
			$centralUserId
		);

		// Track how often cache invalidation happens
		$curTTL = null;
		$cachedValue = $this->wanCache->get( $cacheKey, $curTTL );
		if ( $curTTL && $curTTL > 0 ) {
			$this->statsFactory->withComponent( 'CentralAuth' )
				->getCounter( 'centralauthuser_getblocks_cache' )
				->setLabel( 'interaction', 'invalidate' )
				->increment();
		}

		// Invalidate because something about this user's blocks changed
		$this->wanCache->delete( $cacheKey );
	}
}
