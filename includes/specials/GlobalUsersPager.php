<?php

class GlobalUsersPager extends AlphabeticPager {
	protected $requestedGroup = false;
	protected $requestedUser = false;
	protected $globalIDGroups = [];
	private $localWikisets = [];

	public function __construct( IContextSource $context = null, $par = null ) {
		parent::__construct( $context );
		$this->mDefaultDirection = $this->getRequest()->getBool( 'desc' );
		$this->mDb = CentralAuthUtils::getCentralSlaveDB();
	}

	/**
	 * @param string $group
	 */
	public function setGroup( $group = '' ) {
		if ( !$group ) {
			$this->requestedGroup = false;
			return;
		}
		$this->requestedGroup = $group;
	}

	/**
	 * @param string $username
	 */
	public function setUsername( $username = '' ) {
		if ( !$username ) {
			$this->requestedUser = false;
			return;
		}
		$this->requestedUser = $username;
	}

	/**
	 * @return string
	 */
	function getIndexField() {
		return 'gu_name';
	}

	/**
	 * @return array
	 */
	function getDefaultQuery() {
		$query = parent::getDefaultQuery();
		if ( !isset( $query['group'] ) && $this->requestedGroup ) {
			$query['group'] = $this->requestedGroup;
		}
		$this->mDefaultQuery = $query;
		return $this->mDefaultQuery;
	}

	/**
	 * @return array
	 */
	function getQueryInfo() {
		$conds = [ 'gu_hidden' => CentralAuthUser::HIDDEN_NONE ];

		if ( $this->requestedGroup ) {
			$conds['gug_group'] = $this->requestedGroup;
		}

		if ( $this->requestedUser ) {
			$conds[] = 'gu_name >= ' . $this->mDb->addQuotes( $this->requestedUser );
		}

		return [
			'tables' => [ 'globaluser', 'localuser', 'global_user_groups' ],
			'fields' => [ 'gu_name',
				'gu_id' => 'MAX(gu_id)',
				'gu_locked' => 'MAX(gu_locked)',
				'lu_attached_method' => 'MAX(lu_attached_method)',
				// | cannot be used in a group name
				'gug_group' => 'GROUP_CONCAT(gug_group SEPARATOR \'|\')' ],
			'conds' => $conds,
			'options' => [ 'GROUP BY' => 'gu_name' ],
			'join_conds' => [
				'localuser' => [ 'LEFT JOIN', [ 'gu_name = lu_name', 'lu_wiki' => wfWikiID() ] ],
				'global_user_groups' => [ 'LEFT JOIN', 'gu_id = gug_user' ]
			],
		];
	}

	/**
	 * Formats a row
	 * @param object $row The row to be formatted for output
	 * @return string HTML li element with username and info about this user
	 */
	function formatRow( $row ) {
		$user = htmlspecialchars( $row->gu_name );
		$info = [];
		if ( $row->gu_locked ) {
			$info[] = $this->msg( 'centralauth-listusers-locked' )->text();
		}
		if ( $row->lu_attached_method ) {
			$info[] = $this->msg( 'centralauth-listusers-attached', $row->gu_name )->text();
		} else {
			array_unshift( $info, $this->msg( 'centralauth-listusers-nolocal' )->text() );
		}
		if ( $row->gug_group ) {
			$groups = $this->getUserGroups( $row->gu_id, $row->gu_name );
			$info[] = $groups;
		}

		$info = $this->getLanguage()->commaList( $info );
		return Html::rawElement( 'li', [],
			$this->msg( 'centralauth-listusers-item', $user, $info )->parse() );
	}

	function doBatchLookups() {
		$batch = new LinkBatch();
		foreach ( $this->mResult as $row ) {
			// userpage existence link cache
			$batch->addObj( Title::makeTitleSafe( NS_USER, $row->gu_name ) );
			if ( $row->gug_group ) { // no point in adding users that belong to any group
				$this->globalIDGroups[$row->gu_id] = explode( '|', $row->gug_group );
			}
		}
		$batch->execute();

		// Make an array of global groups for all users in the current result set
		$globalGroups = [];
		foreach ( $this->globalIDGroups as $gugGroup ) {
			$globalGroups = array_merge( $globalGroups, $gugGroup );
		}
		if ( count( $globalGroups ) > 0 ) {
			$wsQuery = $this->mDb->select(
					[ 'global_group_restrictions', 'wikiset' ],
					[ 'ggr_group', 'ws_id', 'ws_name', 'ws_type', 'ws_wikis' ],
					[ 'ggr_set=ws_id', 'ggr_group' => array_unique( $globalGroups ) ],
					__METHOD__
			);
			// Make an array of locally enabled wikisets
			foreach ( $wsQuery as $wsRow ) {
				if ( WikiSet::newFromRow( $wsRow )->inSet() ) {
					$this->localWikisets[] = $wsRow->ggr_group;
				}
			}
		}

		$this->mResult->rewind();
	}

	/**
	 * @return string
	 */
	function getPageHeader() {
		$options = [];
		$options[$this->msg( 'group-all' )->text()] = '';
		foreach ( $this->getAllGroups() as $group => $groupText ) {
			$options[$groupText] = $group;
		}

		$formDescriptor = [
			'usernameText' => [
				'type' => 'text',
				'name' => 'username',
				'id' => 'offset',
				'label' => $this->msg( 'listusersfrom' )->text(),
				'size' => 20,
				'default' => $this->requestedUser,
				'autofocus' => true,
			],
			'groupSelect' => [
				'type' => 'select',
				'name' => 'group',
				'id' => 'group',
				'label-message' => 'group',
				'options' => $options,
				'default' => $group == $this->requestedGroup,
			],
			'descCheck' => [
				'type' => 'check',
				'name' => 'desc',
				'id' => 'desc',
				'label-message' => 'listusers-desc',
				'default' => $this->mDefaultDirection,
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->addHiddenField( 'limit', $this->mLimit )
			->setMethod( 'get' )
			->setId( 'mw-listusers-form' )
			->setSubmitTextMsg( 'allpagessubmit' )
			->setWrapperLegendMsg( 'listusers' )
			->prepareForm()
			->displayForm( false );

		return true;
	}

	/**
	 * Note: Works only for users with $this->globalIDGroups set
	 *
	 * @param string $id
	 * @param string $username
	 * @return string
	 */
	protected function getUserGroups( $id, $username ) {
		$rights = [];
		foreach ( $this->globalIDGroups[$id] as $group ) {
			$wikitextLink = UserGroupMembership::getLink(
				$group, $this->getContext(), 'wiki', $username );

			if ( !in_array( $group, $this->localWikisets ) ) {
				// Mark if the group is not applied on this wiki
				$rights[] = Html::rawElement( 'span',
					[ 'class' => 'groupnotappliedhere' ],
					$wikitextLink
				);
			} else {
				$rights[] = $wikitextLink;
			}
		}

		return $this->getLanguage()->listToText( $rights );
	}

	/**
	 * @return array
	 */
	public function getAllGroups() {
		$result = [];
		foreach ( CentralAuthUser::availableGlobalGroups() as $group ) {
			$result[$group] = UserGroupMembership::getGroupName( $group );
		}
		return $result;
	}
}
