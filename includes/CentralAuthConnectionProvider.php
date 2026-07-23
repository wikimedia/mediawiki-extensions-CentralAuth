<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Exception\ReadOnlyError;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * Service providing access to the CentralAuth internal database.
 *
 * @since 1.47
 */
class CentralAuthConnectionProvider {

	/** @internal Only public for service wiring use */
	public const array CONSTRUCTOR_OPTIONS = [
		CAMainConfigNames::CentralAuthReadOnly,
	];

	private const string VIRTUAL_DOMAIN = 'virtual-centralauth';

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly IConnectionProvider $connectionProvider,
		private readonly LBFactory $lbFactory,
		private readonly ReadOnlyMode $readOnlyMode
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Throw an exception if the database is read-only.
	 *
	 * @throws CentralAuthReadOnlyError
	 */
	public function assertNotReadOnly(): void {
		if ( $this->readOnlyMode->isReadOnly() ) {
			throw new ReadOnlyError;
		}
		$reason = $this->getCentralReadOnlyReason();
		if ( $reason ) {
			throw new CentralAuthReadOnlyError( $reason );
		}
	}

	/**
	 * Determine if either the local or the shared CentralAuth database is
	 * read only. This should determine whether assertNotReadOnly() would
	 * throw.
	 */
	public function isReadOnly(): bool {
		return $this->readOnlyMode->isReadOnly()
			|| ( $this->getCentralReadOnlyReason() !== false );
	}

	/**
	 * Return the reason why either the shared CentralAuth database is read
	 * only, false otherwise.
	 */
	private function getCentralReadOnlyReason(): bool|string {
		$configReason = $this->options->get( CAMainConfigNames::CentralAuthReadOnly );
		if ( $configReason === true ) {
			return '(no reason given)';
		} elseif ( $configReason ) {
			return $configReason;
		}

		return $this->readOnlyMode->getReason(
			// Use $this->connectionProvider directly instead of $this->getPrimaryDatabase() to avoid recursion
			$this->connectionProvider->getPrimaryDatabase( self::VIRTUAL_DOMAIN )->getDomainID()
		);
	}

	public function getPrimaryDatabase(): IDatabase {
		$this->assertNotReadOnly();
		return $this->connectionProvider->getPrimaryDatabase( self::VIRTUAL_DOMAIN );
	}

	public function getReplicaDatabase(): IReadableDatabase {
		return $this->connectionProvider->getReplicaDatabase( self::VIRTUAL_DOMAIN );
	}

	/**
	 * @deprecated Use ::getPrimaryDatabase instead
	 */
	public function getCentralPrimaryDB(): IDatabase {
		return $this->getPrimaryDatabase();
	}

	/**
	 * @deprecated Use ::getReplicaDatabase instead
	 */
	public function getCentralReplicaDB(): IReadableDatabase {
		return $this->getReplicaDatabase();
	}

	/**
	 * @param int $recency IDBAccessObject::READ_* constant
	 */
	public function getDBFromRecency( int $recency ): IReadableDatabase {
		if ( DBAccessObjectUtils::hasFlags( $recency, IDBAccessObject::READ_LATEST ) ) {
			return $this->getPrimaryDatabase();
		} else {
			return $this->getReplicaDatabase();
		}
	}

	/**
	 * Check hasOrMadeRecentPrimaryChanges() on the CentralAuth load balancer
	 */
	public function centralLBHasRecentPrimaryChanges(): bool {
		return $this->lbFactory->getLoadBalancer( self::VIRTUAL_DOMAIN )->hasOrMadeRecentPrimaryChanges();
	}

	/**
	 * Get an IConnectionProvider which is configured to only access the default database belonging
	 * to the specified remote wiki.
	 */
	public function getRemoteWikiConnectionProvider( string $wikiId ): IConnectionProvider {
		return new RemoteWikiConnectionProvider( $this->connectionProvider, $wikiId );
	}
}

// TODO Remove this class alias once it's no longer used
class_alias(
	CentralAuthConnectionProvider::class,
	'MediaWiki\\Extension\\CentralAuth\\CentralAuthDatabaseManager'
);
