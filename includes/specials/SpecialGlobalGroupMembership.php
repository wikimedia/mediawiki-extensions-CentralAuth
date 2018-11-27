<?php

use MediaWiki\MediaWikiServices;

/**
 * Equivalent of Special:Userrights for global groups.
 *
 * @ingroup Extensions
 */
class SpecialGlobalGroupMembership extends UserrightsPage {
	/**
	 * @var CentralAuthUser
	 */
	private $mGlobalUser;

	public function __construct() {
		SpecialPage::__construct( 'GlobalGroupMembership' );

		$this->addHelpLink( 'Extension:CentralAuth' );
		$this->mGlobalUser = CentralAuthUser::getInstance( $this->getUser() );
	}

	/**
	 * @return string
	 */
	public function getSuccessURL() {
		return $this->getPageTitle( $this->mTarget )->getFullURL();
	}

	/**
	 * Output a form to allow searching for a user
	 */
	public function switchForm() {
		global $wgScript;

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
			->setAction( $wgScript )
			->setId( 'mw-userrights-form1' )
			->setName( 'uluser' )
			->setSubmitTextMsg( 'editusergroup' )
			->setWrapperLegendMsg( 'userrights-lookup-user' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @return array
	 */
	public function changeableGroups() {
		if (
			$this->mGlobalUser->exists() &&
			$this->mGlobalUser->isAttached() &&
			MediaWikiServices::getInstance()->getPermissionManager()
				->userHasRight( $this->getUser(), 'globalgroupmembership' )
		) {
			$allGroups = CentralAuthUser::availableGlobalGroups();

			# specify addself and removeself as empty arrays -- bug 16098
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
	 * @param string|User $username
	 * @param bool $writing
	 * @return Status
	 */
	public function fetchUser( $username, $writing = true ) {
		if ( $username instanceof User ) {
			$user = CentralAuthGroupMembershipProxy::newFromId( $username->getId() );
			$name = CentralAuthGroupMembershipProxy::newFromName( $username );
			if ( !$user && !$name ) {
				return Status::newFatal( 'noname' );
			} elseif ( $user ) {
				return Status::newGood( $user );
			} elseif ( $name ) {
				return Status::newGood( $name );
			}
		}
		if ( $username === '' ) {
			return Status::newFatal( 'nouserspecified' );
		}

		if ( $username[0] == '#' ) {
			$id = intval( substr( $username, 1 ) );
			$user = CentralAuthGroupMembershipProxy::newFromId( $id );

			if ( !$user ) {
				return Status::newFatal( 'noname' );
			}
		} else {
			$user = CentralAuthGroupMembershipProxy::newFromName( $username );

			if ( !$user ) {
				return Status::newFatal( 'nosuchusershort', $username );
			}
		}

		return Status::newGood( $user );
	}

	/**
	 * @return array
	 */
	protected static function getAllGroups() {
		return CentralAuthUser::availableGlobalGroups();
	}

	/**
	 * @param User $user
	 * @param OutputPage $output
	 */
	protected function showLogFragment( $user, $output ) {
		$pageTitle = Title::makeTitleSafe( NS_USER, $user->getName() );
		$logPage = new LogPage( 'gblrights' );
		$output->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract( $output, 'gblrights', $pageTitle->getPrefixedText() );
	}

	/**
	 * @param User|CentralAuthGroupMembershipProxy $user
	 * @param array $oldGroups
	 * @param array $newGroups
	 * @param string $reason
	 * @param array $tags Not currently used
	 * @param array $oldUGMs Associative array of (group name => UserGroupMembership)
	 * @param array $newUGMs Associative array of (group name => UserGroupMembership)
	 */
	protected function addLogEntry( $user, array $oldGroups, array $newGroups, $reason,
		array $tags, array $oldUGMs, array $newUGMs
	) {
		// make sure $oldUGMs and $newUGMs are in the same order, and serialise
		// each UGM object to a simplified array
		$oldUGMs = array_map( function ( $group ) use ( $oldUGMs ) {
			return isset( $oldUGMs[$group] ) ?
				parent::serialiseUgmForLog( $oldUGMs[$group] ) :
			null;
		}, $oldGroups );
		$newUGMs = array_map( function ( $group ) use ( $newUGMs ) {
			return isset( $newUGMs[$group] ) ?
				parent::serialiseUgmForLog( $newUGMs[$group] ) :
			null;
		}, $newGroups );

		$logEntry = new ManualLogEntry( 'gblrights', 'usergroups' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $user->getUserPage() );
		$logEntry->setComment( $reason );
		$logEntry->setParameters( [
			'4::oldgroups' => $oldGroups,
			'5::newgroups' => $newGroups,
			'oldmetadata' => $oldUGMs,
			'newmetadata' => $newUGMs,
		] );
		$logEntry->addTags( $tags );

		$logid = $logEntry->insert();
		$logEntry->publish( $logid );
	}
}
