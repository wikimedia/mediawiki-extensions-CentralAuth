<?php

namespace MediaWiki\Extension\CentralAuth;

use LogicException;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Class providing access to other wikis' DBs.
 *
 * @since 1.47
 */
class RemoteWikiConnectionProvider implements IConnectionProvider {

	/**
	 * @internal Use CentralAuthConnectionProvider::getRemoteWikiConnectionProvider() to get an instance of this class
	 */
	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private string $wikiId,
	) {
	}

	/**
	 * @inheritDoc
	 * @phan-param false $domain
	 */
	public function getPrimaryDatabase( $domain = false ): IDatabase {
		if ( $domain !== false ) {
			throw new LogicException( 'Conflicting domain or wiki name provided' );
		}
		return $this->connectionProvider->getPrimaryDatabase( $this->wikiId );
	}

	/**
	 * @inheritDoc
	 * @phan-param false $domain
	 */
	public function getReplicaDatabase( string|false $domain = false, $group = null ): IReadableDatabase {
		if ( $domain !== false ) {
			throw new LogicException( 'Conflicting domain or wiki name provided' );
		}
		return $this->connectionProvider->getReplicaDatabase( $this->wikiId, $group );
	}

	/** @inheritDoc */
	public function commitAndWaitForReplication( $fname, $ticket, array $opts = [] ): bool {
		return $this->connectionProvider->commitAndWaitForReplication( $fname, $ticket, $opts );
	}

	/** @inheritDoc */
	public function getEmptyTransactionTicket( $fname ): mixed {
		return $this->connectionProvider->getEmptyTransactionTicket( $fname );
	}
}
