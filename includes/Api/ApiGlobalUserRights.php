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

namespace MediaWiki\Extension\CentralAuth\Api;

use ApiMain;
use ApiUserrights;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership;

/**
 * @ingroup API
 */
class ApiGlobalUserRights extends ApiUserrights {
	/** @var GlobalGroupLookup */
	private $globalGroupLookup;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param GlobalGroupLookup $globalGroupLookup
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->globalGroupLookup = $globalGroupLookup;
	}

	protected function getUserRightsPage() {
		return new SpecialGlobalGroupMembership( $this->globalGroupLookup );
	}

	protected function getAllGroups() {
		return $this->globalGroupLookup->getDefinedGroups();
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=userrights&user=FooBot&add=bot&remove=sysop|bureaucrat&token=123ABC'
				=> 'apihelp-globaluserrights-example-1',
			'action=userrights&userid=123&add=bot&remove=sysop|bureaucrat&token=123ABC'
				=> 'apihelp-globaluserrights-example-2',
		];
	}
}
