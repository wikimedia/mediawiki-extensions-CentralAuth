<?php
/**
 * Created on 1st October, 2014
 *
 * Copyright © 2014 Alex Monk <krenair@gmail.com>
 *
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiResult;
use MediaWiki\ChangeTags\ChangeTags;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUserHelper;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserGroupAssignmentService;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @ingroup API
 */
class ApiGlobalUserRights extends ApiBase {

	private ?CentralAuthUser $user = null;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		private readonly CentralAuthUserHelper $userHelper,
		private readonly GlobalGroupAssignmentService $globalGroupAssignmentService,
		private readonly GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( $mainModule, $moduleName );
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
			$groupExpiries[$group] = UserGroupAssignmentService::expiryToTimestamp( $expiryValue );
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
		$r = [
			'user' => $user->getName(),
			'userid' => $user->getId(),
		];
		[ $r['added'], $r['removed'] ] = $this->globalGroupAssignmentService->saveChangesToUserGroups(
			// Don't pass null for array params; cast to empty array
			$this->getAuthority(), $user, $add, (array)$params['remove'], $groupExpiries,
			$params['reason'], MessageValue::new( 'centralauth-automatic-global-groups-reason-global' ),
			(array)$tags
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

		if ( isset( $params['userid'] ) ) {
			$id = intval( $params['userid'] );
			$status = $this->userHelper->getCentralAuthUserByIdFromPrimary( $id, $this->getAuthority() );
		} else {
			$status = $this->userHelper->getCentralAuthUserByNameFromPrimary( $params['user'], $this->getAuthority() );
		}

		if ( !$status->isOK() ) {
			$this->dieStatus( $status );
		}

		$user = $status->value;
		if ( !$this->globalGroupAssignmentService->targetCanHaveUserGroups( $user ) ) {
			$this->dieWithError( 'userrights-no-group' );
		}

		$this->user = $user;
		return $user;
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
