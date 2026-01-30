<?php

namespace MediaWiki\Extension\CentralAuth\User;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\Rdbms\IConnectionProvider;

class CentralAuthAntiSpoofManager {

	/** @internal Only public for service wiring use. */
	public const CONSTRUCTOR_OPTIONS = [
		CAMainConfigNames::CentralAuthOldNameAntiSpoofWiki,
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly LoggerInterface $logger,
		private readonly IConnectionProvider $connectionProvider,
		private readonly CentralAuthDatabaseManager $databaseManager
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
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
	 * @return ?string Old username, or null
	 */
	public function getOldRenamedUserName( string $name ): ?string {
		$dbrLogWiki = $this->connectionProvider->getReplicaDatabase(
			// If nobody has set this variable, it will be false,
			// which will mean the current wiki, which sounds like as
			// good a default as we can get.
			$this->options->get( CAMainConfigNames::CentralAuthOldNameAntiSpoofWiki )
		);

		$newNameOfUser = $dbrLogWiki->newSelectQueryBuilder()
			->select( 'log_title' )
			->from( 'logging' )
			->join( 'log_search', null, 'ls_log_id=log_id' )
			->where( [
				'ls_field' => 'oldname',
				'ls_value' => $name,
				'log_type' => 'gblrename',
				'log_namespace' => NS_SPECIAL
			] )
			->caller( __METHOD__ )
			->fetchField();
		$slashPos = strpos( $newNameOfUser ?: '', '/' );
		if ( $newNameOfUser && $slashPos ) {
			// We have to remove the Special:CentralAuth prefix.
			return substr( $newNameOfUser, $slashPos + 1 );
		}
		return null;
	}
}
