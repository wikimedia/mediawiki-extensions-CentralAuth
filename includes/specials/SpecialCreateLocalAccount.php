<?php

/**
 * Special page that can be used to manually create a local account for a global account.
 *
 * @author Taavi "Majavah" Väänänen
 * @ingroup SpecialPage
 * @since 1.36
 */
class SpecialCreateLocalAccount extends FormSpecialPage {
	/** @var CentralAuthForcedLocalCreationService */
	private $forcedLocalCreationService;

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

	protected function preText() {
		return $this->msg( 'centralauth-createlocal-pretext' )->parse();
	}

	public function execute( $par ) {
		$this->requireLogin();
		$this->checkPermissions();

		$this->addHelpLink( 'Help:Extension:CentralAuth/CreateLocalAccount' );
		$out = $this->getOutput();
		$out->addModules( 'ext.centralauth.globaluserautocomplete' );

		parent::execute( $par );
	}

	/**
	 * @return array
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
