<?php
/**
 * Created on Jan 30, 2010
 *
 * CentralAuth extension
 *
 * Copyright (C) 2010 Roan Kattouw roan DOT kattouw AT gmail DOT com
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
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use ApiBase;
use ApiQueryBase;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Query module to list global user info and attachments
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryGlobalUserInfo extends ApiQueryBase {

	/** @var UserNameUtils */
	private $userNameUtils;

	public function __construct( $query, $moduleName, UserNameUtils $userNameUtils ) {
		parent::__construct( $query, $moduleName, 'gui' );
		$this->userNameUtils = $userNameUtils;
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$prop = array_flip( (array)$params['prop'] );
		if ( $params['user'] === null && $params['id'] === null ) {
			$params['user'] = $this->getUser()->getName();
		}

		if ( $params['user'] === null ) {
			$user = CentralAuthUser::newFromId( $params['id'] );
			if ( $user === false ) {
				$this->dieWithError( [ 'apierror-invaliduserid', wfEscapeWikiText( $params['id'] ) ] );
			}
		} else {
			$username = $this->userNameUtils->getCanonical( $params['user'] );
			if ( $username === false ) {
				$this->dieWithError( [ 'apierror-invaliduser', wfEscapeWikiText( $params['user'] ) ] );
			}
			$user = CentralAuthUser::getInstanceByName( $username );
		}

		// Add basic info
		$result = $this->getResult();
		$data = [];
		$userExists = $user->exists();

		if ( $userExists &&
			( $user->getHiddenLevelInt() === CentralAuthUser::HIDDEN_LEVEL_NONE ||
			$this->getAuthority()->isAllowed( 'centralauth-suppress' ) )
		) {
			// The global user exists and it's not hidden or the current user is allowed to see it
			$data['home'] = $user->getHomeWiki();
			$data['id'] = $user->getId();
			$data['registration'] = wfTimestamp( TS_ISO_8601, $user->getRegistration() );
			$data['name'] = $user->getName();

			if ( $user->isLocked() ) {
				$data['locked'] = true;
			}
			if ( $user->isHidden() ) {
				$data['hidden'] = true;
			}
		} else {
			// The user doesn't exist or we pretend it doesn't if it's hidden
			$data['missing'] = true;

			// If we are pretending that the user doesn't exist because it is hidden,
			// do not add any more information
			$userExists = false;
		}
		$result->addValue( 'query', $this->getModuleName(), $data );

		// Add requested info
		if ( $userExists && isset( $prop['groups'] ) ) {
			$groups = $user->getGlobalGroups();
			$result->setIndexedTagName( $groups, 'g' );
			$result->addValue( [ 'query', $this->getModuleName() ], 'groups', $groups );
		}
		if ( $userExists && isset( $prop['rights'] ) ) {
			$rights = $user->getGlobalRights();
			$result->setIndexedTagName( $rights, 'r' );
			$result->addValue( [ 'query', $this->getModuleName() ], 'rights', $rights );
		}

		$attachedAccounts = null;
		if ( $userExists && ( isset( $prop['merged'] ) || isset( $prop['editcount'] ) ) ) {
			$attachedAccounts = $user->queryAttached();
		}

		if ( $userExists && isset( $prop['merged'] ) ) {
			foreach ( $attachedAccounts as $account ) {
				$dbname = $account['wiki'];
				$wiki = WikiMap::getWiki( $dbname );
				$a = [
					'wiki' => $dbname,
					'url' => $wiki->getCanonicalServer(),
					'id' => intval( $account['id'] ),
					'timestamp' => wfTimestamp( TS_ISO_8601, $account['attachedTimestamp'] ),
					'method' => $account['attachedMethod'],
					'editcount' => intval( $account['editCount'] ),
					'registration' => wfTimestamp( TS_ISO_8601, $account['registration'] ),
				];
				if ( $account['groupMemberships'] ) {
					$a['groups'] = array_keys( $account['groupMemberships'] );
					$result->setIndexedTagName( $a['groups'], 'group' );
				}

				if ( $account['blocked'] ) {
					$a['blocked'] = [
						'expiry' => $this->getLanguage()->formatExpiry(
							$account['block-expiry'], TS_ISO_8601 ),
						'reason' => $account['block-reason']
					];
				}
				$result->addValue( [ 'query', $this->getModuleName(), 'merged' ], null, $a );
			}
			$result->addIndexedTagName( [ 'query', $this->getModuleName(), 'merged' ], 'account' );
		}
		if ( $userExists && isset( $prop['editcount'] ) ) {
			$editcount = 0;
			foreach ( $attachedAccounts as $account ) {
				$editcount += $account['editCount'];
			}
			$result->addValue( 'query', $this->getModuleName(), [ 'editcount' => $editcount ] );
		}
		if ( isset( $prop['unattached'] ) ) {
			$accounts = $user->queryUnattached();
			foreach ( $accounts as $account ) {
				$a = [
					'wiki' => $account['wiki'],
					'editcount' => $account['editCount'],
					'registration' => wfTimestamp( TS_ISO_8601, $account['registration'] ),
				];

				if ( $account['groupMemberships'] ) {
					$a['groups'] = array_keys( $account['groupMemberships'] );
					$result->setIndexedTagName( $a['groups'], 'group' );
				}

				if ( $account['blocked'] ) {
					$a['blocked'] = [
						'expiry' => $this->getLanguage()->formatExpiry(
							$account['block-expiry'], TS_ISO_8601 ),
						'reason' => $account['block-reason']
					];
				}
				$result->addValue( [ 'query', $this->getModuleName(), 'unattached' ], null, $a );
			}
			$result->addIndexedTagName(
				[ 'query', $this->getModuleName(), 'unattached' ], 'account'
			);
		}
	}

	public function getCacheMode( $params ) {
		if ( $params['user'] !== null || $params['id'] !== null ) {
			// URL determines user, public caching is fine
			return 'public';
		} else {
			// Code will fall back to the context user, don't cache
			return 'private';
		}
	}

	public function getAllowedParams() {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [
					'name',
					'interwiki',
				],
			],
			'id' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'prop' => [
				ParamValidator::PARAM_TYPE => [
					'groups',
					'rights',
					'merged',
					'unattached',
					'editcount'
				],
				ParamValidator::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			]
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&meta=globaluserinfo'
				=> 'apihelp-query+globaluserinfo-example-1',
			'action=query&meta=globaluserinfo&guiuser=Example&guiprop=groups|merged|unattached'
				=> 'apihelp-query+globaluserinfo-example-2',
		];
	}
}
