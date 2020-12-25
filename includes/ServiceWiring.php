<?php

use MediaWiki\MediaWikiServices;

return [
	'CentralAuth.CentralAuthUtilityService' => function ( MediaWikiServices $services ) : CentralAuthUtilityService {
		return new CentralAuthUtilityService(
			$services->getDBLoadBalancerFactory(),
			$services->getReadOnlyMode(),
			$services->getMainConfig(),
			$services->getAuthManager(),
			$services->getUserFactory(),
			$services->getPermissionManager(),
			$services->getStatsdDataFactory(),
			$services->getTitleFactory()
		);
	},
];
