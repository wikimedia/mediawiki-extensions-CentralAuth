<?php

use MediaWiki\MediaWikiServices;

return [
	'CentralAuth.CentralAuthForcedLocalCreationService' => function (
		MediaWikiServices $services
	) : CentralAuthForcedLocalCreationService {
		return new CentralAuthForcedLocalCreationService(
			$services->getUserFactory(),
			CentralAuthServices::getUtilityService( $services )
		);
	},
	'CentralAuth.CentralAuthUtilityService' => function ( MediaWikiServices $services ) : CentralAuthUtilityService {
		return new CentralAuthUtilityService(
			$services->getDBLoadBalancerFactory(),
			$services->getReadOnlyMode(),
			$services->getMainConfig(),
			$services->getAuthManager(),
			$services->getUserFactory(),
			$services->getStatsdDataFactory(),
			$services->getTitleFactory()
		);
	},
];
