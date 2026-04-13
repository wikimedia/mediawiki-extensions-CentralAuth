<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\Rdbms\IConnectionProvider;

class CentralAuthAntiSpoofManager {

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IConnectionProvider $connectionProvider,
		private readonly CentralAuthDatabaseManager $databaseManager
	) {
	}

	public function getSpoofUser( string $name ): CentralAuthSpoofUser {
		return new CentralAuthSpoofUser(
			$name,
			$this->databaseManager
		);
	}

	/**
	 * Test if an account is acceptable
	 */
	public function testNewAccount(
		User $user, User $creator, bool $enable, bool $override, ?LoggerInterface $logger = null
	): StatusValue {
		$logger ??= $this->logger;

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
}
