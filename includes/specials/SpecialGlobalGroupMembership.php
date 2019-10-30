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
	 * @return bool
	 */
	public function canProcessExpiries() {
		return false;
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

			if ( !$user ) {
				return Status::newFatal( 'noname', $id );
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
	 * @param User $user
	 * @param array $oldGroups
	 * @param array $newGroups
	 * @param string $reason
	 * @param array $tags Not currently used
	 * @param array $oldUGMs Not currently used
	 * @param array $newUGMs Not currently used
	 */
	protected function addLogEntry( $user, array $oldGroups, array $newGroups, $reason,
		array $tags, array $oldUGMs, array $newUGMs
	) {
		$log = new LogPage( 'gblrights' );

		$log->addEntry( 'usergroups',
			$user->getUserPage(),
			$reason,
			[
				$this->makeGroupNameList( $oldGroups ),
				$this->makeGroupNameList( $newGroups )
			]
		);
	}
}
