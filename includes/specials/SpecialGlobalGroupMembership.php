<?php
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

		$this->mGlobalUser = CentralAuthUser::getInstance( $this->getUser() );
	}

	/**
	 * @return string
	 */
	function getSuccessURL() {
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
	function switchForm() {
		global $wgScript;

		$this->getOutput()->addModules( 'ext.centralauth.globaluserautocomplete' );
		$this->getOutput()->addModuleStyles( 'mediawiki.special' );
		$this->getOutput()->addHTML(
			Xml::openElement( 'form', [ 'method' => 'get', 'action' => $wgScript, 'name' => 'uluser', 'id' => 'mw-userrights-form1' ] ) .
			Html::hidden( 'title',  $this->getPageTitle() ) .
			Xml::openElement( 'fieldset' ) .
			Xml::element( 'legend', [], $this->msg( 'userrights-lookup-user' )->text() ) .
			Xml::inputLabel( $this->msg( 'userrights-user-editname' )->text(), 'user', 'username', 30, $this->mTarget,
				[ 'class' => 'mw-autocomplete-global-user' ] ) . ' <br />' .
			Xml::submitButton( $this->msg( 'editusergroup' )->text() ) .
			Xml::closeElement( 'fieldset' ) .
			Xml::closeElement( 'form' ) . "\n"
		);
	}

	/**
	 * @return array
	 */
	function changeableGroups() {
		if (
			$this->mGlobalUser->exists() &&
			$this->mGlobalUser->isAttached() &&
			# Permission MUST be gained from global rights.
			$this->mGlobalUser->hasGlobalPermission( 'globalgroupmembership' )
		) {
			$allGroups = CentralAuthUser::availableGlobalGroups();

			# specify addself and removeself as empty arrays -- bug 16098
			return [
				'add' => $allGroups,
				'remove' =>  $allGroups,
				'add-self' => [],
				'remove-self' => []
			];
		}

		return [
			'add' => [],
			'remove' =>  [],
			'add-self' => [],
			'remove-self' => []
		];
	}

	/**
	 * @param string $username
	 * @param bool $writing
	 * @return Status
	 */
	function fetchUser( $username, $writing = true ) {
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
	 * @static
	 * @return array
	 */
	protected static function getAllGroups() {
		return CentralAuthUser::availableGlobalGroups();
	}

	/**
	 * @param $user User
	 * @param $output OutputPage
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
	function addLogEntry( $user, $oldGroups, $newGroups, $reason,
		$tags = [], $oldUGMs = [], $newUGMs = []
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
