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

use HTMLForm;
use LogEventsList;
use LogPage;
use ManualLogEntry;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\Widget\HTMLGlobalUserTextField;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Specials\SpecialUserRights;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use PermissionsError;
use UserBlockedError;
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
	 * @var null|CentralAuthUser The user object of the target username or null.
	 */
	protected $mFetchedUser = null;

	/** @var bool */
	protected $isself = false;

	private TitleFactory $titleFactory;

	/** @var UserNamePrefixSearch */
	private $userNamePrefixSearch;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var GlobalGroupLookup */
	private $globalGroupLookup;

	/**
	 * @param TitleFactory $titleFactory
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 * @param UserNameUtils $userNameUtils
	 * @param GlobalGroupLookup $globalGroupLookup
	 */
	public function __construct(
		TitleFactory $titleFactory,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils,
		GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( 'GlobalGroupMembership' );
		$this->titleFactory = $titleFactory;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
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

		$out->addModules( [ 'mediawiki.special.userrights' ] );

		$this->mTarget = $par ?? $request->getVal( 'user' );

		if ( $this->mTarget !== null && $this->mTarget === $user->getName() ) {
			$this->isself = true;
		}

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
				$this->getOutput()->addWikiTextAsInterface(
					$fetchedStatus->getWikiText( false, false, $this->getLanguage() )
				);

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
		$existingGroups = $user->getGlobalGroupsWithExpiration();

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

				// if the user can only add this group (not remove it), the expiry time
				// cannot be brought forward (T156784)
				if ( !$this->canRemove( $group ) &&
					isset( $existingGroups[$group] ) &&
					( $existingGroups[$group] ?: 'infinity' ) >
						( $groupExpiries[$group] ?: 'infinity' )
				) {
					return Status::newFatal( 'userrights-cannot-shorten-expiry', $group );
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
	 * @param CentralAuthUser $user
	 * @param array $add Array of groups to add
	 * @param array $remove Array of groups to remove
	 * @param string $reason Reason for group change
	 * @param string[] $tags Array of change tags to add to the log entry
	 * @param array $groupExpiries Associative array of (group name => expiry),
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
		$isself = $user->getName() == $this->getUser()->getName();
		$groups = $user->getGlobalGroupsWithExpiration();
		$changeable = $this->changeableGroups();
		$addable = array_merge( $changeable['add'], $isself ? $changeable['add-self'] : [] );
		$removable = array_merge( $changeable['remove'], $isself ? $changeable['remove-self'] : [] );

		$remove = array_unique( array_intersect( $remove, $removable, array_keys( $groups ) ) );
		$add = array_intersect( $add, $addable );

		// add only groups that are not already present or that need their expiry updated,
		// UNLESS the user can only add this group (not remove it) and the expiry time
		// is being brought forward (T156784)
		$add = array_filter( $add,
			static function ( $group ) use ( $groups, $groupExpiries, $removable ) {
				if ( isset( $groupExpiries[$group] ) &&
					isset( $groups[$group] ) &&
					!in_array( $group, $removable ) &&
					( $groups[$group] ?: 'infinity' ) >
						( $groupExpiries[$group] ?: 'infinity' )
				) {
					return false;
				}
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal False positive
				return !in_array( $group, $groups ) || array_key_exists( $group, $groupExpiries );
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

		// Only add a log entry if something actually changed
		if ( $groups !== $newGroups ) {
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
			$this->getOutput()->addWikiTextAsInterface(
				$status->getWikiText( false, false, $this->getLanguage() )
			);

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
			$username = $this->userNameUtils->getCanonical( $username );
			if ( !is_string( $username ) ) {
				// $username was invalid, return nosuchuser.
				return Status::newFatal( 'nosuchusershort', $username );
			}

			// If the user exists, but is hidden from the viewer, pretend that it does
			// not exist. - T285190
			$globalUser = CentralAuthUser::getPrimaryInstanceByName( $username );
			if (
				!$globalUser->exists()
				|| (
					( $globalUser->isSuppressed() || $globalUser->isHidden() )
					&& !$this->getContext()->getAuthority()->isAllowed( 'centralauth-suppress' )
				)
			) {
				return Status::newFatal( 'nosuchusershort', $username );
			}
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
				'default' => $this->mTarget,
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
			// Strip subpage
			->setTitle( $this->getPageTitle() )
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

		[ $groupCheckboxes, $canChangeAny ] = $this->groupCheckboxes( $user );
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
			// Conflict detection
			Html::hidden(
				'conflictcheck-originalgroups',
				implode( ',', $user->getGlobalGroups() )
			) .
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
	 * Adds a table with checkboxes where you can select what groups to add/remove
	 *
	 * @param CentralAuthUser $user
	 * @return array Array with 2 elements: the XHTML table element with checkxboes, and
	 * whether any groups are changeable
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

		// Put all column info into an associative array so that extensions can
		// more easily manage it.
		$columns = [ 'unchangeable' => [], 'changeable' => [] ];

		foreach ( $allgroups as $group ) {
			$set = array_key_exists( $group, $currentGroups );
			// Users who can add the group, but not remove it, can only lengthen
			// expiries, not shorten them. So they should only see the expiry
			// dropdown if the group currently has a finite expiry
			$canOnlyLengthenExpiry = ( $set && $this->canAdd( $group ) &&
				!$this->canRemove( $group ) && $currentGroups[$group] );
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
		$uiLanguage = $this->getLanguage();
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

				$member = $uiLanguage->getGroupMemberName( $group, $user->getName() );
				if ( $checkbox['irreversible'] ) {
					$text = $this->msg( 'userrights-irreversible-marker', $member )->text();
				} elseif ( $checkbox['disabled'] && !$checkbox['disabled-expiry'] ) {
					$text = $this->msg( 'userrights-no-shorten-expiry-marker', $member )->text();
				} else {
					$text = $member;
				}
				$checkboxHtml = Xml::checkLabel( $text, "wpGroup-" . $group,
					"wpGroup-" . $group, $checkbox['set'], $attr );

				$uiUser = $this->getUser();

				$currentExpiry = $currentGroups[$group] ?? null;

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
						// forward compatibility with HTMLForm
						"mw-input-wpExpiry-$group",
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
		if ( $this->getContext()->getAuthority()->isAllowed( 'globalgroupmembership' ) ) {
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
	 * @param CentralAuthUser $user
	 * @param OutputPage $output
	 */
	private function showLogFragment( $user, $output ) {
		$logPage = new LogPage( 'gblrights' );
		$output->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() . "\n" ) );
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
