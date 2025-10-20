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

use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUserHelper;
use MediaWiki\Extension\CentralAuth\Widget\HTMLGlobalUserTextField;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Linker\Linker;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\UserGroupsSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;

/**
 * Equivalent of Special:Userrights for global groups.
 *
 * @ingroup Extensions
 */
class SpecialGlobalGroupMembership extends UserGroupsSpecialPage {

	/**
	 * @var CentralAuthUser The user object of the target username.
	 */
	protected CentralAuthUser $targetUser;

	private StatusFormatter $statusFormatter;

	public function __construct(
		FormatterFactory $formatterFactory,
		private readonly UserNamePrefixSearch $userNamePrefixSearch,
		private readonly UserNameUtils $userNameUtils,
		private readonly CentralAuthUserHelper $userHelper,
		private readonly GlobalGroupAssignmentService $globalGroupAssignmentService,
		private readonly GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( 'GlobalGroupMembership' );
		$this->statusFormatter = $formatterFactory->getStatusFormatter( $this->getContext() );
	}

	/**
	 * Manage forms to be shown according to posted data.
	 * Depending on the submit button used, call a form or a save function.
	 *
	 * @param string|null $subPage String if any subpage provided, else null
	 * @throws UserBlockedError|PermissionsError
	 */
	public function execute( $subPage ) {
		$user = $this->getUser();
		$request = $this->getRequest();
		$session = $request->getSession();
		$out = $this->getOutput();

		$this->setHeaders();
		$this->outputHeader();

		$out->addModules( [ 'mediawiki.special.userrights' ] );
		$out->addModuleStyles( 'mediawiki.special' );
		$out->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
		$this->addHelpLink( 'Help:Assigning permissions' );

		$targetName = $subPage ?? $request->getText( 'user' );
		$this->switchForm( $targetName );

		// If the user just viewed this page, without trying to submit, return early
		// It prevents from showing "nouserspecified" error message on first view
		if ( $subPage === null && !$request->getCheck( 'user' ) ) {
			return;
		}

		// No need to check if target is empty or non-canonical, fetchUser() does it
		$fetchedStatus = $this->fetchUser( $targetName );
		if ( !$fetchedStatus->isOK() ) {
			$out->addHTML( Html::warningBox(
				$this->statusFormatter->getMessage( $fetchedStatus )->parse()
			) );
			return;
		}

		$fetchedUser = $fetchedStatus->value;
		// Phan false positive on Status object - T323205
		'@phan-var CentralAuthUser $fetchedUser';
		$this->setTargetName( $fetchedUser->getName() );
		$this->targetUser = $fetchedUser;

		if ( !$this->globalGroupAssignmentService->targetCanHaveUserGroups( $fetchedUser ) ) {
			$out->addHTML( Html::warningBox(
				$this->msg( 'userrights-no-group' )->parse()
			) );
			return;
		}

		$this->explicitGroups = $this->globalGroupLookup->getDefinedGroups();
		$this->groupMemberships = $this->getGlobalGroupMemberships();
		$this->enableWatchUser = false;

		$changeableGroups = $this->globalGroupAssignmentService->getChangeableGroups(
			$this->getAuthority(), $fetchedUser );
		$this->addableGroups = $changeableGroups['add'];
		$this->removableGroups = $changeableGroups['remove'];
		foreach ( $changeableGroups['restricted'] as $group => $details ) {
			if ( !$details['condition-met'] ) {
				$this->addGroupAnnotation( $group, $details['message'] );
			}
		}

		$uiLanguage = $this->getLanguage();
		$automaticGroupsConfig = $this->getConfig()->get( CAMainConfigNames::CentralAuthAutomaticGlobalGroups );
		foreach ( $automaticGroupsConfig as $group => $relatedAutomaticGroups ) {
			$groupNames = array_map(
				static function ( $automaticGroup ) use ( $uiLanguage, $fetchedUser ) {
					return $uiLanguage->getGroupMemberName( $automaticGroup, $fetchedUser->getName() );
				},
				$relatedAutomaticGroups
			);
			$this->addGroupAnnotation(
				$group,
				$this->msg( 'centralauth-globalgroupperms-automatic-group-info' )
					->params( Message::listParam( $groupNames ) )
			);
		}

		// show a successbox, if the user rights was saved successfully
		if ( $session->get( 'specialUserrightsSaveSuccess' ) ) {
			// Remove session data for the success message
			$session->remove( 'specialUserrightsSaveSuccess' );

			$out->addModuleStyles( 'mediawiki.notification.convertmessagebox.styles' );
			$out->addHTML(
				Html::successBox(
					Html::element(
						'p',
						[],
						$this->msg( 'savedrights', $fetchedUser->getName() )->text()
					),
					'mw-notify-success'
				)
			);
		}

		if (
			$request->wasPosted() &&
			$request->getCheck( 'saveusergroups' ) &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ), $targetName )
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

			$conflictCheck = $request->getVal( 'conflictcheck-originalgroups' );
			$conflictCheck = ( $conflictCheck === '' ) ? [] : explode( ',', $conflictCheck );
			$userGroups = $fetchedUser->getGlobalGroups();

			if ( $userGroups !== $conflictCheck ) {
				$out->addHTML( Html::errorBox(
					$this->msg( 'userrights-conflict' )->parse()
				) );
			} else {
				$status = $this->saveUserGroups(
					$fetchedUser,
					$request->getVal( 'user-reason' )
				);

				if ( $status->isOK() ) {
					// Set session data for the success message
					$session->set( 'specialUserrightsSaveSuccess', 1 );

					$out->redirect( $this->getSuccessURL( $targetName ) );
					return;
				} else {
					// Print an error message and redisplay the form
					foreach ( $status->getMessages() as $msg ) {
						$this->getOutput()->addHTML( Html::errorBox( $this->msg( $msg )->parse() ) );
					}
				}
			}
		}

		// Show the form (either edit or view)
		$this->getOutput()->addHTML( $this->buildGroupsForm() );
		$this->showLogFragment( 'gblrights', 'gblrights' );
	}

	/**
	 * @return string
	 */
	private function getSuccessURL( string $target ) {
		return $this->getPageTitle( $target )->getFullURL();
	}

	/**
	 * Save user groups changes in the database.
	 * Data comes from the editUserGroupsForm() form function
	 *
	 * @param CentralAuthUser $user Target user object.
	 * @param string $reason Reason for group change
	 */
	private function saveUserGroups( CentralAuthUser $user, string $reason ): Status {
		$newGroupsStatus = $this->readGroupsForm();

		if ( !$newGroupsStatus->isOK() ) {
			return $newGroupsStatus;
		}
		$newGroups = $newGroupsStatus->value;

		// addgroup contains also existing groups with changed expiry
		[ $addgroup, $removegroup, $groupExpiries ] = $this->splitGroupsIntoAddRemove(
			$newGroups, $this->groupMemberships );
		$this->globalGroupAssignmentService->saveChangesToUserGroups( $this->getAuthority(), $user, $addgroup,
			$removegroup, $groupExpiries, $reason );

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
	 * @param string $username
	 * @return Status<CentralAuthUser>
	 */
	private function fetchUser( $username ) {
		if ( str_starts_with( $username, '#' ) ) {
			$id = intval( substr( $username, 1 ) );
			return $this->userHelper->getCentralAuthUserByIdFromPrimary( $id, $this->getAuthority() );
		} else {
			return $this->userHelper->getCentralAuthUserByNameFromPrimary( $username, $this->getAuthority() );
		}
	}

	/**
	 * Output a form to allow searching for a user
	 */
	private function switchForm( string $target ) {
		$formDescriptor = [
			'user' => [
				'class' => HTMLGlobalUserTextField::class,
				'name' => 'user',
				'id' => 'username',
				'label-message' => 'userrights-user-editname',
				'size' => 30,
				'excludetemp' => true,
				'autofocus' => $target === '',
				'default' => $target,
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
	protected function getTargetUserToolLinks(): string {
		return Linker::userToolLinks(
			$this->targetUser->getId(),
			$this->targetUser->getName(),
			// default for redContribsWhenNoEdits
			false,
			Linker::TOOL_LINKS_EMAIL
		);
	}

	/**
	 * @return array<string,UserGroupMembership>
	 */
	protected function getGlobalGroupMemberships(): array {
		$groups = $this->targetUser->getGlobalGroupsWithExpiration();

		$groupMemberships = [];
		foreach ( $groups as $group => $expiration ) {
			$groupMemberships[$group] = new UserGroupMembership( $this->targetUser->getId(), $group, $expiration );
		}
		return $groupMemberships;
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
}
