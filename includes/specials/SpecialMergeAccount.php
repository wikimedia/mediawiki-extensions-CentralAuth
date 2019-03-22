<?php

class SpecialMergeAccount extends SpecialPage {
	/** @var string */
	protected $mUserName;
	/** @var bool */
	protected $mAttemptMerge;
	/** @var string */
	protected $mMergeAction;
	/** @var string */
	protected $mPassword;
	/** @var string[] */
	protected $mWikiIDs;
	/** @var string */
	protected $mSessionToken;
	/** @var string */
	protected $mSessionKey;

	public function __construct() {
		parent::__construct( 'MergeAccount', 'centralauth-merge' );
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $subpage ) {
		$this->setHeaders();

		if ( !is_null( $subpage ) && preg_match( "/^[0-9a-zA-Z]{32}$/", $subpage ) ) {
			$user = User::newFromConfirmationCode( $subpage );

			if ( is_object( $user ) ) {
				$user->confirmEmail();
				$user->saveSettings();
				$this->getOutput()->addWikiMsg( 'confirmemail_success' );
			} else {
				$this->getOutput()->addWikiMsg( 'confirmemail_invalid' );
				// return; // Let's be greedy and still show them MergeAccount
			}
		}

		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->getOutput()->addWikiMsg( 'centralauth-merge-denied' );
			$this->getOutput()->addWikiMsg( 'centralauth-readmore-text' );
			return;
		}

		if ( !$this->getUser()->isLoggedIn() ) {
			$loginpage = SpecialPage::getTitleFor( 'Userlogin' );
			$loginurl = $loginpage->getFullUrl(
				[ 'returnto' => $this->getPageTitle()->getPrefixedText() ]
			);
			$this->getOutput()->addWikiMsg( 'centralauth-merge-notlogged', $loginurl );
			$this->getOutput()->addWikiMsg( 'centralauth-readmore-text' );

			return;
		}

		if ( CentralAuthUtils::isReadOnly() ) {
			$this->getOutput()->setPageTitle( $this->msg( 'readonly' ) );
			$this->getOutput()->addWikiMsg( 'readonlytext', CentralAuthUtils::getReadOnlyReason() );
			return;
		}
		$request = $this->getRequest();

		$this->mUserName = $this->getUser()->getName();

		$this->mAttemptMerge = $request->wasPosted();

		$this->mMergeAction = $request->getVal( 'wpMergeAction' );
		$this->mPassword = $request->getVal( 'wpPassword' );
		$this->mWikiIDs = $request->getArray( 'wpWikis' );
		$this->mSessionToken = $request->getVal( 'wpMergeSessionToken' );
		$this->mSessionKey = pack( "H*", $request->getVal( 'wpMergeSessionKey' ) );

		// Possible demo states

		// success, all accounts merged
		// successful login, some accounts merged, others left
		// successful login, others left
		// not account owner, others left

		// is owner / is not owner
		// did / did not merge some accounts
		// do / don't have more accounts to merge

		if ( $this->mAttemptMerge ) {
			// First check the edit token
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				throw new ErrorPageError( 'sessionfailure-title', 'sessionfailure' );
			}
			switch ( $this->mMergeAction ) {
			case "dryrun":
				$this->doDryRunMerge();
				break;
			case "initial":
				$this->doInitialMerge();
				break;
			case "cleanup":
				$this->doCleanupMerge();
				break;
			case "attach":
				$this->doAttachMerge();
				break;
			default:
				throw new InvalidArgumentException(
					'Invalid merge action ' . $this->mMergeAction . ' given'
				);
			}
			return;
		}

		$globalUser = CentralAuthUser::getInstanceByName( $this->mUserName );
		if ( $globalUser->exists() ) {
			$this->showFormForExistingUsers( $globalUser );
		} else {
			$this->showWelcomeForm();
		}
	}

	/**
	 * Pick which form to show for a user that already exists
	 *
	 * @param CentralAuthUser $globalUser
	 */
	private function showFormForExistingUsers( CentralAuthUser $globalUser ) {
		if ( $globalUser->isAttached() ) {
			$this->showCleanupForm();
		} else {
			$this->showAttachForm();
		}
	}

	/**
	 * To pass potentially multiple passwords from one form submission
	 * to another while previewing the merge behavior, we can store them
	 * in the server-side session information.
	 *
	 * We'd rather not have plaintext passwords floating about on disk
	 * or memcached, so the session store is obfuscated with simple XOR
	 * encryption. The key is passed in the form instead of the session
	 * data, so they won't be found floating in the same place.
	 */
	private function initSession() {
		$this->mSessionToken = MWCryptRand::generateHex( 32 );
		$this->mSessionKey = random_bytes( 128 );
	}

	/**
	 * @return array|mixed
	 */
	private function getWorkingPasswords() {
		Wikimedia\suppressWarnings();
		$data = RequestContext::getMain()->getRequest()->getSessionData( 'wsCentralAuthMigration' );
		$passwords = unserialize(
			gzinflate(
				$this->xorString(
					$data[$this->mSessionToken],
					$this->mSessionKey ) ) );
		Wikimedia\restoreWarnings();
		if ( is_array( $passwords ) ) {
			return $passwords;
		}
		return [];
	}

	/**
	 * @param string $password
	 */
	private function addWorkingPassword( $password ) {
		$passwords = $this->getWorkingPasswords();
		if ( !in_array( $password, $passwords ) ) {
			$passwords[] = $password;
		}

		// Lightly obfuscate the passwords while we're storing them,
		// just to make us feel better about them floating around.
		$request = RequestContext::getMain()->getRequest();
		$data = $request->getSessionData( 'wsCentralAuthMigration' );
		$data[$this->mSessionToken] =
			$this->xorString(
				gzdeflate(
					serialize(
						$passwords ) ),
				$this->mSessionKey );
		$request->setSessionData( 'wsCentralAuthMigration', $data );
	}

	private function clearWorkingPasswords() {
		$request = RequestContext::getMain()->getRequest();
		$data = $request->getSessionData( 'wsCentralAuthMigration' );
		unset( $data[$this->mSessionToken] );
		$request->setSessionData( 'wsCentralAuthMigration', $data );
	}

	/**
	 * @param string $text
	 * @param string $key
	 * @return string
	 */
	private function xorString( $text, $key ) {
		if ( $key !== '' ) {
			$textLen = strlen( $text );
			$keyLen = strlen( $key );
			for ( $i = 0; $i < $textLen; $i++ ) {
				$text[$i] = chr( 0xff & ( ord( $text[$i] ) ^ ord( $key[$i % $keyLen] ) ) );
			}
		}
		return $text;
	}

	private function doDryRunMerge() {
		global $wgCentralAuthDryRun;

		$globalUser = CentralAuthUser::getMasterInstance( $this->getUser() );

		if ( $globalUser->exists() ) {
			// Already exists - race condition
			$this->showFormForExistingUsers( $globalUser );
			return;
		}

		if ( $wgCentralAuthDryRun ) {
			$this->getOutput()->addWikiMsg( 'centralauth-notice-dryrun' );
		}

		$password = $this->getRequest()->getVal( 'wpPassword' );
		$this->addWorkingPassword( $password );
		$passwords = $this->getWorkingPasswords();

		$home = false;
		$attached = [];
		$unattached = [];
		$methods = [];
		$status = $globalUser->migrationDryRun(
			$passwords, $home, $attached, $unattached, $methods
		);

		if ( $status->isGood() ) {
			// This is the global account or matched it
			if ( count( $unattached ) == 0 ) {
				// Everything matched -- very convenient!
				$this->getOutput()->addWikiMsg( 'centralauth-merge-dryrun-complete' );
			} else {
				$this->getOutput()->addWikiMsg( 'centralauth-merge-dryrun-incomplete' );
			}

			if ( count( $unattached ) > 0 ) {
				$this->getOutput()->addHTML( $this->step2PasswordForm( $unattached ) );
				$this->getOutput()->addWikiMsg( 'centralauth-merge-dryrun-or' );
			}

			$subAttached = array_diff( $attached, [ $home ] );
			$this->getOutput()->addHTML( $this->step3ActionForm( $home, $subAttached, $methods ) );
		} else {
			// Show error message from status
			$this->getOutput()->addHTML( '<div class="errorbox" style="float:none;">' );
			$this->getOutput()->addWikiTextAsInterface( $status->getWikiText() );
			$this->getOutput()->addHTML( '</div>' );

			// Show wiki list if required
			if ( $status->hasMessage( 'centralauth-merge-home-password' ) ) {
				$out = Html::rawElement( 'h2', null,
					$this->msg( 'centralauth-list-home-title' )->escaped() );
				$out .= $this->msg( 'centralauth-list-home-dryrun' )->parseAsBlock();
				$out .= $this->listAttached( [ $home ], [ $home => 'primary' ] );
				$this->getOutput()->addHTML( $out );
			}

			// Show password box
			$this->getOutput()->addHTML( $this->step1PasswordForm() );
		}
	}

	private function doInitialMerge() {
		global $wgCentralAuthDryRun;

		$globalUser = CentralAuthUser::getMasterInstance( $this->getUser() );

		if ( $wgCentralAuthDryRun ) {
			$this->dryRunError();
			return;
		}

		if ( $globalUser->exists() ) {
			// Already exists - race condition
			$this->showFormForExistingUsers( $globalUser );
			return;
		}

		$passwords = $this->getWorkingPasswords();
		if ( empty( $passwords ) ) {
			throw new Exception( "Submission error -- invalid input" );
		}

		$globalUser->storeAndMigrate( $passwords, /* $sendToRC = */ true,
			/* $safe = */ false, /* $checkHome = */ true );
		$this->clearWorkingPasswords();

		$this->showCleanupForm();
	}

	private function doCleanupMerge() {
		global $wgCentralAuthDryRun;

		$globalUser = CentralAuthUser::getMasterInstance( $this->getUser() );

		if ( !$globalUser->exists() ) {
			throw new Exception( "User doesn't exist -- race condition?" );
		}

		if ( !$globalUser->isAttached() ) {
			throw new Exception( "Can't cleanup merge if not already attached." );
		}

		if ( $wgCentralAuthDryRun ) {
			$this->dryRunError();
			return;
		}
		$password = $this->getRequest()->getText( 'wpPassword' );

		$attached = [];
		$unattached = [];
		$ok = $globalUser->attemptPasswordMigration( $password, $attached, $unattached );
		$this->clearWorkingPasswords();

		if ( !$ok ) {
			if ( empty( $attached ) ) {
				$this->getOutput()->addWikiMsg( 'centralauth-finish-noconfirms' );
			} else {
				$this->getOutput()->addWikiMsg( 'centralauth-finish-incomplete' );
			}
		}
		$this->showCleanupForm();
	}

	private function doAttachMerge() {
		global $wgCentralAuthDryRun;

		$globalUser = CentralAuthUser::getMasterInstance( $this->getUser() );

		if ( !$globalUser->exists() ) {
			throw new Exception( "User doesn't exist -- race condition?" );
		}

		if ( $globalUser->isAttached() ) {
			// Already attached - race condition
			$this->showCleanupForm();
			return;
		}

		if ( $wgCentralAuthDryRun ) {
			$this->dryRunError();
			return;
		}
		$password = $this->getRequest()->getText( 'wpPassword' );
		if ( $globalUser->authenticate( $password ) == 'ok' ) {
			$globalUser->attach( wfWikiID(), 'password' );
			$this->getOutput()->addWikiMsg( 'centralauth-attach-success' );
			$this->showCleanupForm();
		} else {
			$this->getOutput()->addHTML(
				Html::rawElement( 'div', [ "class" => "errorbox" ],
					$this->msg( 'wrongpassword' )->escaped()
				) . $this->attachActionForm() );
		}
	}

	private function showWelcomeForm() {
		global $wgCentralAuthDryRun;

		if ( $wgCentralAuthDryRun ) {
			$this->getOutput()->addWikiMsg( 'centralauth-notice-dryrun' );
		}

		$this->getOutput()->addWikiMsg( 'centralauth-merge-welcome' );
		$this->getOutput()->addWikiMsg( 'centralauth-readmore-text' );

		$this->initSession();
		$this->getOutput()->addHTML(
			$this->passwordForm(
				'dryrun',
				$this->msg( 'centralauth-merge-step1-title' )->text(),
				$this->msg( 'centralauth-merge-step1-detail' )->escaped(),
				$this->msg( 'centralauth-merge-step1-submit' )->text() )
			);
	}

	private function showCleanupForm() {
		$globalUser = CentralAuthUser::getInstance( $this->getUser() );

		$merged = $globalUser->listAttached();
		$remainder = $globalUser->listUnattached();
		$this->showStatus( $merged, $remainder );
	}

	private function showAttachForm() {
		$globalUser = CentralAuthUser::getInstance( $this->getUser() );
		$merged = $globalUser->listAttached();
		$this->getOutput()->addWikiMsg( 'centralauth-attach-list-attached', $this->mUserName );
		$this->getOutput()->addHTML( $this->listAttached( $merged ) );
		$this->getOutput()->addHTML( $this->attachActionForm() );
	}

	/**
	 * @param string[] $merged
	 * @param string[] $remainder
	 */
	private function showStatus( $merged, $remainder ) {
		$remainderCount = count( $remainder );
		if ( $remainderCount > 0 ) {
			$this->getOutput()->setPageTitle( $this->msg( 'centralauth-incomplete' ) );
			$this->getOutput()->addWikiMsg( 'centralauth-incomplete-text' );
		} else {
			$this->getOutput()->setPageTitle( $this->msg( 'centralauth-complete' ) );
			$this->getOutput()->addWikiMsg( 'centralauth-complete-text' );
		}
		$this->getOutput()->addWikiMsg( 'centralauth-readmore-text' );

		if ( $merged ) {
			$this->getOutput()->addHTML( Xml::element( 'hr' ) );
			$this->getOutput()->addWikiMsg(
				'centralauth-list-attached',
				$this->mUserName,
				count( $merged )
			);
			$this->getOutput()->addHTML( $this->listAttached( $merged ) );
		}

		if ( $remainder ) {
			$this->getOutput()->addHTML( Xml::element( 'hr' ) );
			$this->getOutput()->addWikiMsg(
				'centralauth-list-unattached',
				$this->mUserName,
				$remainderCount
			);
			$this->getOutput()->addHTML( $this->listUnattached( $remainder ) );

			// Try the password form!
			$this->getOutput()->addHTML( $this->passwordForm(
				'cleanup',
				$this->msg( 'centralauth-finish-title' )->text(),
				$this->msg( 'centralauth-finish-text' )->parseAsBlock(),
				$this->msg( 'centralauth-finish-login' )->text() ) );
		}
	}

	/**
	 * @param string[] $wikiList
	 * @param string[] $methods
	 * @return string
	 */
	private function listAttached( $wikiList, $methods = [] ) {
		return $this->listWikis( $wikiList, $methods );
	}

	/**
	 * @param string[] $wikiList
	 * @return string
	 */
	private function listUnattached( $wikiList ) {
		return $this->listWikis( $wikiList );
	}

	/**
	 * @param string[] $list
	 * @param string[] $methods
	 * @return string
	 */
	private function listWikis( $list, $methods = [] ) {
		asort( $list );
		return $this->formatList( $list, $methods, [ $this, 'listWikiItem' ] );
	}

	/**
	 * @param string[] $items
	 * @param string[] $methods
	 * @param callable $callback
	 * @return string
	 */
	private function formatList( $items, $methods, $callback ) {
		if ( !$items ) {
			return '';
		}

		$itemMethods = [];
		foreach ( $items as $item ) {
			$itemMethods[] = $methods[$item] ?? '';
		}

		$html = Xml::openElement( 'ul' ) . "\n";
		$list = array_map( $callback, $items, $itemMethods );
		foreach ( $list as $item ) {
			$html .= Html::rawElement( 'li', [], $item ) . "\n";
		}
		$html .= Xml::closeElement( 'ul' ) . "\n";

		return $html;
	}

	/**
	 * @param string $wikiID
	 * @param string $method
	 * @return string
	 */
	private function listWikiItem( $wikiID, $method ) {
		$return = $this->foreignUserLink( $wikiID );
		if ( $method ) {
			// Give grep a chance to find the usages:
			// centralauth-merge-method-primary, centralauth-merge-method-empty,
			// centralauth-merge-method-mail, centralauth-merge-method-password,
			// centralauth-merge-method-admin, centralauth-merge-method-new,
			// centralauth-merge-method-login,
			$return .= $this->msg( 'word-separator' )->escaped();
			$return .= $this->msg( 'parentheses',
				$this->msg( 'centralauth-merge-method-' . $method )->text()
			)->escaped();
		}
		return $return;
	}

	/**
	 * @param string $wikiID
	 * @return string
	 * @throws Exception
	 */
	private function foreignUserLink( $wikiID ) {
		$wiki = WikiMap::getWiki( $wikiID );
		if ( !$wiki ) {
			throw new Exception( "Invalid wiki: $wikiID" );
		}

		$wikiname = $wiki->getDisplayName();
		return SpecialCentralAuth::foreignLink(
			$wiki,
			MWNamespace::getCanonicalName( NS_USER ) . ':' . $this->mUserName,
			$wikiname,
			$this->msg( 'centralauth-foreign-link', $this->mUserName, $wikiname )->text()
		);
	}

	/**
	 * @param string $action Value for wpMergeAction hidden form field
	 * @param string $title Header for form (Plain text. Will be escaped by this method)
	 * @param string $text Raw html contents of form
	 * @return string HTML of form
	 */
	private function actionForm( $action, $title, $text ) {
		return Xml::openElement( 'div', [ 'id' => "userloginForm" ] ) .
			Xml::openElement( 'form',
				[
					'method' => 'post',
					'action' => $this->getPageTitle()->getLocalUrl( 'action=submit' ) ] ) .
			Xml::element( 'h2', [], $title ) .
			Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
			Html::hidden( 'wpMergeAction', $action ) .
			Html::hidden( 'wpMergeSessionToken', $this->mSessionToken ) .
			Html::hidden( 'wpMergeSessionKey', bin2hex( $this->mSessionKey ) ) .

			$text .

			Xml::closeElement( 'form' ) .
			Xml::element( 'br', [ 'clear' => 'all' ] ) .
			Xml::closeElement( 'div' );
	}

	/**
	 * @param string $action wpMergeAction form value
	 * @param string $title Header of form (Not html escaped)
	 * @param string $text Raw html contents of form
	 * @param string $submit Text of submit button (Not html escaped)
	 * @return string HTML of form.
	 */
	private function passwordForm( $action, $title, $text, $submit ) {
		$table = Html::rawElement( 'table', [],
			Html::rawElement( 'tr', [],
				Html::rawElement( 'td', [],
					Xml::label(
						$this->msg( 'centralauth-finish-password' )->text(),
						'wpPassword1'
					)
				) .
				Html::rawElement( 'td', [],
					Xml::input(
						'wpPassword', 20, '',
						[ 'type' => 'password', 'id' => 'wpPassword1' ] )
				)
			) .
			Html::rawElement( 'tr', [],
				Html::rawElement( 'td' ) .
				Html::rawElement( 'td', [],
					Xml::submitButton( $submit, [ 'name' => 'wpLogin' ] )
				)
			)
		);
		return $this->actionForm( $action, $title, $text . $table );
	}

	/**
	 * @return string
	 */
	private function step1PasswordForm() {
		return $this->passwordForm(
			'dryrun',
			$this->msg( 'centralauth-merge-step1-title' )->text(),
			$this->msg( 'centralauth-merge-step1-detail' )->escaped(),
			$this->msg( 'centralauth-merge-step1-submit' )->text() );
	}

	/**
	 * @param string[] $unattached
	 * @return string
	 */
	private function step2PasswordForm( $unattached ) {
		return $this->passwordForm(
			'dryrun',
			$this->msg( 'centralauth-merge-step2-title' )->text(),
			$this->msg( 'centralauth-merge-step2-detail',
				$this->getUser()->getName() )->parseAsBlock() .
				$this->listUnattached( $unattached ),
			$this->msg( 'centralauth-merge-step2-submit' )->text() );
	}

	/**
	 * @param string $home
	 * @param string[] $attached
	 * @param string[] $methods
	 * @return string
	 */
	private function step3ActionForm( $home, $attached, $methods ) {
		$html = $this->msg( 'centralauth-merge-step3-detail',
			$this->getUser()->getName() )->parseAsBlock() .
			Html::rawElement( 'h3', [],
				$this->msg( 'centralauth-list-home-title' )->escaped()
			) . $this->msg( 'centralauth-list-home-dryrun' )->parseAsBlock() .
			$this->listAttached( [ $home ], $methods );

		if ( count( $attached ) ) {
			$html .= Html::rawElement( 'h3', [],
				$this->msg( 'centralauth-list-attached-title' )->escaped()
			) . $this->msg( 'centralauth-list-attached-dryrun',
				$this->getUser()->getName(),
				count( $attached ) )->parseAsBlock();
		}

		$html .= $this->listAttached( $attached, $methods ) .
			Html::rawElement( 'p', [],
				Xml::submitButton( $this->msg( 'centralauth-merge-step3-submit' )->text(),
					[ 'name' => 'wpLogin' ] )
			);

		return $this->actionForm(
			'initial',
			$this->msg( 'centralauth-merge-step3-title' )->text(),
			$html
		);
	}

	/**
	 * @return string
	 */
	private function attachActionForm() {
		return $this->passwordForm(
			'attach',
			$this->msg( 'centralauth-attach-title' )->text(),
			$this->msg( 'centralauth-attach-text' )->escaped(),
			$this->msg( 'centralauth-attach-submit' )->text() );
	}

	private function dryRunError() {
		$this->getOutput()->addWikiMsg( 'centralauth-disabled-dryrun' );
	}

	protected function getGroupName() {
		return 'login';
	}
}
