<?php

use MediaWiki\Block\DatabaseBlock;

/**
 * Paginated table of search results.
 * @ingroup Pager
 */
class UsersWhoWillBeRenamedPager extends TablePager {

	/**
	 * @var SpecialPage $owner
	 */
	protected $owner;
	/**
	 * @var $mFieldNames array
	 */
	protected $mFieldNames;

	/**
	 * @var User[]
	 */
	protected $users = [];

	/**
	 * @param SpecialPage $owner Containing page
	 * @param IContextSource $context
	 */
	public function __construct( SpecialPage $owner, IContextSource $context ) {
		$this->owner = $owner;
		$this->mDb = CentralAuthUtils::getCentralReplicaDB();
		parent::__construct( $context );
	}

	public function getQueryInfo() {
		return [
			'tables' => [ 'users_to_rename', 'localuser' ],
			'fields' => [
				'utr_id',
				'utr_name',
				'utr_status',
			],
			'conds' => [ 'utr_wiki' => wfWikiID(), 'lu_attached_method IS NULL' ],
			'join_conds' => [ 'localuser' => [ 'LEFT JOIN', 'utr_wiki=lu_wiki AND utr_name=lu_name' ] ],
		];
	}

	protected function preprocessResults( $results ) {
		$names = [];
		foreach ( $results as $result ) {
			$names[] = $result->utr_name;
		}

		if ( !$names ) {
			return;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$userQuery = User::getQueryInfo();
		$blockQuery = DatabaseBlock::getQueryInfo();
		$ipbUser = $blockQuery['ipb_user'] ?? 'ipb_user';
		$res = $dbr->select(
			$userQuery['tables'] + [ 'nestblock' => $blockQuery['tables'] ],
			$userQuery['fields'],
			[ 'user_name' => array_unique( $names ), 'ipb_deleted IS NULL OR ipb_deleted = 0' ],
			__METHOD__,
			[], // $options
			[ 'nestblock' => [ 'LEFT JOIN', "user_id = $ipbUser" ] ]
				+ $userQuery['joins'] + $blockQuery['joins']
		);
		$userArray = UserArray::newFromResult( $res );

		$lb = new LinkBatch();
		foreach ( $userArray as $user ) {
			$this->users[$user->getName()] = $user;
			$lb->addObj( $user->getUserPage() );
			$lb->addObj( $user->getTalkPage() );
		}

		$lb->execute();
	}

	/**
	 * @return array
	 */
	protected function getExtraSortFields() {
		// Break order ties based on the unique id
		return [ 'utr_id' ];
	}

	/**
	 * @param string $field
	 * @return bool
	 */
	public function isFieldSortable( $field ) {
		return $field === 'utr_name';
	}

	public function formatRow( $row ) {
		if ( !isset( $this->users[$row->utr_name] ) ) {
			// Hidden user or they don't exist locally?
			return '';
		}
		return parent::formatRow( $row );
	}

	/**
	 * @param string $name The database field name
	 * @param string $value The value retrieved from the database
	 * @return string HTML to place inside table cell
	 */
	public function formatValue( $name, $value ) {
		$user = $this->users[$this->mCurrentRow->utr_name];
		$formatted = htmlspecialchars( $value );
		switch ( $name ) {
			case 'utr_name':
				$formatted = Linker::userLink( $user->getId(), $user->getName() ) .
					Linker::userToolLinksRedContribs(
						$user->getId(),
						$user->getName(),
						$user->getEditCount(),
						// don't render parentheses in HTML markup (CSS will provide)
						false
					);
				break;
			case 'user_registration':
				$regDate = $user->getRegistration();
				if ( $regDate === null ) {
					$formatted = $this->msg( 'centralauth-uwbr-registration-nodate' )->escaped();
				} else {
					$formatted = $this->formatDateTime( $regDate );
				}
				break;
			case 'user_editcount':
				$formatted = htmlspecialchars( $this->getLanguage()->formatNum( $user->getEditCount() ) );
				break;
		}
		return $formatted;
	}

	/**
	 * @param string $value
	 * @return string Formatted table cell contents
	 */
	protected function formatDateTime( $value ) {
		return htmlspecialchars(
			$this->getLanguage()->userTimeAndDate( $value, $this->getUser() )
		);
	}

	/**
	 * @return string
	 */
	public function getDefaultSort() {
		return 'utr_name';
	}

	/**
	 * @return array
	 */
	public function getFieldNames() {
		if ( $this->mFieldNames === null ) {
			$this->mFieldNames = [
				'utr_name' => $this->msg( 'centralauth-uwbr-name' )->text(),
				'user_registration' => $this->msg( 'centralauth-uwbr-registration' )->text(),
				'user_editcount' => $this->msg( 'centralauth-uwbr-editcount' )->text(),
			];
		}
		return $this->mFieldNames;
	}
}
