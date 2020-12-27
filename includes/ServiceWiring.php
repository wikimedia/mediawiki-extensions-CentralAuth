<?php

use MediaWiki\Config\ServiceOptions;
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
			$services->getTitleFactory(),
			$services->getHookContainer()
		);
	},

	'CentralAuth.CentralAuthWikiListService' => function ( MediaWikiServices $services ) : CentralAuthWikiListService {
		return new CentralAuthWikiListService(
			new ServiceOptions( CentralAuthWikiListService::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getHookContainer()
		);
	},
];
