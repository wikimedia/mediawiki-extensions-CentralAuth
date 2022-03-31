<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use AlphabeticPager;
use Html;
use HTMLForm;
use IContextSource;
use LinkBatch;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CentralAuth\WikiSet;
use stdClass;
use Title;
use UserGroupMembership;
use WikiMap;

class GlobalUsersPager extends AlphabeticPager {
	/** @var string|false */
	protected $requestedGroup = false;
	/** @var string|false */
	protected $requestedUser = false;
	/** @var array[] */
	protected $globalIDGroups = [];
	/** @var string[] */
	private $localWikisets = [];

	/** @var GlobalGroupLookup */
	private $globalGroupLookup;

	/**
	 * @param IContextSource $context
	 * @param CentralAuthDatabaseManager $dbManager
	 * @param GlobalGroupLookup $globalGroupLookup
	 */
	public function __construct(
		IContextSource $context,
		CentralAuthDatabaseManager $dbManager,
		GlobalGroupLookup $globalGroupLookup
	) {
		$this->mDb = $dbManager->getCentralDB( DB_REPLICA );
		parent::__construct( $context );
		$this->mDefaultDirection = $this->getRequest()->getBool( 'desc' );
		$this->globalGroupLookup = $globalGroupLookup;
	}

	/**
	 * @param string $group
	 */
	public function setGroup( string $group = '' ) {
		if ( $group === '' ) {
			$this->requestedGroup = false;
			return;
		}
		$this->requestedGroup = $group;
	}

	/**
	 * @param string $username
	 */
	public function setUsername( string $username = '' ) {
		if ( $username === '' ) {
			$this->requestedUser = false;
			return;
		}
		$this->requestedUser = $username;
	}

	/**
	 * @return string
	 */
	public function getIndexField() {
		return 'gu_name';
	}

	/**
	 * @return array
	 */
	public function getDefaultQuery() {
		$query = parent::getDefaultQuery();
		if ( !isset( $query['group'] ) && $this->requestedGroup !== false ) {
			$query['group'] = $this->requestedGroup;
		}
		$this->mDefaultQuery = $query;
		return $this->mDefaultQuery;
	}

	/**
	 * @return array
	 */
	public function getQueryInfo() {
		$tables = [ 'globaluser', 'localuser' ];

		$conds = [ 'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE ];

		$join_conds = [
			'localuser' => [ 'LEFT JOIN', [ 'gu_name = lu_name', 'lu_wiki' => WikiMap::getCurrentWikiId() ] ],
		];

		if ( $this->requestedGroup !== false ) {
			$tables[] = 'global_user_groups';
			$conds['gug_group'] = $this->requestedGroup;
			$join_conds['global_user_groups'] = [
				'LEFT JOIN',
				'gu_id = gug_user'
			];

			$conds[] = 'gug_expiry IS NULL OR gug_expiry >= ' . $this->mDb->addQuotes( $this->mDb->timestamp() );
		}

		if ( $this->requestedUser !== false ) {
			$conds[] = 'gu_name >= ' . $this->mDb->addQuotes( $this->requestedUser );
		}

		return [
			'tables' => $tables,
			'fields' => [
				'gu_name',
				'gu_id' => 'MAX(gu_id)',
				'gu_locked' => 'MAX(gu_locked)',
				'lu_attached_method' => 'MAX(lu_attached_method)',
				'gug_group' => $this->mDb->buildGroupConcatField(
					// | cannot be used in a group name
					'|',
					[ 'G' => 'global_user_groups' ],
					'G.gug_group',
					[
						'G.gug_user = gu_id',
						'G.gug_expiry IS NULL OR G.gug_expiry >= ' . $this->mDb->addQuotes( $this->mDb->timestamp() )
					]
				),
				'gug_expiry' => $this->mDb->buildGroupConcatField(
					// | is not a part of any MW timestamp
					'|',
					[ 'G' => 'global_user_groups' ],
					'IFNULL(G.gug_expiry, "null")',
					[
						'G.gug_user = gu_id',
						'G.gug_expiry IS NULL OR G.gug_expiry >= ' . $this->mDb->addQuotes( $this->mDb->timestamp() )
					]
				),
			],
			'conds' => $conds,
			'options' => [ 'GROUP BY' => 'gu_name' ],
			'join_conds' => $join_conds,
		];
	}

	/**
	 * Formats a row
	 * @param stdClass $row The row to be formatted for output
	 * @return string HTML li element with username and info about this user
	 */
	public function formatRow( $row ) {
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

	protected function doBatchLookups() {
		$batch = new LinkBatch();
		foreach ( $this->mResult as $row ) {
			// userpage existence link cache
			$batch->addObj( Title::makeTitleSafe( NS_USER, $row->gu_name ) );
			if ( $row->gug_group ) { // no point in adding users that belong to any group
				$groups = explode( '|', $row->gug_group );
				$expiries = explode( '|', $row->gug_expiry );
				$combined = array_combine( $groups, $expiries );

				// Ensure temporary groups are displayed first, to avoid ambiguity like
				// "first, second (expires at some point)" (unclear if only second expires or if both expire)
				uasort( $combined, static function ( $first, $second ) {
					// yes, this is 'null' not null, that's the string we get from the database if there's no expiry
					if ( $first === 'null' && $second !== 'null' ) {
						return 1;
					} elseif ( $first !== 'null' && $second === 'null' ) {
						return -1;
					} else {
						return 0;
					}
				} );

				$this->globalIDGroups[$row->gu_id] = $combined;
			}
		}
		$batch->execute();

		// Make an array of global groups for all users in the current result set
		$globalGroups = [];
		foreach ( $this->globalIDGroups as $gugGroup ) {
			$globalGroups = array_merge( $globalGroups, array_keys( $gugGroup ) );
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
	 * @return bool
	 */
	public function getPageHeader() {
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
				'default' => $this->requestedGroup,
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
		foreach ( $this->globalIDGroups[$id] as $group => $expiry ) {
			$ugm = new UserGroupMembership(
				(int)$id,
				$group,
				$expiry !== 'null' ? $expiry : null
			);

			$wikitextLink = UserGroupMembership::getLink(
				$ugm, $this->getContext(), 'wiki', $username );

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
	 * @return string[]
	 */
	public function getAllGroups() {
		$result = [];
		foreach ( $this->globalGroupLookup->getDefinedGroups() as $group ) {
			$result[$group] = UserGroupMembership::getGroupName( $group );
		}
		asort( $result );
		return $result;
	}
}
