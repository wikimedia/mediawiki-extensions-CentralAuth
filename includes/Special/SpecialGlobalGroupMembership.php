<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use CentralAuthGroupMembershipProxy;
use CentralAuthUser;
use HTMLForm;
use HTMLGlobalUserTextField;
use LogEventsList;
use LogPage;
use OutputPage;
use Status;
use Title;
use User;
use UserrightsPage;
use Xml;

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
		parent::__construct();
		$this->mName = 'GlobalGroupMembership';

		$this->addHelpLink( 'Extension:CentralAuth' );
		$this->mGlobalUser = CentralAuthUser::getInstance( $this->getUser() );
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
	protected function switchForm() {
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
	protected function changeableGroups() {
		if (
			$this->mGlobalUser->exists() &&
			$this->mGlobalUser->isAttached() &&
			$this->getContext()->getAuthority()->isAllowed( 'globalgroupmembership' )
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
			$globalUser = CentralAuthUser::newPrimaryInstanceFromId( $id );

			// If the user exists, but is hidden from the viewer, pretend that it does
			// not exist. - T285190/T260863
			if ( !$user || ( ( $globalUser->isOversighted() || $globalUser->isHidden() ) &&
				!$this->getContext()->getAuthority()->isAllowed( 'centralauth-oversight' ) )
			) {
				return Status::newFatal( 'noname', $id );
			}
		} else {
			$user = CentralAuthGroupMembershipProxy::newFromName( $username );

			// If the user exists, but is hidden from the viewer, pretend that it does
			// not exist. - T285190
			$globalUser = CentralAuthUser::getPrimaryInstanceByName( $username );
			if ( !$user || ( ( $globalUser->isOversighted() || $globalUser->isHidden() ) &&
				!$this->getContext()->getAuthority()->isAllowed( 'centralauth-oversight' ) )
			) {
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
			],
			$this->getUser()
		);
	}
}
