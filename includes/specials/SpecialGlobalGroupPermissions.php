<?php
# This file is part of MediaWiki.

# MediaWiki is free software: you can redistribute it and/or modify
# it under the terms of version 2 of the GNU General Public License
# as published by the Free Software Foundation.

# MediaWiki is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

/**
 * Special page to allow managing global groups
 * Prototype for a similar system in core.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialGlobalGroupPermissions extends SpecialPage {
	public function __construct() {
		parent::__construct( 'GlobalGroupPermissions' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	function userCanEdit( $user ) {
		$globalUser = CentralAuthUser::getInstance( $user );

		# # Should be a global user
		if ( !$globalUser->exists() || !$globalUser->isAttached() ) {
			return false;
		}

		# Check the wiki is global action permitted wikis - T194232
		if ( !CentralAuthUtils::isPermittedGlobalActionWiki() ) {
			return false;
		}

		return $user->isAllowed( 'globalgrouppermissions' );
	}

	function execute( $subpage ) {
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->getOutput()->setPageTitle( $this->msg( 'globalgrouppermissions' ) );

		$this->getOutput()->addModuleStyles( 'ext.centralauth.globalgrouppermissions' );
		$this->getOutput()->setRobotPolicy( "noindex,nofollow" );
		$this->getOutput()->setArticleRelated( false );
		$this->getOutput()->enableClientCache( false );

		if ( $subpage == '' ) {
			$subpage = $this->getRequest()->getVal( 'wpGroup' );
		}

		if (
			$subpage != ''
			&& $this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) )
			&& $this->getRequest()->wasPosted()
		) {
			$this->doSubmit( $subpage );
		} elseif ( $subpage != '' ) {
			$this->buildGroupView( $subpage );
		} else {
			$this->buildMainView();
		}
	}

	function buildMainView() {
		global $wgScript;
		$out = $this->getOutput();
		$groups = CentralAuthUser::availableGlobalGroups();

		if ( count( $groups ) ) {
			$out->addHTML(
				$this->msg( 'centralauth-globalgroupperms-groups-intro' )->parseAsBlock()
					. $this->getGlobalGroupsTable( $groups )
			);
		} else {
			$out->addWikiMsg( 'centralauth-globalgroupperms-nogroups' );
		}

		if ( $this->userCanEdit( $this->getUser() ) ) {
			// "Create a group" prompt
			// @todo Move this out of main view to a separate page
			$html = Xml::fieldset( $this->msg( 'centralauth-newgroup-legend' )->text() );
			$html .= $this->msg( 'centralauth-newgroup-intro' )->parseAsBlock();
			$html .= Xml::openElement( 'form', [
				'method' => 'post',
				'action' => $wgScript,
				'name' => 'centralauth-globalgroups-newgroup'
			] );
			$html .= Html::hidden( 'title',
				SpecialPage::getTitleFor( 'GlobalGroupPermissions' )->getPrefixedText() );

			$fields = [ 'centralauth-globalgroupperms-newgroupname' => Xml::input( 'wpGroup' ) ];

			$html .= Xml::buildForm( $fields, 'centralauth-globalgroupperms-creategroup-submit' );
			$html .= Xml::closeElement( 'form' );
			$html .= Xml::closeElement( 'fieldset' );

			$out->addHTML( $html );
		}
	}

	/**
	 * @param array $groups
	 * @return string HTML for the group permissions table
	 */
	protected function getGlobalGroupsTable( $groups ) {
		$table = Html::openElement( 'table',
			[ 'class' => 'mw-centralauth-groups-table wikitable' ] );

		// Header stuff
		$table .= Html::openElement( 'tr' );
		$table .= Html::element( 'th', [],
			$this->msg( 'centralauth-globalgroupperms-group' )->text()
		);
		$table .= Html::element( 'th', [],
			$this->msg( 'centralauth-globalgroupperms-rights' )->text()
		);
		$table .= Html::closeElement( 'tr' );

		foreach ( $groups as $groupName ) {
			$groupInfo = $this->getGroupInfo( $groupName );
			$wikiset = $groupInfo['wikiset'];

			$table .= Html::openElement( 'tr' );

			// Column with group name, links and local disabled status
			$table .= Html::openElement( 'td' );
			$table .= $this->getOutput()->parseInline(
				UserGroupMembership::getLink( $groupName, $this->getContext(), 'wiki' ) ) . '<br />';

			$linkRenderer = $this->getLinkRenderer();
			$links = [
				$linkRenderer->makeKnownLink(
					$this->getPageTitle( $groupName ),
					$this->msg( 'centralauth-globalgroupperms-management' )->text()
				),
				$linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'GlobalUsers', $groupName ),
					$this->msg( 'centralauth-globalgroupperms-group-listmembers' )->text()
				),
			];
			$table .= $this->msg( 'parentheses' )
				->rawParams( $this->getLanguage()->pipeList( $links ) )->escaped();

			if ( $wikiset !== null && !$wikiset['enabledHere'] ) {
				$table .= '<br /><small>';
				$table .= $this->msg( 'centralauth-globalgroupperms-group-disabled' )->escaped() .
					'</small>';
			}
			$table .= Html::closeElement( 'td' );

			// Column for wikiset info and group rights list
			$table .= Html::openElement( 'td' );
			if ( $wikiset === null ) {
				$table .= $this->msg( 'centralauth-globalgroupperms-wikiset-none' )->escaped();
			} else {
				$table .= $this->msg( 'centralauth-globalgroupperms-group-wikiset' )
					->rawParams(
						$linkRenderer->makeKnownLink(
							SpecialPage::getTitleFor( 'WikiSets', $wikiset['id'] ),
							$wikiset['name']
						)
					)->escaped();
			}

			$table .= '<hr />';

			$rightsList = '';
			foreach ( $groupInfo['rights'] as $right ) {
				$rightsList .= Html::rawElement( 'li', [], $this->formatRight( $right ) );
			}
			$table .= '<ul>' . $rightsList . '</ul>';
			$table .= Html::closeElement( 'td' );

			$table .= Html::closeElement( 'tr' );
		}

		$table .= Html::closeElement( 'table' );

		return $table;
	}

	/**
	 * @param string $group The group's name
	 * @return array
	 * 	 - rights: string The list of rights assigned to the group
	 *   - wikiset: array|null Either array with id, name, enabledHere or
	 *      null if the group is not associated to any wikiset
	 * @throws Exception
	 */
	protected function getGroupInfo( $group ) {
		$info = [ 'rights' => $this->getAssignedRights( $group ) ];

		$wikiset = WikiSet::getWikiSetForGroup( $group );
		if ( $wikiset !== 0 ) {
			$wikiset = WikiSet::newFromID( $wikiset );
			if ( !$wikiset ) {
				throw new Exception( "__METHOD__: $group with unknown wikiset." );
			}
			$info['wikiset'] = [
				'id' => $wikiset->getId(),
				'name' => $wikiset->getName(),
				'enabledHere' => $wikiset->inSet(),
			];
		} else {
			$info['wikiset'] = null;
		}

		return $info;
	}

	/**
	 * @param string $group
	 */
	function buildGroupView( $group ) {
		$editable = $this->userCanEdit( $this->getUser() );

		$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );

		$fieldsetClass = $editable
			? 'mw-centralauth-editgroup'
			: 'mw-centralauth-editgroup-readonly';
		$html = Xml::fieldset(
			$this->msg( 'centralauth-editgroup-fieldset', $group )->text(),
			false,
			[ 'class' => $fieldsetClass ]
		);

		if ( $editable ) {
			$html .= Xml::openElement( 'form', [
				'method' => 'post',
				'action' =>
					SpecialPage::getTitleFor( 'GlobalGroupPermissions', $group )->getLocalUrl(),
				'name' => 'centralauth-globalgroups-newgroup'
			] );
			$html .= Html::hidden( 'wpGroup', $group );
			$html .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		}

		$fields = [];

		if ( $editable ) {
			$fields['centralauth-editgroup-name'] = Xml::input( 'wpGlobalGroupName', 50, $group );
		} else {
			$fields['centralauth-editgroup-name'] = htmlspecialchars( $group );
		}

		if ( $this->getUser()->isAllowed( 'editinterface' ) ) {
			# Show edit link only to user with the editinterface right
			$fields['centralauth-editgroup-display'] = $this->msg(
				'centralauth-editgroup-display-edit',
				$group,
				UserGroupMembership::getGroupName( $group )
			)->parse();
			$fields['centralauth-editgroup-member'] = $this->msg(
				'centralauth-editgroup-member-edit',
				$group,
				UserGroupMembership::getGroupMemberName( $group, '#' )
			)->parse();
		} else {
			$fields['centralauth-editgroup-display'] =
				htmlspecialchars( UserGroupMembership::getGroupName( $group ) );
			$fields['centralauth-editgroup-member'] =
				htmlspecialchars( UserGroupMembership::getGroupMemberName( $group, '#' ) );
		}
		$fields['centralauth-editgroup-members'] = $this->msg(
			'centralauth-editgroup-members-link',
			$group,
			UserGroupMembership::getGroupMemberName( $group, '#' )
		)->parse();
		$fields['centralauth-editgroup-restrictions'] = $this->buildWikiSetSelector( $group );
		$fields['centralauth-editgroup-perms'] = $this->buildCheckboxes( $group );

		if ( $editable ) {
			$fields['centralauth-editgroup-reason'] = Xml::input( 'wpReason', 60 );
		}

		$html .= Xml::buildForm( $fields,  $editable ? 'centralauth-editgroup-submit' : null );

		if ( $editable ) {
			$html .= Xml::closeElement( 'form' );
		}

		$html .= Xml::closeElement( 'fieldset' );

		$this->getOutput()->addHTML( $html );

		$this->showLogFragment( $group, $this->getOutput() );
	}

	/**
	 * @param string $group
	 * @return string
	 */
	function buildWikiSetSelector( $group ) {
		$sets = WikiSet::getAllWikiSets();
		$default = WikiSet::getWikiSetForGroup( $group );

		if ( !$this->userCanEdit( $this->getUser() ) ) {
			$set = WikiSet::newFromID( $default );
			if ( $set ) {
				return $this->getLinkRenderer()->makeLink(
					SpecialPage::getTitleFor( 'WikiSets', $set->getId() ),
					$set->getName()
				);
			} else {
				return $this->msg( 'centralauth-editgroup-nowikiset' )->parse();
			}
		}

		$select = new XmlSelect( 'set', 'wikiset', $default );
		$select->addOption( $this->msg( 'centralauth-editgroup-noset' )->text(), '0' );
		/**
		 * @var $set WikiSet
		 */
		foreach ( $sets as $set ) {
			$select->addOption( $set->getName(), $set->getID() );
		}

		$editlink = $this->msg( 'centralauth-editgroup-editsets' )->parse();
		return $select->getHTML() . "&#160;{$editlink}";
	}

	/**
	 * @param string $group
	 * @return string
	 */
	function buildCheckboxes( $group ) {
		$editable = $this->userCanEdit( $this->getUser() );

		$assignedRights = $this->getAssignedRights( $group );

		$checkboxes = [];
		$attribs = [];

		if ( !$editable ) {
			$attribs['disabled'] = 'disabled';
			if ( !$assignedRights ) {
				$this->getOutput()->wrapWikiMsg( '<div class="error">$1</div>',
					[ 'centralauth-editgroup-nonexistent', $group ] );
			}
		}

		$rights = User::getAllRights();
		sort( $rights );

		foreach ( $rights as $right ) {
			// Build a checkbox
			$checked = in_array( $right, $assignedRights );

			$desc = $this->formatRight( $right );

			$checkbox = Xml::check( "wpRightAssigned-$right", $checked,
				array_merge( $attribs, [ 'id' => "wpRightAssigned-$right" ] ) );
			$label = Xml::tags( 'label', [ 'for' => "wpRightAssigned-$right" ],
					$desc );

			$liClass = $checked
				? 'mw-centralauth-editgroup-checked'
				: 'mw-centralauth-editgroup-unchecked';
			$checkboxes[] = Html::rawElement(
				'li', [ 'class' => $liClass ], "$checkbox&#160;$label" );
		}

		$count = count( $checkboxes );

		$html = Html::openElement( 'div', [ 'class' => 'mw-centralauth-rights' ] )
			. '<ul>';

		foreach ( $checkboxes as $cb ) {
			$html .= $cb;
		}

		$html .= '</ul>'
			. Html::closeElement( 'div' );

		return $html;
	}

	/**
	 * Given a user right name, return HTML with the description
	 * of the right and it's name for displaying to the user
	 * @param string $right
	 * @return string escaped html
	 */
	protected function formatRight( $right ) {
		$rightDesc = $this->msg(
			'listgrouprights-right-display',
			User::getRightDescription( $right ),
			Html::element(
				'span',
				[ 'class' => 'mw-listgrouprights-right-name' ],
				$right
			)
		)->parse();

		return $rightDesc;
	}

	/**
	 * @param string $group
	 * @return array
	 */
	function getAssignedRights( $group ) {
		return CentralAuthUser::globalGroupPermissions( $group );
	}

	/**
	 * @param string $group
	 */
	function doSubmit( $group ) {
		// It is important to check userCanEdit, as otherwise an
		// unauthorized user could manually construct a POST request.
		if ( !$this->userCanEdit( $this->getUser() ) ) {
			return;
		}
		$reason = $this->getRequest()->getVal( 'wpReason', '' );

		// Current name of the group
		$group = Title::newFromText( $group );
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'centralauth-editgroup-invalid-name' );
			return;
		}
		$group = $group->getUserCaseDBKey();

		// (Potentially) New name of the group
		$newname = $this->getRequest()->getVal( 'wpGlobalGroupName', $group );

		$newname = Title::newFromText( $newname );
		if ( !$newname ) {
			$this->getOutput()->addWikiMsg( 'centralauth-editgroup-invalid-name' );
			return;
		}
		$newname = $newname->getUserCaseDBKey();

		if ( $group != $newname ) {
			if ( in_array( $newname, CentralAuthUser::availableGlobalGroups() ) ) {
				$this->getOutput()->addWikiMsg( 'centralauth-editgroup-rename-taken', $newname );
				return;
			}

			$dbw = CentralAuthUtils::getCentralDB();
			$updates = [
				'global_group_permissions' => 'ggp_group',
				'global_group_restrictions' => 'ggr_group',
				'global_user_groups' => 'gug_group'
			];

			foreach ( $updates as $table => $field ) {
				$dbw->update(
					$table,
					[ $field => $newname ],
					[ $field => $group ],
					__METHOD__
				);
			}
			$this->addRenameLog( $group, $newname, $reason );

			// The rest of the changes here will be performed on the "new" group
			$group = $newname;
		}

		// Permissions
		$addRights = [];
		$removeRights = [];
		$oldRights = $this->getAssignedRights( $group );
		$allRights = User::getAllRights();

		foreach ( $allRights as $right ) {
			$alreadyAssigned = in_array( $right, $oldRights );

			if ( !$alreadyAssigned && $this->getRequest()->getCheck( "wpRightAssigned-$right" ) ) {
				$addRights[] = $right;
			} elseif ( $alreadyAssigned &&
				!$this->getRequest()->getCheck( "wpRightAssigned-$right" )
			) {
				$removeRights[] = $right;
			} # Otherwise, do nothing.
		}

		// Assign the rights.
		if ( count( $addRights ) > 0 ) {
			$this->grantRightsToGroup( $group, $addRights );
		}
		if ( count( $removeRights ) > 0 ) {
			$this->revokeRightsFromGroup( $group, $removeRights );
		}

		// Log it
		if ( !( count( $addRights ) == 0 && count( $removeRights ) == 0 ) ) {
			$this->addPermissionLog( $group, $addRights, $removeRights, $reason );
		}

		// Change set
		$current = WikiSet::getWikiSetForGroup( $group );
		$new = $this->getRequest()->getVal( 'set' );
		if ( $current != $new ) {
			$this->setRestrictions( $group, $new );
			$this->addWikiSetLog( $group, $current, $new, $reason );
		}

		$this->invalidateRightsCache( $group );

		// Display success
		$this->getOutput()->setSubTitle( $this->msg( 'centralauth-editgroup-success' ) );
		$this->getOutput()->addWikiMsg( 'centralauth-editgroup-success-text', $group );
	}

	/**
	 * @param string $group
	 * @param string[] $rights
	 */
	function revokeRightsFromGroup( $group, $rights ) {
		$dbw = CentralAuthUtils::getCentralDB();

		# Delete from the DB
		$dbw->delete(
			'global_group_permissions',
			[ 'ggp_group' => $group, 'ggp_permission' => $rights ],
			__METHOD__
		);
	}

	/**
	 * @param string $group
	 * @param string[]|string $rights
	 */
	function grantRightsToGroup( $group, $rights ) {
		$dbw = CentralAuthUtils::getCentralDB();

		if ( !is_array( $rights ) ) {
			$rights = [ $rights ];
		}

		$insertRows = [];
		foreach ( $rights as $right ) {
			$insertRows[] = [ 'ggp_group' => $group, 'ggp_permission' => $right ];
		}

		# Replace into the DB
		$dbw->replace(
			'global_group_permissions',
			[ 'ggp_group', 'ggp_permission' ],
			$insertRows,
			__METHOD__
		);
	}

	/**
	 * @param string $group
	 * @param OutputPage $output
	 */
	protected function showLogFragment( $group, $output ) {
		$title = SpecialPage::getTitleFor( 'GlobalUsers', $group );
		$logPage = new LogPage( 'gblrights' );
		$output->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract( $output, 'gblrights', $title->getPrefixedText() );
	}

	/**
	 * Log permission changes
	 *
	 * @param string $group
	 * @param string[] $addRights
	 * @param string[] $removeRights
	 * @param string $reason
	 */
	function addPermissionLog( $group, $addRights, $removeRights, $reason ) {
		$log = new LogPage( 'gblrights' );

		$log->addEntry(
			'groupprms2',
			SpecialPage::getTitleFor( 'GlobalUsers', $group ),
			$reason,
			[
				$this->makeRightsList( $addRights ),
				$this->makeRightsList( $removeRights )
			]
		);
	}

	/**
	 * Log the renaming of a global group
	 *
	 * @param string $oldName
	 * @param string $newName
	 * @param string $reason
	 */
	function addRenameLog( $oldName, $newName, $reason ) {
		$log = new LogPage( 'gblrights' );

		$log->addEntry(
			'grouprename',
			// This has to point to 'Special:GlobalUsers so that self::showLogFragment can find it
			SpecialPage::getTitleFor( 'GlobalUsers', $newName ),
			$reason,
			[
				SpecialPage::getTitleFor( 'GlobalGroupPermissions', $newName ),
				SpecialPage::getTitleFor( 'GlobalGroupPermissions', $oldName )
			]
		);
	}

	/**
	 * Log wikiset changes
	 *
	 * @param string $group
	 * @param string $old
	 * @param string $new
	 * @param string $reason
	 */
	function addWikiSetLog( $group, $old, $new, $reason ) {
		$log = new LogPage( 'gblrights' );

		$log->addEntry(
			'groupprms3',
			SpecialPage::getTitleFor( 'GlobalUsers', $group ),
			$reason,
			[
				$this->getWikiSetName( $old ),
				$this->getWikiSetName( $new ),
			]
		);
	}

	/**
	 * @param string[] $ids
	 * @return string
	 */
	function makeRightsList( $ids ) {
		return (bool)count( $ids )
			? implode( ', ', $ids )
			: $this->msg( 'rightsnone' )->inContentLanguage()->text();
	}

	/**
	 * @param string $group
	 * @param int $set
	 * @return bool
	 */
	function setRestrictions( $group, $set ) {
		$dbw = CentralAuthUtils::getCentralDB();
		if ( $set == 0 ) {
			$dbw->delete(
				'global_group_restrictions',
				[ 'ggr_group' => $group ],
				__METHOD__
			);
		} else {
			$dbw->replace( 'global_group_restrictions',
				[ 'ggr_group' ],
				[ 'ggr_group' => $group, 'ggr_set' => $set, ],
				__METHOD__
			);
		}
		return (bool)$dbw->affectedRows();
	}

	/**
	 * @param string|int $id
	 * @return string
	 */
	function getWikiSetName( $id ) {
		if ( $id ) {
			return WikiSet::newFromID( $id )->getName();
		} else {
			return $this->msg( 'centralauth-editgroup-noset' )->inContentLanguage()->text();
		}
	}

	/**
	 * @param string $group
	 */
	function invalidateRightsCache( $group ) {
		// Figure out all the users in this group.
		// Use the master over here as this could go horribly wrong with newly created or just
		// renamed groups
		$dbr = CentralAuthUtils::getCentralDB();

		$res = $dbr->select(
			[ 'global_user_groups', 'globaluser' ],
			'gu_name',
			[ 'gug_group' => $group, 'gu_id=gug_user' ],
			__METHOD__
		);

		// Invalidate their rights cache.
		foreach ( $res as $row ) {
			// Use READ_LATEST for paranoia, though the DB isn't used in this method
			$cu = CentralAuthUser::getMasterInstanceByName( $row->gu_name );
			$cu->quickInvalidateCache();
		}
	}

	protected function getGroupName() {
		return 'users';
	}
}
