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

use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Extension\CentralAuth\CentralAuthAutomaticGlobalGroupManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\Widget\HTMLGlobalUserTextField;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\Linker;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Specials\SpecialUserRights;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use MediaWiki\Xml\XmlSelect;

/**
 * Equivalent of Special:Userrights for global groups.
 *
 * @ingroup Extensions
 */
class SpecialGlobalGroupMembership extends SpecialPage {

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

	private HookContainer $hookContainer;
	private TitleFactory $titleFactory;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private UserNameUtils $userNameUtils;
	private CentralAuthAutomaticGlobalGroupManager $automaticGroupManager;
	private GlobalGroupLookup $globalGroupLookup;

	public function __construct(
		HookContainer $hookContainer,
		TitleFactory $titleFactory,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils,
		CentralAuthAutomaticGlobalGroupManager $automaticGroupManager,
		GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( 'GlobalGroupMembership' );
		$this->hookContainer = $hookContainer;
		$this->titleFactory = $titleFactory;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
		$this->automaticGroupManager = $automaticGroupManager;
		$this->globalGroupLookup = $globalGroupLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return true;
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
	 * @return Status
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

		$this->doSaveUserGroups( $user, $addgroup, $removegroup, $reason, [], $groupExpiries );

		return Status::newGood();
	}

	/**
	 * Add or remove automatic global groups, or update expiries, based on:
	 * - existing global groups
	 * - existing local groups
	 * - groups we are about to add
	 * - groups we are about to remove
	 * - groups whose expiries we are about to change
	 *
	 * @param CentralAuthUser $user
	 * @param array<string,?string> $globalGroups Associative array of (group name => expiry),
	 *   representing global groups that $user already has
	 * @param string[] &$add Array of groups to add
	 * @param string[] &$remove Array of groups to remove
	 * @param array<string,?string> &$groupExpiries Associative array of (group name => expiry),
	 *   containing only those groups that are to have new expiry values set
	 */
	private function adjustForAutomaticGlobalGroups(
		CentralAuthUser $user,
		array $globalGroups,
		array &$add,
		array &$remove,
		array &$groupExpiries
	) {
		// Get the user's local groups and their expiries. If the user has the same group on
		// multiple wikis, add the latest expiry (with null representing no expiry).
		$userInfo = $user->queryAttached();
		$localGroups = [];
		foreach ( $userInfo as $info ) {
			foreach ( $info['groupMemberships'] as $groupMembership ) {
				$group = $groupMembership->getGroup();
				$expiry = $groupMembership->getExpiry();
				if ( $expiry === null ) {
					$localGroups[$group] = null;
				} elseif (
					!array_key_exists( $group, $localGroups ) ||
					( $localGroups[$group] !== null && $localGroups[$group] < $expiry )
				) {
					$localGroups[$group] = $expiry;
				}
			}
		}

		$addGroupsWithExpiries = array_intersect_key(
			$groupExpiries,
			array_fill_keys( $add, null )
		);
		$assignedGroups = array_diff_key(
			array_merge( $globalGroups, $localGroups, $addGroupsWithExpiries ),
			array_fill_keys( $remove, null )
		);

		$this->automaticGroupManager->handleAutomaticGlobalGroups(
			$assignedGroups,
			$add,
			$remove,
			$groupExpiries
		);
	}

	/**
	 * Save user groups changes in the database. This function does not throw errors;
	 * instead, it ignores groups that the performer does not have permission to set.
	 *
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
		// Validate input set...
		$groups = $user->getGlobalGroupsWithExpiration();
		$changeable = $this->changeableGroups();

		$remove = array_unique( array_intersect( $remove, $changeable, array_keys( $groups ) ) );
		$add = array_intersect( $add, $changeable );

		$this->adjustForAutomaticGlobalGroups( $user, $groups, $add, $remove, $groupExpiries );

		// add only groups that are not already present or that need their expiry updated
		$add = array_filter( $add,
			static function ( $group ) use ( $groups, $groupExpiries ) {
				return !array_key_exists( $group, $groups ) || array_key_exists( $group, $groupExpiries );
			} );

		// Remove groups, then add new ones/update expiries of existing ones
		if ( $remove ) {
			foreach ( $remove as $group ) {
				$user->removeFromGlobalGroups( $group );
			}
		}
		if ( $add ) {
			foreach ( $add as $group ) {
				$expiry = $groupExpiries[$group] ?? null;
				$user->addToGlobalGroup( $group, $expiry );
			}
		}

		$newGroups = $user->getGlobalGroupsWithExpiration();

		// Ensure that caches are cleared
		$user->invalidateCache();

		$reason = $this->getLogReason( $reason, $add, $remove );

		// Only add a log entry if something actually changed
		if ( $groups !== $newGroups ) {
			// Allow other extensions to respond to changes in global group membership
			$caHookRunner = new CentralAuthHookRunner( $this->hookContainer );
			$caHookRunner->onCentralAuthGlobalUserGroupMembershipChanged( $user, $groups, $newGroups );
			$this->addLogEntry(
				$user,
				$groups,
				$newGroups,
				$reason,
				$tags
			);
		}

		return [ $add, $remove ];
	}

	/**
	 * Update the reason if any automatic global groups were changed, unless the
	 * reason already explains an automatic update due to a local group change.
	 *
	 * @param string $reason The given reason
	 * @param string[] $addedGroups
	 * @param string[] $removedGroups
	 * @return string The updated reason
	 */
	private function getLogReason(
		string $reason,
		array $addedGroups,
		array $removedGroups
	) {
		$automaticGroups = $this->automaticGroupManager->getAutomaticGlobalGroups();
		$localReason = $this->msg( 'centralauth-automatic-global-groups-reason-local' )
			->inContentLanguage()
			->text();

		if ( $reason !== $localReason ) {
			foreach ( $automaticGroups as $automaticGroup ) {
				if (
					in_array( $automaticGroup, $addedGroups ) ||
					in_array( $automaticGroup, $removedGroups )
				) {
					$reason = $this->msg( 'centralauth-automatic-global-groups-reason-global', $reason )->text();
					break;
				}
			}
		}

		return $reason;
	}

	/**
	 * @param CentralAuthUser $user
	 * @param array $oldGroups
	 * @param array $newGroups
	 * @param string $reason
	 * @param array $tags Not currently used
	 */
	private function addLogEntry(
		CentralAuthUser $user,
		array $oldGroups,
		array $newGroups,
		string $reason,
		array $tags
	) {
		$oldGroupNames = [];
		$newGroupNames = [];
		$oldGroupMetadata = [];
		$newGroupMetadata = [];

		foreach ( $oldGroups as $key => &$value ) {
			$oldGroupNames[] = $key;
			$oldGroupMetadata[] = [ 'expiry' => $value ];
		}

		foreach ( $newGroups as $key => &$value ) {
			$newGroupNames[] = $key;
			$newGroupMetadata[] = [ 'expiry' => $value ];
		}

		// The following message is generated here:
		// * logentry-gblrename-usergroups
		// * log-action-filter-gblrename-usergroups
		$entry = new ManualLogEntry( 'gblrights', 'usergroups' );
		$entry->setTarget( $this->titleFactory->makeTitle( NS_USER, $user->getName() ) );
		$entry->setPerformer( $this->getUser() );
		$entry->setComment( $reason );
		$entry->setParameters( [
			'oldGroups' => $oldGroupNames,
			'newGroups' => $newGroupNames,
			'oldMetadata' => $oldGroupMetadata,
			'newMetadata' => $newGroupMetadata,
		] );
		$logid = $entry->insert();
		$entry->publish( $logid );
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

		$this->showEditUserGroupsForm( $user );

		// This isn't really ideal logging behavior, but let's not hide the
		// interwiki logs if we're using them as is.
		$this->showLogFragment( $user, $this->getOutput() );
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

	/**
	 * Show the form to edit group memberships.
	 * @param CentralAuthUser $user user you're editing
	 */
	private function showEditUserGroupsForm( CentralAuthUser $user ) {
		$list = $membersList = $tempList = $tempMembersList = [];
		foreach ( $user->getGlobalGroupsWithExpiration() as $group => $expiration ) {
			$ugm = new UserGroupMembership( $user->getId(), $group, $expiration );
			$linkG = UserGroupMembership::getLinkHTML( $ugm, $this->getContext() );
			$linkM = UserGroupMembership::getLinkHTML( $ugm, $this->getContext(), $user->getName() );
			if ( $ugm->getExpiry() ) {
				$tempList[] = $linkG;
				$tempMembersList[] = $linkM;
			} else {
				$list[] = $linkG;
				$membersList[] = $linkM;
			}
		}

		$language = $this->getLanguage();
		$displayedList = $this->msg( 'userrights-groupsmember-type' )
			->rawParams(
				$language->commaList( array_merge( $tempList, $list ) ),
				$language->commaList( array_merge( $tempMembersList, $membersList ) )
			)->escaped();

		$grouplist = '';
		$count = count( $list ) + count( $tempList );
		if ( $count > 0 ) {
			$grouplist = $this->msg( 'userrights-groupsmember' )
				->numParams( $count )
				->params( $user->getName() )
				->parse();
			$grouplist = '<p>' . $grouplist . ' ' . $displayedList . "</p>\n";
		}

		$userToolLinks = Linker::userToolLinks(
			$user->getId(),
			$user->getName(),
			// default for redContribsWhenNoEdits
			false,
			Linker::TOOL_LINKS_EMAIL
		);

		$canChangeAny = $this->changeableGroups() !== [];
		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				[
					'method' => 'post',
					'action' => $this->getPageTitle()->getLocalURL(),
					'name' => 'editGroup',
					'id' => 'mw-userrights-form2'
				]
			) .
			Html::hidden( 'user', $this->mTarget ) .
			Html::hidden( 'wpEditToken', $this->getUser()->getEditToken( $this->mTarget ) ) .
			// Conflict detection
			Html::hidden(
				'conflictcheck-originalgroups',
				implode( ',', $user->getGlobalGroups() )
			) .
			Html::openElement( 'fieldset' ) .
			Html::element(
				'legend',
				[],
				$this->msg(
					$canChangeAny ? 'userrights-editusergroup' : 'userrights-viewusergroup',
					$user->getName()
				)->text()
			) .
			$this->msg(
				$canChangeAny ? 'editinguser' : 'viewinguserrights'
			)->params( wfEscapeWikiText( $user->getName() ) )
				->rawParams( $userToolLinks )->parse()
		);
		if ( $canChangeAny ) {
			$this->getOutput()->addHTML(
				$this->msg( 'userrights-groups-help', $user->getName() )->parse() .
				$grouplist .
				$this->groupCheckboxes( $user ) .
				Html::openElement( 'table', [ 'id' => 'mw-userrights-table-outer' ] ) .
					"<tr>
						<td class='mw-label'>" .
							Html::label( $this->msg( 'userrights-reason' )->text(), 'wpReason' ) .
						"</td>
						<td class='mw-input'>" .
							Html::input( 'user-reason', $this->getRequest()->getVal( 'user-reason' ) ?? false, 'text', [
								'id' => 'wpReason',
								'size' => 60,
								// HTML maxlength uses "UTF-16 code units", which means that characters outside BMP
								// (e.g. emojis) count for two each. This limit is overridden in JS to instead count
								// Unicode codepoints.
								'maxlength' => CommentStore::COMMENT_CHARACTER_LIMIT,
							] ) .
						"</td>
					</tr>
					<tr>
						<td></td>
						<td class='mw-submit'>" .
							Html::submitButton( $this->msg( 'saveusergroups', $user->getName() )->text(),
								[ 'name' => 'saveusergroups' ] +
									Linker::tooltipAndAccesskeyAttribs( 'userrights-set' )
							) .
						"</td>
					</tr>" .
				Html::closeElement( 'table' ) . "\n"
			);
		} else {
			$this->getOutput()->addHTML( $grouplist );
		}
		$this->getOutput()->addHTML(
			Html::closeElement( 'fieldset' ) .
			Html::closeElement( 'form' ) . "\n"
		);
	}

	/**
	 * Adds a table with checkboxes where you can select what groups to add/remove.
	 *
	 * This is only called when the user can change any of the groups.
	 *
	 * @param CentralAuthUser $user
	 * @return string The HTML table element with checkboxes and expiry dropdowns
	 */
	private function groupCheckboxes( CentralAuthUser $user ) {
		$allgroups = $this->globalGroupLookup->getDefinedGroups();
		$currentGroups = $user->getGlobalGroupsWithExpiration();
		$ret = '';

		// Get the list of preset expiry times from the system message
		$expiryOptionsMsg = $this->msg( 'userrights-expiry-options' )->inContentLanguage();
		$expiryOptions = $expiryOptionsMsg->isDisabled()
			? []
			: XmlSelect::parseOptionsMessage( $expiryOptionsMsg->text() );

		// Build the HTML table
		$ret .= Html::openElement( 'table', [ 'class' => 'mw-userrights-groups' ] ) .
			"<tr>\n";
		$ret .= Html::element(
			'th',
			[],
			$this->msg( 'userrights-changeable-col', count( $allgroups ) )->text()
		);

		$ret .= "</tr>\n<tr>\n";
		$uiLanguage = $this->getLanguage();

		$ret .= "\t<td style='vertical-align:top;'>\n";
		foreach ( $allgroups as $group ) {
			$set = array_key_exists( $group, $currentGroups );

			$member = $uiLanguage->getGroupMemberName( $group, $user->getName() );
			$id = "wpGroup-$group";
			$checkboxHtml = Html::element( 'input', [
				'class' => 'mw-userrights-groupcheckbox',
				'type' => 'checkbox', 'value' => '1', 'checked' => $set,
				'id' => $id, 'name' => $id,
			] ) . '&nbsp;' . Html::label( $member, $id );

			$uiUser = $this->getUser();

			$currentExpiry = $currentGroups[$group] ?? null;

			$expiryHtml = Html::element( 'span', [],
				$this->msg( 'userrights-expiry' )->text() );
			$expiryHtml .= Html::openElement( 'span' );

			// add a form element to set the expiry date
			$expiryFormOptions = new XmlSelect(
				"wpExpiry-$group",
				// forward compatibility with HTMLForm
				"mw-input-wpExpiry-$group",
				$currentExpiry ? 'existing' : 'infinite'
			);

			if ( $currentExpiry ) {
				$timestamp = $uiLanguage->userTimeAndDate( $currentExpiry, $uiUser );
				$d = $uiLanguage->userDate( $currentExpiry, $uiUser );
				$t = $uiLanguage->userTime( $currentExpiry, $uiUser );
				$existingExpiryMessage = $this->msg( 'userrights-expiry-existing',
					$timestamp, $d, $t );
				$expiryFormOptions->addOption( $existingExpiryMessage->text(), 'existing' );
			}

			$expiryFormOptions->addOption(
				$this->msg( 'userrights-expiry-none' )->text(),
				'infinite'
			);
			$expiryFormOptions->addOption(
				$this->msg( 'userrights-expiry-othertime' )->text(),
				'other'
			);

			$expiryFormOptions->addOptions( $expiryOptions );

			// Add expiry dropdown
			$expiryHtml .= $expiryFormOptions->getHTML() . '<br />';

			// Add custom expiry field
			$attribs = [
				'id' => "mw-input-wpExpiry-$group-other",
				'class' => 'mw-userrights-expiryfield',
				'size' => 30,
			];
			$expiryHtml .= Html::input( "wpExpiry-$group-other", '', 'text', $attribs );

			$expiryHtml .= Html::closeElement( 'span' );

			$divAttribs = [
				'id' => "mw-userrights-nested-wpGroup-$group",
				'class' => 'mw-userrights-nested',
			];
			$checkboxHtml .= "\t\t\t" . Html::rawElement( 'div', $divAttribs, $expiryHtml ) . "\n";

			$ret .= "\t\t" . Html::rawElement( 'div', [], $checkboxHtml
			) . "\n";
		}
		$ret .= "\t</td>\n";

		$ret .= Html::closeElement( 'tr' ) . Html::closeElement( 'table' );

		return $ret;
	}

	/**
	 * @return string[]
	 */
	private function changeableGroups() {
		if ( $this->getContext()->getAuthority()->isAllowed( 'globalgroupmembership' ) ) {
			return $this->globalGroupLookup->getDefinedGroups();
		}
		return [];
	}

	/**
	 * @param CentralAuthUser $user
	 * @param OutputPage $output
	 */
	private function showLogFragment( $user, $output ) {
		$logPage = new LogPage( 'gblrights' );
		$output->addHTML( Html::element( 'h2', [], $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract(
			$output,
			'gblrights',
			$this->titleFactory->makeTitle( NS_USER, $user->getName() )
		);
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
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}
}
