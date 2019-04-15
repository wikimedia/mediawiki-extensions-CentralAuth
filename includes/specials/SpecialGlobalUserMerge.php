<?php

class SpecialGlobalUserMerge extends FormSpecialPage {

	/**
	 * Maximum number of users that can be
	 * merged at once
	 */
	const MAX_USERS_TO_MERGE = 5;

	/**
	 * @var CentralAuthUser[]
	 */
	private $oldCAUsers = [];

	/**
	 * @var string
	 */
	private $newUsername;

	public function __construct() {
		parent::__construct( 'GlobalUserMerge', 'centralauth-usermerge' );
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $par ) {
		global $wgCentralAuthEnableUserMerge;
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UserMerge' ) ) {
			$this->setHeaders();
			throw new ErrorPageError( 'error', 'centralauth-usermerge-notinstalled' );
		}

		if ( !$wgCentralAuthEnableUserMerge ) {
			$this->setHeaders();
			throw new ErrorPageError( 'error', 'centralauth-usermerge-disabled' );
		}

		$this->getOutput()->addModules( 'ext.centralauth.globalrenameuser' );
		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		return [
			'finaluser' => [
				'id' => 'mw-globalusermerge-usernames',
				'name' => 'newuser',
				'label-message' => 'centralauth-usermerge-form-newuser',
				'type' => 'text',
				'required' => true,
				'validation-callback' => [ $this, 'validateUsername' ],
			],
			'usernames' => [
				'id' => 'mw-globalusermerge-usernames',
				'name' => 'usernames',
				'label-message' => 'centralauth-usermerge-form-usernames',
				'type' => 'cloner',
				'format' => 'table',
				'create-button-message' => 'centralauth-usermerge-form-adduser',
				'delete-button-message' => 'centralauth-usermerge-form-deleteuser',
				'fields' => [
					'name' => [
						'type' => 'text',
						'validation-callback' => [ $this, 'validateUsername' ],
					],
				],
			],
			'reason' => [
				'id' => 'mw-globalusermerge-reason',
				'name' => 'reason',
				'label-message' => 'centralauth-usermerge-form-reason',
				'type' => 'text',
			],
		];
	}

	/**
	 * @param string|null $name
	 * @return string|bool
	 */
	public function validateUsername( $name ) {
		if ( $name === null || $name === '' ) {
			// blank cloner field, bypass.
			return true;
		}

		$name = User::getCanonicalName( $name, 'usable' );
		if ( !$name ) {
			return $this->msg( 'centralauth-usermerge-invalid', $name )->escaped();
		}

		if ( $name === $this->getUser()->getName() ) {
			return $this->msg( 'centralauth-usermerge-noself' )->escaped();
		}

		$caUser = CentralAuthUser::getMasterInstanceByName( $name );
		if ( !$caUser->exists() ) {
			return $this->msg( 'centralauth-usermerge-invalid', $name )->escaped();
		}

		if ( $caUser->renameInProgress() ) {
			return $this->msg( 'centralauth-usermerge-already', $name )->escaped();
		}

		return true;
	}

	/**
	 * Implement a rudimentary rate limiting system,
	 * we can't use User::pingLImiter() because stewards
	 * have the "noratelimit" userright
	 *
	 * Hardcoded to allow 1 merge per 60 seconds
	 *
	 * @return bool true if we should let the user proceed
	 */
	private function checkRateLimit() {
		$cache = ObjectCache::newAnything();
		$key = 'centralauth:usermerge:' . md5( $this->getUser()->getName() );
		$found = $cache->get( $key );
		if ( $found === false ) {
			$cache->set( $key, true, 60 );
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$newUser = User::newFromName( $data['finaluser'], 'creatable' );
		if ( !$newUser ) {
			return Status::newFatal( 'centralauth-usermerge-invalid' );
		}

		if ( !$this->checkRateLimit() ) {
			return Status::newFatal( 'centralauth-usermerge-ratelimited' );
		}

		foreach ( $data['usernames'] as $field ) {
			if ( trim( $field['name'] ) !== '' ) {
				$name = User::getCanonicalName( $field['name'] );
				if ( $name === $newUser->getName() ) {
					// The new user is also specified as one of the targets,
					// DWIM and ignore it
					continue;
				}
				$caUser = CentralAuthUser::getMasterInstanceByName( $name );
				$this->oldCAUsers[] = $caUser;
			}
		}

		if ( !$this->oldCAUsers ) {
			return $this->msg( 'centralauth-usermerge-nousers' )->escaped();
		}

		if ( count( $this->oldCAUsers ) > self::MAX_USERS_TO_MERGE ) {
			return Status::newFatal( $this->msg( 'centralauth-usermerge-toomany' )
				->numParams( self::MAX_USERS_TO_MERGE ) );
		}

		$this->newUsername = $newUser->getName();

		$globalUserMerge = new GlobalUserMerge(
			$this->getUser(),
			$this->oldCAUsers,
			CentralAuthUser::getInstance( $newUser ),
			new GlobalRenameUserStatus( $newUser->getName() ),
			'JobQueueGroup::singleton',
			new GlobalUserMergeDatabaseUpdates(),
			new GlobalUserMergeLogger( $this->getUser() ),
			$this->getContext()->exportSession()
		);
		$status = $globalUserMerge->merge( $data['reason'] );

		return $status;
	}

	/**
	 * Get a HTML link to this user's Special:CentralAuth page
	 *
	 * @param string $name
	 * @return string raw HTML
	 */
	public function getLocalizedCentralAuthLink( $name ) {
		return $this->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'CentralAuth', $name ),
			$name
		);
	}

	public function onSuccess() {
		$lang = $this->getLanguage();
		$us = $this;
		$globalUsers = array_map( function ( CentralAuthUser $user ) use ( $us ) {
			return $us->getLocalizedCentralAuthLink( $user->getName() );
		}, $this->oldCAUsers );
		$userList = $lang->commaList( $globalUsers );

		$msg = $this->msg( 'centralauth-usermerge-queued' )
			->rawParams(
				$userList,
				$this->getLocalizedCentralAuthLink( $this->newUsername )
			)->params( $this->newUsername )->parse();
		$this->getOutput()->addHTML( $msg );
	}

	protected function getGroupName() {
		return 'users';
	}
}
