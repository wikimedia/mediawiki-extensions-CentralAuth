<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameJob;

use Exception;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Mail\MailAddress;
use MediaWiki\Mail\UserMailer;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use RuntimeException;

/**
 * This job vanishes global user accounts.
 *
 * This is done with a job so that vanishes can be performed on a wiki of our
 * choosing by setting $wgCentralAuthAutomaticVanishWiki (T380527).
 *
 * @copyright © 2025 Wikimedia Foundation and contributors
 */
class GlobalVanishJob extends Job {

	/**
	 * The type of this job, as registered in wgJobTypeConf.
	 */
	public const TYPE = 'GlobalVanishJob';

	/**
	 * @param array $params an associative array of options:
	 *   globalRenameRequestId - an id that corresponds to a global rename request record
	 *   renamer - the name of the account that will be attributed with the vanish being fulfilled
	 */
	public function __construct( array $params ) {
		parent::__construct( self::TYPE, $params );
	}

	/**
	 * Create a new job specification for globally vanishing a user.
	 *
	 * @param GlobalRenameRequest $request The GlobalRenameRequest to process via a job. Should have the
	 *   vanishing type.
	 * @param string $renamer The username to attribute the vanish to
	 */
	public static function newSpec(
		GlobalRenameRequest $request,
		string $renamer
	): IJobSpecification {
		return new JobSpecification(
			self::TYPE,
			[
				'globalRenameRequestId' => $request->getId(),
				'renamer' => $renamer,
			],
			[],
			null
		);
	}

	/**
	 * @inheritDoc
	 */
	public function run(): bool {
		$services = MediaWikiServices::getInstance();
		$globalRenameRequestStore = CentralAuthServices::getGlobalRenameRequestStore( $services );
		$logger = LoggerFactory::getInstance( 'CentralAuth' );

		// Fetch the rename request that should have already been created by
		// the special page that made the request.
		$request = $globalRenameRequestStore->newFromId( $this->params['globalRenameRequestId'] );
		if ( !$request->getName() || !$request->getNewName() ) {
			$error = 'Vanish request is invalid or doesn\'t exist';
			$logger->warning( 'Failed to global rename ({error})', [
				'component' => 'GlobalVanish',
				'error' => $error,
				'exception' => new RuntimeException( $error ),
			] );
			// Allow retrying in case the lookup is being performed on a
			// replica and replication hasn't occurred yet.
			return false;
		}

		// Only allow account vanishing for now as currently this job only
		// exists to support automatic account vanishing. Usually this is done
		// by Special:GlobalRenameQueue.
		if ( $request->getType() !== GlobalRenameRequest::VANISH ) {
			$error = 'GlobalVanishJob only accepts vanish requests currently';
			$logger->error( 'Failed to global vanish ({error})', [
				'component' => 'GlobalVanish',
				'error' => $error,
				'exception' => new RuntimeException( $error ),
			] );
			return true;
		}

		$causer = CentralAuthUser::getInstanceByName( $request->getName() );
		if ( !$causer->exists() ) {
			$error = "global user with name '{$request->getName()}' cannot be found";
			$logger->error( 'Failed to global vanish ({error})', [
				'component' => 'GlobalVanish',
				'error' => $error,
				'exception' => new RuntimeException( $error ),
			] );
			return true;
		}

		$localAutomaticVanishPerformer = $services->getUserIdentityLookup()
			->getUserIdentityByName( $this->params['renamer'] );
		if ( !$localAutomaticVanishPerformer ) {
			$error = "Renamer with username '{$this->params['renamer']}' cannot be found";
			$logger->error( 'Failed to global vanish ({error})', [
				'component' => 'GlobalVanish',
				'error' => $error,
				'exception' => new RuntimeException( $error ),
			] );
			return true;
		}

		try {
			$this->globalVanishUser( $localAutomaticVanishPerformer, $request, $causer );
		} catch ( Exception $e ) {
			$logger->error( 'Failed to global vanish ({error})', [
				'component' => 'GlobalVanish',
				'error' => $e->getMessage(),
				'exception' => $e,
			] );
			return true;
		}

		try {
			$this->sendVanishingSuccessfulEmail( $request, $causer );
		} catch ( Exception $e ) {
			$logger->error( 'Failed to send vanishing success email ({error})', [
				'component' => 'GlobalVanish',
				'error' => $e->getMessage(),
				'exception' => $e,
			] );
			return true;
		}

		return true;
	}

	/**
	 * Globally vanish a user on the wiki this job is being run on.
	 */
	private function globalVanishUser(
		UserIdentity $renamer,
		GlobalRenameRequest $request,
		CentralAuthUser $causer
	): void {
		$globalRenameFactory = CentralAuthServices::getGlobalRenameFactory();

		// Scrub the "reason". In the context of GlobalRenameUser, reason
		// is the public log entry, not the private reason stated by the
		// user. That value should never be logged.
		$request->setReason(
			wfMessage( 'globalvanishrequest-autoapprove-public-note' )
				->inContentLanguage()
				->text() );

		$requestArray = $request->toArray();

		// We need to add these two fields that are usually being provided by
		// the form. Both are assumed true for automatic vanish requests.
		$requestArray['movepages'] = true;
		$requestArray['suppressredirects'] = true;

		$renameResult = $globalRenameFactory
			->newGlobalRenameUser( $renamer, $causer, $request->getNewName() )
			->withLockPerformingUser( $renamer )
			->rename( $requestArray );

		if ( !$renameResult->isGood() ) {
			throw new RuntimeException(
				implode( ', ', array_map( wfMessage( ... ), $renameResult->getMessages() ) )
			);
		}
	}

	/**
	 * Attempt to send a success email to the user whose vanish was fulfilled.
	 */
	private function sendVanishingSuccessfulEmail( GlobalRenameRequest $request, CentralAuthUser $causer ): void {
		$services = MediaWikiServices::getInstance();
		$logger = LoggerFactory::getInstance( 'CentralAuth' );
		$config = $services->getMainConfig();

		$bodyKey = $request->getComments() === ''
			? 'globalrenamequeue-vanish-email-body-approved'
			: 'globalrenamequeue-vanish-email-body-approved-with-note';

		$subject = wfMessage( 'globalrenamequeue-vanish-email-subject-approved' )
			->inContentLanguage()
			->text();
		$body = wfMessage( $bodyKey, [ $request->getName(), $request->getComments() ] )
			->inContentLanguage()
			->text();

		$from = new MailAddress(
			$config->get( 'PasswordSender' ),
			wfMessage( 'emailsender' )->inContentLanguage()->text()
		);
		$to = new MailAddress( $causer->getEmail(), $causer->getName(), '' );

		// Users don't always have email addresses. Since this is acceptable
		// and expected behavior, bail out with a warning if there isn't one.
		if ( !$to->address ) {
			$logger->info(
				"Unable to sent approval email to User:{oldName} as there is no email address to send to.",
				[ 'oldName' => $request->getName(), 'component' => 'GlobalVanish' ]
			);
			return;
		}

		$logger->info( 'Send approval email to User:{oldName}', [
			'oldName' => $request->getName(),
			'component' => 'GlobalVanish',
		] );

		// Attempt to send the email, and log an error if this fails.
		$emailSendResult = UserMailer::send( $to, $from, $subject, $body );
		if ( !$emailSendResult->isOK() ) {
			throw new RuntimeException( $emailSendResult->getValue() );
		}
	}
}
