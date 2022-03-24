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

use User;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "CentralAuthIsUIReloadRecommended" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @author Taavi "Majavah" Väänänen
 */
interface CentralAuthIsUIReloadRecommendedHook {
	/**
	 * This hook is called after a cross-wiki auto login to figure out
	 * if the users preferences are customized enough that they should
	 * refresh the page for the preferences to take effect.
	 *
	 * @param User $user The user logging in.
	 * @param bool &$recommendReload Set to true if the user should be recommended
	 * to reload the page.
	 *
	 * @return bool|void True or no return value to continue or false to abort
	 * @since 1.39
	 */
	public function onCentralAuthIsUIReloadRecommended( User $user, bool &$recommendReload );
}
