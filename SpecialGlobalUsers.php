<?php

class SpecialGlobalUsers extends SpecialPage {
	function __construct() {
		parent::__construct( 'GlobalUsers' );
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgContLang;
		$this->setHeaders();

		$pg = new GlobalUsersPager();

		if ( $par ) {
			$pg->setGroup( $par );
		}
		$rqGroup = $wgRequest->getVal( 'group' );
		if ( $rqGroup ) {
			$pg->setGroup( $rqGroup );
		}
		$rqUsername = $wgContLang->ucfirst( $wgRequest->getVal( 'username' ) )
		if ( $rqUsername ) {
			$pg->setUsername( $rqUsername );
		}

		$wgOut->addHTML( $pg->getPageHeader() );
		$wgOut->addHTML( $pg->getNavigationBar() );
		$wgOut->addHTML( '<ul>' . $pg->getBody() . '</ul>' );
		$wgOut->addHTML( $pg->getNavigationBar() );
	}
}

class GlobalUsersPager extends UsersPager {
	private $requestedGroup = false, $requestedUser;

	function __construct() {
		parent::__construct();
		$this->mDb = CentralAuthUser::getCentralSlaveDB();
	}

	function setGroup( $group = '' ) {
		if ( !$group ) {
			$this->requestedGroup = false;
			return;
		}
		$groups = array_keys( $this->getAllGroups() );
		if ( in_array( $group, $groups ) ) {
			$this->requestedGroup = $group;
		} else {
			$this->requestedGroup = false;
		}
	}

	function setUsername( $username = '' ) {
		if ( !$username ) {
			$this->requestedUser = false;
			return;
		}
		$this->requestedUser = $username;
	}

	function getIndexField() {
		return 'gu_name';
	}

	function getDefaultQuery() {
		$query = parent::getDefaultQuery();
		if ( !isset( $query['group'] ) && $this->requestedGroup ) {
			$query['group'] = $this->requestedGroup;
		}
		return $this->mDefaultQuery = $query;

	}

	function getQueryInfo() {
		$localwiki = wfWikiID();
		$conds = array( 'gu_hidden' => CentralAuthUser::HIDDEN_NONE );

		if ( $this->requestedGroup ) {
			$conds['gug_group'] = $this->requestedGroup;
		}

		if ( $this->requestedUser ) {
			$conds[] = 'gu_name >= ' . $this->mDb->addQuotes( $this->requestedUser );
		}

		return array(
			'tables' => " (globaluser LEFT JOIN localuser ON gu_name = lu_name AND lu_wiki = '{$localwiki}') LEFT JOIN global_user_groups ON gu_id = gug_user ",
			'fields' => array( 'gu_id', 'gu_name', 'gu_locked', 'lu_attached_method', 'COUNT(gug_group) AS gug_numgroups', 'MAX(gug_group) AS gug_singlegroup'  ),
			'conds' => $conds,
			'options' => array( 'GROUP BY' => 'gu_name' ),
		);
	}

	/**
	 * Formats a row
	 * @param object $row The row to be formatted for output
	 * @return string HTML li element with username and info about this user
	 */
	function formatRow( $row ) {
		global $wgLang;
		$user = htmlspecialchars( $row->gu_name );
		$info = array();
		if ( $row->gu_locked ) {
			$info[] = wfMsg( 'centralauth-listusers-locked' );
		}
		if ( $row->lu_attached_method ) {
			$info[] = wfMsg( 'centralauth-listusers-attached', $row->gu_name );
		} else {
			$info[] = wfMsg( 'centralauth-listusers-nolocal' );
		}
		$groups = $this->getUserGroups( $row );
		
		if ( $groups ) {
			$info[] = $groups;
		}
		$info = $wgLang->commaList( $info );
		return Html::rawElement( 'li', array(), wfMsgExt( 'centralauth-listusers-item', array('parseinline'), $user, $info ) );
	}

	function getBody() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}
		$batch = new LinkBatch;

		$this->mResult->rewind();

		foreach ( $this->mResult as $row ) {
			$batch->addObj( Title::makeTitleSafe( NS_USER, $row->gu_name ) );
		}
		$batch->execute();
		$this->mResult->rewind();
		return AlphabeticPager::getBody();
	}

	protected function getUserGroups( $row ) {
		if ( !$row->gug_numgroups ) {
			return false;
		}
		if ( $row->gug_numgroups == 1 ) {
			return User::makeGroupLinkWiki( $row->gug_singlegroup, User::getGroupMember( $row->gug_singlegroup ) );
		}
		$result = $this->mDb->select( 'global_user_groups', 'gug_group', array( 'gug_user' => $row->gu_id ), __METHOD__ );
		$rights = array();
		foreach ( $result as $row2 ) {
			$rights[] = User::makeGroupLinkWiki( $row2->gug_group, User::getGroupMember( $row2->gug_group ) );
		}
		return implode( ', ', $rights );
	}

	public function getAllGroups() {
		$result = array();
		foreach ( CentralAuthUser::availableGlobalGroups() as $group ) {
			$result[$group] = User::getGroupName( $group );
		}
		return $result;
	}
}
