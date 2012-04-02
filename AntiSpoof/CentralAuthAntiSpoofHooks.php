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
					$message = wfMsgExt( 'antispoof-conflict-top', array( 'parsemag' ), htmlspecialchars( $name ), $numConflicts );
					$message .= '<ul>';
					foreach ( $conflicts as $simUser ) {
						$message .= '<li>' . wfMsg( 'antispoof-conflict-item', $simUser ) . '</li>';
					}
					$message .= '</ul>' . wfMsg( 'antispoof-conflict-bottom' );
					return false;
				}
			}
		} else {
			$error = $spoof->getError();
			wfDebugLog( 'antispoof', "{$mode}ILLEGAL new account '$name' $error" );
			if ( $active ) {
				$message = wfMsg( 'antispoof-name-illegal', $name, $error );
				return false;
			}
		}
		return true;
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
