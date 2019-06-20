<?php

namespace MediaWiki\CentralAuth;

use MediaWiki\Block\BlockRestrictionStore;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILBFactory;

class UserManager {

	/**
	 * @var \Language
	 */
	private $language;

	/**
	 * @var ILBFactory
	 */
	private $lbFactory;

	/**
	 * @var \ActorMigration
	 */
	private $actorMigration;

	/**
	 * @var \CommentStore
	 */
	private $commentStore;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var BlockRestrictionStore
	 */
	private $blockRestrictions;

	/**
	 * Construct a new User Manager.
	 *
	 * @param \Language $language
	 * @param ILBFactory $lbFactory
	 * @param \ActorMigration $actorMigraiton
	 * @param \CommentStore $commentStore
	 * @param LoggerInterface $logger
	 * @param BlockRestrictionStore $blockRestrictions
	 */
	public function __construct(
		\Language $language,
		ILBFactory $lbFactory,
		\ActorMigration $actorMigraiton,
		\CommentStore $commentStore,
		LoggerInterface $logger,
		BlockRestrictionStore $blockRestrictions
	) {
		$this->language = $language;
		$this->lbFactory = $lbFactory;
		$this->actorMigration = $actorMigraiton;
		$this->commentStore = $commentStore;
		$this->logger = $logger;
		$this->blockRestrictions = $blockRestrictions;
	}

	/**
	 * Fetch a row of user data needed for migration.
	 *
	 * Returns most data in the user and ipblocks tables, user groups, and editcount.
	 *
	 * @param string $wikiID
	 * @param string $username
	 *
	 * @throws \LocalUserNotFoundException if local user not found
	 * @return array
	 */
	public function getLocalUserData( $wikiID, $username ) {
		$lb = $this->lbFactory->getMainLB( $wikiID );
		$db = $lb->getConnectionRef( DB_REPLICA, [], $wikiID );
		$fields = [
				'user_id',
				'user_email',
				'user_name',
				'user_email_authenticated',
				'user_password',
				'user_editcount',
				'user_registration',
			];
		$conds = [ 'user_name' => $username ];
		$row = $db->selectRow( 'user', $fields, $conds, __METHOD__ );
		if ( !$row ) {
			# Row missing from slave, try the master instead
			$db = $lb->getConnectionRef( DB_MASTER, [], $wikiID );
			$row = $db->selectRow( 'user', $fields, $conds, __METHOD__ );
		}
		if ( !$row ) {
			$ex = new \LocalUserNotFoundException(
				"Could not find local user data for {$username}@{$wikiID}" );
			$this->logger->warning(
				'Could not find local user data for {username}@{wikiId}',
				[
					'username' => $username,
					'wikiId' => $wikiID,
					'exception' => $ex,
				]
			);
			throw $ex;
		}

		$data = [
			'wiki' => $wikiID,
			'id' => $row->user_id,
			'name' => $row->user_name,
			'email' => $row->user_email,
			'emailAuthenticated' =>
				wfTimestampOrNull( TS_MW, $row->user_email_authenticated ),
			'registration' =>
				wfTimestampOrNull( TS_MW, $row->user_registration ),
			'password' => $row->user_password,
			'editCount' => $row->user_editcount,
			'groupMemberships' => [], // array of (group name => UserGroupMembership object)
			'blocked' => false ];

		// Edit count field may not be initialized...
		if ( is_null( $row->user_editcount ) ) {
			$actorWhere = $this->actorMigration->getWhere( $db, 'rev_user', \User::newFromId( $data['id'] ) );
			$data['editCount'] = 0;
			foreach ( $actorWhere['orconds'] as $cond ) {
				$data['editCount'] += $db->selectField(
					[ 'revision' ] + $actorWhere['tables'],
					'COUNT(*)',
					$cond,
					__METHOD__,
					[],
					$actorWhere['joins']
				);
			}
		}

		// And we have to fetch groups separately, sigh...
		$data['groupMemberships'] = \UserGroupMembership::getMembershipsForUser( $data['id'], $db );

		// And while we're in here, look for user blocks :D
		$commentQuery = $this->commentStore->getJoin( 'ipb_reason' );
		$result = $db->select(
			[ 'ipblocks' ] + $commentQuery['tables'],
			[
				'ipb_id',
				'ipb_expiry', 'ipb_block_email',
				'ipb_anon_only', 'ipb_create_account',
				'ipb_enable_autoblock', 'ipb_allow_usertalk',
				'ipb_sitewide',
			] + $commentQuery['fields'],
			[ 'ipb_user' => $data['id'] ],
			__METHOD__,
			[],
			$commentQuery['joins']
		);
		foreach ( $result as $row ) {
			if ( $this->language->formatExpiry( $row->ipb_expiry, TS_MW ) > wfTimestampNow() ) {
				$data['block-expiry'] = $row->ipb_expiry;
				$data['block-reason'] = $this->commentStore->getComment( 'ipb_reason', $row )->text;
				$data['block-anononly'] = (bool)$row->ipb_anon_only;
				$data['block-nocreate'] = (bool)$row->ipb_create_account;
				$data['block-noautoblock'] = !( (bool)$row->ipb_enable_autoblock );
				// Poorly named database column
				$data['block-nousertalk'] = !( (bool)$row->ipb_allow_usertalk );
				$data['block-noemail'] = (bool)$row->ipb_block_email;
				$data['block-sitewide'] = (bool)$row->ipb_sitewide;
				$data['block-restrictions'] = (bool)$row->ipb_sitewide ? [] :
					$this->blockRestrictions->loadByBlockId( $row->ipb_id, $db );
				$data['blocked'] = true;
			}
		}

		return $data;
	}
}
