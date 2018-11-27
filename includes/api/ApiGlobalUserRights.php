<?php
/**
 * Created on 1st October, 2014
 *
 * Copyright © 2014 Alex Monk <krenair@gmail.com>
 *
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

/**
 * @ingroup API
 */
class ApiGlobalUserRights extends ApiUserrights {
	protected function getUserRightsPage() {
		return new SpecialGlobalGroupMembership;
	}

	protected function getAllGroups() {
		return CentralAuthUser::availableGlobalGroups();
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		$a = [
			'action=globaluserrights&user=FooBot&add=bot&remove=sysop|bureaucrat&token=123ABC'
				=> 'apihelp-globaluserrights-example-1',
			'action=globaluserrights&userid=123&add=bot&remove=sysop|bureaucrat&token=123ABC'
				=> 'apihelp-globaluserrights-example-2',
		];
		if ( $this->getUserRightsPage()->canProcessExpiries() ) {
			$a['action=globaluserrights&user=SometimeSysop&add=sysop&expiry=1%20month&token=123ABC']
				= 'apihelp-globaluserrights-example-expiry';
		}
		return $a;
	}
}
