<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Tests\Phpunit\Integration\Hooks\Handlers;

use CentralAuthTestUser;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\Hooks\Handlers\SpecialContributionsHookHandler;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\Handlers\SpecialContributionsHookHandler
 */
class SpecialContributionsHookHandlerTest extends MediaWikiIntegrationTestCase {

	private function newHandler(): SpecialContributionsHookHandler {
		$services = $this->getServiceContainer();
		return new SpecialContributionsHookHandler(
			$services->getNamespaceInfo(),
			$services->getUserFactory(),
			$services->getUserNameUtils()
		);
	}

	private function newLockedUser( string $name ): CentralAuthTestUser {
		$u = new CentralAuthTestUser(
			$name,
			'GUP@ssword',
			[ 'gu_id' => '1001', 'gu_locked' => 1 ],
			[ [ WikiMap::getCurrentWikiId(), 'primary' ] ]
		);
		$u->save( $this->getDb() );
		return $u;
	}

	public function testLockNoticeHasClassWhenLogEntryExists() {
		$name = 'CentralAuthLockedUserWithLog';
		$this->newLockedUser( $name );

		$log = new ManualLogEntry( 'globalauth', 'setstatus' );
		$log->setTarget( Title::makeTitle( NS_USER, $name . '@global' ) );
		$log->setParameters( [ 'added' => [ 'locked' ], 'removed' => [] ] );
		$log->setPerformer( $this->getTestUser()->getUser() );
		$log->insert( $this->getDb() );

		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'Contributions' ) );

		$specialPage = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Contributions' );
		$specialPage->setContext( RequestContext::getMain() );

		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $name );
		$this->newHandler()->onSpecialContributionsBeforeMainOutput( $user->getId(), $user, $specialPage );

		$html = $specialPage->getOutput()->getHTML();

		$this->assertStringContainsString( 'mw-centralauth-contribs-locked-notice', $html );
	}

	public function testLockNoticeHasClassWhenLogEntryMissing() {
		$name = 'CentralAuthLockedUserNoLog';
		$this->newLockedUser( $name );

		RequestContext::getMain()->setTitle( Title::makeTitle( NS_SPECIAL, 'Contributions' ) );

		$specialPage = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Contributions' );
		$specialPage->setContext( RequestContext::getMain() );

		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $name );
		$this->newHandler()->onSpecialContributionsBeforeMainOutput( $user->getId(), $user, $specialPage );

		$html = $specialPage->getOutput()->getHTML();

		$this->assertStringContainsString( 'mw-centralauth-contribs-locked-notice', $html );
		$this->assertStringContainsString( 'mw-warning-with-logexcerpt', $html );
	}

}
