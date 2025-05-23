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

use MediaWiki\Hook\LogEventsListGetExtraInputsHook;
use MediaWiki\Hook\SpecialLogAddLogSearchRelationsHook;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class LogHookHandler implements
	LogEventsListGetExtraInputsHook,
	SpecialLogAddLogSearchRelationsHook
{

	private IConnectionProvider $dbProvider;
	private UserNameUtils $userNameUtils;

	public function __construct(
		IConnectionProvider $dbProvider,
		UserNameUtils $userNameUtils
	) {
		$this->dbProvider = $dbProvider;
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * @param string $type
	 * @param WebRequest $request
	 * @param string[] &$qc
	 * @return bool|void
	 */
	public function onSpecialLogAddLogSearchRelations( $type, $request, &$qc ) {
		if ( $type === 'gblrename' ) {
			$oldname = trim( $request->getText( 'oldname' ) );
			$canonicalOldname = $this->userNameUtils->getCanonical( $oldname );
			if ( $oldname !== '' ) {
				$qc = [ 'ls_field' => 'oldname', 'ls_value' => $canonicalOldname ];

				$hiddenBits = 0;
				$user = $request->getSession()->getUser();
				if ( !$user->isAllowed( 'deletedhistory' ) ) {
					$hiddenBits = LogPage::DELETED_ACTION;
				} elseif ( !$user->isAllowedAny( 'suppressrevision', 'viewsuppressed' ) ) {
					$hiddenBits = LogPage::DELETED_ACTION | LogPage::DELETED_RESTRICTED;
				}

				if ( $hiddenBits ) {
					$bitfield = $this->dbProvider->getReplicaDatabase()
						->bitAnd( 'log_deleted', $hiddenBits );
					$qc[] = "$bitfield != $hiddenBits";
				}
			}
		}
	}

	/**
	 * @param string $type
	 * @param LogEventsList $list
	 * @param string &$input HTML
	 * @param array &$formDescriptor Form descriptor
	 */
	public function onLogEventsListGetExtraInputs(
		$type, $list, &$input, &$formDescriptor
	) {
		if ( $type === 'gblrename' ) {
			$value = $list->getRequest()->getVal( 'oldname' );
			if ( $value !== null ) {
				$name = $this->userNameUtils->getCanonical( $value );
				$value = $name === false ? '' : $name;
			}
			$formDescriptor = [
				'type' => 'text',
				'label-message' => 'centralauth-log-gblrename-oldname',
				'name' => 'oldname',
				'id' => 'mw-log-gblrename-oldname',
				'default' => $value,
			];
		}
	}
}
