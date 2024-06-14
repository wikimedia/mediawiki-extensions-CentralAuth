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

use HTMLForm;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
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

	/**
	 * @param GlobalRenameDenylist $globalRenameDenylist
	 */
	public function __construct( GlobalRenameDenylist $globalRenameDenylist ) {
		parent::__construct( 'GlobalVanishRequest' );
		$this->globalRenameDenylist = $globalRenameDenylist;
	}

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

		parent::execute( $par );
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
		$form->setSubmitTextMsg( 'globalvanishrequest-submit-text' );
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
		return $this->msg( 'globalvanishrequest-pretext' )->parse();
	}

	/**
	 * @return array[]
	 */
	public function getFormFields() {
		$fields = [
			'username' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'default'       => $this->getUser()->getName(),
				'label-message' => 'globalvanishrequest-username-label',
				'required'      => true,
				'type'          => 'text',
				'disabled'      => true,
			],
			'email' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'id'            => 'mw-vanishrequest-email',
				'label-message' => 'globalvanishrequest-email-label',
				'name'          => 'email',
				'placeholder'   => 'username@example.com',
				'required'      => true,
				'type'          => 'email',
			],
			'subject' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'id'            => 'mw-vanishrequest-subject',
				'label-message' => 'globalvanishrequest-subject-label',
				'name'          => 'subject',
				'type'          => 'text',
			],
			'message' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'id'            => 'mw-vanishrequest-message',
				'label-message' => 'globalvanishrequest-message-label',
				'name'          => 'message',
				'rows'          => 3,
				'type'          => 'textarea',
			],
			'copy' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'id'            => 'mw-vanishrequest-copy',
				'label-message' => 'globalvanishrequest-copy-label',
				'name'          => 'copy',
				'type'          => 'check',
			],
		];

		return $fields;
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		// Logic for the submit will be done in a separate ticket
		return Status::newGood();
	}

	public function onSuccess() {
		// Logic for the success will be done in a separate ticket
	}

	/**
	 * Blocked users should not be able to request vanish
	 * @return bool
	 */
	public function requiresUnblock() {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'login';
	}
}
