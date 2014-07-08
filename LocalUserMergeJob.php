<?php

/**
 * Job class to merge a user locally
 * This is intended to be run on each wiki individually
 */
class LocalUserMergeJob extends LocalCentralAuthJob {
	/**
	 * @param Title $title
	 * @param array $params
	 * @param int $id
	 */
	public function __construct( $title, $params, $id = 0 ) {
		parent::__construct( 'LocalUserMergeJob', $title, $params, $id );
	}

	public function run() {
		if ( !class_exists( 'SpecialUserMerge' ) ) {
			$this->updateStatus( 'failed' );
			throw new MWException( 'Extension:UserMerge is not installed' );
		}
		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->setRenameUserStatus( new GlobalRenameUserStatus( $from ) );
		$this->updateStatus( 'inprogress' );

		$um = new SpecialUserMerge();
		$data = array(
			'delete' => true,
			'olduser' => $from,
			'newuser' => $to
		);
		$status = $um->onSubmit( $data );
		if ( !$status->isGood() ) {
			$this->updateStatus( 'failed' );
			throw new MWException( 'SpecialUserMerge::onSubmit returned a bad status: '
				. $status->getWikiText() );
		}

		$this->done();
		return true;
	}

}
