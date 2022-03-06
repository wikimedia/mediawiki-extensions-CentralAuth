<?php

namespace MediaWiki\Extension\CentralAuth;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use ReadOnlyError;
use ReadOnlyMode;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * Service providing access to the CentralAuth internal database.
 *
 * @since 1.37
 * @author Taavi "Majavah" Väänänen <hi@taavi.wtf>
 */
class CentralAuthDatabaseManager {
	/** @internal Only public for service wiring use */
	public const CONSTRUCTOR_OPTIONS = [
		'CentralAuthDatabase',
		'CentralAuthReadOnly',
	];

	/** @var ServiceOptions */
	private $options;

	/** @var LBFactory */
	private $lbFactory;

	/** @var ReadOnlyMode */
	private $readOnlyMode;

	/**
	 * @param ServiceOptions $options
	 * @param LBFactory $lbFactory
	 * @param ReadOnlyMode $readOnlyMode
	 */
	public function __construct( ServiceOptions $options, LBFactory $lbFactory, ReadOnlyMode $readOnlyMode ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->lbFactory = $lbFactory;
		$this->readOnlyMode = $readOnlyMode;
	}

	/**
	 * Returns a database load balancer that can be used to access the shared CentralAuth database.
	 * @return ILoadBalancer
	 */
	public function getLoadBalancer(): ILoadBalancer {
		$database = $this->options->get( 'CentralAuthDatabase' );
		return $this->lbFactory->getMainLB( $database );
	}

	/**
	 * Throw an exception if the database is read-only.
	 *
	 * @throws CentralAuthReadOnlyError
	 */
	public function assertNotReadOnly() {
		if ( $this->readOnlyMode->isReadOnly() ) {
			// ReadOnlyError gets its reason text from the global ReadOnlyMode
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

		$database = $this->options->get( 'CentralAuthDatabase' );
		$lb = $this->getLoadBalancer();

		return $lb->getReadOnlyReason( $database );
	}

	/**
	 * Wait for the CentralAuth DB replicas to catch up
	 */
	public function waitForReplication(): void {
		$this->lbFactory->waitForReplication( [ 'domain' => $this->options->get( 'CentralAuthDatabase' ) ] );
	}

	/**
	 * Gets a database connection to the CentralAuth database.
	 *
	 * @param int $index DB_PRIMARY or DB_REPLICA
	 *
	 * @return IDatabase
	 * @throws CentralAuthReadOnlyError
	 * @throws InvalidArgumentException
	 */
	public function getCentralDB( int $index ): IDatabase {
		if ( $index !== DB_PRIMARY && $index !== DB_REPLICA ) {
			throw new InvalidArgumentException( "Unknown index $index, expected DB_PRIMARY or DB_REPLICA" );
		}

		if ( $index === DB_PRIMARY ) {
			$this->assertNotReadOnly();
		}

		$database = $this->options->get( 'CentralAuthDatabase' );

		return $this->getLoadBalancer()
			->getConnectionRef( $index, [], $database );
	}

	/**
	 * Check hasOrMadeRecentPrimaryChanges() on the CentralAuth load balancer
	 *
	 * @return bool
	 */
	public function centralLBHasRecentPrimaryChanges() {
		return $this->getLoadBalancer()->hasOrMadeRecentPrimaryChanges();
	}
}
