<?php
/**
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Special;

use IDBAccessObject;
use MailAddress;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use PermissionsError;
use UserMailer;

/**
 * Request an account vanish.
 */
class SpecialGlobalVanishRequest extends FormSpecialPage {

	/** @var \Psr\Log\LoggerInterface */
	private $logger;

	/** @var GlobalRenameDenylist */
	private $globalRenameDenylist;

	/** @var GlobalRenameRequestStore */
	private $globalRenameRequestStore;

	/** @var GlobalRenameFactory */
	private $globalRenameFactory;

	/**
	 * @param GlobalRenameDenylist $globalRenameDenylist
	 */
	public function __construct(
		GlobalRenameDenylist $globalRenameDenylist,
		GlobalRenameRequestStore $globalRenameRequestStore,
		GlobalRenameFactory $globalRenameFactory
	) {
		parent::__construct( 'GlobalVanishRequest' );

		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
		$this->globalRenameDenylist = $globalRenameDenylist;
		$this->globalRenameRequestStore = $globalRenameRequestStore;
		$this->globalRenameFactory = $globalRenameFactory;
	}

	/** @inheritDoc */
	public function onSubmit( array $data ): Status {
		$newUsername = $this->generateUsername();
		if ( !$newUsername ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-save-error' ) );
		}

		// Verify that the user is a global user.
		$causer = $this->getGlobalUser();
		if ( !$causer ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-globaluser-error' ) );
		}

		// Disallow for users that have blocks on any connected wikis.
		if ( $causer->isBlocked() ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-blocked-error' ) );
		}

		// Disallow duplicate rename / vanish requests.
		$username = $this->getUser()->getName();
		if ( $this->globalRenameRequestStore->currentNameHasPendingRequest( $username ) ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-pending-request-error' ) );
		}

		$request = $this->globalRenameRequestStore
			->newBlankRequest()
			->setName( $username )
			->setNewName( $newUsername )
			->setReason( $data['reason'] ?? null )
			->setType( GlobalRenameRequest::VANISH );

		$automaticVanishPerformerName = $this->getConfig()->get( 'CentralAuthAutomaticVanishPerformer' );
		$automaticVanishPerformer = $automaticVanishPerformerName !== null
			? CentralAuthUser::getInstanceByName( $automaticVanishPerformerName )
			: null;

		// Immediately start the vanish if we already know that the user is
		// eligible for approval without a review.
		if (
			isset( $automaticVanishPerformer ) &&
			$automaticVanishPerformer->exists() &&
			$automaticVanishPerformer->isAttached() &&
			$this->eligibleForAutomaticVanish()
		) {
			$renameResult = $this->globalRenameFactory
				->newGlobalRenameUser( $this->getUser(), $causer, $request->getNewName() )
				->withSession( $this->getContext()->exportSession() )
				->rename( $request->toArray() );

			if ( !$renameResult->isGood() ) {
				return $renameResult;
			}

			// We still want to leave a record that this happened, so change
			// the status over to 'approved' for the subsequent save.
			$request
				->setPerformer( $automaticVanishPerformer->getId() )
				->setStatus( GlobalRenameRequest::APPROVED );
		}

		// Save the request to the database for it to be processed later.
		if ( !$this->globalRenameRequestStore->save( $request ) ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-save-error' ) );
		}

		$this->sendVanishingSuccessfulEmail( $request );

		return Status::newGood();
	}

	/** @inheritDoc */
	public function onSuccess(): void {
		$isVanished = $this->globalRenameRequestStore
			->currentNameHasApprovedVanish(
				$this->getUser()->getName(), IDBAccessObject::READ_LATEST );

		$destination = $isVanished ? 'vanished' : 'status';

		$this->getOutput()->redirect(
			$this->getPageTitle( $destination )->getFullURL(), '303'
		);
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$out = $this->getOutput();

		if ( $subPage === 'vanished' ) {
			$out->setPageTitle( $this->msg( 'globalvanishrequest-vanished-title' ) );
			$out->addWikiMsg( 'globalvanishrequest-vanished-text' );
			return;
		}

		$this->requireNamedUser();
		$username = $this->getUser()->getName();
		$hasPending = $this->globalRenameRequestStore->currentNameHasPendingRequest( $username );

		if ( $subPage === 'status' ) {
			if ( !$hasPending ) {
				$out->redirect( $this->getPageTitle()->getFullURL(), '303' );
				return;
			}

			$out->setPageTitle( $this->msg( 'globalvanishrequest-status-title' ) );
			$out->addWikiMsg( 'globalvanishrequest-status-text' );
		} else {
			if ( $hasPending ) {
				$out = $this->getOutput();
				$out->redirect( $this->getPageTitle( 'status' )->getFullURL(), '303' );
				return;
			}

			$out->addModules( 'ext.centralauth.globalvanishrequest' );

			parent::execute( $subPage );
		}
	}

	/** @inheritDoc */
	public function getFormFields(): array {
		return [
			'username' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'default'       => $this->getUser()->getName(),
				'label-message' => 'globalvanishrequest-username-label',
				'required'      => true,
				'type'          => 'text',
				'disabled'      => true,
			],
			'reason' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'id'            => 'mw-vanishrequest-reason',
				'label-message' => 'globalvanishrequest-reason-label',
				'name'          => 'reason',
				'rows'          => 3,
				'type'          => 'textarea',
			],
		];
	}

	/** @inheritDoc */
	public function doesWrites(): bool {
		return true;
	}

	/**
	 * Blocked users should not be able to request a vanish.
	 * @return bool
	 */
	public function requiresUnblock(): bool {
		return true;
	}

	/** @inheritDoc */
	public function userCanExecute( User $user ): bool {
		return $this->globalRenameDenylist->checkUser( $user->getName() );
	}

	/** @inheritDoc */
	public function displayRestrictionError(): void {
		throw new PermissionsError( null, [ 'centralauth-badaccess-blacklisted' ] );
	}

	/** @inheritDoc */
	protected function alterForm( HTMLForm $form ): void {
		$form
			->setSubmitTextMsg( 'globalvanishrequest-submit-text' )
			->setSubmitID( 'mw-vanishrequest-submit' );
	}

	/** @inheritDoc */
	protected function getDisplayFormat(): string {
		return 'ooui';
	}

	/** @inheritDoc */
	protected function preHtml(): string {
		return $this->msg( 'globalvanishrequest-pretext' )->parse();
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'login';
	}

	/**
	 * Return the global user if the authenticated user has a global account.
	 * @return CentralAuthUser|false
	 */
	private function getGlobalUser() {
		$user = $this->getUser();
		$causer = CentralAuthUser::getInstance( $user );

		if ( $causer->exists() && $causer->isAttached() ) {
			return $causer;
		}
		return false;
	}

	/**
	 * Generate a random username that the user requesting a vanish would be
	 * renamed to if the request is accepted.
	 *
	 * @return string|false contains a string if successful
	 */
	private function generateUsername() {
		$attempts = 0;

		do {
			$random = wfRandomString();
			$candidate = "Vanished user {$random}";
			if ( GlobalRenameRequest::isNameAvailable( $candidate, IDBAccessObject::READ_NORMAL )->isOK() ) {
				return $candidate;
			}
			$attempts++;
		} while ( $attempts < 5 );

		return false;
	}

	/**
	 * Checks if the currently authenticated user is eligible for automatic vanishing.
	 * @return bool
	 */
	private function eligibleForAutomaticVanish(): bool {
		$causer = $this->getGlobalUser();
		if ( !$causer ) {
			return false;
		}

		return $causer->getGlobalEditCount() === 0 &&
			!$causer->isBlocked() &&
			!$causer->hasPublicLogs();
	}

	/**
	 * Attempt to send a success email to the user whose vanish was fulfilled.
	 * TODO: https://phabricator.wikimedia.org/T369134 - refactor email sending
	 * @param GlobalRenameRequest $request
	 * @return void
	 */
	private function sendVanishingSuccessfulEmail( GlobalRenameRequest $request ): void {
		$causer = $this->getGlobalUser();
		if ( !$causer ) {
			return;
		}

		$bodyKey = $request->getComments() === ''
			? 'globalrenamequeue-vanish-email-body-approved'
			: 'globalrenamequeue-vanish-email-body-approved-with-note';

		$subject = $this->msg( 'globalrenamequeue-vanish-email-subject-approved' )
			->inContentLanguage()
			->text();
		$body = $this->msg( $bodyKey, [ $request->getName(), $request->getComments() ] )
			->inContentLanguage()
			->text();

		$from = new MailAddress(
			$this->getConfig()->get( 'PasswordSender' ),
			$this->msg( 'emailsender' )->inContentLanguage()->text()
		);
		$to = new MailAddress( $causer->getEmail(), $causer->getName(), '' );

		// Users don't always have email addresses. Since this is acceptable
		// and expected behavior, bail out with a warning if there isn't one.
		if ( !$to->address ) {
			$this->logger->info(
				"Unable to sent approval email to User:{oldName} as there is no email address to send to.",
				[ 'oldName' => $request->getName(), 'component' => 'GlobalVanish' ]
			);
			return;
		}

		$this->logger->info( 'Send approval email to User:{oldName}', [
			'oldName' => $request->getName(),
			'component' => 'GlobalVanish',
		] );

		// Attempt to send the email, and log an error if this fails.
		$emailSendResult = UserMailer::send( $to, $from, $subject, $body );
		if ( !$emailSendResult->isOK() ) {
			$this->logger->error( $emailSendResult->getValue() );
		}
	}
}
