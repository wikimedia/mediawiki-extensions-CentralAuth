<?php

namespace MediaWiki\Extension\CentralAuth;

use CentralAuthReadOnlyError;
use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
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
	 * Determine if either the local or the shared CentralAuth database is read only.
	 * @return bool
	 */
	public function isReadOnly(): bool {
		return ( $this->getReadOnlyReason() !== false );
	}

	/**
	 * Return the reason why either the local or the shared CentralAuth database is read only, false otherwise
	 * @return bool|string
	 */
	public function getReadOnlyReason() {
		if ( $this->readOnlyMode->isReadOnly() ) {
			return $this->readOnlyMode->getReason();
		}

		// TODO: is there a reason not to check $wgCentralAuthReadOnly here?

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

		// TODO: is there a reason not to check db-level RO data ($this->isReadOnly()) here?
		if ( $index === DB_PRIMARY && $this->options->get( 'CentralAuthReadOnly' ) ) {
			throw new CentralAuthReadOnlyError();
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
