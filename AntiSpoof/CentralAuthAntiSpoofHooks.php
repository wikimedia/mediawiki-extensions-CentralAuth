<?php

use Psr\Log\LoggerInterface;

class CentralAuthAntiSpoofHooks {

	/**
	 * Can be used to cancel user account creation
	 *
	 * @param User $user
	 * @param string &$message
	 * @return bool true to continue, false to abort user creation
	 */
	public static function asAbortNewAccountHook( $user, &$message ) {
		global $wgAntiSpoofAccounts, $wgUser, $wgRequest;

		$status = self::testNewAccount(
			$user, $wgUser, $wgAntiSpoofAccounts, $wgRequest->getCheck( 'wpIgnoreAntiSpoof' )
		);
		if ( !$status->isGood() ) {
			$message = Status::wrap( $status )->getMessage()->escaped();
		}
		return $status->isGood();
	}

	/**
	 * Test if an account is acceptable
	 * @param User $user
	 * @param User $creator
	 * @param bool $enable
	 * @param bool $override
	 * @param LoggerInterface|null $logger
	 * @return StatusValue
	 */
	public static function testNewAccount( $user, $creator, $enable, $override, $logger = null ) {
		global $wgCentralAuthOldNameAntiSpoofWiki;
		if ( $logger === null ) {
			$logger = \MediaWiki\Logger\LoggerFactory::getInstance( 'antispoof' );
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
		$spoof = new CentralAuthSpoofUser( $name );
		if ( $spoof->isLegal() ) {
			$normalized = $spoof->getNormalized();
			$conflicts = $spoof->getConflicts();
			if ( empty( $conflicts ) ) {
				$logger->info( "{$mode}PASS new account '$name' [$normalized]" );
			} else {
				$logger->info( "{$mode}CONFLICT new account '$name' [$normalized] spoofs " .
					implode( ',', $conflicts ) );
				if ( $active ) {
					$numConflicts = count( $conflicts );

					// This message pasting-together sucks.
					$message = wfMessage( 'antispoof-conflict-top', $name )
						->numParams( $numConflicts )->escaped();
					$message .= '<ul>';
					foreach ( $conflicts as $simUser ) {
						$message .= '<li>' .
							wfMessage( 'antispoof-conflict-item', $simUser )->escaped() . '</li>';
					}
					$message .= '</ul>' . wfMessage( 'antispoof-conflict-bottom' )->escaped();
					return StatusValue::newFatal(
						new RawMessage( '$1', Message::rawParam( $message ) )
					);
				}
			}
		} else {
			$error = $spoof->getError();
			$logger->info( "{$mode}ILLEGAL new account '$name' $error" );
			if ( $active ) {
				return StatusValue::newFatal( 'antispoof-name-illegal', $name, $error );
			}
		}

		$dbLogWiki = wfGetDB( DB_REPLICA, [], $wgCentralAuthOldNameAntiSpoofWiki );
		$newNameOfUser = $dbLogWiki->selectField(
			[ 'logging', 'log_search' ],
			'log_title',
			[
				'ls_field' => 'old_name',
				'ls_value' => $name,
				'log_type' => 'gblrename',
				'log_namespace' => NS_SPECIAL
			],
			__METHOD__,
			[ 'LIMIT' => 1 ],
			[ 'logging' => [ 'INNER JOIN', 'ls_log_id=log_id' ] ]
		);
		$slashPos = strpos( $newNameOfUser, '/' );
		if ( $newNameOfUser && $slashPos ) {
			// We have to remove the Special:CentralAuth prefix.
			$error = substr( $newNameOfUser, $slashPos + 1 );
			$logger->info( "{$mode}ILLEGAL [due to rename] new account '$name' $error" );
			if ( $active ) {
				return StatusValue::newFatal( 'antispoof-name-illegal', $name, $error );
			}

		}
		return StatusValue::newGood();
	}

	/**
	 * On new account creation, record the username's thing-bob.
	 * (Called after a user account is created)
	 *
	 * @param User $user
	 * @return bool
	 */
	public static function asAddNewAccountHook( $user ) {
		$spoof = new CentralAuthSpoofUser( $user->getName() );
		$spoof->record();
		return true;
	}

	/**
	 * On rename, remove the old entry and add the new
	 * (After a sucessful user rename)
	 *
	 * @param int $uid Unused
	 * @param string $oldName
	 * @param string $newName
	 * @return bool
	 */
	public static function asAddRenameUserHook( $uid, $oldName, $newName ) {
		$spoof = new CentralAuthSpoofUser( $newName );
		$spoof->update( $oldName );
		return true;
	}
}
