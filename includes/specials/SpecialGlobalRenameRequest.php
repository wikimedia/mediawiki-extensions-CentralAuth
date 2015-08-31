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

/**
 * Request an account rename.
 *
 * @author Bryan Davis <bd808@wikimedia.org>
 * @copyright © 2014 Bryan Davis and Wikimedia Foundation.
 */
class SpecialGlobalRenameRequest extends FormSpecialPage {

	public function __construct() {
		parent::__construct( 'GlobalRenameRequest' );
	}

	/**
	 * @param string $par Subpage string if one was specified
	 */
	public function execute( $par ) {
		$this->requireLogin();

		switch ( $par ) {
			case 'status':
				// Render status page
				$user = $this->getUser();
				$username = $user->getName();
				$wiki = $this->isGlobalUser() ? null : wfWikiID();
				$pending = GlobalRenameRequest::newForUser(
					$username, $wiki
				);
				if ( !$pending->exists() ) {
					$this->getOutput()->redirect(
						SpecialPage::getTitleFor( 'GlobalRenameRequest' )->getFullURL(),
						'303'
					);
					return;
				}
				$out = $this->getOutput();
				$out->setPageTitle(
					$this->msg( 'globalrenamerequest-status-title' )
				);
				$out->addWikiMsg( 'globalrenamerequest-status-text',
					$username, $pending->getNewName()
				);
				break;

			case 'available':
				// TODO: ajax name availability check (bug 70623)
				break;

			default:
				// Request form
				$out = $this->getOutput();
				$user = $this->getUser();
				$wiki = $this->isGlobalUser() ? null : wfWikiID();

				$pending = GlobalRenameRequest::newForUser(
					$user->getName(), $wiki
				);
				if ( $pending->exists() ) {
					$out->redirect(
						$this->getPageTitle( 'status' )->getFullURL(), '303'
					);
					return;
				}

				$out->addModuleStyles( array(
					'mediawiki.ui',
					'mediawiki.ui.button',
					'mediawiki.ui.input',
					'ext.centralauth.globalrenamerequest.styles',
				) );
				$out->addModules( 'ext.centralauth.globalrenamerequest' );
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

	/**
	* @param HTMLForm $form
	*/
	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'globalrenamerequest-submit-text' );
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'vform';
	}

	/**
	 * @return string
	 */
	protected function preText() {
		$msg = $this->msg( 'globalrenamerequest-pretext' )->parse();
		if ( !$this->isGlobalUser() ) {
			$msg = $this->msg( 'globalrenamerequest-forced' )->parse() . $msg;
		}
		return $msg;
	}

	/**
	 * @return array
	 */
	public function getFormFields() {
		$fields = array(
			'username' => array(
				'cssclass'      => 'mw-globalrenamerequest-field',
				'csshelpclass'  => 'mw-globalrenamerequest-help',
				'default'       => $this->getUser()->getName(),
				'help-message'  => 'globalrenamerequest-username-help',
				'label-message' => 'globalrenamerequest-username-label',
				'type'          => 'info',
			),
			'newname' => array(
				'cssclass'     => 'mw-globalrenamerequest-field',
				'csshelpclass' => 'mw-globalrenamerequest-help',
				'default'      => $this->getRequest()->getVal( 'newname', $this->par ),
				'help-message' => array(
					'globalrenamerequest-newname-help',
					$this->suggestedUsername(),
				),
				'id'            => 'mw-renamerequest-newname',
				'label-message' => 'globalrenamerequest-newname-label',
				'name'          => 'newname',
				'required'      => true,
				'type'          => 'text',
				'validation-callback' => array( $this, 'validateNewname' ),
			),
		);

		$currentEmail = $this->getUser()->getEmail();
		if ( empty( $currentEmail ) ) {
			$fields['email'] = array(
				'cssclass'      => 'mw-globalrenamerequest-field',
				'csshelpclass'  => 'mw-globalrenamerequest-help',
				'default'       => $this->getRequest()->getVal( 'email', $this->par ),
				'help-message'  => 'globalrenamerequest-email-help',
				'id'            => 'mw-renamerequest-email',
				'label-message' => 'globalrenamerequest-email-label',
				'name'          => 'email',
				'placeholder'   => 'username@example.com',
				'required'      => true,
				'type'          => 'email',
			);
			$fields['email2'] = array(
				'cssclass'      => 'mw-globalrenamerequest-field',
				'csshelpclass'  => 'mw-globalrenamerequest-help',
				'default'       => $this->getRequest()->getVal( 'email2', $this->par ),
				'help-message'  => 'globalrenamerequest-email2-help',
				'id'            => 'mw-renamerequest-email2',
				'label-message' => 'globalrenamerequest-email2-label',
				'name'          => 'email2',
				'placeholder'   => 'username@example.com',
				'required'      => true,
				'type'          => 'email',
				'validation-callback' => array( $this, 'validateEmail' ),
			);
		}

		if ( $this->isGlobalUser() ) {
			$fields['reason'] = array(
				'cssclass'      => 'mw-globalrenamerequest-field',
				'default'       => $this->getRequest()->getVal( 'reason', $this->par ),
				'id'            => 'mw-renamerequest-reason',
				'label-message' => 'globalrenamerequest-reason-label',
				'name'          => 'reason',
				'required'      => true,
				'rows'          => 5,
				'type'          => 'textarea',
			);
		}
		return $fields;
	}

	/**
	 * Generate a username that appears to be globally available that an
	 * unimaginative user could use if they like.
	 *
	 * @return string
	 */
	protected function suggestedUsername() {
		do {
			$rand = $this->getUser()->getName() . rand( 123, 999 );
		} while ( !GlobalRenameRequest::isNameAvailable( $rand )->isOk() );
		return $rand;
	}

	/**
	 * @param string|array $value The value the field was submitted with
	 * @param array $alldata The data collected from the form
	 * @param HTMLForm $form
	 * @return bool|string True on success, or String error to display, or
	 *   false to fail validation without displaying an error.
	 */
	public function validateNewname ( $value, $alldata, HTMLForm $form ) {
		if ( $value === null ) {
			// Not submitted yet
			return true;
		}
		$check = GlobalRenameRequest::isNameAvailable( $value );
		return $check->isGood() ? true : (string) $check->getMessage();
	}

	/**
	 * @param string|array $value The value the field was submitted with
	 * @param array $alldata The data collected from the form
	 * @param HTMLForm $form
	 * @return bool|string True on success, or String error to display, or
	 *   false to fail validation without displaying an error.
	 */
	public function validateEmail ( $value, $alldata, HTMLForm $form ) {
		if ( $alldata['email'] !== $alldata['email2'] ) {
			return $this->msg( 'globalrenamerequest-email-mismatch' )->text();
		} elseif ( !Sanitizer::validateEmail( $alldata['email'] ) ) {
			return $this->msg( 'globalrenamerequest-email-invalid' )->text();
		}
		return true;
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$wiki = $this->isGlobalUser() ? null : wfWikiID();
		$reason = isset( $data['reason'] ) ? $data['reason'] : null;
		$safeName = User::getCanonicalName( $data['newname'], 'creatable' );

		$request = new GlobalRenameRequest;
		$request->setName( $this->getUser()->getName() );
		$request->setWiki( $wiki );
		$request->setNewName( $safeName );
		$request->setReason( $reason );

		if ( $request->save() ) {
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
	 */
	public function requiresUnblock() {
		return false;
	}

	protected function getGroupName() {
		return 'login';
	}
}
