<?php

class SpecialUsersWhoWillBeRenamed extends SpecialPage {
	public function __construct() {
		parent::__construct( 'UsersWhoWillBeRenamed' );
	}

	public function execute( $subPage ) {
		$this->setHeaders();
		$pager = new UsersWhoWillBeRenamedPager( $this, $this->getContext() );
		$this->getOutput()->addWikiMsg( 'centralauth-uwbr-intro' );
		$this->getOutput()->addParserOutput( $pager->getFullOutput() );
	}
}

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
	protected $users = array();

	/**
	 * @param SpecialPage $owner Containing page
	 * @param IContextSource $context
	 */
	public function __construct( SpecialPage $owner, IContextSource $context ) {
		$this->owner = $owner;
		$this->mDb = CentralAuthUtils::getCentralSlaveDB();
		parent::__construct( $context );
	}

	public function getQueryInfo() {
		return array(
			'tables' => array( 'users_to_rename', 'localuser' ),
			'fields' => array(
				'utr_id',
				'utr_name',
				'utr_status',
			),
			'conds' => array( 'utr_wiki' => wfWikiID(), 'lu_attached_method IS NULL' ),
			'join_conds' => array( 'localuser' => array( 'LEFT JOIN', 'utr_wiki=lu_wiki AND utr_name=lu_name' ) ),
		);
	}

	protected function preprocessResults( $results ) {
		$names = array();
		foreach ( $results as $result ) {
			$names[] = $result->utr_name;
		}

		if ( !$names ) {
			return;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'user', 'ipblocks' ),
			User::selectFields(),
			array( 'user_name' => array_unique( $names ), 'ipb_deleted IS NULL OR ipb_deleted = 0' ),
			__METHOD__,
			array(), // $options
			array( 'ipblocks' => array( 'LEFT JOIN', 'user_id = ipb_user' ) )
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
		return array( 'utr_id' );
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
					Linker::userToolLinksRedContribs( $user->getId(), $user->getName(), $user->getEditCount() );
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
				$formatted = $this->getLanguage()->formatNum( $user->getEditCount() );
				break;
		}
		return $formatted;
	}

	/**
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
			$this->mFieldNames = array(
				'utr_name' => $this->msg( 'centralauth-uwbr-name' )->text(),
				'user_registration' => $this->msg( 'centralauth-uwbr-registration' ),
				'user_editcount' => $this->msg( 'centralauth-uwbr-editcount' ),
			);
		}
		return $this->mFieldNames;
	}
}
