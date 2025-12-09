<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\JobQueue\Job;
use MediaWiki\Logger\LoggerFactory;

/**
 * A job to do crosswiki suppression in batches, rather than
 * in one request. Size of batch is changed by changing
 * $wgCentralAuthWikisPerSuppressJob.
 */
class CentralAuthSuppressUserJob extends Job {

	/**
	 * @param array $params Job parameters
	 */
	public function __construct( $params ) {
		parent::__construct( 'crosswikiSuppressUser', $params );
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		$username = $this->params['username'];
		$by = $this->params['by'];
		$wikis = $this->params['wikis'];
		$suppress = $this->params['suppress'];
		$reason = $this->params['reason'];
		$user = CentralAuthUser::getPrimaryInstanceByName( $username );
		$logger = LoggerFactory::getInstance( 'CentralAuth' );
		if ( !$user->exists() ) {
			$logger->debug( "Requested to suppress non-existent user {$username} by {$by}" );
		}

		foreach ( $wikis as $wiki ) {
			$user->doLocalSuppression( $suppress, $wiki, $by, $reason );
			$logger->debug(
				$suppress
					? "Suppressed {$username} at {$wiki} by {$by} via job queue"
					: "Unsuppressed {$username} at {$wiki} by {$by} via job queue"
			);
		}
		return true;
	}
}
