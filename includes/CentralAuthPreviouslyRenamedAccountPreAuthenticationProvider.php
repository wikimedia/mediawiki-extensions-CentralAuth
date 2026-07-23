<?php

namespace MediaWiki\Extension\CentralAuth;

use MediaWiki\Auth\PreviouslyRenamedAccountPreAuthenticationProvider;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserLogger;
use MediaWiki\User\UserFactory;
use MediaWiki\WikiMap\WikiMap;
use StatusValue;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class CentralAuthPreviouslyRenamedAccountPreAuthenticationProvider
	extends PreviouslyRenamedAccountPreAuthenticationProvider
{

	public function __construct(
		IConnectionProvider $dbProvider,
		UserFactory $userFactory,
		private readonly CentralAuthConnectionProvider $caConnectionProvider,
		array $params = []
	) {
		parent::__construct( $dbProvider, $userFactory, $params );
	}

	protected function previouslyRenamedAccountStatus( string $username, int $flags ): StatusValue {
		if (
			GlobalRenameUserLogger::isPreviouslyRenamedAccount(
				$username,
				DBAccessObjectUtils::getDBFromRecency(
					$this->caConnectionProvider->getRemoteWikiConnectionProvider(
						$this->config->get( CAMainConfigNames::CentralAuthOldNameAntiSpoofWiki ) ?:
							WikiMap::getCurrentWikiId()
					),
					$flags
				)
			)
		) {
			return StatusValue::newFatal( 'username-previously-renamed-account' );
		}
		return StatusValue::newGood();
	}
}
