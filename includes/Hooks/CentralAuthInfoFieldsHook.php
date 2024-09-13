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

namespace MediaWiki\Extension\CentralAuth\Hooks;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Message\Message;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthInfoFieldsHook" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface CentralAuthInfoFieldsHook {

	/**
	 * Use this hook to modify the information displayed in the 'Global account information' fieldset on
	 * Special:CentralAuth.
	 *
	 * @param CentralAuthUser $centralAuthUser The user being viewed on Special:CentralAuth.
	 * @param IContextSource $context The context used for the special page, intended to generate messages
	 *   and get the relevant {@link Authority} object.
	 * @param array &$attribs The fields for the 'Global account information' fieldset. The keys are field names, but
	 *   they are not used in the UI or for generating messages. The values are arrays with the following keys:
	 *  - 'label': The label for the information which is a Message object or string message key
	 *  - 'data': The data point about the global account, which should be HTML escaped
	 * @phan-param array<string,array{label:string|Message,data:string}> &$attribs
	 *
	 * @return bool|void True or no return value to continue or false to abort
	 * @since 1.43
	 */
	public function onCentralAuthInfoFields(
		CentralAuthUser $centralAuthUser,
		IContextSource $context,
		array &$attribs
	);
}
