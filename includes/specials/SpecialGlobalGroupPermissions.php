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
	function __construct() {
		parent::__construct( 'GlobalGroupPermissions' );
	}

	/**
	 * @param $user
	 * @return bool
	 */
	function userCanEdit( $user ) {
		$globalUser = CentralAuthUser::getInstance( $user );

		# # Should be a global user
		if ( !$globalUser->exists() || !$globalUser->isAttached() ) {
			return false;
		}

		# # Permission MUST be gained from global rights.
		return $globalUser->hasGlobalPermission( 'globalgrouppermissions' );
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

		if ( $subpage != '' && $this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) ) ) {
			$this->doSubmit( $subpage );
		} elseif ( $subpage != '' ) {
			$this->buildGroupView( $subpage );
		} else {
			$this->buildMainView();
		}
	}

	function buildMainView() {
		global $wgScript;

		$groups = CentralAuthUser::availableGlobalGroups();

		// Existing groups
		$html = Xml::fieldset( $this->msg( 'centralauth-existinggroup-legend' )->text() );

		$this->getOutput()->addHTML( $html );

		if ( count( $groups ) ) {
			$this->getOutput()->addWikiMsg( 'centralauth-globalgroupperms-grouplist' );
			$this->getOutput()->addHTML( Xml::openElement( 'ul' ) );

			foreach ( $groups as $group ) {
				$text = $this->msg(
					'centralauth-globalgroupperms-grouplistitem',
					User::getGroupName( $group ),
					$group,
					Xml::element( 'span',
						array( "class" => "centralauth-globalgroupperms-groupname"),
						$group
					)
				)->parse();
				$this->getOutput()->addHTML( Html::rawElement( 'li', null, ' ' . $text . ' ' ) );
			}
		} else {
			$this->getOutput()->addWikiMsg( 'centralauth-globalgroupperms-nogroups' );
		}

		$this->getOutput()->addHTML( Xml::closeElement( 'ul' ) . Xml::closeElement( 'fieldset' ) );

		if ( $this->userCanEdit( $this->getUser() ) ) {
			// "Create a group" prompt
			$html = Xml::fieldset( $this->msg( 'centralauth-newgroup-legend' )->text() );
			$html .= $this->msg( 'centralauth-newgroup-intro' )->parseAsBlock();
			$html .= Xml::openElement( 'form', array( 'method' => 'post', 'action' => $wgScript, 'name' => 'centralauth-globalgroups-newgroup' ) );
			$html .= Html::hidden( 'title',  SpecialPage::getTitleFor( 'GlobalGroupPermissions' )->getPrefixedText() );

			$fields = array( 'centralauth-globalgroupperms-newgroupname' => Xml::input( 'wpGroup' ) );

			$html .= Xml::buildForm( $fields, 'centralauth-globalgroupperms-creategroup-submit' );
			$html .= Xml::closeElement( 'form' );
			$html .= Xml::closeElement( 'fieldset' );

			$this->getOutput()->addHTML( $html );
		}
	}

	/**
	 * @param $group
	 */
	function buildGroupView( $group ) {
		$editable = $this->userCanEdit( $this->getUser() );

		$subtitleMessage = $editable ? 'centralauth-editgroup-subtitle' : 'centralauth-editgroup-subtitle-readonly';
		$this->getOutput()->setSubtitle( $this->msg( $subtitleMessage, $group ) );

		$fieldsetClass = $editable ? 'mw-centralauth-editgroup' : 'mw-centralauth-editgroup-readonly';
		$html = Xml::fieldset( $this->msg( 'centralauth-editgroup-fieldset', $group )->text(), false, array( 'class' => $fieldsetClass ) );

		if ( $editable ) {
			$html .= Xml::openElement( 'form', array(
				'method' => 'post',
				'action' => SpecialPage::getTitleFor( 'GlobalGroupPermissions', $group )->getLocalUrl(),
				'name' => 'centralauth-globalgroups-newgroup'
			) );
			$html .= Html::hidden( 'wpGroup', $group );
			$html .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		}

		$fields = array();

		if ( $editable ) {
			$fields['centralauth-editgroup-name'] = Xml::input( 'wpGlobalGroupName', 50, $group );
		} else {
			$fields['centralauth-editgroup-name'] = $group;
		}

		if( $this->getUser()->isAllowed( 'editinterface' ) ) {
			# Show edit link only to user with the editinterface right
			$fields['centralauth-editgroup-display'] = $this->msg( 'centralauth-editgroup-display-edit', $group, User::getGroupName( $group ) )->parse();
			$fields['centralauth-editgroup-member'] = $this->msg( 'centralauth-editgroup-member-edit', $group, User::getGroupMember( $group ) )->parse();
		} else {
			$fields['centralauth-editgroup-display'] = User::getGroupName( $group );
			$fields['centralauth-editgroup-member'] = User::getGroupMember( $group );
		}
		$fields['centralauth-editgroup-members'] = $this->msg( 'centralauth-editgroup-members-link', $group, User::getGroupMember( $group ) )->parse();
		$fields['centralauth-editgroup-restrictions'] = $this->buildWikiSetSelector( $group );
		$fields['centralauth-editgroup-perms'] = $this->buildCheckboxes( $group );

		if ( $editable ) {
			$fields['centralauth-editgroup-reason'] = Xml::input( 'wpReason', 60 );
		}

		$html .= Xml::buildForm( $fields,  $editable ? 'centralauth-editgroup-submit' : null );

		if ( $editable )
			$html .= Xml::closeElement( 'form' );

		$html .= Xml::closeElement( 'fieldset' );

		$this->getOutput()->addHTML( $html );

		$this->showLogFragment( $group, $this->getOutput() );
	}

	/**
	 * @param $group
	 * @return string
	 */
	function buildWikiSetSelector( $group ) {
		$sets = WikiSet::getAllWikiSets();
		$default = WikiSet::getWikiSetForGroup( $group );

		if ( !$this->userCanEdit( $this->getUser() ) ) {
			$set = WikiSet::newFromID( $default );
			if ( $set ) {
				return Linker::link(
					SpecialPage::getTitleFor( 'WikiSets', $set->getId() ),
					htmlspecialchars( $set->getName() )
				);
			} else {
				return $this->msg( 'centralauth-editgroup-nowikiset' );
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
	 * @param $group
	 * @return string
	 */
	function buildCheckboxes( $group ) {
		$editable = $this->userCanEdit( $this->getUser() );

		$assignedRights = $this->getAssignedRights( $group );


		$checkboxes = array();
		$attribs = array();

		if ( !$editable ) {
			$attribs['disabled'] = 'disabled';
			if ( !$assignedRights ) {
				$this->getOutput()->wrapWikiMsg( '<div class="error">$1</div>',
					array( 'centralauth-editgroup-nonexistent', $group ) );
			}
		}

		$rights = User::getAllRights();
		sort( $rights );

		foreach ( $rights as $right ) {
			// Build a checkbox
			$checked = in_array( $right, $assignedRights );

			$desc = $this->getOutput()->parseInline( User::getRightDescription( $right ) ) . ' ' .
						Xml::element( 'code', null, $this->msg( 'parentheses', $right )->text() );

			$checkbox = Xml::check( "wpRightAssigned-$right", $checked,
				array_merge( $attribs, array( 'id' => "wpRightAssigned-$right" ) ) );
			$label = Xml::tags( 'label', array( 'for' => "wpRightAssigned-$right" ),
					$desc );

			$liClass = $checked ? 'mw-centralauth-editgroup-checked' : 'mw-centralauth-editgroup-unchecked';
			$checkboxes[] = Html::rawElement( 'li', array( 'class' => $liClass ), "$checkbox&#160;$label" );
		}

		$count = count( $checkboxes );

		$html =  Html::openElement( 'div', array( 'class' => 'mw-centralauth-rights' ) )
			. '<ul>';

		foreach ( $checkboxes as $cb ) {
			$html .= $cb;
		}

		$html .= '</ul>'
			. Html::closeElement( 'div' );

		return $html;
	}

	/**
	 * @param $group
	 * @return array
	 */
	function getAssignedRights( $group ) {
		return CentralAuthUser::globalGroupPermissions( $group );
	}

	/**
	 * @param $group string
	 */
	function doSubmit( $group ) {
		// Paranoia -- the edit token shouldn't match anyway
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
			$updates = array(
				'global_group_permissions' => 'ggp_group',
				'global_group_restrictions' => 'ggr_group',
				'global_user_groups' => 'gug_group'
			);

			foreach ( $updates as $table => $field ) {
				$dbw->update(
					$table,
					array( $field => $newname ),
					array( $field => $group ),
					__METHOD__
				);
			}
			$this->addRenameLog( $group, $newname, $reason );

			// The rest of the changes here will be performed on the "new" group
			$group = $newname;
		}

		// Permissions
		$addRights = array();
		$removeRights = array();
		$oldRights = $this->getAssignedRights( $group );
		$allRights = User::getAllRights();

		foreach ( $allRights as $right ) {
			$alreadyAssigned = in_array( $right, $oldRights );

			if ( !$alreadyAssigned && $this->getRequest()->getCheck( "wpRightAssigned-$right" ) ) {
				$addRights[] = $right;
			} elseif ( $alreadyAssigned && !$this->getRequest()->getCheck( "wpRightAssigned-$right" ) ) {
				$removeRights[] = $right;
			} # Otherwise, do nothing.
		}

		// Assign the rights.
		if ( count( $addRights ) > 0 )
			$this->grantRightsToGroup( $group, $addRights );
		if ( count( $removeRights ) > 0 )
			$this->revokeRightsFromGroup( $group, $removeRights );

		// Log it
		if ( !( count( $addRights ) == 0 && count( $removeRights ) == 0 ) )
			$this->addPermissionLog( $group, $addRights, $removeRights, $reason );

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
	 * @param $group
	 * @param $rights
	 */
	function revokeRightsFromGroup( $group, $rights ) {
		$dbw = CentralAuthUtils::getCentralDB();

		# Delete from the DB
		$dbw->delete( 'global_group_permissions', array( 'ggp_group' => $group, 'ggp_permission' => $rights ), __METHOD__ );
	}

	/**
	 * @param $group
	 * @param $rights
	 */
	function grantRightsToGroup( $group, $rights ) {
		$dbw = CentralAuthUtils::getCentralDB();

		if ( !is_array( $rights ) ) {
			$rights = array( $rights );
		}

		$insertRows = array();
		foreach ( $rights as $right ) {
			$insertRows[] = array( 'ggp_group' => $group, 'ggp_permission' => $right );
		}

		# Replace into the DB
		$dbw->replace( 'global_group_permissions', array( 'ggp_group', 'ggp_permission' ), $insertRows, __METHOD__ );
	}

	/**
	 * @param $group
	 * @param $output OutputPage
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
	 * @param $group string
	 * @param $addRights array
	 * @param $removeRights array
	 * @param $reason string
	 */
	function addPermissionLog( $group, $addRights, $removeRights, $reason ) {
		$log = new LogPage( 'gblrights' );

		$log->addEntry(
			'groupprms2',
			SpecialPage::getTitleFor( 'GlobalUsers', $group ),
			$reason,
			array(
				$this->makeRightsList( $addRights ),
				$this->makeRightsList( $removeRights )
			)
		);
	}

	/**
	 * Log the renaming of a global group
	 *
	 * @param $oldName string
	 * @param $newName string
	 * @param $reason string
	 */
	function addRenameLog( $oldName, $newName, $reason ) {
		$log = new LogPage( 'gblrights' );

		$log->addEntry(
			'grouprename',
			// This has to point to 'Special:GlobalUsers so that self::showLogFragment can find it
			SpecialPage::getTitleFor( 'GlobalUsers', $newName ),
			$reason,
			array(
				SpecialPage::getTitleFor( 'GlobalGroupPermissions', $newName ),
				SpecialPage::getTitleFor( 'GlobalGroupPermissions', $oldName )
			)
		);
	}

	/**
	 * Log wikiset changes
	 *
	 * @param $group string
	 * @param $old string
	 * @param $new string
	 * @param $reason string
	 */
	function addWikiSetLog( $group, $old, $new, $reason ) {
		$log = new LogPage( 'gblrights' );

		$log->addEntry(
			'groupprms3',
			SpecialPage::getTitleFor( 'GlobalUsers', $group ),
			$reason,
			array(
				$this->getWikiSetName( $old ),
				$this->getWikiSetName( $new ),
			)
		);
	}

	/**
	 * @param $ids
	 * @return string
	 */
	function makeRightsList( $ids ) {
		return (bool)count( $ids ) ? implode( ', ', $ids ) : $this->msg( 'rightsnone' )->inContentLanguage()->text();
	}

	/**
	 * @param $group
	 * @param $set
	 * @return bool
	 */
	function setRestrictions( $group, $set ) {
		$dbw = CentralAuthUtils::getCentralDB();
		if ( $set == 0 ) {
			$dbw->delete(
				'global_group_restrictions',
				array( 'ggr_group' => $group ),
				__METHOD__
			);
		} else {
			$dbw->replace( 'global_group_restrictions',
				array( 'ggr_group' ),
				array( 'ggr_group' => $group, 'ggr_set' => $set, ),
				__METHOD__
			);
		}
		return (bool)$dbw->affectedRows();
	}

	/**
	 * @param $id string|int
	 * @return String
	 */
	function getWikiSetName( $id ) {
		if ( $id ) {
			return WikiSet::newFromID( $id )->getName();
		} else {
			return $this->msg( 'centralauth-editgroup-noset' )->inContentLanguage()->text();
		}
	}

	/**
	 * @param $group string
	 */
	function invalidateRightsCache( $group ) {
		// Figure out all the users in this group.
		// Use the master over here as this could go horribly wrong with newly created or just
		// renamed groups
		$dbr = CentralAuthUtils::getCentralDB();

		$res = $dbr->select( array( 'global_user_groups', 'globaluser' ), 'gu_name', array( 'gug_group' => $group, 'gu_id=gug_user' ), __METHOD__ );

		// Invalidate their rights cache.
		foreach ( $res as $row ) {
			// Use READ_LATEST for paranoia, though the DB isn't used in this method
			$cu = new CentralAuthUser( $row->gu_name, CentralAuthUser::READ_LATEST );
			$cu->quickInvalidateCache();
		}
	}

	protected function getGroupName() {
		return 'users';
	}
}
