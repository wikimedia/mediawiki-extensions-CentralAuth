<?php

use MediaWiki\CentralAuth\UserManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'CentralAuthUserManager' => function ( MediaWikiServices $services ) : UserManager {
		$context = RequestContext::getMain();
		return new UserManager(
			$context->getLanguage(),
			$services->getDBLoadBalancerFactory(),
			$services->getActorMigration(),
			$services->getCommentStore(),
			LoggerFactory::getInstance( 'CentralAuth' ),
			$services->getBlockRestrictionStore()
		);
	},
];
