<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Special;

use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Request an account rename.
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2014 Bryan Davis and Wikimedia Foundation.
 */
class SpecialGlobalRenameRequest extends FormSpecialPage {

	private GlobalRenameDenylist $globalRenameDenylist;
	private UserNameUtils $userNameUtils;
	private GlobalRenameRequestStore $globalRenameRequestStore;

	public function __construct(
		GlobalRenameDenylist $globalRenameDenylist,
		UserNameUtils $userNameUtils,
		GlobalRenameRequestStore $globalRenameRequestStore
	) {
		parent::__construct( 'GlobalRenameRequest' );
		$this->globalRenameDenylist = $globalRenameDenylist;
		$this->userNameUtils = $userNameUtils;
		$this->globalRenameRequestStore = $globalRenameRequestStore;
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	public function userCanExecute( User $user ) {
		return $this->globalRenameDenylist->checkUser( $user->getName() );
	}

	public function displayRestrictionError() {
		throw new PermissionsError( null, [ 'centralauth-badaccess-blacklisted' ] );
	}

	/**
	 * @param string|null $par Subpage string if one was specified
	 */
	public function execute( $par ) {
		$this->requireNamedUser();
		$this->addHelpLink( 'Help:Extension:CentralAuth/Global_rename' );

		switch ( $par ) {
			case 'status':
				// Render status page
				$user = $this->getUser();
				$username = $user->getName();
				$wiki = $this->isGlobalUser() ? null : WikiMap::getCurrentWikiId();
				$pending = $this->globalRenameRequestStore->newForUser(
					$username, $wiki
				);
				if ( !$pending->exists() ) {
					$this->getOutput()->redirect( $this->getPageTitle()->getFullURL(), 303 );
					return;
				}
				$out = $this->getOutput();
				$out->setPageTitleMsg(
					$this->msg( 'globalrenamerequest-status-title' )
				);
				$out->addWikiMsg( 'globalrenamerequest-status-text',
					$username, $pending->getNewName()
				);
				break;

			case 'available':
				// TODO: ajax name availability check (T72623)
				break;

			default:
				// Request form
				$out = $this->getOutput();
				$user = $this->getUser();
				$wiki = $this->isGlobalUser() ? null : WikiMap::getCurrentWikiId();

				$pending = $this->globalRenameRequestStore->newForUser(
					$user->getName(), $wiki
				);
				if ( $pending->exists() ) {
					$out->redirect(
						$this->getPageTitle( 'status' )->getFullURL(), '303'
					);
					return;
				}
				parent::execute( $par );
				break;
		}
	}

	/**
	 * Is the current user a global user?
	 * @return bool
	 */
	protected function isGlobalUser() {
		$user = $this->getUser();
		$causer = CentralAuthUser::getInstance( $user );
		return $causer->exists() && $causer->isAttached();
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'globalrenamerequest-submit-text' );
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @return string
	 */
	protected function preHtml() {
		$msg = $this->msg( 'globalrenamerequest-pretext' )->parse();
		if ( !$this->isGlobalUser() ) {
			$msg = $this->msg( 'globalrenamerequest-forced' )->parse() . $msg;
		}
		return $msg;
	}

	/**
	 * @return array[]
	 */
	public function getFormFields() {
		$suggestedUsername = $this->suggestedUsername();
		if ( $suggestedUsername !== false ) {
			$suggestedHelp = [
				'globalrenamerequest-newname-help',
				$suggestedUsername,
			];
		} else {
			// Help message if we couldn't generate a suggested username
			$suggestedHelp = 'globalrenamerequest-newname-help-basic';
		}
		$fields = [
			'username' => [
				'cssclass'      => 'mw-globalrenamerequest-field',
				'csshelpclass'  => 'mw-globalrenamerequest-help',
				'default'       => $this->getUser()->getName(),
				'label-message' => 'globalrenamerequest-username-label',
				'type'          => 'info',
			],
			'newname' => [
				'cssclass'     => 'mw-globalrenamerequest-field',
				'csshelpclass' => 'mw-globalrenamerequest-help',
				'default'      => $this->getRequest()->getVal( 'newname', $this->par ),
				'id'            => 'mw-renamerequest-newname',
				'label-message' => 'globalrenamerequest-newname-label',
				'name'          => 'newname',
				'required'      => true,
				'type'          => 'text',
				'help-message'  => $suggestedHelp,
				'validation-callback' => [ $this, 'validateNewname' ],
			],
		];

		$currentEmail = $this->getUser()->getEmail();
		if ( $currentEmail === '' ) {
			$fields['email'] = [
				'cssclass'      => 'mw-globalrenamerequest-field',
				'csshelpclass'  => 'mw-globalrenamerequest-help',
				'default'       => $this->getRequest()->getVal( 'email', $this->par ),
				'id'            => 'mw-renamerequest-email',
				'label-message' => 'globalrenamerequest-email-label',
				'name'          => 'email',
				'placeholder'   => 'username@example.com',
				'required'      => true,
				'type'          => 'email',
				'help-message'  => 'globalrenamerequest-email-why-explain',
			];
			$fields['email2'] = [
				'cssclass'      => 'mw-globalrenamerequest-field',
				'csshelpclass'  => 'mw-globalrenamerequest-help',
				'default'       => $this->getRequest()->getVal( 'email2', $this->par ),
				'id'            => 'mw-renamerequest-email2',
				'label-message' => 'globalrenamerequest-email2-label',
				'name'          => 'email2',
				'placeholder'   => 'username@example.com',
				'required'      => true,
				'type'          => 'email',
				'help-message'  => 'globalrenamerequest-email2-help',
				'validation-callback' => [ $this, 'validateEmail' ],
			];
		}

		if ( $this->isGlobalUser() ) {
			$fields['reason'] = [
				'cssclass'      => 'mw-globalrenamerequest-field',
				'default'       => $this->getRequest()->getVal( 'reason', $this->par ),
				'id'            => 'mw-renamerequest-reason',
				'label-message' => 'globalrenamerequest-reason-label',
				'name'          => 'reason',
				'required'      => true,
				'rows'          => 5,
				'type'          => 'textarea',
			];
		}
		return $fields;
	}

	/**
	 * Generate a username that appears to be globally available that an
	 * unimaginative user could use if they like.
	 *
	 * @return string|bool false if can't generate a username
	 */
	protected function suggestedUsername() {
		// Only allow 5 tries (T260865)
		$counter = 0;
		// Whether we found a username that is available to use
		$found = false;
		$rand = '';
		while ( !$found && $counter < 5 ) {
			$rand = $this->getUser()->getName() . rand( 123, 999 );
			$found = GlobalRenameRequest::isNameAvailable( $rand, IDBAccessObject::READ_NORMAL )->isOK();
			$counter++;
		}
		if ( $found ) {
			return $rand;
		} else {
			return false;
		}
	}

	/**
	 * @param string|array $value The value the field was submitted with
	 * @param array $alldata The data collected from the form
	 * @param HTMLForm $form
	 * @return bool|Message True on success, or String error to display, or
	 *   false to fail validation without displaying an error.
	 */
	public function validateNewname( $value, $alldata, HTMLForm $form ) {
		if ( $value === null ) {
			// Not submitted yet
			return true;
		}
		$check = GlobalRenameRequest::isNameAvailable( $value );
		return $check->isGood() ? true : $check->getMessage();
	}

	/**
	 * @param string|array $value The value the field was submitted with
	 * @param array $alldata The data collected from the form
	 * @param HTMLForm $form
	 * @return bool|Message True on success, or String error to display, or
	 *   false to fail validation without displaying an error.
	 */
	public function validateEmail( $value, $alldata, HTMLForm $form ) {
		if ( $alldata['email'] !== $alldata['email2'] ) {
			return $this->msg( 'globalrenamerequest-email-mismatch' );
		} elseif ( !Sanitizer::validateEmail( $alldata['email'] ) ) {
			return $this->msg( 'globalrenamerequest-email-invalid' );
		}
		return true;
	}

	/**
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$wiki = $this->isGlobalUser() ? null : WikiMap::getCurrentWikiId();
		$reason = $data['reason'] ?? null;
		$safeName = $this->userNameUtils->getCanonical( $data['newname'], UserNameUtils::RIGOR_CREATABLE );

		$request = $this->globalRenameRequestStore->newBlankRequest();
		$request->setName( $this->getUser()->getName() );
		$request->setWiki( $wiki );
		$request->setNewName( $safeName );
		$request->setReason( $reason );

		if ( $this->globalRenameRequestStore->save( $request ) ) {
			$status = Status::newGood();

			if ( isset( $data['email'] ) ) {
				$user = $this->getUser();
				$user->setEmail( $data['email'] );
				$user->saveSettings();
				$status = $user->sendConfirmationMail( 'set' );
			}
		} else {
			$status = Status::newFatal(
				$this->msg( 'globalrenamerequest-save-error' )
			);
		}
		return $status;
	}

	public function onSuccess() {
		$this->getOutput()->redirect(
			$this->getPageTitle( 'status' )->getFullURL(), '303'
		);
	}

	/**
	 * Even blocked users should be able to ask for a rename.
	 * @return bool
	 */
	public function requiresUnblock() {
		return false;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'login';
	}
}
