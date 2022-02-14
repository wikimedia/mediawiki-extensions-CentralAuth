<?php

use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;

/**
 * Setup test global users
 */
class CentralAuthTestUser {

	/**
	 * Name for both local wiki and global user
	 * @var string
	 */
	private $username;
	/**
	 * @var string
	 */
	private $password;

	/**
	 * @var string gu_id
	 */
	private $guId;

	/**
	 * @var string gu_password
	 */
	private $passHash;

	/**
	 * @var string gu_salt
	 */
	private $salt;

	/**
	 * @var string gu_auth_token
	 */
	private $authToken;

	/**
	 * @var int gu_locked
	 */
	private $locked;

	/**
	 * @var string gu_hidden_level
	 */
	private $hiddenLevel;

	/**
	 * @var string gu_registration
	 */
	private $registration;

	/**
	 * @var string gu_email
	 */
	private $email;

	/**
	 * @var string gu_email_authenticated
	 */
	private $emailAuthenticated;

	/**
	 * @var string gu_home_db
	 */
	private $homeDb;

	/**
	 * @var string gu_enabled
	 */
	private $enabled;

	/**
	 * @var string gu_enabled_method
	 */
	private $enabledMethod;

	/**
	 * Array of attachments to insert into localuser
	 * @var array[]
	 */
	private $wikis;

	/**
	 * @var bool If we should create the local wiki user too. Usually we do, but
	 * sometimes we want to test when it doesn't.
	 */
	private $createLocal;

	/**
	 * @param string $username
	 * @param string $password password for the account
	 * @param array $attrs associative array of global user attributs
	 * @param array[] $wikis array of arrays of wiki, attachement method
	 * @param bool $createLocal
	 */
	public function __construct(
		$username,
		$password,
		array $attrs = [],
		array $wikis = [],
		$createLocal = true
	) {
		$this->username = $username;
		$this->password = $password;

		$passwordFactory = new PasswordFactory();
		$passwordFactory->init( RequestContext::getMain()->getConfig() );

		$attrs += [
			'gu_id' => '1000',
			'gu_password' => $passwordFactory->newFromPlaintext( $password )->toString(),
			'gu_salt' => '',
			'gu_auth_token' => '1234',
			'gu_locked' => 0,
			'gu_hidden_level' => CentralAuthUser::HIDDEN_LEVEL_NONE,
			'gu_registration' => '20130627183537',
			'gu_email' => 'test@localhost',
			'gu_email_authenticated' => '20130801040214',
			'gu_home_db' => WikiMap::getCurrentWikiId(),
			'gu_enabled' => '',
			'gu_enabled_method' => null,
		];

		$this->guId = $attrs['gu_id'];
		$this->passHash = $attrs['gu_password'];
		$this->salt = $attrs['gu_salt'];
		$this->authToken = $attrs['gu_auth_token'];
		$this->locked = $attrs['gu_locked'];
		$this->hiddenLevel = $attrs['gu_hidden_level'];
		$this->registration = $attrs['gu_registration'];
		$this->email = $attrs['gu_email'];
		$this->emailAuthenticated = $attrs['gu_email_authenticated'];
		$this->homeDb = $attrs['gu_home_db'];
		$this->enabled = $attrs['gu_enabled'];
		$this->enabledMethod = $attrs['gu_enabled_method'];

		$this->wikis = [];
		foreach ( $wikis as $wiki ) {
			$this->wikis[] = [
				'lu_wiki' => $wiki[0],
				'lu_name' => $this->username,
				'lu_attached_timestamp' => $this->registration,
				'lu_attached_method' => $wiki[1],
			];
		}

		$this->createLocal = $createLocal;
	}

	/**
	 * Save the user into a centralauth database
	 * @param IDatabase $db
	 */
	public function save( IDatabase $db ) {
		$user = User::newFromName( $this->username );

		// Setup global user
		$row = [
			'gu_name' => $this->username,
			'gu_id' => $this->guId,
			'gu_password' => $this->passHash,
			'gu_salt' => $this->salt,
			'gu_auth_token' => $this->authToken,
			'gu_locked' => $this->locked,
			'gu_hidden_level' => $this->hiddenLevel,
			'gu_registration' => $db->timestamp( $this->registration ),
			'gu_email' => $this->email,
			'gu_email_authenticated' => $db->timestamp( $this->emailAuthenticated ),
			'gu_home_db' => $this->homeDb,
			'gu_enabled' => $db->timestamp( $this->enabled ),
			'gu_enabled_method' => $this->enabledMethod,
		];
		$db->insert(
			'globaluser',
			$row,
			__METHOD__
		);

		// Attach global to local accounts
		$db->delete(
			'localuser',
			[ 'lu_name' => $this->username ],
			__METHOD__
		);

		if ( count( $this->wikis ) ) {
			foreach ( $this->wikis as &$wikiRows ) {
				$wikiRows['lu_attached_timestamp'] = $db->timestamp( $wikiRows['lu_attached_timestamp'] );
			}
			$db->insert( 'localuser', $this->wikis, __METHOD__ );
		}

		// Setup local wiki user
		if ( $this->createLocal ) {
			if ( $user->idForName() == 0 ) {
				$user->addToDatabase();
				TestUser::setPasswordForUser( $user, $this->password );
			}
		}
	}

}
