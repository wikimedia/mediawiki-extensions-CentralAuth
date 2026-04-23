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
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\WikiSet;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Specials\Helpers\UserRequirementsConditionFormatterTrait;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\RestrictedUserGroupConfigReader;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\Xml\XmlSelect;
use StatusValue;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Special page to allow managing global groups
 * Prototype for a similar system in core.
 *
 * @ingroup Extensions
 */
class SpecialGlobalGroupPermissions extends SpecialPage {

	use UserRequirementsConditionFormatterTrait;

	private const RESTRICTED_GROUPS_SECTION_ID = 'restricted_groups';
	private const RESTRICTED_GROUPS_ID_PREFIX = 'group_restrictions-';

	public function __construct(
		private readonly PermissionManager $permissionManager,
		private readonly RestrictedUserGroupConfigReader $restrictedUserGroupConfigReader,
		private readonly CentralAuthDatabaseManager $databaseManager,
		private readonly GlobalGroupManager $globalGroupManager
	) {
		parent::__construct( 'GlobalGroupPermissions' );
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

		$subpage ??= $this->getRequest()->getText( 'wpGroup' );

		if ( $subpage !== '' ) {
			if (
				$this->getRequest()->wasPosted()
				&& $this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) )
			) {
				$submitStatus = $this->doSubmit( $subpage );

				if ( $submitStatus->isGood() ) {
					$this->getOutput()->addHTML( Html::successBox(
						$this->msg( 'centralauth-editgroup-success-text', $subpage )->parse()
					) );
				} else {
					foreach ( $submitStatus->getMessages() as $msg ) {
						$this->getOutput()->addHTML( Html::errorBox(
							$this->msg( $msg )->parse()
						) );
					}
				}
			}
			$this->buildGroupView( $subpage );
		} else {
			$this->buildMainView();
		}
	}

	private function buildMainView() {
		$out = $this->getOutput();
		$groups = $this->globalGroupManager->getDefinedGroups();

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
			$formDescriptor = [
				'Group' => [
					'type' => 'text',
					'label-message' => 'centralauth-globalgroupperms-newgroupname',
				]
			];

			HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
				->setMethod( 'get' )
				->setSubmitTextMsg( 'centralauth-globalgroupperms-creategroup-submit' )
				->setWrapperLegend( $this->msg( 'centralauth-newgroup-legend' )->text() )
				->addHeaderHtml( $this->msg( 'centralauth-newgroup-intro' )->parseAsBlock() )
				->setName( 'centralauth-globalgroups-newgroup' )
				->setTitle( $this->getPageTitle() )
				->prepareForm()
				->displayForm( false );
		}

		$this->outputRestrictedGroupsConfig();
	}

	/**
	 * @param array $groups
	 * @return string HTML for the group permissions table
	 */
	protected function getGlobalGroupsTable( $groups ) {
		$table = Html::openElement( 'table',
			[ 'class' => 'mw-centralauth-groups-table wikitable' ] );

		// Header stuff
		$table .= Html::rawElement( 'tr', [],
			Html::element( 'th', [],
				$this->msg( 'centralauth-globalgroupperms-group' )->text()
			) .
			Html::element( 'th', [],
				$this->msg( 'centralauth-globalgroupperms-rights' )->text()
			)
		);

		$centralWiki = $this->getConfig()->get( CAMainConfigNames::CentralAuthCentralWiki ) ?? false;
		$restrictedGroups = $this->restrictedUserGroupConfigReader
			->getConfig( $centralWiki, GlobalGroupAssignmentService::RESTRICTION_SCOPE );

		foreach ( $groups as $groupName ) {
			$groupInfo = $this->getGroupInfo( $groupName );
			$wikiset = $groupInfo['wikiset'];

			$table .= Html::openElement( 'tr',
				[ 'id' => Sanitizer::escapeIdForAttribute( $groupName ) ]
			);

			// Column with group name, links and other group information (if it behaves differently from
			// normal local groups)
			$table .= Html::openElement( 'td' );
			$table .= $this->getOutput()->parseInlineAsInterface(
				UserGroupMembership::getLinkWiki( $groupName, $this->getContext() ) ) . '<br />';

			$table .= Html::element( 'code', [],
					$this->msg( 'parentheses', $groupName )->text()
				) . Html::element( 'br' );

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

			if ( $wikiset !== null ) {
				$table .= Html::rawElement( 'p', [],
					$this->msg( 'centralauth-globalgroupperms-group-wikiset' )->rawParams(
						$linkRenderer->makeKnownLink(
							SpecialPage::getTitleFor( 'WikiSets', $wikiset['id'] ),
							$wikiset['name']
						)
					)->escaped() .
					( $wikiset['enabledHere'] ? '' :
						$this->msg( 'word-separator' )->escaped() .
						$this->msg( 'centralauth-globalgroupperms-group-disabled' )->escaped()
					)
				);
			}

			$automaticGroupsConfig = $this->getConfig()->get( CAMainConfigNames::CentralAuthAutomaticGlobalGroups );
			$relatedGroups = [];
			foreach ( $automaticGroupsConfig as $sourceGroup => $targetGroups ) {
				if ( in_array( $groupName, $targetGroups ) ) {
					$relatedGroups[] = $sourceGroup;
				}
			}
			if ( $relatedGroups ) {
				$table .= Html::rawElement( 'p', [],
					$this->msg( 'centralauth-globalgroupperms-automatic-group-info-reverse' )
						->params( Message::listParam(
							array_map(
								fn ( $automaticGroup ) => Message::rawParam(
									htmlspecialchars( $this->getLanguage()->getGroupName( $automaticGroup ) ) .
										$this->msg( 'word-separator' )->escaped() .
										Html::element( 'code', [],
											$this->msg( 'parentheses', $automaticGroup )->text()
										)
								),
								$relatedGroups
							)
						) )
						->escaped()
				);
			}

			if (
				array_key_exists( $groupName, $restrictedGroups )
				&& $restrictedGroups[$groupName]->hasAnyConditions()
			) {
				$restrictionsSection = Sanitizer::escapeIdForAttribute(
					self::RESTRICTED_GROUPS_ID_PREFIX . $groupName
				);
				$table .= Html::rawElement( 'p', [],
					$this->msg( 'listgrouprights-restricted' )
						->params( '#' . $restrictionsSection )
						->parse()
				);
			}

			$table .= Html::closeElement( 'td' );

			// Column for group rights list
			$table .= Html::openElement( 'td' );
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

		$this->getOutput()->enableOOUI();
		$html = '';

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
			$groupNameWidget = new \OOUI\TextInputWidget( [
				'type' => 'text',
				'name' => 'wpGlobalGroupName',
				'value' => $group,
			] );
		} else {
			$groupNameWidget = new \OOUI\LabelWidget( [
				'label' => $group,
			] );
		}
		$fields[] = new \OOUI\FieldLayout(
			$groupNameWidget,
			[
				'label' => $this->msg( 'centralauth-editgroup-name' )->text(),
			]
		);

		$lang = $this->getLanguage();
		if ( $this->getAuthority()->isAllowed( 'editinterface' ) ) {
			# Show edit link only to user with the editinterface right
			$localGroupName = new \OOUI\HtmlSnippet( $this->msg(
				'centralauth-editgroup-display-edit',
				$group,
				$lang->getGroupName( $group )
			)->parse() );
			$localGroupMember = new \OOUI\HtmlSnippet( $this->msg(
				'centralauth-editgroup-member-edit',
				$group,
				$lang->getGroupMemberName( $group, '#' )
			)->parse() );
		} else {
			$localGroupName = $lang->getGroupName( $group );
			$localGroupMember = $lang->getGroupMemberName( $group, '#' );
		}
		$fields[] = new \OOUI\FieldLayout(
			new \OOUI\LabelWidget( [
				'label' => $localGroupName,
			] ),
			[
				'label' => $this->msg( 'centralauth-editgroup-display' )->text(),
			]
		);
		$fields[] = new \OOUI\FieldLayout(
			new \OOUI\LabelWidget( [
				'label' => $localGroupMember,
			] ),
			[
				'label' => $this->msg( 'centralauth-editgroup-member' )->text(),
			]
		);

		// A group only exists if it has one or more rights assigned to it.
		// Special:GlobalUsers/<group> will interpret group as a username to
		// start iterating from if the specific group does not exist (has no rights
		// assigned to it). So only show the link for groups that have rights
		// assigned to them on page load.
		if ( $assignedRights ) {
			$fields[] = new \OOUI\FieldLayout(
				new \OOUI\LabelWidget( [
					'label' => new \OOUI\HtmlSnippet( $this->msg(
						'centralauth-editgroup-members-link',
						$group,
						$lang->getGroupMemberName( $group, '#' )
					)->parse() ),
				] ),
				[
					'label' => $this->msg( 'centralauth-editgroup-members' )->text(),
				]
			);
		}

		$fields[] = new \OOUI\FieldLayout(
			new \OOUI\LabelWidget( [
				'label' => new \OOUI\HtmlSnippet( $this->buildWikiSetSelector( $group ) ),
			] ),
			[
				'label' => $this->msg( 'centralauth-editgroup-restrictions' )->text(),
			]
		);
		$fields[] = new \OOUI\FieldLayout(
			new \OOUI\LabelWidget( [
				'label' => new \OOUI\HtmlSnippet( $this->buildCheckboxes( $group ) ),
			] ),
			[
				'label' => $this->msg( 'centralauth-editgroup-perms' )->text(),
				'align' => 'top',
			]
		);

		if ( $editable ) {
			$fields[] = new \OOUI\FieldLayout(
				new \OOUI\TextInputWidget( [
					'type' => 'text',
					'name' => 'wpReason',
				] ),
				[
					'label' => $this->msg( 'centralauth-editgroup-reason' )->text(),
					'align' => 'top',
				]
			);
			$fields[] = new \OOUI\ActionFieldLayout(
				new \OOUI\ButtonInputWidget( [
					'type' => 'submit',
					'label' => $this->msg( 'centralauth-editgroup-submit' )->text(),
					'flags' => [ 'primary', 'progressive' ],
				] )
			);
		}

		$html .= new \OOUI\PanelLayout( [
			'expanded' => false,
			'padded' => true,
			'framed' => true,
			'content' => new \OOUI\FieldsetLayout( [
				'items' => $fields,
				'label' => $this->msg( 'centralauth-editgroup-fieldset', $group )->text(),
			] )
		] );

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

		$checkboxes = '';
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
			$checkboxes .= Html::rawElement(
				'li', [ 'class' => $liClass ], "$checkbox&#160;$label" );
		}

		return Html::rawElement( 'div', [ 'class' => 'mw-centralauth-rights' ],
			Html::rawElement( 'ul', [], $checkboxes )
		);
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
		return $this->globalGroupManager->getRightsForGroup( $group );
	}

	private function doSubmit( string $group ): StatusValue {
		// It is important to check userCanEdit, as otherwise an
		// unauthorized user could manually construct a POST request.
		if ( !$this->userCanEdit( $this->getUser() ) ) {
			throw new PermissionsError( 'globalgrouppermissions' );
		}
		$reason = $this->getRequest()->getVal( 'wpReason', '' );

		// Current name of the group
		// XXX This is a horrible hack. We should not use Title for normalization. We need to prefix
		// the group name so that the first letter doesn't get uppercased.
		$group = Title::newFromText( "A/$group" );
		if ( !$group ) {
			return StatusValue::newFatal( 'centralauth-editgroup-invalid-name' );
		}
		$group = ltrim( substr( $group->getDBkey(), 2 ), '_' );

		// (Potentially) New name of the group
		$newname = $this->getRequest()->getVal( 'wpGlobalGroupName', $group );

		$newname = Title::newFromText( "A/$newname" );
		if ( !$newname ) {
			return StatusValue::newFatal( 'centralauth-editgroup-invalid-name' );
		}
		$newname = ltrim( substr( $newname->getDBkey(), 2 ), '_' );

		// all new group names should be lowercase: check all new and changed group names (T202095)
		if (
			!in_array( $group, $this->globalGroupManager->getDefinedGroups( DB_PRIMARY ) )
			|| ( $group !== $newname )
		) {
			$nameValidationResult = $this->validateGroupName( $newname );
			if ( !$nameValidationResult->isGood() ) {
				return $nameValidationResult;
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
			$addRights === []
			&& array_diff( $oldRights, $removeRights ) === []
			&& !$this->globalGroupManager->isGroupEmpty( $group, IDBAccessObject::READ_LATEST )
		) {
			return StatusValue::newFatal( 'centralauth-editgroup-delete-removemembers' );
		}

		// Check if we need to rename the group
		if ( $group !== $newname ) {
			$renameStatus = $this->globalGroupManager->renameGroup( $group, $newname );
			if ( !$renameStatus->isGood() ) {
				return $renameStatus;
			}
			$this->addRenameLog( $group, $newname, $reason );

			// The rest of the changes here will be performed on the "new" group
			$group = $newname;
		}

		// Update the rights. These are unlikely to fail, as we do the necessary checks before starting
		// any manipulation to groups, but in case it happens, let's be prepared
		$addStatus = $this->globalGroupManager->addRightsToGroup( $group, $addRights );
		if ( !$addStatus->isGood() ) {
			return $addStatus;
		}
		$removeStatus = $this->globalGroupManager->removeRightsFromGroup( $group, $removeRights );
		if ( !$removeStatus->isGood() ) {
			// Optimistically undo earlier changes to rights
			$this->globalGroupManager->removeRightsFromGroup( $group, $addRights );
			return $removeStatus;
		}

		// Log the rights changes
		if ( $addRights !== [] || $removeRights !== [] ) {
			$this->addPermissionLog( $group, $addRights, $removeRights, $reason );
		}

		// Change set
		$current = WikiSet::getWikiSetForGroup( $group );
		$new = $this->getRequest()->getInt( 'set' );
		if ( $current != $new ) {
			$this->globalGroupManager->setWikiSet( $group, $new );
			$this->addWikiSetLog( $group, $current, $new, $reason );
		}

		$this->invalidateRightsCache( $group );
		return StatusValue::newGood();
	}

	private function outputRestrictedGroupsConfig(): void {
		$out = $this->getOutput();
		$centralWiki = $this->getConfig()->get( CAMainConfigNames::CentralAuthCentralWiki ) ?? false;
		$restrictedGroups = $this->restrictedUserGroupConfigReader->getConfig(
			$centralWiki, GlobalGroupAssignmentService::RESTRICTION_SCOPE );

		$definedGroups = $this->globalGroupManager->getDefinedGroups();
		$restrictedGroups = array_filter(
			$restrictedGroups,
			static fn ( $restriction, $group ) => in_array( $group, $definedGroups )
				&& $restriction->hasAnyConditions(),
			ARRAY_FILTER_USE_BOTH
		);

		if ( !$restrictedGroups ) {
			return;
		}

		$header = $this->msg( 'listgrouprights-restrictedgroups-header' )->text();
		$out->addHTML(
			Html::element( 'h2', [
				'id' => Sanitizer::escapeIdForAttribute( self::RESTRICTED_GROUPS_SECTION_ID )
			], $header ) .
			Html::openElement( 'table', [ 'class' => 'wikitable mw-centralauth-groups-table' ] ) .
			Html::rawElement( 'tr', [],
				Html::element( 'th', [], $this->msg( 'centralauth-globalgroupperms-group' )->text() ) .
				Html::element( 'th', [], $this->msg( 'listgrouprights-restrictedgroups-config' )->text() )
			)
		);
		ksort( $restrictedGroups );

		foreach ( $restrictedGroups as $group => $groupConfig ) {
			$out->addHTML(
				Html::openElement( 'tr', [
					'id' => Sanitizer::escapeIdForAttribute( self::RESTRICTED_GROUPS_ID_PREFIX . $group )
				] ) .
				Html::rawElement( 'td', [],
					$this->getLinkRenderer()->makeKnownLink(
						new TitleValue( NS_SPECIAL, $this->getLocalName(), $group ),
						$this->getLanguage()->getGroupName( $group )
					)
				) .
				Html::openElement( 'td' )
			);

			$conditionsParts = [];
			$memberConditions = $groupConfig->getMemberConditions();
			if ( $memberConditions ) {
				$memberHtml = $this->msg( 'listgrouprights-restrictedgroups-memberconditions' )->parse();
				$memberHtml .= Html::rawElement( 'ul', [],
					Html::rawElement( 'li', [], $this->formatCondition( $memberConditions ) )
				);
				$conditionsParts[] = $memberHtml;
			}
			$updaterConditions = $groupConfig->getUpdaterConditions();
			if ( $updaterConditions ) {
				$updaterHtml = $this->msg( 'listgrouprights-restrictedgroups-updaterconditions' )->parse();
				$updaterHtml .= Html::rawElement( 'ul', [],
					Html::rawElement( 'li', [], $this->formatCondition( $updaterConditions ) )
				);
				$conditionsParts[] = $updaterHtml;
			}
			if ( $groupConfig->canBeIgnored() ) {
				$conditionsParts[] = $this->msg( 'listgrouprights-restrictedgroups-bypassable' )
					->params( User::getRightDescription( 'ignore-restricted-groups' ) )
					->rawParams( Html::element( 'code', [], 'ignore-restricted-groups' ) )
					->parse();
			}
			if ( $groupConfig->allowsAutomaticDemotion() ) {
				$conditionsParts[] = $this->msg( 'listgrouprights-restrictedgroups-autodemotion' )->parse();
			}
			$out->addHTML( implode( '', $conditionsParts ) );

			$out->addHTML(
				Html::closeElement( 'td' ) .
				Html::closeElement( 'tr' )
			);
		}
		$out->addHTML( Html::closeElement( 'table' ) );
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

	private function validateGroupName( string $name ): StatusValue {
		// all new group names should be lowercase (T202095)
		if ( $name !== strtolower( $name ) ) {
			return StatusValue::newFatal( 'centralauth-editgroup-invalid-name-lowercase' );
		}

		return StatusValue::newGood();
	}
}
