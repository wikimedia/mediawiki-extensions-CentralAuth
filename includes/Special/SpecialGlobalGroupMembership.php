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

use CommentStore;
use Html;
use HTMLForm;
use Linker;
use LogEventsList;
use LogPage;
use ManualLogEntry;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthGroupMembershipProxy;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\Widget\HTMLGlobalUserTextField;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use OutputPage;
use PermissionsError;
use SpecialPage;
use Status;
use User;
use UserBlockedError;
use UserGroupMembership;
use UserrightsPage;
use UserRightsProxy;
use Xml;
use XmlSelect;

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
	 * @var null|User|CentralAuthGroupMembershipProxy The user object of the target username or null.
	 */
	protected $mFetchedUser = null;

	/** @var bool */
	protected $isself = false;

	/** @var GlobalGroupLookup */
	private $globalGroupLookup;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var UserNamePrefixSearch */
	private $userNamePrefixSearch;

	/**
	 * @param GlobalGroupLookup $globalGroupLookup
	 * @param UserGroupManagerFactory $userGroupManagerFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 */
	public function __construct(
		GlobalGroupLookup $globalGroupLookup,
		UserGroupManagerFactory $userGroupManagerFactory,
		UserNameUtils $userNameUtils,
		UserNamePrefixSearch $userNamePrefixSearch
	) {
		parent::__construct( 'GlobalGroupMembership' );
		$this->userNameUtils = $userNameUtils;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userGroupManager = $userGroupManagerFactory->getUserGroupManager( false );
		$this->globalGroupLookup = $globalGroupLookup;
	}

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

		$out->addModules( [ 'mediawiki.special.userrights' ] );

		$this->mTarget = $par ?? $request->getVal( 'user' );

		if ( is_string( $this->mTarget ) ) {
			$this->mTarget = trim( $this->mTarget );
		}

		if ( $this->mTarget !== null && $this->userNameUtils->getCanonical( $this->mTarget ) === $user->getName() ) {
			$this->isself = true;
		}

		$fetchedStatus = $this->mTarget === null ? Status::newFatal( 'nouserspecified' ) :
			$this->fetchUser( $this->mTarget, true );
		if ( $fetchedStatus->isOK() ) {
			$this->mFetchedUser = $fetchedStatus->value;
			if ( $this->mFetchedUser instanceof User ) {
				// Set the 'relevant user' in the skin, so it displays links like Contributions,
				// User logs, UserRights, etc.
				$this->getSkin()->setRelevantUser( $this->mFetchedUser );
			}
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
				Html::rawElement(
					'div',
					[
						'class' => 'mw-notify-success successbox',
						'id' => 'mw-preferences-success',
						'data-mw-autohide' => 'false',
					],
					Html::element(
						'p',
						[],
						$this->msg( 'savedrights', $this->mFetchedUser->getName() )->text()
					)
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
				$this->getOutput()->addWikiTextAsInterface(
					$fetchedStatus->getWikiText( false, false, $this->getLanguage() )
				);

				return;
			}

			$targetUser = $this->mFetchedUser;
			if ( $targetUser instanceof User ) { // UserRightsProxy doesn't have this method (T63252)
				$targetUser->clearInstanceCache(); // T40989
			}

			$conflictCheck = $request->getVal( 'conflictcheck-originalgroups' );
			$conflictCheck = ( $conflictCheck === '' ) ? [] : explode( ',', $conflictCheck );
			$userGroups = $targetUser->getGroups();

			if ( $userGroups !== $conflictCheck ) {
				$out->addHTML( Html::errorBox(
					$this->msg( 'userrights-conflict' )->parse()
				) );
			} else {
				$status = $this->saveUserGroups(
					$this->mTarget,
					$request->getVal( 'user-reason' ),
					$targetUser
				);

				if ( $status->isOK() ) {
					// Set session data for the success message
					$session->set( 'specialUserrightsSaveSuccess', 1 );

					$out->redirect( $this->getSuccessURL() );
					return;
				} else {
					// Print an error message and redisplay the form
					$out->wrapWikiTextAsInterface(
						'error', $status->getWikiText( false, false, $this->getLanguage() )
					);
				}
			}
		}

		// show some more forms
		if ( $this->mTarget !== null ) {
			$this->editUserGroupsForm( $this->mTarget );
		}
	}

	private function getSuccessURL() {
		return $this->getPageTitle( $this->mTarget )->getFullURL();
	}

	/**
	 * @return bool
	 */
	private function canProcessExpiries() {
		return $this->getConfig()->get( 'CentralAuthEnableTemporaryGlobalGroups' );
	}

	/**
	 * Save user groups changes in the database.
	 * Data comes from the editUserGroupsForm() form function
	 *
	 * @param string $username Username to apply changes to.
	 * @param string $reason Reason for group change
	 * @param User|UserRightsProxy $user Target user object.
	 * @return Status
	 */
	private function saveUserGroups( $username, $reason, $user ) {
		$allgroups = $this->getAllGroups();
		$addgroup = [];
		$groupExpiries = []; // associative array of (group name => expiry)
		$removegroup = [];
		$existingUGMs = $user->getGroupMemberships();

		// This could possibly create a highly unlikely race condition if permissions are changed between
		//  when the form is loaded and when the form is saved. Ignoring it for the moment.
		foreach ( $allgroups as $group ) {
			// We'll tell it to remove all unchecked groups, and add all checked groups.
			// Later on, this gets filtered for what can actually be removed
			if ( $this->getRequest()->getCheck( "wpGroup-$group" ) ) {
				$addgroup[] = $group;

				if ( $this->canProcessExpiries() ) {
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
					$groupExpiries[$group] = UserrightsPage::expiryToTimestamp( $expiryValue );

					if ( $groupExpiries[$group] === false ) {
						return Status::newFatal( 'userrights-invalid-expiry', $group );
					}

					// not allowed to have things expiring in the past
					if ( $groupExpiries[$group] && $groupExpiries[$group] < wfTimestampNow() ) {
						return Status::newFatal( 'userrights-expiry-in-past', $group );
					}

					// if the user can only add this group (not remove it), the expiry time
					// cannot be brought forward (T156784)
					if ( !$this->canRemove( $group ) &&
						isset( $existingUGMs[$group] ) &&
						( $existingUGMs[$group]->getExpiry() ?: 'infinity' ) >
							( $groupExpiries[$group] ?: 'infinity' )
					) {
						return Status::newFatal( 'userrights-cannot-shorten-expiry', $group );
					}
				}
			} else {
				$removegroup[] = $group;
			}
		}

		$this->doSaveUserGroups( $user, $addgroup, $removegroup, $reason, [], $groupExpiries );

		return Status::newGood();
	}

	/**
	 * Save user groups changes in the database. This function does not throw errors;
	 * instead, it ignores groups that the performer does not have permission to set.
	 *
	 * @param User|CentralAuthGroupMembershipProxy $user
	 * @param array $add Array of groups to add
	 * @param array $remove Array of groups to remove
	 * @param string $reason Reason for group change
	 * @param string[] $tags Array of change tags to add to the log entry
	 * @param array $groupExpiries Associative array of (group name => expiry),
	 *   containing only those groups that are to have new expiry values set
	 * @return array Tuple of added, then removed groups
	 */
	public function doSaveUserGroups( $user, array $add, array $remove, $reason = '',
		array $tags = [], array $groupExpiries = []
	) {
		// Validate input set...
		$isself = $user->getName() == $this->getUser()->getName();
		$groups = $user->getGroups();
		$ugms = $user->getGroupMemberships();
		$changeable = $this->changeableGroups();
		$addable = array_merge( $changeable['add'], $isself ? $changeable['add-self'] : [] );
		$removable = array_merge( $changeable['remove'], $isself ? $changeable['remove-self'] : [] );

		$remove = array_unique( array_intersect( $remove, $removable, $groups ) );
		$add = array_intersect( $add, $addable );

		// add only groups that are not already present or that need their expiry updated,
		// UNLESS the user can only add this group (not remove it) and the expiry time
		// is being brought forward (T156784)
		$add = array_filter( $add,
			static function ( $group ) use ( $groups, $groupExpiries, $removable, $ugms ) {
				if ( isset( $groupExpiries[$group] ) &&
					!in_array( $group, $removable ) &&
					isset( $ugms[$group] ) &&
					( $ugms[$group]->getExpiry() ?: 'infinity' ) >
						( $groupExpiries[$group] ?: 'infinity' )
				) {
					return false;
				}
				return !in_array( $group, $groups ) || array_key_exists( $group, $groupExpiries );
			} );

		$this->getHookRunner()->onChangeUserGroups( $this->getUser(), $user, $add, $remove );

		$oldGroups = $groups;
		$oldUGMs = $user->getGroupMemberships();
		$newGroups = $oldGroups;

		// Remove groups, then add new ones/update expiries of existing ones
		if ( $remove ) {
			foreach ( $remove as $index => $group ) {
				if ( !$user->removeGroup( $group ) ) {
					unset( $remove[$index] );
				}
			}
			$newGroups = array_diff( $newGroups, $remove );
		}
		if ( $add ) {
			foreach ( $add as $index => $group ) {
				$expiry = $groupExpiries[$group] ?? null;
				if ( !$user->addGroup( $group, $expiry ) ) {
					unset( $add[$index] );
				}
			}
			$newGroups = array_merge( $newGroups, $add );
		}
		$newGroups = array_unique( $newGroups );
		$newUGMs = $user->getGroupMemberships();

		// Ensure that caches are cleared
		$user->invalidateCache();

		// update groups in external authentication database
		$this->getHookRunner()->onUserGroupsChanged( $user, $add, $remove,
			$this->getUser(), $reason, $oldUGMs, $newUGMs );

		wfDebug( 'oldGroups: ' . print_r( $oldGroups, true ) );
		wfDebug( 'newGroups: ' . print_r( $newGroups, true ) );
		wfDebug( 'oldUGMs: ' . print_r( $oldUGMs, true ) );
		wfDebug( 'newUGMs: ' . print_r( $newUGMs, true ) );

		// Only add a log entry if something actually changed
		if ( $newGroups != $oldGroups || $newUGMs != $oldUGMs ) {
			$this->addLogEntry( $user, $oldGroups, $newGroups, $reason, $tags, $oldUGMs, $newUGMs );
		}

		return [ $add, $remove ];
	}

	/**
	 * Serialise a UserGroupMembership object for storage in the log_params section
	 * of the logging table. Only keeps essential data, removing redundant fields.
	 *
	 * @param UserGroupMembership|null $ugm May be null if things get borked
	 * @return array|null
	 */
	private static function serialiseUgmForLog( $ugm ) {
		if ( !$ugm instanceof UserGroupMembership ) {
			return null;
		}
		return [ 'expiry' => $ugm->getExpiry() ];
	}

	/**
	 * @param User $user
	 * @param array $oldGroups
	 * @param array $newGroups
	 * @param string $reason
	 * @param array $tags Not currently used
	 * @param array $oldUGMs Not currently used
	 * @param array $newUGMs Not currently used
	 */
	private function addLogEntry( $user, array $oldGroups, array $newGroups, $reason,
		array $tags, array $oldUGMs, array $newUGMs
	) {
		// make sure $oldUGMs and $newUGMs are in the same order, and serialise
		// each UGM object to a simplified array
		$oldUGMs = array_map( function ( $group ) use ( $oldUGMs ) {
			return isset( $oldUGMs[$group] )
				? self::serialiseUgmForLog( $oldUGMs[$group] )
				: null;
		}, $oldGroups );

		$newUGMs = array_map( function ( $group ) use ( $newUGMs ) {
		return isset( $newUGMs[$group] )
				? self::serialiseUgmForLog( $newUGMs[$group] )
				: null;
		}, $newGroups );

		$entry = new ManualLogEntry( 'gblrights', 'usergroups' );
		$entry->setTarget( $user->getUserPage() );
		$entry->setPerformer( $this->getUser() );
		$entry->setComment( $reason );
		$entry->setParameters( [
			'oldGroups' => $oldGroups,
			'newGroups' => $newGroups,
			'oldMetadata' => $oldUGMs,
			'newMetadata' => $newUGMs,
		] );
		$logid = $entry->insert();
		$entry->publish( $logid );
	}

	/**
	 * Edit user groups membership
	 * @param string $username Name of the user.
	 */
	private function editUserGroupsForm( $username ) {
		$status = $this->fetchUser( $username, true );
		if ( !$status->isOK() ) {
			$this->getOutput()->addWikiTextAsInterface(
				$status->getWikiText( false, false, $this->getLanguage() )
			);

			return;
		}

		/** @var User|CentralAuthGroupMembershipProxy $user */
		$user = $status->value;
		'@phan-var User|CentralAuthGroupMembershipProxy $user';

		$groups = $user->getGroups();
		$groupMemberships = $user->getGroupMemberships();
		$this->showEditUserGroupsForm( $user, $groups, $groupMemberships );

		// This isn't really ideal logging behavior, but let's not hide the
		// interwiki logs if we're using them as is.
		$this->showLogFragment( $user, $this->getOutput() );
	}

	/**
	 * @param string $username
	 * @param bool $writing
	 * @return Status
	 */
	public function fetchUser( $username, $writing = true ) {
		if ( $username === '' ) {
			return Status::newFatal( 'nouserspecified' );
		}

		if ( $username[0] == '#' ) {
			$id = intval( substr( $username, 1 ) );
			$user = CentralAuthGroupMembershipProxy::newFromId( $id );
			$globalUser = CentralAuthUser::newPrimaryInstanceFromId( $id );

			// If the user exists, but is hidden from the viewer, pretend that it does
			// not exist. - T285190/T260863
			if ( !$user || ( ( $globalUser->isSuppressed() || $globalUser->isHidden() ) &&
				!$this->getContext()->getAuthority()->isAllowed( 'centralauth-suppress' ) )
			) {
				return Status::newFatal( 'noname', $id );
			}
		} else {
			$user = CentralAuthGroupMembershipProxy::newFromName( $username );

			// If the user exists, but is hidden from the viewer, pretend that it does
			// not exist. - T285190
			$globalUser = CentralAuthUser::getPrimaryInstanceByName( $username );
			if ( !$user || ( ( $globalUser->isSuppressed() || $globalUser->isHidden() ) &&
				!$this->getContext()->getAuthority()->isAllowed( 'centralauth-suppress' ) )
			) {
				return Status::newFatal( 'nosuchusershort', $username );
			}
		}

		return Status::newGood( $user );
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
				'default' => $this->mTarget,
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->addHiddenField( 'title', $this->getPageTitle() )
			->setMethod( 'get' )
			->setAction( $this->getConfig()->get( 'Script' ) )
			->setId( 'mw-userrights-form1' )
			->setName( 'uluser' )
			->setSubmitTextMsg( 'editusergroup' )
			->setWrapperLegendMsg( 'userrights-lookup-user' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Show the form to edit group memberships.
	 *
	 * @param User|CentralAuthGroupMembershipProxy $user User or UserRightsProxy you're editing
	 * @param string[] $groups Array of groups the user is in. Not used by this implementation
	 *   anymore, but kept for backward compatibility with subclasses
	 * @param UserGroupMembership[] $groupMemberships Associative array of (group name => UserGroupMembership
	 *   object) containing the groups the user is in
	 */
	private function showEditUserGroupsForm( $user, $groups, $groupMemberships ) {
		$list = $membersList = $tempList = $tempMembersList = [];
		foreach ( $groupMemberships as $ugm ) {
			$linkG = UserGroupMembership::getLink( $ugm, $this->getContext(), 'html' );
			$linkM = UserGroupMembership::getLink( $ugm, $this->getContext(), 'html',
				$user->getName() );
			if ( $ugm->getExpiry() ) {
				$tempList[] = $linkG;
				$tempMembersList[] = $linkM;
			} else {
				$list[] = $linkG;
				$membersList[] = $linkM;

			}
		}

		$autoList = [];
		$autoMembersList = [];

		$isUserInstance = $user instanceof User;

		if ( $isUserInstance ) {
			foreach ( $this->userGroupManager->getUserAutopromoteGroups( $user ) as $group ) {
				$autoList[] = UserGroupMembership::getLink( $group, $this->getContext(), 'html' );
				$autoMembersList[] = UserGroupMembership::getLink( $group, $this->getContext(),
					'html', $user->getName() );
			}
		}

		$language = $this->getLanguage();
		// @phan-suppress-next-line SecurityCheck-XSS T183174
		$displayedList = $this->msg( 'userrights-groupsmember-type' )
			->rawParams(
				$language->commaList( array_merge( $tempList, $list ) ),
				$language->commaList( array_merge( $tempMembersList, $membersList ) )
			)->escaped();
		// @phan-suppress-next-line SecurityCheck-XSS T183174
		$displayedAutolist = $this->msg( 'userrights-groupsmember-type' )
			->rawParams(
				$language->commaList( $autoList ),
				$language->commaList( $autoMembersList )
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

		$count = count( $autoList );
		if ( $count > 0 ) {
			$autogrouplistintro = $this->msg( 'userrights-groupsmember-auto' )
				->numParams( $count )
				->params( $user->getName() )
				->parse();
			$grouplist .= '<p>' . $autogrouplistintro . ' ' . $displayedAutolist . "</p>\n";
		}

		$systemUser = $isUserInstance && $user->isSystemUser();
		if ( $systemUser ) {
			$systemusernote = $this->msg( 'userrights-systemuser' )
				->params( $user->getName() )
				->parse();
			$grouplist .= '<p>' . $systemusernote . "</p>\n";
		}

		// Only add an email link if the user is not a system user
		$flags = $systemUser ? 0 : Linker::TOOL_LINKS_EMAIL;
		$userToolLinks = Linker::userToolLinks(
			$user->getId(),
			$user->getName(),
			false, /* default for redContribsWhenNoEdits */
			$flags
		);

		list( $groupCheckboxes, $canChangeAny ) =
			$this->groupCheckboxes( $groupMemberships, $user );
		$this->getOutput()->addHTML(
			Xml::openElement(
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
			Html::hidden(
				'conflictcheck-originalgroups',
				implode( ',', $user->getGroups() )
			) . // Conflict detection
			Xml::openElement( 'fieldset' ) .
			Xml::element(
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
				$groupCheckboxes .
				Xml::openElement( 'table', [ 'id' => 'mw-userrights-table-outer' ] ) .
					"<tr>
						<td class='mw-label'>" .
							Xml::label( $this->msg( 'userrights-reason' )->text(), 'wpReason' ) .
						"</td>
						<td class='mw-input'>" .
							Xml::input( 'user-reason', 60, $this->getRequest()->getVal( 'user-reason' ) ?? false, [
								'id' => 'wpReason',
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
							Xml::submitButton( $this->msg( 'saveusergroups', $user->getName() )->text(),
								[ 'name' => 'saveusergroups' ] +
									Linker::tooltipAndAccesskeyAttribs( 'userrights-set' )
							) .
						"</td>
					</tr>" .
				Xml::closeElement( 'table' ) . "\n"
			);
		} else {
			$this->getOutput()->addHTML( $grouplist );
		}
		$this->getOutput()->addHTML(
			Xml::closeElement( 'fieldset' ) .
			Xml::closeElement( 'form' ) . "\n"
		);
	}

	/**
	 * @return string[]
	 */
	private static function getAllGroups() {
		return CentralAuthServices::getGlobalGroupLookup()->getDefinedGroups();
	}

	/**
	 * Adds a table with checkboxes where you can select what groups to add/remove
	 *
	 * @param UserGroupMembership[] $usergroups Associative array of (group name as string =>
	 *   UserGroupMembership object) for groups the user belongs to
	 * @param User $user
	 * @return array Array with 2 elements: the XHTML table element with checkxboes, and
	 * whether any groups are changeable
	 */
	private function groupCheckboxes( $usergroups, $user ) {
		$allgroups = $this->getAllGroups();
		$ret = '';

		// Get the list of preset expiry times from the system message
		$expiryOptionsMsg = $this->msg( 'userrights-expiry-options' )->inContentLanguage();
		$expiryOptions = $expiryOptionsMsg->isDisabled()
			? []
			: XmlSelect::parseOptionsMessage( $expiryOptionsMsg->text() );

		// Put all column info into an associative array so that extensions can
		// more easily manage it.
		$columns = [ 'unchangeable' => [], 'changeable' => [] ];

		foreach ( $allgroups as $group ) {
			$set = isset( $usergroups[$group] );
			// Users who can add the group, but not remove it, can only lengthen
			// expiries, not shorten them. So they should only see the expiry
			// dropdown if the group currently has a finite expiry
			$canOnlyLengthenExpiry = ( $set && $this->canAdd( $group ) &&
				!$this->canRemove( $group ) && $usergroups[$group]->getExpiry() );
			// Should the checkbox be disabled?
			$disabledCheckbox = !(
				( $set && $this->canRemove( $group ) ) ||
				( !$set && $this->canAdd( $group ) ) );
			// Should the expiry elements be disabled?
			$disabledExpiry = $disabledCheckbox && !$canOnlyLengthenExpiry;
			// Do we need to point out that this action is irreversible?
			$irreversible = !$disabledCheckbox && (
				( $set && !$this->canAdd( $group ) ) ||
				( !$set && !$this->canRemove( $group ) ) );

			$checkbox = [
				'set' => $set,
				'disabled' => $disabledCheckbox,
				'disabled-expiry' => $disabledExpiry,
				'irreversible' => $irreversible
			];

			if ( $disabledCheckbox && $disabledExpiry ) {
				$columns['unchangeable'][$group] = $checkbox;
			} else {
				$columns['changeable'][$group] = $checkbox;
			}
		}

		// Build the HTML table
		$ret .= Xml::openElement( 'table', [ 'class' => 'mw-userrights-groups' ] ) .
			"<tr>\n";
		foreach ( $columns as $name => $column ) {
			if ( $column === [] ) {
				continue;
			}
			// Messages: userrights-changeable-col, userrights-unchangeable-col
			$ret .= Xml::element(
				'th',
				null,
				$this->msg( 'userrights-' . $name . '-col', count( $column ) )->text()
			);
		}

		$ret .= "</tr>\n<tr>\n";
		foreach ( $columns as $column ) {
			if ( $column === [] ) {
				continue;
			}
			$ret .= "\t<td style='vertical-align:top;'>\n";
			foreach ( $column as $group => $checkbox ) {
				$attr = [ 'class' => 'mw-userrights-groupcheckbox' ];
				if ( $checkbox['disabled'] ) {
					$attr['disabled'] = 'disabled';
				}

				$member = UserGroupMembership::getGroupMemberName( $group, $user->getName() );
				if ( $checkbox['irreversible'] ) {
					$text = $this->msg( 'userrights-irreversible-marker', $member )->text();
				} elseif ( $checkbox['disabled'] && !$checkbox['disabled-expiry'] ) {
					$text = $this->msg( 'userrights-no-shorten-expiry-marker', $member )->text();
				} else {
					$text = $member;
				}
				$checkboxHtml = Xml::checkLabel( $text, "wpGroup-" . $group,
					"wpGroup-" . $group, $checkbox['set'], $attr );

				if ( $this->canProcessExpiries() ) {
					$uiUser = $this->getUser();
					$uiLanguage = $this->getLanguage();

					$currentExpiry = isset( $usergroups[$group] ) ?
						$usergroups[$group]->getExpiry() :
						null;

					// If the user can't modify the expiry, print the current expiry below
					// it in plain text. Otherwise provide UI to set/change the expiry
					if ( $checkbox['set'] &&
						( $checkbox['irreversible'] || $checkbox['disabled-expiry'] )
					) {
						if ( $currentExpiry ) {
							$expiryFormatted = $uiLanguage->userTimeAndDate( $currentExpiry, $uiUser );
							$expiryFormattedD = $uiLanguage->userDate( $currentExpiry, $uiUser );
							$expiryFormattedT = $uiLanguage->userTime( $currentExpiry, $uiUser );
							$expiryHtml = Xml::element( 'span', null,
								$this->msg( 'userrights-expiry-current' )->params(
								$expiryFormatted, $expiryFormattedD, $expiryFormattedT )->text() );
						} else {
							$expiryHtml = Xml::element( 'span', null,
								$this->msg( 'userrights-expiry-none' )->text() );
						}
						// T171345: Add a hidden form element so that other groups can still be manipulated,
						// otherwise saving errors out with an invalid expiry time for this group.
						$expiryHtml .= Html::hidden( "wpExpiry-$group",
							$currentExpiry ? 'existing' : 'infinite' );
						$expiryHtml .= "<br />\n";
					} else {
						$expiryHtml = Xml::element( 'span', null,
							$this->msg( 'userrights-expiry' )->text() );
						$expiryHtml .= Xml::openElement( 'span' );

						// add a form element to set the expiry date
						$expiryFormOptions = new XmlSelect(
							"wpExpiry-$group",
							"mw-input-wpExpiry-$group", // forward compatibility with HTMLForm
							$currentExpiry ? 'existing' : 'infinite'
						);
						if ( $checkbox['disabled-expiry'] ) {
							$expiryFormOptions->setAttribute( 'disabled', 'disabled' );
						}

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
						];
						if ( $checkbox['disabled-expiry'] ) {
							$attribs['disabled'] = 'disabled';
						}
						$expiryHtml .= Xml::input( "wpExpiry-$group-other", 30, '', $attribs );

						// If the user group is set but the checkbox is disabled, mimic a
						// checked checkbox in the form submission
						if ( $checkbox['set'] && $checkbox['disabled'] ) {
							$expiryHtml .= Html::hidden( "wpGroup-$group", 1 );
						}

						$expiryHtml .= Xml::closeElement( 'span' );
					}

					$divAttribs = [
						'id' => "mw-userrights-nested-wpGroup-$group",
						'class' => 'mw-userrights-nested',
					];
					$checkboxHtml .= "\t\t\t" . Xml::tags( 'div', $divAttribs, $expiryHtml ) . "\n";
				}
				$ret .= "\t\t" . ( ( $checkbox['disabled'] && $checkbox['disabled-expiry'] )
					? Xml::tags( 'div', [ 'class' => 'mw-userrights-disabled' ], $checkboxHtml )
					: Xml::tags( 'div', [], $checkboxHtml )
				) . "\n";
			}
			$ret .= "\t</td>\n";
		}
		$ret .= Xml::closeElement( 'tr' ) . Xml::closeElement( 'table' );

		return [ $ret, (bool)$columns['changeable'] ];
	}

	/**
	 * @param string $group The name of the group to check
	 * @return bool Can we remove the group?
	 */
	private function canRemove( $group ) {
		$groups = $this->changeableGroups();

		return in_array(
			$group,
			$groups['remove'] ) || ( $this->isself && in_array( $group, $groups['remove-self'] )
		);
	}

	/**
	 * @param string $group The name of the group to check
	 * @return bool Can we add the group?
	 */
	private function canAdd( $group ) {
		$groups = $this->changeableGroups();

		return in_array(
			$group,
			$groups['add'] ) || ( $this->isself && in_array( $group, $groups['add-self'] )
		);
	}

	/**
	 * @return array[]
	 */
	private function changeableGroups() {
		$globalUser = CentralAuthUser::getInstance( $this->getUser() );
		if (
			$globalUser->exists() &&
			$globalUser->isAttached() &&
			$this->getContext()->getAuthority()->isAllowed( 'globalgroupmembership' )
		) {
			$allGroups = $this->globalGroupLookup->getDefinedGroups();

			# specify addself and removeself as empty arrays - T18098
			return [
				'add' => $allGroups,
				'remove' => $allGroups,
				'add-self' => [],
				'remove-self' => []
			];
		}

		return [
			'add' => [],
			'remove' => [],
			'add-self' => [],
			'remove-self' => []
		];
	}

	/**
	 * @param User|CentralAuthGroupMembershipProxy $user
	 * @param OutputPage $output
	 */
	private function showLogFragment( $user, $output ) {
		$logPage = new LogPage( 'gblrights' );
		$output->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract( $output, 'gblrights', $user->getUserPage() );
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

	protected function getGroupName() {
		return 'users';
	}
}
