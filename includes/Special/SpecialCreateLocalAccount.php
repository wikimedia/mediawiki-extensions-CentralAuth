<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use MediaWiki\Extension\CentralAuth\User\CentralAuthForcedLocalCreationService;
use MediaWiki\Extension\CentralAuth\Widget\HTMLGlobalUserTextField;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;

/**
 * Special page that can be used to manually create a local account for a global account.
 *
 * @author Taavi "Majavah" Väänänen
 * @ingroup SpecialPage
 * @since 1.36
 */
class SpecialCreateLocalAccount extends FormSpecialPage {

	private CentralAuthForcedLocalCreationService $forcedLocalCreationService;

	public function __construct( CentralAuthForcedLocalCreationService $forcedLocalCreationService ) {
		parent::__construct( 'CreateLocalAccount', 'centralauth-createlocal' );
		$this->forcedLocalCreationService = $forcedLocalCreationService;
	}

	/**
	 * @return bool
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/** @inheritDoc */
	protected function preHtml() {
		return $this->msg( 'centralauth-createlocal-pretext' )->parse();
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->requireNamedUser();
		$this->checkPermissions();

		$this->addHelpLink( 'Help:Extension:CentralAuth/CreateLocalAccount' );

		parent::execute( $par );
	}

	/**
	 * @return array[]
	 */
	public function getFormFields() {
		return [
			'username' => [
				'class' => HTMLGlobalUserTextField::class,
				'name' => 'target',
				'label-message' => 'centralauth-createlocal-username',
			],
			'reason' => [
				'type' => 'text',
				'label-message' => 'centralauth-createlocal-reason',
			]
		];
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$username = $data['username'];
		$reason = $data['reason'];

		return $this->forcedLocalCreationService
			->attemptAutoCreateLocalUserFromName( $username, $this->getUser(), $reason );
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'centralauth-createlocal-success' );
	}
}
