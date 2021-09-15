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

use CentralAuthUser;
use LogEventsList;
use MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook;
use NamespaceInfo;
use SpecialPage;
use User;

class SpecialContributionsHookHandler implements
	SpecialContributionsBeforeMainOutputHook
{
	/** @var NamespaceInfo */
	private $namespaceInfo;

	/**
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct( NamespaceInfo $namespaceInfo ) {
		$this->namespaceInfo = $namespaceInfo;
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

		$centralUser = CentralAuthUser::getInstance( $user );
		if ( !$centralUser->exists() || !$centralUser->isAttached()
			|| !$centralUser->isLocked() || $centralUser->isHidden()
		) {
			return true;
		}

		$out = $sp->getOutput();
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

		if ( $count === 0 ) { // we couldn't load the log entry
			$out->wrapWikiMsg( '<div class="warningbox mw-warning-with-logexcerpt">$1</div>',
				[ 'centralauth-contribs-locked', $user ]
			);
		}
	}
}
