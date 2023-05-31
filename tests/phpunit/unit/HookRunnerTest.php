<?php

namespace MediaWiki\Extension\CentralAuth\Hooks\Tests\Unit;

use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \MediaWiki\Extension\CentralAuth\Hooks\CentralAuthHookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {

	public static function provideHookRunners() {
		yield CentralAuthHookRunner::class => [ CentralAuthHookRunner::class ];
	}
}
