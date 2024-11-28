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

use LogEventsList;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\ContributionsToolLinksHook;
use MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;

class SpecialContributionsHookHandler implements
	ContributionsToolLinksHook,
	SpecialContributionsBeforeMainOutputHook
{

	private NamespaceInfo $namespaceInfo;
	private UserFactory $userFactory;
	private UserNameUtils $userNameUtils;

	public function __construct(
		NamespaceInfo $namespaceInfo,
		UserFactory $userFactory,
		UserNameUtils $userNameUtils
	) {
		$this->namespaceInfo = $namespaceInfo;
		$this->userFactory = $userFactory;
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * @param int $id User ID
	 * @param Title $title User page title
	 * @param array &$tools Array of tool links
	 * @param SpecialPage $sp for context
	 * @return bool|void
	 */
	public function onContributionsToolLinks( $id, Title $title, array &$tools, SpecialPage $sp ) {
		$user = $this->userFactory->newFromId( $id );
		if ( !$user->isRegistered() ) {
			return true;
		}
		if ( $this->userNameUtils->getCanonical( $user->getName() ) === false ) {
			return true;
		}
		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached() ) {
			return true;
		}
		$linkRenderer = $sp->getLinkRenderer();
		$tools['centralauth'] = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'CentralAuth', $title->getText() ),
			$sp->msg( 'centralauth-contribs-link' )->text(),
			[ 'class' => 'mw-contributions-link-centralauth' ]
		);
	}

	/**
	 * @param int $id User ID
	 * @param User $user
	 * @param SpecialPage $sp
	 * @return bool|void
	 */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ) {
		if ( !$user->isRegistered() ) {
			return true;
		}
		if ( $this->userNameUtils->getCanonical( $user->getName() ) === false ) {
			return true;
		}

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached()
			|| !$centralUser->isLocked() || $centralUser->isHidden()
		) {
			return true;
		}

		$out = $sp->getOutput();
		$out->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
		$count = LogEventsList::showLogExtract(
			$out,
			[ 'globalauth' ],
			$this->namespaceInfo->getCanonicalName( NS_USER ) . ":{$user}@global",
			'',
			[
				'lim' => 1,
				'showIfEmpty' => false,
				'msgKey' => [
					'centralauth-contribs-locked-log',
					$user->getName()
				],
				'offset' => '',
			]
		);

		if ( $count === 0 ) {
			// we couldn't load the log entry
			$out->addHTML(
				Html::warningBox(
					$out->msg( 'centralauth-contribs-locked', $user )->parse(),
					'mw-warning-with-logexcerpt'
				)
			);
		}
	}
}
