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

namespace MediaWiki\Extension\CentralAuth\Special;

use InvalidArgumentException;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\Widget\HTMLGlobalUserTextField;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\Linker;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\UserGroupsSpecialPage;
use MediaWiki\Specials\SpecialUserRights;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserGroupsSpecialPageTarget;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;

/**
 * Equivalent of Special:Userrights for global groups.
 *
 * @ingroup Extensions
 */
class SpecialGlobalGroupMembership extends UserGroupsSpecialPage {

	/**
	 * The target of the local right-adjuster's interest.  Can be gotten from
	 * either a GET parameter or a subpage-style parameter, so have a member
	 * variable for it.
	 * @var null|string
	 */
	protected $mTarget;

	/**
	 * @var null|CentralAuthUser The user object of the target username or null.
	 */
	protected $mFetchedUser = null;

	private UserNamePrefixSearch $userNamePrefixSearch;
	private UserNameUtils $userNameUtils;
	private CentralAuthAutomaticGlobalGroupManager $automaticGroupManager;
	private GlobalGroupLookup $globalGroupLookup;
	private GlobalGroupAssignmentService $globalGroupAssignmentService;

	public function __construct(
		HookContainer $hookContainer,
		TitleFactory $titleFactory,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils,
		CentralAuthAutomaticGlobalGroupManager $automaticGroupManager,
		GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( 'GlobalGroupMembership' );
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
		$this->automaticGroupManager = $automaticGroupManager;
		$this->globalGroupLookup = $globalGroupLookup;
		// This is temporary, to avoid breaking changes in the constructor signature until all usages
		// of this special page are migrated to the assignment service.
		$this->globalGroupAssignmentService = CentralAuthServices::getGlobalGroupAssignmentService();
	}

	/**
	 * Manage forms to be shown according to posted data.
	 * Depending on the submit button used, call a form or a save function.
	 *
	 * @param string|null $par String if any subpage provided, else null
	 * @throws UserBlockedError|PermissionsError
	 */
	public function execute( $par ) {
		$user = $this->getUser();
		$request = $this->getRequest();
		$session = $request->getSession();
		$out = $this->getOutput();

		$out->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
		$out->addModuleStyles( 'ext.centralauth.misc.styles' );
		$out->addModules( [ 'mediawiki.special.userrights' ] );

		$this->mTarget = $par ?? $request->getVal( 'user' );

		$fetchedStatus = $this->mTarget === null ? Status::newFatal( 'nouserspecified' ) :
			$this->fetchUser( $this->mTarget );
		if ( $fetchedStatus->isOK() ) {
			$this->mFetchedUser = $fetchedStatus->value;
		}

		// show a successbox, if the user rights was saved successfully
		if (
			$session->get( 'specialUserrightsSaveSuccess' ) &&
			$this->mFetchedUser !== null
		) {
			// Remove session data for the success message
			$session->remove( 'specialUserrightsSaveSuccess' );

			$out->addModuleStyles( 'mediawiki.notification.convertmessagebox.styles' );
			$out->addHTML(
				Html::successBox(
					Html::element(
						'p',
						[],
						$this->msg( 'savedrights', $this->mFetchedUser->getName() )->text()
					),
					'mw-notify-success'
				)
			);
		}

		$this->setHeaders();
		$this->outputHeader();

		$out->addModuleStyles( 'mediawiki.special' );
		$this->addHelpLink( 'Help:Assigning permissions' );

		$this->switchForm();

		if (
			$request->wasPosted() &&
			$request->getCheck( 'saveusergroups' ) &&
			$this->mTarget !== null &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ), $this->mTarget )
		) {
			/*
			 * If the user is blocked and they only have "partial" access
			 * (e.g. they don't have the userrights permission), then don't
			 * allow them to change any user rights.
			 */
			if ( !$this->getAuthority()->isAllowed( 'userrights' ) ) {
				$block = $user->getBlock();
				if ( $block && $block->isSitewide() ) {
					throw new UserBlockedError(
						$block,
						$user,
						$this->getLanguage(),
						$request->getIP()
					);
				}
			}

			$this->checkReadOnly();

			// save settings
			if ( !$fetchedStatus->isOK() ) {
				foreach ( $fetchedStatus->getMessages() as $msg ) {
					$this->getOutput()->addWikiMsg( $msg );
				}

				return;
			}

			$targetUser = $this->mFetchedUser;

			$conflictCheck = $request->getVal( 'conflictcheck-originalgroups' );
			$conflictCheck = ( $conflictCheck === '' ) ? [] : explode( ',', $conflictCheck );
			$userGroups = $targetUser->getGlobalGroups();

			if ( $userGroups !== $conflictCheck ) {
				$out->addHTML( Html::errorBox(
					$this->msg( 'userrights-conflict' )->parse()
				) );
			} else {
				$status = $this->saveUserGroups(
					$targetUser,
					$request->getVal( 'user-reason' )
				);

				if ( $status->isOK() ) {
					// Set session data for the success message
					$session->set( 'specialUserrightsSaveSuccess', 1 );

					$out->redirect( $this->getSuccessURL() );
					return;
				} else {
					// Print an error message and redisplay the form
					foreach ( $status->getMessages() as $msg ) {
						$this->getOutput()->addHTML( Html::errorBox( $this->msg( $msg )->parse() ) );
					}
				}
			}
		}

		// show some more forms
		if ( $this->mTarget !== null ) {
			$this->editUserGroupsForm( $this->mTarget );
		}
	}

	/**
	 * @return string
	 */
	private function getSuccessURL() {
		return $this->getPageTitle( $this->mTarget )->getFullURL();
	}

	/**
	 * Save user groups changes in the database.
	 * Data comes from the editUserGroupsForm() form function
	 *
	 * @param CentralAuthUser $user Target user object.
	 * @param string $reason Reason for group change
	 */
	private function saveUserGroups( CentralAuthUser $user, string $reason ): Status {
		$allgroups = $this->globalGroupLookup->getDefinedGroups();
		$addgroup = [];
		// associative array of (group name => expiry)
		$groupExpiries = [];
		$removegroup = [];

		// This could possibly create a highly unlikely race condition if permissions are changed between
		//  when the form is loaded and when the form is saved. Ignoring it for the moment.
		foreach ( $allgroups as $group ) {
			// We'll tell it to remove all unchecked groups, and add all checked groups.
			// Later on, this gets filtered for what can actually be removed
			if ( $this->getRequest()->getCheck( "wpGroup-$group" ) ) {
				$addgroup[] = $group;

				// read the expiry information from the request
				$expiryDropdown = $this->getRequest()->getVal( "wpExpiry-$group" );
				if ( $expiryDropdown === 'existing' ) {
					continue;
				}

				if ( $expiryDropdown === 'other' ) {
					$expiryValue = $this->getRequest()->getVal( "wpExpiry-$group-other" );
				} else {
					$expiryValue = $expiryDropdown;
				}

				// validate the expiry
				$groupExpiries[$group] = SpecialUserRights::expiryToTimestamp( $expiryValue );

				if ( $groupExpiries[$group] === false ) {
					return Status::newFatal( 'userrights-invalid-expiry', $group );
				}

				// not allowed to have things expiring in the past
				if ( $groupExpiries[$group] && $groupExpiries[$group] < wfTimestampNow() ) {
					return Status::newFatal( 'userrights-expiry-in-past', $group );
				}
			} else {
				$removegroup[] = $group;
			}
		}

		$this->globalGroupAssignmentService->saveChangesToUserGroups(
			$this->getAuthority(),
			$user,
			$addgroup,
			$removegroup,
			$groupExpiries,
			$reason
		);

		return Status::newGood();
	}

	/**
	 * Save user groups changes in the database. This function does not throw errors;
	 * instead, it ignores groups that the performer does not have permission to set.
	 *
	 * @deprecated since 1.45, use {@see GlobalGroupAssignmentService::saveChangesToUserGroups()} directly
	 * @param CentralAuthUser $user
	 * @param string[] $add Array of groups to add
	 * @param string[] $remove Array of groups to remove
	 * @param string $reason Reason for group change
	 * @param string[] $tags Array of change tags to add to the log entry
	 * @param array<string,?string> $groupExpiries Associative array of (group name => expiry),
	 *   containing only those groups that are to have new expiry values set
	 * @return array Tuple of added, then removed groups
	 */
	public function doSaveUserGroups(
		CentralAuthUser $user,
		array $add,
		array $remove,
		string $reason = '',
		array $tags = [],
		array $groupExpiries = []
	) {
		return $this->globalGroupAssignmentService->saveChangesToUserGroups(
			$this->getAuthority(),
			$user,
			$add,
			$remove,
			$groupExpiries,
			$reason,
			$tags
		);
	}

	/**
	 * Edit user groups membership
	 * @param string $username Name of the user.
	 */
	private function editUserGroupsForm( $username ) {
		$status = $this->fetchUser( $username );
		if ( !$status->isOK() ) {
			foreach ( $status->getMessages() as $msg ) {
				$this->getOutput()->addWikiMsg( $msg );
			}

			return;
		}

		/** @var CentralAuthUser $user */
		$user = $status->value;
		'@phan-var CentralAuthUser $user';

		$target = new UserGroupsSpecialPageTarget( $user->getName(), $user );
		$this->getOutput()->addHTML( $this->buildGroupsForm( $target ) );

		// This isn't really ideal logging behavior, but let's not hide the
		// interwiki logs if we're using them as is.
		$this->showLogFragment( $target, $this->getOutput() );
	}

	/**
	 * @param string $username
	 * @return Status
	 */
	public function fetchUser( $username ) {
		if ( $username === '' ) {
			return Status::newFatal( 'nouserspecified' );
		}

		if ( $username[0] == '#' ) {
			$id = intval( substr( $username, 1 ) );
			$globalUser = CentralAuthUser::newPrimaryInstanceFromId( $id );
			// If the user exists, but is hidden from the viewer, pretend that it does
			// not exist. - T285190/T260863
			if (
				!$globalUser
				|| (
					( $globalUser->isSuppressed() || $globalUser->isHidden() )
					&& !$this->getContext()->getAuthority()->isAllowed( 'centralauth-suppress' )
				)
			) {
				return Status::newFatal( 'noname', $id );
			}
		} else {
			// fetchUser() is public; normalize in case the caller forgot to. See T343963 and
			// T344495.
			$canonUsername = $this->userNameUtils->getCanonical( $username );
			if ( !is_string( $canonUsername ) ) {
				// $username was invalid, return nosuchuser.
				return Status::newFatal( 'nosuchusershort', $username );
			}

			// If the user exists, but is hidden from the viewer, pretend that it does
			// not exist. - T285190
			$globalUser = CentralAuthUser::getPrimaryInstanceByName( $canonUsername );
			if (
				!$globalUser->exists()
				|| (
					( $globalUser->isSuppressed() || $globalUser->isHidden() )
					&& !$this->getContext()->getAuthority()->isAllowed( 'centralauth-suppress' )
				)
			) {
				return Status::newFatal( 'nosuchusershort', $canonUsername );
			}
		}

		if ( $this->userNameUtils->isTemp( $globalUser->getName() ) ) {
			return Status::newFatal( 'userrights-no-group' );
		}

		return Status::newGood( $globalUser );
	}

	/**
	 * Output a form to allow searching for a user
	 */
	private function switchForm() {
		$this->addHelpLink( 'Extension:CentralAuth' );
		$this->getOutput()->addModuleStyles( 'mediawiki.special' );
		$formDescriptor = [
			'user' => [
				'class' => HTMLGlobalUserTextField::class,
				'name' => 'user',
				'id' => 'username',
				'label-message' => 'userrights-user-editname',
				'size' => 30,
				'excludetemp' => true,
				'default' => $this->mTarget,
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
			// Strip subpage
			->setTitle( $this->getPageTitle() )
			->setAction( $this->getConfig()->get( MainConfigNames::Script ) )
			->setId( 'mw-userrights-form1' )
			->setName( 'uluser' )
			->setSubmitTextMsg( 'editusergroup' )
			->setWrapperLegendMsg( 'userrights-lookup-user' )
			->prepareForm()
			->displayForm( false );
	}

	/** @inheritDoc */
	protected function makeConflictCheckKey( UserGroupsSpecialPageTarget $target ): string {
		$user = $this->assertIsCentralAuthUser( $target->userObject );
		return implode( ',', $user->getGlobalGroups() );
	}

	/** @inheritDoc */
	protected function getTargetDescriptor(): string {
		return $this->mTarget;
	}

	/** @inheritDoc */
	protected function getTargetUserToolLinks( UserGroupsSpecialPageTarget $target ): string {
		$user = $this->assertIsCentralAuthUser( $target->userObject );
		return Linker::userToolLinks(
			$user->getId(),
			$user->getName(),
			// default for redContribsWhenNoEdits
			false,
			Linker::TOOL_LINKS_EMAIL
		);
	}

	/** @inheritDoc */
	protected function listAllExplicitGroups(): array {
		return $this->globalGroupLookup->getDefinedGroups();
	}

	/** @inheritDoc */
	protected function getGroupMemberships( UserGroupsSpecialPageTarget $target ): array {
		$user = $this->assertIsCentralAuthUser( $target->userObject );
		$groups = $user->getGlobalGroupsWithExpiration();

		$groupMemberships = [];
		foreach ( $groups as $group => $expiration ) {
			$groupMemberships[$group] = new UserGroupMembership( $user->getId(), $group, $expiration );
		}
		return $groupMemberships;
	}

	protected function canAdd( string $group ): bool {
		if ( !$this->getContext()->getAuthority()->isAllowed( 'globalgroupmembership' ) ) {
			return false;
		}

		$automaticGroups = $this->automaticGroupManager->getAutomaticGlobalGroups();
		if ( in_array( $group, $automaticGroups ) ) {
			return false;
		}

		return true;
	}

	protected function canRemove( string $group ): bool {
		return $this->canAdd( $group );
	}

	protected function getGroupAnnotations( string $group ): array {
		$automaticGroups = $this->automaticGroupManager->getAutomaticGlobalGroups();
		$automaticGroupsConfig = $this->getConfig()->get(
			CAMainConfigNames::CentralAuthAutomaticGlobalGroups
		);

		$groupIsAutomatic = in_array( $group, $automaticGroups );

		if ( $groupIsAutomatic ) {
			return [ 'centralauth-globalgroupperms-automatic-group-reason' ];
		} elseif ( isset( $automaticGroupsConfig[$group] ) && $automaticGroupsConfig[$group] ) {
			$uiLanguage = $this->getLanguage();
			$user = $this->mFetchedUser;
			$groupNames = array_map(
				static function ( $automaticGroup ) use ( $uiLanguage, $user ) {
					return $uiLanguage->getGroupMemberName( $automaticGroup, $user->getName() );
				},
				$automaticGroupsConfig[$group]
			);

			return [
				$this->msg( 'centralauth-globalgroupperms-automatic-group-info' )
					->params( Message::listParam( $groupNames ) )
			];
		}
		return parent::getGroupAnnotations( $group );
	}

	/** @inheritDoc */
	protected function supportsWatchUser( UserGroupsSpecialPageTarget $target ): bool {
		return false;
	}

	/** @inheritDoc */
	protected function getLogType(): array {
		return [ 'gblrights', 'gblrights' ];
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$search = $this->userNameUtils->getCanonical( $search );
		if ( !$search ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return $this->userNamePrefixSearch
			->search( UserNamePrefixSearch::AUDIENCE_PUBLIC, $search, $limit, $offset );
	}

	/**
	 * A helper function to assert that an object is of type {@see CentralAuthUser}.
	 * It's used when retrieving the user object from a {@see UserGroupsSpecialPageTarget}.
	 * @throws InvalidArgumentException If the object is not of the expected type
	 * @param mixed $object The object to check
	 * @return CentralAuthUser The input object, but with a type hint for IDEs
	 */
	private function assertIsCentralAuthUser( mixed $object ): CentralAuthUser {
		if ( !$object instanceof CentralAuthUser ) {
			throw new InvalidArgumentException( 'Target userObject must be a CentralAuthUser' );
		}
		return $object;
	}
}
