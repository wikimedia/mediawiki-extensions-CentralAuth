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

use ChangeTags;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiResult;
use MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Specials\SpecialUserRights;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @ingroup API
 */
class ApiGlobalUserRights extends ApiBase {

	private ?CentralAuthUser $user = null;

	private TitleFactory $titleFactory;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private UserNameUtils $userNameUtils;
	private CentralAuthAutomaticGlobalGroupManager $automaticGroupManager;
	private GlobalGroupLookup $globalGroupLookup;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		TitleFactory $titleFactory,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils,
		CentralAuthAutomaticGlobalGroupManager $automaticGroupManager,
		GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->titleFactory = $titleFactory;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
		$this->automaticGroupManager = $automaticGroupManager;
		$this->globalGroupLookup = $globalGroupLookup;
	}

	private function getUserRightsPage(): SpecialGlobalGroupMembership {
		return new SpecialGlobalGroupMembership(
			$this->titleFactory,
			$this->userNamePrefixSearch,
			$this->userNameUtils,
			$this->automaticGroupManager,
			$this->globalGroupLookup
		);
	}

	public function execute() {
		$pUser = $this->getUser();
		// Deny if the user is blocked and doesn't have the full 'userrights' permission.
		// This matches what Special:UserRights does for the web UI.
		if ( !$this->getAuthority()->isAllowed( 'userrights' ) ) {
			$block = $pUser->getBlock( IDBAccessObject::READ_LATEST );
			if ( $block && $block->isSitewide() ) {
				$this->dieBlocked( $block );
			}
		}
		$params = $this->extractRequestParams();
		$expiry = (array)$params['expiry'];
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
			$groupExpiries[$group] = SpecialUserRights::expiryToTimestamp( $expiryValue );
			if ( $groupExpiries[$group] === false ) {
				$this->dieWithError( [ 'apierror-invalidexpiry', wfEscapeWikiText( $expiryValue ) ] );
			}
			// not allowed to have things expiring in the past
			if ( $groupExpiries[$group] && $groupExpiries[$group] < wfTimestampNow() ) {
				$this->dieWithError( [ 'apierror-pastexpiry', wfEscapeWikiText( $expiryValue ) ] );
			}
		}

		$user = $this->getCentralAuthUser( $params );

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
		[ $r['added'], $r['removed'] ] = $form->doSaveUserGroups(
			// Don't pass null to doSaveUserGroups() for array params, cast to empty array
			$user, $add, (array)$params['remove'],
			$params['reason'], (array)$tags, $groupExpiries
		);
		$result = $this->getResult();
		ApiResult::setIndexedTagName( $r['added'], 'group' );
		ApiResult::setIndexedTagName( $r['removed'], 'group' );
		$result->addValue( null, $this->getModuleName(), $r );
	}

	private function getCentralAuthUser( array $params ): CentralAuthUser {
		if ( $this->user !== null ) {
			return $this->user;
		}

		$this->requireOnlyOneParameter( $params, 'user', 'userid' );
		$user = $params['user'] ?? '#' . $params['userid'];
		$form = $this->getUserRightsPage();
		$form->setContext( $this->getContext() );
		$status = $form->fetchUser( $user );
		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}

		$this->user = $status->value;
		return $status->value;
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams( $flags = 0 ) {
		$allGroups = $this->globalGroupLookup->getDefinedGroups();
		if ( $flags & ApiBase::GET_VALUES_FOR_HELP ) {
			sort( $allGroups );
		}
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'id' ],
			],
			'userid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEPRECATED => true,
			],
			'add' => [
				ParamValidator::PARAM_TYPE => $allGroups,
				ParamValidator::PARAM_ISMULTI => true
			],
			'expiry' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ALLOW_DUPLICATES => true,
				ParamValidator::PARAM_DEFAULT => 'infinite',
			],
			'remove' => [
				ParamValidator::PARAM_TYPE => $allGroups,
				ParamValidator::PARAM_ISMULTI => true
			],
			'reason' => [
				ParamValidator::PARAM_DEFAULT => ''
			],
			'token' => [
				// Standard definition automatically inserted
				ApiBase::PARAM_HELP_MSG_APPEND => [ 'api-help-param-token-webui' ],
			],
			'tags' => [
				ParamValidator::PARAM_TYPE => 'tags',
				ParamValidator::PARAM_ISMULTI => true
			],
		];
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'userrights';
	}

	/** @inheritDoc */
	protected function getWebUITokenSalt( array $params ) {
		return $this->getCentralAuthUser( $params )->getName();
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

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:User_group_membership';
	}
}
