<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename\LocalRenameJob;

use LogicException;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MediaWikiServices;
use MediaWiki\RenameUser\RenameuserSQL;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

/**
 * Job class to rename a user locally
 * This is intended to be run on each wiki individually
 */
class LocalRenameUserJob extends LocalRenameJob {

	/**
	 * @param Title $title
	 * @param array $params An associative array of options:
	 *   from - old username
	 *   to - new username
	 *   force - try to do the rename even if the old username is invalid
	 *   renamer - whom the renaming should be attributed in logs
	 *   reason - reason to use in the rename log
	 *   movepages - move user / user talk pages and their subpages
	 *   suppressredirects - when moving pages, suppress redirects
	 *   reattach - after rename, attach the local account. When used, should be set to
	 *     [ wiki ID => [ 'attachedMethod' => method, 'attachedTimestamp' => timestamp ].
	 *     See CentralAuthUser::queryAttached. (default: false)
	 *   promotetoglobal - globalize the new user account (default: false)
	 *   session - session data from RequestContext::exportSession, for checkuser data
	 *   ignorestatus - ignore update status, run the job even if it seems like another job
	 *     is already working on it
	 */
	public function __construct( $title, $params ) {
		$this->command = 'LocalRenameUserJob';

		// For back-compat
		if ( !isset( $params['promotetoglobal'] ) ) {
			$params['promotetoglobal'] = false;
		}
		if ( !isset( $params['reason'] ) ) {
			$params['reason'] = '';
		}
		if ( !isset( $params['reattach'] ) ) {
			$params['reattach'] = false;
		}
		if ( !isset( $params['type'] ) ) {
			$params['type'] = GlobalRenameRequest::RENAME;
		}

		parent::__construct( $title, $params );
	}

	/** @inheritDoc */
	public function doRun( $fnameTrxOwner ) {
		$from = $this->params['from'];
		$to = $this->params['to'];

		$this->updateStatus( 'inprogress' );
		$services = MediaWikiServices::getInstance();
		// Make the status update visible to all other transactions immediately
		$factory = $services->getDBLoadBalancerFactory();
		$factory->commitPrimaryChanges( $fnameTrxOwner );

		if ( isset( $this->params['force'] ) && $this->params['force'] ) {
			// If we're dealing with an invalid username, load the data ourselves to avoid
			// any normalization at all done by User or Title.
			$userQuery = User::getQueryInfo();
			$row = $services->getConnectionProvider()->getPrimaryDatabase()->newSelectQueryBuilder()
				->tables( $userQuery['tables'] )
				->select( $userQuery['fields'] )
				->where( [ 'user_name' => $from ] )
				->joinConds( $userQuery['joins'] )
				->caller( __METHOD__ )
				->fetchRow();
			$oldUser = User::newFromRow( $row );
		} else {
			$oldUser = User::newFromName( $from );
		}

		$rename = new RenameuserSQL(
			$from,
			$to,
			$oldUser->getId(),
			$this->getRenameUser(),
			[
				'checkIfUserExists' => false,
				'debugPrefix' => 'GlobalRename',
				'reason' => $this->params['reason'],
			]
		);
		if ( !$rename->renameUser()->isOk() ) {
			// This should never happen!
			// If it does happen, the user will be locked out of their account
			// until a sysadmin intervenes...
			throw new LogicException( 'RenameuserSQL::rename returned false.' );
		}

		if ( $this->params['type'] === GlobalRenameRequest::VANISH ) {
			// $renamedUser should always be truthy, otherwise the exception
			// above would be thrown
			$renamedUser = $services->getUserFactory()->newFromName( $to );
			if ( $renamedUser ) {
				User::newSystemUser( $renamedUser->getName(), [ 'steal' => true ] );
			}
		}

		if ( $this->params['reattach'] ) {
			$caUser = CentralAuthUser::getInstanceByName( $this->params['to'] );
			$wikiId = WikiMap::getCurrentWikiId();
			$details = $this->params['reattach'][$wikiId];
			$caUser->attach(
				$wikiId,
				$details['attachedMethod'],
				false,
				$details['attachedTimestamp']
			);
		}

		if ( $this->params['movepages'] ) {
			$this->movePages( $oldUser );
		}

		if ( $this->params['promotetoglobal'] ) {
			$this->promoteToGlobal();
		}

		$this->done();
	}

	private function promoteToGlobal() {
		$newName = $this->params['to'];
		$caUser = CentralAuthUser::getPrimaryInstanceByName( $newName );
		$status = $caUser->promoteToGlobal( WikiMap::getCurrentWikiId() );
		if ( !$status->isOK() ) {
			if ( $status->hasMessage( 'promote-not-on-wiki' ) ) {
				// Eh, what?
				throw new LogicException( "Tried to promote '$newName' to a global account except it " .
					"doesn't exist locally" );
			} elseif ( $status->hasMessage( 'promote-already-exists' ) ) {
				// Even more wtf.
				throw new LogicException( "Tried to prommote '$newName' to a global account except it " .
					"already exists" );
			}
		}

		$caUser->quickInvalidateCache();
	}

	/**
	 * Queue up jobs to move pages
	 */
	public function movePages( User $oldUser ) {
		$from = $this->params['from'];
		$to = $this->params['to'];

		$fromDBkey = $oldUser->getUserPage()->getDBkey();
		$toDBkey = Title::makeTitleSafe( NS_USER, $to )->getDBkey();
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [
				'page_namespace' => [ NS_USER, NS_USER_TALK ],
				$dbr->expr( 'page_title', IExpression::LIKE, new LikeValue( $fromDBkey . '/', $dbr->anyString() ) )
					->or( 'page_title', '=', $fromDBkey )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$jobParams = [
			'to' => $to,
			'from' => $from,
			'renamer' => $this->getRenameUser()->getName(),
			'suppressredirects' => $this->params['suppressredirects'],
		];
		if ( isset( $this->params['session'] ) ) {
			$jobParams['session'] = $this->params['session'];
		}
		$jobs = [];

		$toReplace = static::escapeReplacement( $toDBkey );
		foreach ( $rows as $row ) {
			$oldPage = Title::newFromRow( $row );
			$newPage = Title::makeTitleSafe( $row->page_namespace,
				preg_replace( '!^[^/]+!', $toReplace, $row->page_title ) );
			$jobs[] = new LocalPageMoveJob(
				Title::newFromText( 'LocalRenameUserJob' ),
				$jobParams + [
					'old' => [ $oldPage->getNamespace(), $oldPage->getDBkey() ],
					'new' => [ $newPage->getNamespace(), $newPage->getDBkey() ],
				]
			);
		}

		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $jobs );
	}

	protected function done() {
		parent::done();
		$caOld = CentralAuthUser::getInstanceByName( $this->params['from'] );
		$caOld->quickInvalidateCache();
	}

	/**
	 * Escape a string to be used as a replacement by preg_replace so that
	 * anything in it that looks like a backreference is treated as a literal
	 * substitution.
	 *
	 * @param string $str String to escape
	 * @return string
	 */
	protected static function escapeReplacement( $str ) {
		// T188171: escape any occurrence of '$n' or '\n' in the replacement
		// string passed to preg_replace so that it will not be treated as
		// a backreference.
		return preg_replace(
			// find $n, ${n}, and \n
			'/[$\\\\]{?\d+}?/',
			// prepend with a literal '\\'
			'\\\\${0}',
			$str
		);
	}
}
