<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Handlers;

use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\UserEditCountUpdateHook;

class UserEditCountUpdateHookHandler implements UserEditCountUpdateHook {
	/** @var CentralAuthEditCounter */
	private $editCounter;

	public function __construct( CentralAuthEditCounter $editCounter ) {
		$this->editCounter = $editCounter;
	}

	public function onUserEditCountUpdate( $infos ): void {
		foreach ( $infos as $info ) {
			$centralUser = CentralAuthUser::getInstanceByName( $info->getUser()->getName() );
			if ( $centralUser->isAttached() ) {
				$this->editCounter->increment( $centralUser, $info->getIncrement() );
			}
		}
	}
}
