<?php
/**
 * Resource loader module for global user customizations.
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
 * @author Szymon Świerkosz
 * @author Kunal Mehta
 */

/**
 * Module for user customizations - runs on all wikis
 * This module does not do provide any resources directly.
 * It instructs Resource Loader to load a module from a remote site.
 */
class ResourceLoaderGlobalUserModule extends ResourceLoaderWikiModule {

	/**
	 * name of global wiki database
	 * @var string
	 */
	protected $wiki;
	/**
	 * name of a ResourceLoader source pointing to the global wiki
	 * @var string
	 */
	protected $source;

	protected $origin = self::ORIGIN_USER_INDIVIDUAL;

	public function __construct( $options ) {
		$this->wiki = $options['wiki'];
		$this->source = $options['source'];
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		$username = $context->getUser();

		if ( $username === null ) {
			return array();
		}

		// Get the normalized title of the user's user page
		// Note this validates against the local site rather than
		// the target site.
		$userpageTitle = Title::makeTitleSafe( NS_USER, $username );

		if ( !$userpageTitle instanceof Title ) {
			return array();
		}

		$userpage = $userpageTitle->getDBkey();

		return array(
			"User:$userpage/global.js" => array( 'type' => 'script' ),
			"User:$userpage/global.css" => array( 'type' => 'style' ),
		);
	}

	/**
	 * @return string
	 */
	public function getSource() {
		return wfWikiID() === $this->wiki ? 'local' : $this->source;
	}

	/**
	 * @return DatabaseBase
	 */
	protected function getDB() {
		if ( $this->wiki === wfWikiID() ) {
			return wfGetDB( DB_SLAVE );
		} else {
			return wfGetDB( DB_SLAVE, array(), $this->wiki );
		}
	}

	/**
	 * @return string
	 */
	public function getGroup() {
		return 'user';
	}
}
