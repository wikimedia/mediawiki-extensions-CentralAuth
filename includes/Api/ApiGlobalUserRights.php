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

use ApiBase;
use ApiMain;
use ApiResult;
use ChangeTags;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use UserrightsPage;

/**
 * @ingroup API
 */
class ApiGlobalUserRights extends ApiBase {
	/** @var CentralAuthUser */
	private $mUser = null;

	/** @var GlobalGroupLookup */
	private $globalGroupLookup;

	/** @var UserGroupManagerFactory */
	private $userGroupManagerFactory;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var UserNamePrefixSearch */
	private $userNamePrefixSearch;

	/** @var UserFactory */
	private $userFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param GlobalGroupLookup $globalGroupLookup
	 * @param UserGroupManagerFactory $userGroupManagerFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		GlobalGroupLookup $globalGroupLookup,
		UserGroupManagerFactory $userGroupManagerFactory,
		UserNameUtils $userNameUtils,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserFactory $userFactory
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->userNameUtils = $userNameUtils;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userFactory = $userFactory;
		$this->userGroupManagerFactory = $userGroupManagerFactory;
		$this->globalGroupLookup = $globalGroupLookup;
	}

	protected function getUserRightsPage() {
		return new SpecialGlobalGroupMembership(
			$this->globalGroupLookup,
			$this->userGroupManagerFactory,
			$this->userNameUtils,
			$this->userNamePrefixSearch,
			$this->userFactory
		);
	}

	protected function getAllGroups() {
		return $this->globalGroupLookup->getDefinedGroups();
	}

	public function execute() {
		$pUser = $this->getUser();
		// Deny if the user is blocked and doesn't have the full 'userrights' permission.
		// This matches what Special:UserRights does for the web UI.
		if ( !$this->getAuthority()->isAllowed( 'userrights' ) ) {
			$block = $pUser->getBlock( Authority::READ_LATEST );
			if ( $block && $block->isSitewide() ) {
				$this->dieBlocked( $block );
			}
		}
		$params = $this->extractRequestParams();
		// Figure out expiry times from the input
		// $params['expiry'] is not set in CentralAuth's ApiGlobalUserRights subclass
		if ( isset( $params['expiry'] ) ) {
			$expiry = (array)$params['expiry'];
		} else {
			$expiry = [ 'infinity' ];
		}
		$add = (array)$params['add'];
		if ( !$add ) {
			$expiry = [];
		} elseif ( count( $expiry ) !== count( $add ) ) {
			if ( count( $expiry ) === 1 ) {
				$expiry = array_fill( 0, count( $add ), $expiry[0] );
			} else {
				$this->dieWithError( [
					'apierror-toofewexpiries',
					count( $expiry ),
					count( $add )
				] );
			}
		}
		// Validate the expiries
		$groupExpiries = [];
		foreach ( $expiry as $index => $expiryValue ) {
			$group = $add[$index];
			$groupExpiries[$group] = UserrightsPage::expiryToTimestamp( $expiryValue );
			if ( $groupExpiries[$group] === false ) {
				$this->dieWithError( [ 'apierror-invalidexpiry', wfEscapeWikiText( $expiryValue ) ] );
			}
			// not allowed to have things expiring in the past
			if ( $groupExpiries[$group] && $groupExpiries[$group] < wfTimestampNow() ) {
				$this->dieWithError( [ 'apierror-pastexpiry', wfEscapeWikiText( $expiryValue ) ] );
			}
		}
		$user = $this->getUrUser( $params );
		$tags = $params['tags'];
		// Check if user can add tags
		if ( $tags !== null ) {
			$ableToTag = ChangeTags::canAddTagsAccompanyingChange( $tags, $this->getAuthority() );
			if ( !$ableToTag->isOK() ) {
				$this->dieStatus( $ableToTag );
			}
		}
		$form = $this->getUserRightsPage();
		$form->setContext( $this->getContext() );
		$r = [];
		$r['user'] = $user->getName();
		$r['userid'] = $user->getId();
		list( $r['added'], $r['removed'] ) = $form->doSaveUserGroups(
			// Don't pass null to doSaveUserGroups() for array params, cast to empty array
			// doSaveUserGroup does work with CentralAuthGroupMembership
			// @phan-suppress-next-line PhanTypeMismatchArgument
			$user, $add, (array)$params['remove'],
			$params['reason'], (array)$tags, $groupExpiries
		);
		$result = $this->getResult();
		ApiResult::setIndexedTagName( $r['added'], 'group' );
		ApiResult::setIndexedTagName( $r['removed'], 'group' );
		$result->addValue( null, $this->getModuleName(), $r );
	}

	/**
	 * @param array $params
	 * @return CentralAuthUser
	 */
	private function getUrUser( array $params ) {
		if ( $this->mUser !== null ) {
			return $this->mUser;
		}
		$this->requireOnlyOneParameter( $params, 'user', 'userid' );
		$user = $params['user'] ?? '#' . $params['userid'];
		$form = $this->getUserRightsPage();
		$form->setContext( $this->getContext() );
		$status = $form->fetchUser( $user );
		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}
		$this->mUser = $status->value;
		return $status->value;
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams( $flags = 0 ) {
		$allGroups = $this->getAllGroups();
		if ( $flags & ApiBase::GET_VALUES_FOR_HELP ) {
			sort( $allGroups );
		}
		$a = [
			'user' => [
				ApiBase::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'id' ],
			],
			'userid' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_DEPRECATED => true,
			],
			'add' => [
				ApiBase::PARAM_TYPE => $allGroups,
				ApiBase::PARAM_ISMULTI => true
			],
			'remove' => [
				ApiBase::PARAM_TYPE => $allGroups,
				ApiBase::PARAM_ISMULTI => true
			],
			'reason' => [
				ApiBase::PARAM_DFLT => ''
			],
			'token' => [
				// Standard definition automatically inserted
				ApiBase::PARAM_HELP_MSG_APPEND => [ 'api-help-param-token-webui' ],
			],
			'tags' => [
				ApiBase::PARAM_TYPE => 'tags',
				ApiBase::PARAM_ISMULTI => true
			],
		];
		return $a;
	}

	public function needsToken() {
		return 'userrights';
	}

	protected function getWebUITokenSalt( array $params ) {
		return $this->getUrUser( $params )->getName();
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

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:User_group_membership';
	}
}
