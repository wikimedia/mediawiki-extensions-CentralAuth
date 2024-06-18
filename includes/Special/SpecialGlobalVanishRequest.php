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
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use PermissionsError;

/**
 * Request an account vanish.
 */
class SpecialGlobalVanishRequest extends FormSpecialPage {

	/** @var GlobalRenameDenylist */
	private $globalRenameDenylist;

	/** @var GlobalRenameRequestStore */
	private $globalRenameRequestStore;

	/**
	 * @param GlobalRenameDenylist $globalRenameDenylist
	 */
	public function __construct(
		GlobalRenameDenylist $globalRenameDenylist,
		GlobalRenameRequestStore $globalRenameRequestStore
	) {
		parent::__construct( 'GlobalVanishRequest' );
		$this->globalRenameDenylist = $globalRenameDenylist;
		$this->globalRenameRequestStore = $globalRenameRequestStore;
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

		// Save the vanish request to the database.
		if ( !$this->globalRenameRequestStore->save( $request ) ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-save-error' ) );
		}

		return Status::newGood();
	}

	public function onSuccess(): void {
		$this->getOutput()->redirect(
			$this->getPageTitle( 'status' )->getFullURL(), '303'
		);
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->requireNamedUser();

		$username = $this->getUser()->getName();
		$hasPending = $this->globalRenameRequestStore->currentNameHasPendingRequest( $username );

		if ( $subPage === 'status' ) {
			$out = $this->getOutput();
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
		$form->setSubmitTextMsg( 'globalvanishrequest-submit-text' );
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
}
