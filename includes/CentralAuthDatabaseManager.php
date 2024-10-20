<?php

namespace MediaWiki\Extension\CentralAuth;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\WikiMap\WikiMap;
use ReadOnlyError;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * Service providing access to the CentralAuth internal database.
 *
 * @since 1.37
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class CentralAuthDatabaseManager {

	/** @internal Only public for service wiring use */
	public const CONSTRUCTOR_OPTIONS = [
		'CentralAuthReadOnly',
	];

	private ServiceOptions $options;
	private LBFactory $lbFactory;
	private ReadOnlyMode $readOnlyMode;

	public function __construct( ServiceOptions $options, LBFactory $lbFactory, ReadOnlyMode $readOnlyMode ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->lbFactory = $lbFactory;
		$this->readOnlyMode = $readOnlyMode;
	}

	/**
	 * Determine the database domain for this CentralAuth instance.
	 *
	 * @return false|string
	 */
	private function resolveDatabaseDomain() {
		return $this->lbFactory->getPrimaryDatabase( 'virtual-centralauth' )->getDomainID();
	}

	/**
	 * Throw an exception if the database is read-only.
	 *
	 * @throws CentralAuthReadOnlyError
	 */
	public function assertNotReadOnly() {
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
	 *
	 * @return bool
	 */
	public function isReadOnly(): bool {
		return $this->readOnlyMode->isReadOnly()
			|| ( $this->getCentralReadOnlyReason() !== false );
	}

	/**
	 * Return the reason why either the shared CentralAuth database is read
	 * only, false otherwise.
	 *
	 * @return bool|string
	 */
	private function getCentralReadOnlyReason() {
		$configReason = $this->options->get( 'CentralAuthReadOnly' );
		if ( $configReason === true ) {
			return '(no reason given)';
		} elseif ( $configReason ) {
			return $configReason;
		}

		return $this->readOnlyMode->getReason( $this->resolveDatabaseDomain() );
	}

	/**
	 * @return IDatabase a connection to the CentralAuth database primary.
	 */
	public function getCentralPrimaryDB(): IDatabase {
		$this->assertNotReadOnly();
		return $this->lbFactory->getPrimaryDatabase( 'virtual-centralauth' );
	}

	/**
	 * @return IReadableDatabase a connection to a CentralAuth database replica
	 */
	public function getCentralReplicaDB(): IReadableDatabase {
		return $this->lbFactory->getReplicaDatabase( 'virtual-centralauth' );
	}

	/**
	 * @param int $recency IDBAccessObject::READ_* constant
	 * @return IReadableDatabase
	 */
	public function getCentralDBFromRecency( int $recency ): IReadableDatabase {
		if ( DBAccessObjectUtils::hasFlags( $recency, IDBAccessObject::READ_LATEST ) ) {
			return $this->getCentralPrimaryDB();
		} else {
			return $this->getCentralReplicaDB();
		}
	}

	/**
	 * Gets a database connection to the local database based on a wikiId
	 *
	 * @param int $index DB_PRIMARY or DB_REPLICA
	 * @param string $wikiId
	 *
	 * @todo Split to two for IReadableDatabase support or drop entirely
	 *
	 * @return IDatabase
	 * @throws CentralAuthReadOnlyError
	 * @throws InvalidArgumentException
	 */
	public function getLocalDB( int $index, string $wikiId ): IDatabase {
		if ( $index !== DB_PRIMARY && $index !== DB_REPLICA ) {
			throw new InvalidArgumentException( "Unknown index $index, expected DB_PRIMARY or DB_REPLICA" );
		}

		if ( WikiMap::isCurrentWikiId( $wikiId ) ) {
			$wikiId = false;
		}

		return $this->lbFactory->getMainLB( $wikiId )
			->getConnection( $index, [], $wikiId );
	}

	/**
	 * Check hasOrMadeRecentPrimaryChanges() on the CentralAuth load balancer
	 *
	 * @return bool
	 */
	public function centralLBHasRecentPrimaryChanges() {
		return $this->lbFactory->getLoadBalancer( 'virtual-centralauth' )->hasOrMadeRecentPrimaryChanges();
	}

}
