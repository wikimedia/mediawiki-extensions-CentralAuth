<?php

class GlobalUsersPager extends AlphabeticPager {
	protected $requestedGroup = false;
	protected $requestedUser = false;
	protected $globalIDGroups = [];
	protected $globalExpGroups = [];
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
		$conds[] = 'gug_expiry IS NULL OR gug_expiry >= '
			. $this->mDb->addQuotes( $this->mDb->timestamp() );

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
				'gug_group' => 'GROUP_CONCAT(gug_group SEPARATOR \'|\')',
				'gug_expiry' => 'GROUP_CONCAT( IFNULL (gug_expiry, \'null\') SEPARATOR \'|\')' ],
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
				$this->globalExpGroups[$row->gu_id] = array_combine(
					explode( '|', $row->gug_group ), explode( '|', $row->gug_expiry ) );
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
				if ( !WikiSet::newFromRow( $wsRow )->inSet() ) {
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
	 * @throws MWException
	 */
	protected function getUserGroups( $id, $username ) {
		// As done in core, store expiring global groups separately, so we can place them before
		// non-expiring global groups in the list. This is to avoid the ambiguity of something like
		// "administrator, bureaucrat (until X date)"
		$permGroups = $tempGroups = [];
		$uiLanguage = $this->GetContext()->getLanguage();
		$uiUser = $this->GetContext()->getUser();

		foreach ( $this->globalIDGroups[$id] as $group ) {
			$ugm = new UserGroupMembership( $id, $group,
				$this->globalExpGroups[$id][$group] );
			$wikitextLink = UserGroupMembership::getLink(
				$ugm->getGroup(), $this->getContext(), 'wiki', $username );
			if ( $ugm->getExpiry() !== 'null' ) {
				$expiryDT = $uiLanguage->userTimeAndDate( $ugm->getExpiry(), $uiUser );
				$expiryMsg = ' ' . $this->getContext()->msg(
						'group-membership-link-with-expiry' )->params( null, $expiryDT )->text();
				if ( in_array( $ugm->getGroup(), $this->localWikisets ) ) {
					// Mark if the group is not applied on this wiki
					$tempGroups[] = Html::rawElement( 'span',
							[ 'class' => 'groupnotappliedhere' ],
							$wikitextLink
						) . $expiryMsg;
				} else {
					$tempGroups[] = $wikitextLink . $expiryMsg;
				}
			} else {
				if ( in_array( $ugm->getGroup(), $this->localWikisets ) ) {
					// Mark if the group is not applied on this wiki
					$permGroups[] = Html::rawElement( 'span',
							[ 'class' => 'groupnotappliedhere' ],
							$wikitextLink
						);
				} else {
					$permGroups[] = $wikitextLink;
				}
			}
		}

		return $this->getLanguage()->listToText( array_merge( $tempGroups, $permGroups ) );
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
