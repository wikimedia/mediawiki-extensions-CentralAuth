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

namespace MediaWiki\Extension\CentralAuth;

use ErrorPageError;

/**
 * Show an error when the CentralAuth database is locked/read-only
 * and the user tries to do something that requires CentralAuth
 * write access
 * @ingroup Exception
 */
class CentralAuthReadOnlyError extends ErrorPageError {

	/**
	 * @param string $reason
	 */
	public function __construct( $reason ) {
		parent::__construct(
			'centralauth-readonly',
			'centralauth-readonlytext',
			[ $reason ]
		);
	}
}
