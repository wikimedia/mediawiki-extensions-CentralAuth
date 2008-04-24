<?php

#This file is part of MediaWiki.

#MediaWiki is free software: you can redistribute it and/or modify
#it under the terms of version 2 of the GNU General Public License
#as published by the Free Software Foundation.

#MediaWiki is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.

/**
 * Special page to allow managing global groups
 * Prototype for a similar system in core.
 *
 * @addtogroup Extensions
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralAuth extension\n";
	exit( 1 );
}


class SpecialGlobalGroupPermissions extends SpecialPage
{
	function __construct() {
		SpecialPage::SpecialPage('GlobalGroupPermissions', 'globalgrouppermissions');
		wfLoadExtensionMessages('SpecialCentralAuth');
	}
	
	function userCanExecute($user) {		
		$globalUser = CentralAuthUser::getInstance( $user );
		
		## Should be a global user
		if (!$globalUser->exists() || !$globalUser->isAttached()) {
			return false;
		}
		
		## Permission MUST be gained from global rights.
		return $globalUser->hasGlobalPermission( 'globalgrouppermissions' );
	}

	function execute( $subpage ) {
		global $wgRequest,$wgOut,$wgUser;
		
		if (!$this->userCanExecute($wgUser)) {
			$this->displayRestrictionError();
			return;
		}
		
		$wgOut->setPageTitle( wfMsg( 'globalgrouppermissions' ) );
		$wgOut->setRobotpolicy( "noindex,nofollow" );
		$wgOut->setArticleRelated( false );
		$wgOut->enableClientCache( false );
		
		if ($subpage == '' ) {
			$subpage = $wgRequest->getVal( 'wpGroup' );
		}

		if ($subpage != '' && $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) )) {
			$this->doSubmit($subpage);
		} else if ($subpage != '') {
			$this->buildGroupView($subpage);
		} else {
			$this->buildMainView();
		}
	}

	function buildMainView() {
		global $wgOut,$wgUser,$wgInvitationTypes;
		$sk = $wgUser->getSkin();

		$groups = CentralAuthUser::availableGlobalGroups();
		
		// Existing groups
		$html = Xml::openElement( 'fieldset' );
		$html .= Xml::element( 'legend', null, wfMsg( 'centralauth-existinggroup-legend' ) );
		
		$wgOut->addHtml( $html );

		if (count($groups)) {
			$wgOut->addWikitext( wfMsg( 'centralauth-globalgroupperms-grouplist' ) );
			$wgOut->addHTML( '<ul>' );

			foreach ($groups as $group) {
				$text = wfMsgExt( 'centralauth-globalgroupperms-grouplistitem', array( 'parseinline' ), User::getGroupName($group), $group );

				$wgOut->addHTML( "<li> $text </li>" );
			}
		} else {
			$wgOut->addWikitext( wfMsg( 'centralauth-globalgroupperms-nogroups' ) );
		}
		
		$wgOut->addHtml( Xml::closeElement( 'fieldset' ) );
		
		// "Create a group" prompt
		$html = Xml::openElement( 'fieldset' ) . Xml::element( 'legend', null, wfMsg( 'centralauth-newgroup-legend' ) );
		$html .= wfMsgExt( 'centralauth-newgroup-intro', array( 'parse' ) );
		$html .= Xml::openElement( 'form', array( 'method' => 'post', 'action' => $wgScript, 'name' => 'centralauth-globalgroups-newgroup' ) );
		$html .= Xml::hidden( 'title',  SpecialPage::getTitleFor('GlobalGroupPermissions')->getPrefixedText() );
		$html .= Xml::hidden( 'wpEditToken', $wgUser->editToken() );
		
		$fields = array( 'centralauth-globalgroupperms-newgroupname' => wfInput( 'wpGroup' ) );
		
		$html .= wfBuildForm( $fields, 'centralauth-globalgroupperms-creategroup-submit' );
		$html .= Xml::closeElement( 'fieldset' );
		
		$wgOut->addHtml( $html );
	}
	
	function buildGroupView( $group ) {
		global $wgOut, $wgUser;
		
		$wgOut->setSubtitle( wfMsg( 'centralauth-editgroup-subtitle', $group ) );
		
		$html = Xml::openElement( 'fieldset' ) . Xml::element( 'legend', null, wfMsg( 'centralauth-editgroup-fieldset', $group ) );
		$html .= Xml::openElement( 'form', array( 'method' => 'post', 'action' => $wgScript, 'name' => 'centralauth-globalgroups-newgroup' ) );
		$html .= Xml::hidden( 'title',  SpecialPage::getTitleFor('GlobalGroupPermissions')->getPrefixedText() );
		$html .= Xml::hidden( 'wpGroup', $group );
		$html .= Xml::hidden( 'wpEditToken', $wgUser->editToken() );
		
		$fields = array();
		
		$fields['centralauth-editgroup-name'] = $group;
		$fields['centralauth-editgroup-display'] = wfMsgExt( 'centralauth-editgroup-display-edit', array( 'parseinline' ), $group, User::getGroupName( $group ) );
		$fields['centralauth-editgroup-member'] = wfMsgExt( 'centralauth-editgroup-member-edit', array( 'parseinline' ), $group, User::getGroupMember( $group ) );
		$fields['centralauth-editgroup-members'] = wfMsgExt( 'centralauth-editgroup-members-link', array( 'parseinline' ), $group, User::getGroupMember( $group ) );
		$fields['centralauth-editgroup-perms'] = $this->buildCheckboxes($group);
		$fields['centralauth-editgroup-reason'] = wfInput( 'wpReason' );
		
		$html .= wfBuildForm( $fields, 'centralauth-editgroup-submit' );
		
		$html .= Xml::closeElement( 'form' );
		$html .= Xml::closeElement( 'fieldset' );
		
		$wgOut->addHtml( $html );
		
		$this->showLogFragment( $group, $wgOut );
	}
	
	function buildCheckboxes( $group ) {
		$html = '<ul>';
		
		$rights = wfGetAvailableRights();
		$assignedRights = $this->getAssignedRights( $group );
		
		foreach( $rights as $right ) {
			# Build a checkbox.
			$checked = in_array( $right, $assignedRights );
			
			$checkbox = wfCheckLabel( wfMsg( "right-$right" ), "wpRightAssigned-$right", "wpRightAssigned-$right", $checked );
			
			$html .= "<li>$checkbox</li>";
		}
		
		$html .= '</ul>';
		
		return $html;
	}
	
	function getAssignedRights( $group ) {
		return CentralAuthUser::globalGroupPermissions( $group );
	}
	
	function doSubmit( $group ) {
		global $wgRequest,$wgOut;
		
		$newRights = array();
		$addRights = array();
		$removeRights = array();
		$oldRights = $this->getAssignedRights( $group );
		$allRights = wfGetAvailableRights();
		
		$reason = $wgRequest->getVal( 'wpReason', '' );
		
		foreach ($allRights as $right) {
			$alreadyAssigned = in_array( $right, $oldRights );
			
			if ($wgRequest->getCheck( "wpRightAssigned-$right" )) {
				$newGroups[] = $right;
			}
			
			if (!$alreadyAssigned && $wgRequest->getCheck( "wpRightAssigned-$right" )) {
				$addRights[] = $right;
			} else if ($alreadyAssigned && !$wgRequest->getCheck( "wpRightAssigned-$right" ) ) {
				$removeRights[] = $right;
			} # Otherwise, do nothing.
		}
		
		// Assign the rights.
		if (count($addRights)>0)
			$this->grantRightsToGroup( $group, $addRights );
		if (count($removeRights)>0)
			$this->revokeRightsFromGroup( $group, $removeRights );
		
		// Log it
		if (!(count($addRights)==0 && count($removeRights)==0))
			$this->addLogEntry( $group, $oldRights, $newRights, $reason );
		
		// Display success
		$wgOut->setSubTitle( wfMsg( 'centralauth-editgroup-success' ) );
		$wgOut->addWikitext( wfMsg( 'centralauth-editgroup-success-text' ) );
	}
	
	function revokeRightsFromGroup( $group, $rights ) {
		$dbw = CentralAuthUser::getCentralDB();
		
		# Delete from the DB
		$dbw->delete( 'global_group_permissions', array( 'ggp_group' => $group, 'ggp_permission' => $rights), __METHOD__ );
	}
	
	function grantRightsToGroup( $group, $rights ) {
		$dbw = CentralAuthUser::getCentralDB();
		
		if (!is_array($rights)) {
			$rights = array($rights);
		}
		
		$insert_rows = array();
		foreach( $rights as $right ) {
			$insert_rows[] = array( 'ggp_group' => $group, 'ggp_permission' => $right );
		}
		
		# Replace into the DB
		$dbw->replace( 'global_group_permissions', array( 'ggp_group', 'ggp_permission' ), $insert_rows, __METHOD__ );
	}
	
	protected function showLogFragment( $group, $output ) {
		$title = SpecialPage::getTitleFor( 'ListUsers', $group );
		$output->addHtml( Xml::element( 'h2', null, LogPage::logName( 'gblrights' ) . "\n" ) );
		LogEventsList::showLogExtract( $output, 'globalrights', $title->getPrefixedText() );
	}
	
	function addLogEntry( $group, $oldRights, $newRights, $reason ) {
		global $wgRequest;
		
		$log = new LogPage( 'gblrights' );

		$log->addEntry( 'groupperms',
			SpecialPage::getTitleFor( 'ListUsers', $group ),
			$reason,
			array(
				$this->makeRightsList( $oldRights ),
				$this->makeRightsList( $newRights )
			)
		);
	}
	
	function makeRightsList( $ids ) {
		return implode( ', ', $ids );
	}
}
<?php

#This file is part of MediaWiki.

#MediaWiki is free software: you can redistribute it and/or modify
#it under the terms of version 2 of the GNU General Public License
#as published by the Free Software Foundation.

#MediaWiki is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.

/**
 * Special page to allow managing global groups
 * Prototype for a similar system in core.
 *
 * @addtogroup Extensions
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralAuth extension\n";
	exit( 1 );
}


class SpecialGlobalGroupPermissions extends SpecialPage
{
	function __construct() {
		SpecialPage::SpecialPage('GlobalGroupPermissions', 'globalgrouppermissions');
		wfLoadExtensionMessages('SpecialCentralAuth');
	}
	
	function userCanExecute($user) {		
		$globalUser = CentralAuthUser::getInstance( $user );
		
		## Should be a global user
		if (!$globalUser->exists() || !$globalUser->isAttached()) {
			return false;
		}
		
		## Permission MUST be gained from global rights.
		return $globalUser->hasGlobalPermission( 'globalgrouppermissions' );
	}

	function execute( $subpage ) {
		global $wgRequest,$wgOut,$wgUser;
		
		if (!$this->userCanExecute($wgUser)) {
			$this->displayRestrictionError();
			return;
		}
		
		$wgOut->setPageTitle( wfMsg( 'globalgrouppermissions' ) );
		$wgOut->setRobotpolicy( "noindex,nofollow" );
		$wgOut->setArticleRelated( false );
		$wgOut->enableClientCache( false );
		
		if ($subpage == '' ) {
			$subpage = $wgRequest->getVal( 'wpGroup' );
		}

		if ($subpage != '' && $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) )) {
			$this->doSubmit($subpage);
		} else if ($subpage != '') {
			$this->buildGroupView($subpage);
		} else {
			$this->buildMainView();
		}
	}

	function buildMainView() {
		global $wgOut,$wgUser,$wgInvitationTypes;
		$sk = $wgUser->getSkin();

		$groups = CentralAuthUser::availableGlobalGroups();
		
		// Existing groups
		$html = Xml::openElement( 'fieldset' );
		$html .= Xml::element( 'legend', null, wfMsg( 'centralauth-existinggroup-legend' ) );
		
		$wgOut->addHtml( $html );

		if (count($groups)) {
			$wgOut->addWikitext( wfMsg( 'centralauth-globalgroupperms-grouplist' ) );
			$wgOut->addHTML( '<ul>' );

			foreach ($groups as $group) {
				$text = wfMsgExt( 'centralauth-globalgroupperms-grouplistitem', array( 'parseinline' ), User::getGroupName($group), $group );

				$wgOut->addHTML( "<li> $text </li>" );
			}
			
			$wgOut->addHTML( '</ul>' );
		} else {
			$wgOut->addWikitext( wfMsg( 'centralauth-globalgroupperms-nogroups' ) );
		}
		
		$wgOut->addHtml( Xml::closeElement( 'fieldset' ) );
		
		// "Create a group" prompt
		$html = Xml::openElement( 'fieldset' ) . Xml::element( 'legend', null, wfMsg( 'centralauth-newgroup-legend' ) );
		$html .= wfMsgExt( 'centralauth-newgroup-intro', array( 'parse' ) );
		$html .= Xml::openElement( 'form', array( 'method' => 'post', 'action' => $wgScript, 'name' => 'centralauth-globalgroups-newgroup' ) );
		$html .= Xml::hidden( 'title',  SpecialPage::getTitleFor('GlobalGroupPermissions')->getPrefixedText() );
		$html .= Xml::hidden( 'wpEditToken', $wgUser->editToken() );
		
		$fields = array( 'centralauth-globalgroupperms-newgroupname' => wfInput( 'wpGroup' ) );
		
		$html .= wfBuildForm( $fields, 'centralauth-globalgroupperms-creategroup-submit' );
		$html .= Xml::closeElement( 'fieldset' );
		
		$wgOut->addHtml( $html );
	}
	
	function buildGroupView( $group ) {
		global $wgOut, $wgUser;
		
		$wgOut->setSubtitle( wfMsg( 'centralauth-editgroup-subtitle', $group ) );
		
		$html = Xml::openElement( 'fieldset' ) . Xml::element( 'legend', null, wfMsg( 'centralauth-editgroup-fieldset', $group ) );
		$html .= Xml::openElement( 'form', array( 'method' => 'post', 'action' => $wgScript, 'name' => 'centralauth-globalgroups-newgroup' ) );
		$html .= Xml::hidden( 'title',  SpecialPage::getTitleFor('GlobalGroupPermissions')->getPrefixedText() );
		$html .= Xml::hidden( 'wpGroup', $group );
		$html .= Xml::hidden( 'wpEditToken', $wgUser->editToken() );
		
		$fields = array();
		
		$fields['centralauth-editgroup-name'] = $group;
		$fields['centralauth-editgroup-display'] = wfMsgExt( 'centralauth-editgroup-display-edit', array( 'parseinline' ), $group, User::getGroupName( $group ) );
		$fields['centralauth-editgroup-member'] = wfMsgExt( 'centralauth-editgroup-member-edit', array( 'parseinline' ), $group, User::getGroupMember( $group ) );
		$fields['centralauth-editgroup-members'] = wfMsgExt( 'centralauth-editgroup-members-link', array( 'parseinline' ), $group, User::getGroupMember( $group ) );
		$fields['centralauth-editgroup-perms'] = $this->buildCheckboxes($group);
		$fields['centralauth-editgroup-reason'] = wfInput( 'wpReason' );
		
		$html .= wfBuildForm( $fields, 'centralauth-editgroup-submit' );
		
		$html .= Xml::closeElement( 'form' );
		$html .= Xml::closeElement( 'fieldset' );
		
		$wgOut->addHtml( $html );
		
		$this->showLogFragment( $group, $wgOut );
	}
	
	function buildCheckboxes( $group ) {
		$html = '<ul>';
		
		$rights = wfGetAvailableRights();
		$assignedRights = $this->getAssignedRights( $group );
		
		foreach( $rights as $right ) {
			# Build a checkbox.
			$checked = in_array( $right, $assignedRights );
			
			$checkbox = wfCheckLabel( wfMsg( "right-$right" ), "wpRightAssigned-$right", "wpRightAssigned-$right", $checked );
			
			$html .= "<li>$checkbox</li>";
		}
		
		$html .= '</ul>';
		
		return $html;
	}
	
	function getAssignedRights( $group ) {
		return CentralAuthUser::globalGroupPermissions( $group );
	}
	
	function doSubmit( $group ) {
		global $wgRequest,$wgOut;
		
		$newRights = array();
		$addRights = array();
		$removeRights = array();
		$oldRights = $this->getAssignedRights( $group );
		$allRights = wfGetAvailableRights();
		
		$reason = $wgRequest->getVal( 'wpReason', '' );
		
		foreach ($allRights as $right) {
			$alreadyAssigned = in_array( $right, $oldRights );
			
			if ($wgRequest->getCheck( "wpRightAssigned-$right" )) {
				$newGroups[] = $right;
			}
			
			if (!$alreadyAssigned && $wgRequest->getCheck( "wpRightAssigned-$right" )) {
				$addRights[] = $right;
			} else if ($alreadyAssigned && !$wgRequest->getCheck( "wpRightAssigned-$right" ) ) {
				$removeRights[] = $right;
			} # Otherwise, do nothing.
		}
		
		// Assign the rights.
		if (count($addRights)>0)
			$this->grantRightsToGroup( $group, $addRights );
		if (count($removeRights)>0)
			$this->revokeRightsFromGroup( $group, $removeRights );
		
		// Log it
		if (!(count($addRights)==0 && count($removeRights)==0))
			$this->addLogEntry( $group, $oldRights, $newRights, $reason );
		
		// Display success
		$wgOut->setSubTitle( wfMsg( 'centralauth-editgroup-success' ) );
		$wgOut->addWikitext( wfMsg( 'centralauth-editgroup-success-text' ) );
	}
	
	function revokeRightsFromGroup( $group, $rights ) {
		$dbw = CentralAuthUser::getCentralDB();
		
		# Delete from the DB
		$dbw->delete( 'global_group_permissions', array( 'ggp_group' => $group, 'ggp_permission' => $rights), __METHOD__ );
	}
	
	function grantRightsToGroup( $group, $rights ) {
		$dbw = CentralAuthUser::getCentralDB();
		
		if (!is_array($rights)) {
			$rights = array($rights);
		}
		
		$insert_rows = array();
		foreach( $rights as $right ) {
			$insert_rows[] = array( 'ggp_group' => $group, 'ggp_permission' => $right );
		}
		
		# Replace into the DB
		$dbw->replace( 'global_group_permissions', array( 'ggp_group', 'ggp_permission' ), $insert_rows, __METHOD__ );
	}
	
	protected function showLogFragment( $group, $output ) {
		$title = SpecialPage::getTitleFor( 'ListUsers', $group );
		$output->addHtml( Xml::element( 'h2', null, LogPage::logName( 'gblrights' ) . "\n" ) );
		LogEventsList::showLogExtract( $output, 'globalrights', $title->getPrefixedText() );
	}
	
	function addLogEntry( $group, $oldRights, $newRights, $reason ) {
		global $wgRequest;
		
		$log = new LogPage( 'gblrights' );

		$log->addEntry( 'groupperms',
			SpecialPage::getTitleFor( 'ListUsers', $group ),
			$reason,
			array(
				$this->makeRightsList( $oldRights ),
				$this->makeRightsList( $newRights )
			)
		);
	}
	
	function makeRightsList( $ids ) {
		return implode( ', ', $ids );
	}
}