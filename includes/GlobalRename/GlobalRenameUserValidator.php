<?php

namespace MediaWiki\Extension\CentralAuth\GlobalRename;

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;

/**
 * Verify whether a potential global rename is valid
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch < hoo@online.de >
 */
class GlobalRenameUserValidator {

	/** @var UserNameUtils */
	private $userNameUtils;

	/**
	 * @param UserNameUtils $userNameUtils
	 */
	public function __construct( UserNameUtils $userNameUtils ) {
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * Check that we can perform the rename
	 *
	 * @param UserIdentity $oldUser
	 * @param UserIdentity $newUser
	 *
	 * @return Status
	 */
	public function validate( UserIdentity $oldUser, UserIdentity $newUser ) {
		$status = new Status();

		if ( !$this->userNameUtils->isCreatable( $newUser->getName() ) ) {
			$status->fatal( 'centralauth-rename-badusername' );
		}

		$caOldUser = CentralAuthUser::getInstance( $oldUser );
		if ( !$caOldUser->exists() ) {
			$status->fatal( 'centralauth-rename-doesnotexist' );
		}
		$caNewUser = CentralAuthUser::getInstance( $newUser );
		if ( $caNewUser->exists() ) {
			$status->fatal( 'centralauth-rename-alreadyexists' );
		}

		$unattached = $caNewUser->listUnattached();
		if ( $unattached ) {
			$status->fatal( 'centralauth-rename-unattached-intheway' );
		}

		// Check we're not currently renaming the user
		$renameState = $caOldUser->renameInProgress();
		if ( $renameState ) {
			$status->fatal( 'centralauth-rename-alreadyinprogress', $renameState[1] );
		}

		return $status;
	}
}
