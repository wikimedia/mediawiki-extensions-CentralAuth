<?php

class CentralAuthAntiSpoofHooks {

	/**
	 * Can be used to cancel user account creation
	 *
	 * @param $user User
	 * @param $message string
	 * @return bool true to continue, false to abort user creation
	 */
	public static function asAbortNewAccountHook( $user, &$message ) {
		$status = self::testNewAccount( $user );
		if ( !$status->isGood() ) {
			$message = Status::wrap( $status )->getMessage()->escaped();
		}
		return $status->isGood();
	}

	/**
	 * Test if an account is acceptable
	 * @param $user User
	 * @return StatusValue
	 */
	public static function testNewAccount( $user ) {
		global $wgAntiSpoofAccounts, $wgUser, $wgRequest;

		if ( !$wgAntiSpoofAccounts ) {
			$mode = 'LOGGING ';
			$active = false;
		} elseif ( $wgRequest->getCheck( 'wpIgnoreAntiSpoof' ) &&
				$wgUser->isAllowed( 'override-antispoof' ) ) {
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
				wfDebugLog( 'antispoof', "{$mode}PASS new account '$name' [$normalized]" );
			} else {
				wfDebugLog( 'antispoof', "{$mode}CONFLICT new account '$name' [$normalized] spoofs " . implode( ',', $conflicts ) );
				if ( $active ) {
					$numConflicts = count( $conflicts );

					// This message pasting-together sucks.
					$message = wfMessage( 'antispoof-conflict-top', $name )->numParams( $numConflicts )->escaped();
					$message .= '<ul>';
					foreach ( $conflicts as $simUser ) {
						$message .= '<li>' . wfMessage( 'antispoof-conflict-item', $simUser )->escaped() . '</li>';
					}
					$message .= '</ul>' . wfMessage( 'antispoof-conflict-bottom' )->escaped();
					return StatusValue::newFatal( new RawMessage( '$1', Message::rawParam( $message ) ) );
				}
			}
		} else {
			$error = $spoof->getError();
			wfDebugLog( 'antispoof', "{$mode}ILLEGAL new account '$name' $error" );
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
	 * @param $user User
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
	 * @param $uid
	 * @param $oldName string
	 * @param $newName string
	 * @return bool
	 */
	public static function asAddRenameUserHook( $uid, $oldName, $newName ) {
		$spoof = new CentralAuthSpoofUser( $newName );
		$spoof->update( $oldName );
		return true;
	}
}
