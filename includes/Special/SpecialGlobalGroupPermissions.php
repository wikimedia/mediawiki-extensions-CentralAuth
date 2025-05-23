<?php
# This file is part of MediaWiki.

# MediaWiki is free software: you can redistribute it and/or modify
# it under the terms of version 2 of the GNU General Public License
# as published by the Free Software Foundation.

# MediaWiki is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

namespace MediaWiki\Extension\CentralAuth\Special;

use Exception;
use InvalidArgumentException;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\WikiSet;
use MediaWiki\Html\Html;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\Xml\Xml;
use MediaWiki\Xml\XmlSelect;

/**
 * Special page to allow managing global groups
 * Prototype for a similar system in core.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialGlobalGroupPermissions extends SpecialPage {

	private PermissionManager $permissionManager;
	private CentralAuthDatabaseManager $databaseManager;
	private GlobalGroupLookup $globalGroupLookup;

	public function __construct(
		PermissionManager $permissionManager,
		CentralAuthDatabaseManager $databaseManager,
		GlobalGroupLookup $globalGroupLookup
	) {
		parent::__construct( 'GlobalGroupPermissions' );
		$this->permissionManager = $permissionManager;
		$this->databaseManager = $databaseManager;
		$this->globalGroupLookup = $globalGroupLookup;
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function userCanEdit( $user ) {
		$globalUser = CentralAuthUser::getInstance( $user );

		# Should be a global user
		if ( !$globalUser->exists() || !$globalUser->isAttached() ) {
			return false;
		}

		return $user->isAllowed( 'globalgrouppermissions' );
	}

	/** @inheritDoc */
	public function execute( $subpage ) {
		$this->addHelpLink( 'Extension:CentralAuth' );
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
		}

		$this->getOutput()->setPageTitleMsg( $this->msg( 'globalgrouppermissions' ) );

		$this->getOutput()->addModuleStyles( 'mediawiki.codex.messagebox.styles' );
		$this->getOutput()->addModuleStyles( 'ext.centralauth.misc.styles' );
		$this->getOutput()->setRobotPolicy( "noindex,nofollow" );
		$this->getOutput()->setArticleRelated( false );
		$this->getOutput()->disableClientCache();

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

	private function buildMainView() {
		$out = $this->getOutput();
		$groups = $this->globalGroupLookup->getDefinedGroups();

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
			$html .= Html::openElement( 'form', [
				'method' => 'post',
				'action' => $this->getConfig()->get( MainConfigNames::Script ),
				'name' => 'centralauth-globalgroups-newgroup'
			] );
			$html .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() );

			$fields = [ 'centralauth-globalgroupperms-newgroupname' => Html::input( 'wpGroup' ) ];

			$html .= Xml::buildForm( $fields, 'centralauth-globalgroupperms-creategroup-submit' );
			$html .= Html::closeElement( 'form' );
			$html .= Html::closeElement( 'fieldset' );

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
			$table .= $this->getOutput()->parseInlineAsInterface(
				UserGroupMembership::getLinkWiki( $groupName, $this->getContext() ) ) . '<br />';

			$groupWithParentheses = $this->msg( 'parentheses' )->params( $groupName )->escaped();
			$table .= Html::openElement( 'code' );
			$table .= $groupWithParentheses;
			$table .= Html::closeElement( 'code' ) . Html::element( 'br' );

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
				throw new InvalidArgumentException( "__METHOD__: $group with unknown wikiset." );
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
	private function buildGroupView( $group ) {
		$editable = $this->userCanEdit( $this->getUser() );
		$assignedRights = $this->getAssignedRights( $group );
		$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );

		if ( !$assignedRights ) {
			// if the group doesn't exist and the user can not manage the global groups,
			// an error message should be shown instead of the permission list box.
			if ( !$editable ) {
				$this->getOutput()->addHTML( Html::errorBox(
					$this->msg( 'centralauth-editgroup-nonexistent', $group )->parse()
				) );
				$this->showLogFragment( $group, $this->getOutput() );
				return;
			}

			$nameValidationResult = $this->validateGroupName( $group );
			if ( !$nameValidationResult->isGood() ) {
				foreach ( $nameValidationResult->getMessages() as $msg ) {
					$this->getOutput()->addHTML( Html::errorBox( $this->msg( $msg )->parse() ) );
				}
				$this->showLogFragment( $group, $this->getOutput() );
				return;
			}
		}

		$fieldsetClass = $editable
			? 'mw-centralauth-editgroup'
			: 'mw-centralauth-editgroup-readonly';
		$html = Xml::fieldset(
			$this->msg( 'centralauth-editgroup-fieldset', $group )->text(),
			false,
			[ 'class' => $fieldsetClass ]
		);

		if ( $editable ) {
			$html .= Html::openElement( 'form', [
				'method' => 'post',
				'action' => $this->getPageTitle( $group )->getLocalUrl(),
				'name' => 'centralauth-globalgroups-newgroup'
			] );
			$html .= Html::hidden( 'wpGroup', $group );
			$html .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		}

		$fields = [];

		if ( $editable ) {
			$fields['centralauth-editgroup-name'] = Html::input(
				'wpGlobalGroupName', $group, 'text', [ 'size' => 50 ]
			);
		} else {
			$fields['centralauth-editgroup-name'] = htmlspecialchars( $group );
		}

		$lang = $this->getLanguage();
		if ( $this->getContext()->getAuthority()->isAllowed( 'editinterface' ) ) {
			# Show edit link only to user with the editinterface right
			$fields['centralauth-editgroup-display'] = $this->msg(
				'centralauth-editgroup-display-edit',
				$group,
				$lang->getGroupName( $group )
			)->parse();
			$fields['centralauth-editgroup-member'] = $this->msg(
				'centralauth-editgroup-member-edit',
				$group,
				$lang->getGroupMemberName( $group, '#' )
			)->parse();
		} else {
			$fields['centralauth-editgroup-display'] =
				htmlspecialchars( $lang->getGroupName( $group ) );
			$fields['centralauth-editgroup-member'] =
				htmlspecialchars( $lang->getGroupMemberName( $group, '#' ) );
		}
		$fields['centralauth-editgroup-members'] = $this->msg(
			'centralauth-editgroup-members-link',
			$group,
			$lang->getGroupMemberName( $group, '#' )
		)->parse();
		$fields['centralauth-editgroup-restrictions'] = $this->buildWikiSetSelector( $group );
		$fields['centralauth-editgroup-perms'] = $this->buildCheckboxes( $group );

		if ( $editable ) {
			$fields['centralauth-editgroup-reason'] = Html::input( 'wpReason', '', 'text', [ 'size' => 60 ] );
		}

		$html .= Xml::buildForm( $fields, $editable ? 'centralauth-editgroup-submit' : null );

		if ( $editable ) {
			$html .= Html::closeElement( 'form' );
		}

		$html .= Html::closeElement( 'fieldset' );

		$this->getOutput()->addHTML( $html );

		$this->showLogFragment( $group, $this->getOutput() );
	}

	/**
	 * @param string $group
	 * @return string
	 */
	private function buildWikiSetSelector( $group ) {
		$sets = WikiSet::getAllWikiSets();
		$default = WikiSet::getWikiSetForGroup( $group );

		if ( !$this->userCanEdit( $this->getUser() ) ) {
			$set = WikiSet::newFromID( $default );
			if ( $set ) {
				return $this->getLinkRenderer()->makeLink(
					SpecialPage::getTitleFor( 'WikiSets', (string)$set->getId() ),
					$set->getName()
				);
			} else {
				return $this->msg( 'centralauth-editgroup-nowikiset' )->parse();
			}
		}

		$select = new XmlSelect( 'set', 'wikiset', (string)$default );
		$select->addOption( $this->msg( 'centralauth-editgroup-noset' )->text(), '0' );
		foreach ( $sets as $set ) {
			$select->addOption( $set->getName(), (string)$set->getID() );
		}

		$editlink = $this->msg( 'centralauth-editgroup-editsets' )->parse();
		return $select->getHTML() . "&#160;{$editlink}";
	}

	/**
	 * @param string $group
	 * @return string
	 */
	private function buildCheckboxes( $group ) {
		$editable = $this->userCanEdit( $this->getUser() );

		$assignedRights = $this->getAssignedRights( $group );

		$checkboxes = [];
		$attribs = [];

		if ( !$editable ) {
			$attribs['disabled'] = 'disabled';
		}

		$rights = array_unique(
			array_merge(
				$this->permissionManager->getAllPermissions(),
				$assignedRights
			)
		);
		sort( $rights );

		foreach ( $rights as $right ) {
			// Build a checkbox
			$checked = in_array( $right, $assignedRights );

			$desc = $this->formatRight( $right );

			$checkbox = Html::check( "wpRightAssigned-$right", $checked,
				array_merge( $attribs, [ 'id' => "wpRightAssigned-$right" ] ) );
			$label = Html::rawElement( 'label', [ 'for' => "wpRightAssigned-$right" ],
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
		return $this->msg( 'listgrouprights-right-display' )
			->params( User::getRightDescription( $right ) )
			->rawParams( Html::element(
				'span',
				[ 'class' => 'mw-listgrouprights-right-name' ],
				$right
			) )
			->parse();
	}

	/**
	 * @param string $group
	 * @return string[]
	 */
	private function getAssignedRights( $group ) {
		return $this->globalGroupLookup->getRightsForGroup( $group );
	}

	/**
	 * @param string $group
	 */
	private function doSubmit( $group ) {
		// It is important to check userCanEdit, as otherwise an
		// unauthorized user could manually construct a POST request.
		if ( !$this->userCanEdit( $this->getUser() ) ) {
			return;
		}
		$reason = $this->getRequest()->getVal( 'wpReason', '' );

		// Current name of the group
		// XXX This is a horrible hack. We should not use Title for normalization. We need to prefix
		// the group name so that the first letter doesn't get uppercased.
		$group = Title::newFromText( "A/$group" );
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'centralauth-editgroup-invalid-name' );
			return;
		}
		$group = ltrim( substr( $group->getDBkey(), 2 ), '_' );

		// (Potentially) New name of the group
		$newname = $this->getRequest()->getVal( 'wpGlobalGroupName', $group );

		$newname = Title::newFromText( "A/$newname" );
		if ( !$newname ) {
			$this->getOutput()->addWikiMsg( 'centralauth-editgroup-invalid-name' );
			return;
		}
		$newname = ltrim( substr( $newname->getDBkey(), 2 ), '_' );

		// all new group names should be lowercase: check all new and changed group names (T202095)
		if (
			!in_array( $group, $this->globalGroupLookup->getDefinedGroups( DB_PRIMARY ) )
			|| ( $group !== $newname )
		) {
			$nameValidationResult = $this->validateGroupName( $newname );
			if ( !$nameValidationResult->isGood() ) {
				foreach ( $nameValidationResult->getMessages() as $msg ) {
					$this->getOutput()->addHTML( Html::errorBox( $this->msg( $msg )->parse() ) );
				}
				return;
			}
		}

		// Calculate permission changes already! We'll only save any changes
		// here after processing a possible group rename, but want to add
		// validation logic before that.
		$addRights = [];
		$removeRights = [];
		$oldRights = $this->getAssignedRights( $group );
		$allRights = array_unique(
			array_merge(
				$this->permissionManager->getAllPermissions(),
				$oldRights
			)
		);

		foreach ( $allRights as $right ) {
			$alreadyAssigned = in_array( $right, $oldRights );
			$checked = $this->getRequest()->getCheck( "wpRightAssigned-$right" );

			if ( !$alreadyAssigned && $checked ) {
				$addRights[] = $right;
			} elseif ( $alreadyAssigned && !$checked ) {
				$removeRights[] = $right;
			}
		}

		// Disallow deleting existing groups with members in them
		if (
			count( $oldRights ) !== 0
			&& count( $addRights ) === 0
			&& count( $removeRights ) === count( $oldRights )
		) {
			$dbr = $this->databaseManager->getCentralReplicaDB();
			$memberCount = $dbr->newSelectQueryBuilder()
				->select( 'gug_group' )
				->from( 'global_user_groups' )
				->where( [ 'gug_group' => $group ] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( $memberCount ) {
				$this->getOutput()->addWikiMsg( 'centralauth-editgroup-delete-removemembers' );
				return;
			}
		}

		// Check if we need to rename the group
		if ( $group != $newname ) {
			if ( in_array( $newname, $this->globalGroupLookup->getDefinedGroups( DB_PRIMARY ) ) ) {
				$this->getOutput()->addWikiMsg( 'centralauth-editgroup-rename-taken', $newname );
				return;
			}

			$dbw = $this->databaseManager->getCentralPrimaryDB();
			$updates = [
				'global_group_permissions' => 'ggp_group',
				'global_group_restrictions' => 'ggr_group',
				'global_user_groups' => 'gug_group'
			];

			foreach ( $updates as $table => $field ) {
				$dbw->newUpdateQueryBuilder()
					->update( $table )
					->set( [ $field => $newname ] )
					->where( [ $field => $group ] )
					->caller( __METHOD__ )
					->execute();
			}
			$this->addRenameLog( $group, $newname, $reason );

			// The rest of the changes here will be performed on the "new" group
			$group = $newname;
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
		$new = $this->getRequest()->getInt( 'set' );
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
	private function revokeRightsFromGroup( $group, $rights ) {
		$dbw = $this->databaseManager->getCentralPrimaryDB();

		# Delete from the DB
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'global_group_permissions' )
			->where( [ 'ggp_group' => $group, 'ggp_permission' => $rights ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string $group
	 * @param string[]|string $rights
	 */
	private function grantRightsToGroup( $group, $rights ) {
		$dbw = $this->databaseManager->getCentralPrimaryDB();

		if ( !is_array( $rights ) ) {
			$rights = [ $rights ];
		}

		$insertRows = [];
		foreach ( $rights as $right ) {
			$insertRows[] = [ 'ggp_group' => $group, 'ggp_permission' => $right ];
		}

		# Replace into the DB
		$dbw->newReplaceQueryBuilder()
			->replaceInto( 'global_group_permissions' )
			->uniqueIndexFields( [ 'ggp_group', 'ggp_permission' ] )
			->rows( $insertRows )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string $group
	 * @param OutputPage $output
	 */
	protected function showLogFragment( $group, $output ) {
		$title = SpecialPage::getTitleFor( 'GlobalUsers', $group );
		$logPage = new LogPage( 'gblrights' );
		$output->addHTML( Html::element( 'h2', [], $logPage->getName()->text() . "\n" ) );
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
	private function addPermissionLog( $group, $addRights, $removeRights, $reason ) {
		// The following message is generated here:
		// * logentry-gblrename-groupprms2
		// * log-action-filter-gblrename-groupprms2
		$entry = new ManualLogEntry( 'gblrights', 'groupprms2' );
		$entry->setTarget( SpecialPage::getTitleFor( 'GlobalUsers', $group ) );
		$entry->setPerformer( $this->getUser() );
		$entry->setComment( $reason );
		$entry->setParameters( [
			'addRights' => $addRights,
			'removeRights' => $removeRights,
		] );
		$logid = $entry->insert();
		$entry->publish( $logid );
	}

	/**
	 * Log the renaming of a global group
	 *
	 * @param string $oldName
	 * @param string $newName
	 * @param string $reason
	 */
	private function addRenameLog( $oldName, $newName, $reason ) {
		// The following message is generated here:
		// * logentry-gblrename-grouprename
		// * log-action-filter-gblrename-grouprename
		$entry = new ManualLogEntry( 'gblrights', 'grouprename' );
		// This has to point to 'Special:GlobalUsers so that self::showLogFragment can find it
		$entry->setTarget( SpecialPage::getTitleFor( 'GlobalUsers', $newName ) );
		$entry->setPerformer( $this->getUser() );
		$entry->setComment( $reason );
		$entry->setParameters( [
			'newName' => $newName,
			'oldName' => $oldName,
		] );
		$logid = $entry->insert();
		$entry->publish( $logid );
	}

	/**
	 * Log wikiset changes
	 *
	 * @param string $group
	 * @param int $old
	 * @param int $new
	 * @param string $reason
	 */
	private function addWikiSetLog( $group, $old, $new, $reason ) {
		// The following message is generated here:
		// * logentry-gblrename-groupprms3
		// * log-action-filter-gblrename-groupprms3
		$entry = new ManualLogEntry( 'gblrights', 'groupprms3' );
		$entry->setTarget( SpecialPage::getTitleFor( 'GlobalUsers', $group ) );
		$entry->setPerformer( $this->getUser() );
		$entry->setComment( $reason );
		$params = [];
		$mapping = [
			'old' => [ 4, $old ],
			'new' => [ 5, $new ],
		];
		foreach ( $mapping as $param => [ $id, $set ] ) {
			$name = $this->getWikiSetName( $set );
			if ( $name !== null ) {
				$params["$id::$param"] = $name;
			} else {
				$params["$id:msg:$param"] = 'centralauth-editgroup-noset';
			}
		}
		$entry->setParameters( $params );
		$logid = $entry->insert();
		$entry->publish( $logid );
	}

	/**
	 * @param string $group
	 * @param int $set
	 * @return bool
	 */
	private function setRestrictions( $group, $set ) {
		$dbw = $this->databaseManager->getCentralPrimaryDB();
		if ( $set == 0 ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'global_group_restrictions' )
				->where( [ 'ggr_group' => $group ] )
				->caller( __METHOD__ )
				->execute();
		} else {
			$dbw->newReplaceQueryBuilder()
				->replaceInto( 'global_group_restrictions' )
				->uniqueIndexFields( 'ggr_group' )
				->row( [ 'ggr_group' => $group, 'ggr_set' => $set, ] )
				->caller( __METHOD__ )
				->execute();
		}
		return (bool)$dbw->affectedRows();
	}

	/**
	 * @param string|int $id
	 * @return string|null
	 */
	private function getWikiSetName( $id ) {
		$wikiset = WikiSet::newFromID( $id );
		if ( $wikiset !== null ) {
			return $wikiset->getName();
		}
		return null;
	}

	/**
	 * @param string $group
	 */
	private function invalidateRightsCache( $group ) {
		// Figure out all the users in this group.
		// Use the primary database over here as this could go horribly wrong with newly created or just
		// renamed groups
		$dbr = $this->databaseManager->getCentralPrimaryDB();

		$res = $dbr->newSelectQueryBuilder()
			->select( 'gu_name' )
			->from( 'globaluser' )
			->join( 'global_user_groups', null, 'gu_id=gug_user' )
			->where( [ 'gug_group' => $group ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		// Invalidate their rights cache.
		foreach ( $res as $name ) {
			// Use READ_LATEST for paranoia, though the DB isn't used in this method
			$cu = CentralAuthUser::getPrimaryInstanceByName( $name );
			$cu->quickInvalidateCache();
		}
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}

	private function validateGroupName( string $name ): Status {
		// all new group names should be lowercase (T202095)
		if ( $name !== strtolower( $name ) ) {
			return Status::newFatal( 'centralauth-editgroup-invalid-name-lowercase' );
		}

		return Status::newGood();
	}
}
