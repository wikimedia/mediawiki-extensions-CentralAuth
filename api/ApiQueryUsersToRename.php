<?php

class ApiQueryUsersToRename extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'utr' );
	}

	protected function getDB() {
		static $db = null ;
		if ( is_null( $db ) ) {
			$db = CentralAuthUser::getCentralSlaveDB();
		}

		return $db;
	}

	public function execute() {
		$params = $this->extractRequestParams();

		$result = $this->getResult();

		$this->addTables( 'users_to_rename' );

		$this->addFields( array(
			'utr_id',
			'utr_name',
			'utr_wiki',
		) );

		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		$this->addWhereRange( 'utr_id', $params['dir'], $params['startid'], $params['endid'] );

		if ( isset( $params['name'] ) ) {
			$this->addWhere( array( 'utr_name' => $params['name'] ) );
		}

		if ( isset( $params['wiki'] ) ) {
			$this->addWhere( array( 'utr_wiki' => $params['wiki'] ) );
		}

		$res = $this->select( __METHOD__ );

		$count = 0;
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've had enough
				$this->setContinueEnumParameter( 'startid', $row->utr_id );
				break;
			}
			$entry = array(
				'id' => $row->utr_id,
				'name' => $row->utr_name,
				'wiki' => $row->utr_wiki,
			);

			$fit = $result->addValue( array( 'query', $this->getModuleName() ), null, $entry );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'startid', $row->utr_id );
				break;
			}
		}

		$result->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'name' );
	}

	public function getAllowedParams() {
		return array(
			'startid' => array(
				ApiBase::PARAM_TYPE => 'integer'
			),
			'endid' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'name' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'wiki' => array(
				ApiBase::PARAM_TYPE => CentralAuthUser::getWikiList(),
			),
			'dir' => array(
				ApiBase::PARAM_TYPE => array(
					'older',
					'newer'
				),
				ApiBase::PARAM_DFLT => 'newer'
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			),
		);
	}

	public function getParamDescription() {
		return array(
			'startid' => 'The id to start from',
			'endid' => 'The id to stop enumerating at',
			'name' => 'Username to check',
			'wiki' => 'wikiid to check',
			'dir' => 'The direction in which to enumerate',
			'limit' => 'The maximum number of results to list',
		);
	}

	public function getDescription() {
		return 'Show list of users who are going to be renamed.';
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=userstorename',
		);
	}
}
