<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Language\RawMessage;
use Message;
use Psr\Log\LoggerInterface;
use StatusValue;
use User;
use Wikimedia\Rdbms\IConnectionProvider;

class CentralAuthAntiSpoofManager {
	/** @internal Only public for service wiring use. */
	public const CONSTRUCTOR_OPTIONS = [
		'CentralAuthOldNameAntiSpoofWiki',
	];

	private ServiceOptions $options;
	private LoggerInterface $logger;
	private IConnectionProvider $connectionProvider;
	private CentralAuthDatabaseManager $databaseManager;

	/**
	 * @param ServiceOptions $options
	 * @param LoggerInterface $logger
	 * @param IConnectionProvider $connectionProvider
	 * @param CentralAuthDatabaseManager $databaseManager
	 */
	public function __construct(
		ServiceOptions $options,
		LoggerInterface $logger,
		IConnectionProvider $connectionProvider,
		CentralAuthDatabaseManager $databaseManager
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->logger = $logger;
		$this->connectionProvider = $connectionProvider;
		$this->databaseManager = $databaseManager;
	}

	/**
	 * @param string $name
	 * @return CentralAuthSpoofUser
	 */
	public function getSpoofUser( string $name ): CentralAuthSpoofUser {
		return new CentralAuthSpoofUser(
			$name,
			$this->databaseManager
		);
	}

	/**
	 * Test if an account is acceptable
	 *
	 * @param User $user
	 * @param User $creator
	 * @param bool $enable
	 * @param bool $override
	 * @param LoggerInterface|null $logger
	 *
	 * @return StatusValue
	 */
	public function testNewAccount( $user, $creator, $enable, $override, $logger = null ) {
		if ( $logger === null ) {
			$logger = $this->logger;
		}

		if ( !$enable ) {
			$mode = 'LOGGING ';
			$active = false;
		} elseif ( $override && $creator->isAllowed( 'override-antispoof' ) ) {
			$mode = 'OVERRIDE ';
			$active = false;
		} else {
			$mode = '';
			$active = true;
		}

		$name = $user->getName();
		$spoof = $this->getSpoofUser( $name );
		if ( $spoof->isLegal() ) {
			$normalized = $spoof->getNormalized();
			$conflicts = $spoof->getConflicts();
			$oldUserName = $this->getOldRenamedUserName( $name );
			if ( $oldUserName !== null ) {
				$conflicts[] = $oldUserName;
			}
			if ( !$conflicts ) {
				$logger->info( "{$mode}PASS new account '$name' [$normalized]" );
			} else {
				$logger->info( "{$mode}CONFLICT new account '$name' [$normalized] spoofs " .
					implode( ',', $conflicts ) );

				if ( $active ) {
					$list = [];
					foreach ( $conflicts as $simUser ) {
						$list[] = "* " . wfMessage( 'antispoof-conflict-item', $simUser )->plain();
					}
					$list = implode( "\n", $list );

					return StatusValue::newFatal(
						'antispoof-conflict',
						$name,
						Message::numParam( count( $conflicts ) ),
						// Avoid forced wikitext escaping for params in the Status class
						new RawMessage( $list )
					);
				}
			}
		} else {
			$error = $spoof->getErrorStatus();
			$logger->info( "{mode}ILLEGAL new account '{name}' {error}", [
				'mode' => $mode,
				'name' => $name,
				'error' => $error->getMessage( false, false, 'en' )->text(),
			] );
			if ( $active ) {
				return StatusValue::newFatal( 'antispoof-name-illegal', $name, $error->getMessage() );
			}
		}
		return StatusValue::newGood();
	}

	/**
	 * Given a username, find the old name
	 *
	 * @param string $name Name to lookup
	 *
	 * @return null|string Old username, or null
	 */
	public function getOldRenamedUserName( $name ) {
		$dbrLogWiki = $this->connectionProvider->getReplicaDatabase(
			// If nobody has set this variable, it will be false,
			// which will mean the current wiki, which sounds like as
			// good a default as we can get.
			$this->options->get( 'CentralAuthOldNameAntiSpoofWiki' )
		);

		$newNameOfUser = $dbrLogWiki->selectField(
			[ 'logging', 'log_search' ],
			'log_title',
			[
				'ls_field' => 'oldname',
				'ls_value' => $name,
				'log_type' => 'gblrename',
				'log_namespace' => NS_SPECIAL
			],
			__METHOD__,
			[],
			[ 'logging' => [ 'INNER JOIN', 'ls_log_id=log_id' ] ]
		);
		$slashPos = strpos( $newNameOfUser ?: '', '/' );
		if ( $newNameOfUser && $slashPos ) {
			// We have to remove the Special:CentralAuth prefix.
			return substr( $newNameOfUser, $slashPos + 1 );
		}
		return null;
	}
}
