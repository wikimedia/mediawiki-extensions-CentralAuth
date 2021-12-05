<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use CentralAuthServices;
use HTMLForm;
use LogEventsList;
use LogPage;
use ManualLogEntry;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthGroupMembershipProxy;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\Widget\HTMLGlobalUserTextField;
use OutputPage;
use Status;
use User;
use UserrightsPage;
use Xml;

/**
 * Equivalent of Special:Userrights for global groups.
 *
 * @ingroup Extensions
 */
class SpecialGlobalGroupMembership extends UserrightsPage {

	/** @var GlobalGroupLookup */
	private $globalGroupLookup;

	public function __construct( GlobalGroupLookup $globalGroupLookup ) {
		parent::__construct();
		$this->mName = 'GlobalGroupMembership';
		$this->globalGroupLookup = $globalGroupLookup;
	}

	/**
	 * @return bool
	 */
	public function canProcessExpiries() {
		return $this->getConfig()->get( 'CentralAuthEnableTemporaryGlobalGroups' );
	}

	/**
	 * Output a form to allow searching for a user
	 */
	protected function switchForm() {
		global $wgScript;

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
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
			->setAction( $wgScript )
			->setId( 'mw-userrights-form1' )
			->setName( 'uluser' )
			->setSubmitTextMsg( 'editusergroup' )
			->setWrapperLegendMsg( 'userrights-lookup-user' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * @return array[]
	 */
	protected function changeableGroups() {
		$globalUser = CentralAuthUser::getInstance( $this->getUser() );
		if (
			$globalUser->exists() &&
			$globalUser->isAttached() &&
			$this->getContext()->getAuthority()->isAllowed( 'globalgroupmembership' )
		) {
			$allGroups = $this->globalGroupLookup->getDefinedGroups();

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
	 * @return string[]
	 */
	protected static function getAllGroups() {
		return CentralAuthServices::getGlobalGroupLookup()->getDefinedGroups();
	}

	/**
	 * @param User $user
	 * @param OutputPage $output
	 */
	protected function showLogFragment( $user, $output ) {
		$logPage = new LogPage( 'gblrights' );
		$output->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract( $output, 'gblrights', $user->getUserPage() );
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
}
