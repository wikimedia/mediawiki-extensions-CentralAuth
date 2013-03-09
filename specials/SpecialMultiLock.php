<?php
/**
 * Special page to allow locking and hiding multiple users
 * at one time. Lots of code derived from Special:CentralAuth.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialMultiLock extends SpecialPage {
	private $mCanOversight;
	private $mGlobalUsers, $mUserNames, $mPrefixSearch, $mPosted, $mMethod;
	private $mActionLock, $mActionHide, $mReason;

	function __construct() {
		parent::__construct( 'MultiLock', 'centralauth-lock' );
	}

	function execute( $subpage ) {
		$this->setHeaders();
		$this->checkPermissions();

		$this->mCanOversight = $this->getUser()->isAllowed( 'centralauth-oversight' );
		$this->getOutput()->addModules( 'ext.centralauth' );
		$this->getOutput()->addModuleStyles( 'ext.centralauth.noflash' );
		$this->mMethod = $this->getRequest()->getVal( 'wpMethod', '' );
		$this->mActionLock = $this->getRequest()->getVal( 'wpActionLock', 'nochange' );
		$this->mActionHide = $this->getRequest()->getVal( 'wpActionHide', 'nochange' );
		$this->mUserNames = $this->getRequest()->getVal( 'wpTarget', '' );
		$this->mPrefixSearch = $this->getRequest()->getVal( 'wpSearchTarget', '' );
		$this->mActionUserNames = $this->getRequest()->getArray( 'wpActionTarget' );
		$this->mPosted = $this->getRequest()->wasPosted();

		$this->mReason = $this->getRequest()->getText( 'wpReasonList' );
		$reasonDetail = $this->getRequest()->getText( 'wpReason' );

		if ( $this->mReason == 'other' ) {
			$this->mReason = $reasonDetail;
		} elseif ( $reasonDetail ) {
			$this->mReason .= $this->msg( 'colon-separator' )->inContentLanguage()->text() . $reasonDetail;
		}

		if ( $this->mUserNames !== '' ) {
			$this->mUserNames = explode( "\n", $this->mUserNames );
		} else {
			$this->mUserNames = array();
		}

		if ( $this->mPrefixSearch !== '' ) {
			$this->mPrefixSearch = $this->getLang()->ucfirst( trim( $this->mPrefixSearch ) );
		}

		if ( $this->mMethod === '' ) {
			$this->getOutput()->addWikiMsg( 'centralauth-admin-multi-intro' );
			$this->showUsernameForm();
			return;
		} elseif ( $this->mPosted && $this->mMethod == 'search' && count( $this->mUserNames ) > 0 ) {
			$this->showUserTable();
		} elseif ( $this->mPosted && $this->mMethod == 'search' && $this->mPrefixSearch !== '' ) {
			$this->searchForUsers();
			$this->showUserTable();
		} elseif ( $this->mPosted && $this->mMethod == 'set-status' && is_array( $this->mActionUserNames ) ) {
			$this->mGlobalUsers = array_unique( array_map( "self::getGlobalUser", $this->mActionUserNames ), SORT_REGULAR );
			$this->setStatus();
			$this->showUserTable();
		} else {
			$this->showError( 'centralauth-admin-multi-username' );
		}

		$this->showUsernameForm();

		$this->showLogExtract();
	}

	/**
	 * Get the CentralAuthUser from a line of text
	 *
	 * @return CentralAuthUser|string User object, or a string containing the error
	 */
	private function getGlobalUser( $username ) {
		$username = trim( $username );
		if ( $username === '' ) {
			return false;
		}
		$username = $this->getLang()->ucfirst( $username );

		$globalUser = new CentralAuthUser( $username );
		if ( !$globalUser->exists()
			|| ( !$this->mCanOversight && ( $globalUser->isOversighted() || $globalUser->isHidden() ) )
		) {
			return $this->msg( 'centralauth-admin-nonexistent', $username )->parse();
		}
		return $globalUser;
	}

	/**
	 * Search the CentralAuth db for all usernames prefixed with mPrefixSearch
	 */
	private function searchForUsers() {

		$dbr = CentralAuthUser::getCentralSlaveDB();

		$where = array( 'gu_name' . $dbr->buildLike( $this->mPrefixSearch, $dbr->anyString() ) );
		if ( !$this->mCanOversight ) {
			$where['gu_hidden'] = CentralAuthUser::HIDDEN_NONE;
		}

		$result = $dbr->select(
			array( 'globaluser' ),
			array( 'gu_name' ),
			$where,
			__METHOD__,
			array( 'LIMIT' => 100 )
		);

		foreach ( $result as $row ) {
			$this->mUserNames[] = $row->gu_name;
		}
	}

	/**
	 * Show the Lock and/or Hide form, appropriate for this admin user's rights.
	 * The <form> and <fieldset> were started in showTableHeader()
	 */
	private function showStatusForm() {

		$form = '';
		$radioLocked =
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-lock-nochange' )->parse(),
				'wpActionLock',
				'nochange',
				'mw-centralauth-status-locked-no',
				true ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-lock-unlock' )->parse(),
				'wpActionLock',
				'unlock',
				'centralauth-admin-action-lock-unlock',
				false ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-lock-lock' )->parse(),
				'wpActionLock',
				'lock',
				'centralauth-admin-action-lock-lock',
				false );
		$radioHidden =
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-hide-nochange' )->parse(),
				'wpActionHide',
				'nochange',
				'mw-centralauth-status-hidden-nochange',
				true ) .
			'<br />';
		if ( $this->mCanOversight ) {
			$radioHidden .= Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-hide-none' )->parse(),
				'wpActionHide',
				CentralAuthUser::HIDDEN_NONE,
				'mw-centralauth-status-hidden-no',
				false ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-hide-lists' )->parse(),
				'wpActionHide',
				CentralAuthUser::HIDDEN_LISTS,
				'mw-centralauth-status-hidden-list',
				false ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-hide-oversight' )->parse(),
				'wpActionHide',
				CentralAuthUser::HIDDEN_OVERSIGHT,
				'mw-centralauth-status-hidden-oversight',
				false
			);
		}

		$reasonList = Xml::listDropDown(
			'wpReasonList',
			$this->msg( 'centralauth-admin-status-reasons' )->inContentLanguage()->text(),
			$this->msg( 'ipbreasonotherlist' )->inContentLanguage()->text()
		);
		$reasonField = Xml::input( 'wpReason', 45, false );

		$form .= Xml::buildForm(
			array(
				'centralauth-admin-status-locked' => $radioLocked,
				'centralauth-admin-status-hidden' => $radioHidden,
				'centralauth-admin-reason' => $reasonList,
				'centralauth-admin-reason-other' => $reasonField
			),
			'centralauth-admin-status-submit'
		);

		$searchlist = $this->mUserNames;
		if ( is_array( $this->mUserNames ) ) {
			$searchlist = implode( "\n", $this->mUserNames );
		}
		$form .= Html::hidden( 'wpTarget', $searchlist );

		$form .= '</fieldset></form>';

		$this->getOutput()->addHTML( $form );
	}

	/**
	 * Start admin <form>, and start the table listing usernames to take action on
	 */
	private function showTableHeader() {

		$out = $this->getOutput();

		$header = Xml::openElement(
			'form',
			array(
				'method' => 'POST',
				'action' => $this->getTitle()->getFullUrl()
			)
		);

		$header .= Xml::fieldset( $this->msg( 'centralauth-admin-status' )->text() );
		$header .= Html::hidden( 'wpMethod', 'set-status' );
		$header .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		$header .= $this->msg( 'centralauth-admin-status-intro' )->parseAsBlock();

		$header .= Xml::openElement(
			'table',
			array( 'class' => 'wikitable sortable mw-centralauth-wikislist' )
		);

		$header .= '<thead><tr>' .
				'<th></th>' .
				'<th>' .
				$out->getContext()->msg( 'centralauth-admin-username' )->escaped() .
				'</th>' .
				'<th>' .
				$out->getContext()->msg( 'centralauth-admin-info-home')->escaped() .
				'</th>' .
				'<th>' .
				$out->getContext()->msg( 'centralauth-admin-info-registered' )->escaped() .
				'</th>' .
				'<th>' .
				$out->getContext()->msg( 'centralauth-admin-info-locked' )->escaped() .
				'</th>' .
				'<th>' .
				$out->getContext()->msg( 'centralauth-admin-info-hidden' )->escaped() .
				'</th>' .
			'</tr></thead>' .
			'<tbody>';

		$out->addHTML( $header );
	}

	/**
	 * Build the table of users to lock and/or hide
	 */
	private function showUserTable() {

		$this->mGlobalUsers = array_unique( array_map( "self::getGlobalUser", $this->mUserNames ), SORT_REGULAR );

		$out = $this->getOutput();

		if ( count( $this->mGlobalUsers ) < 1 ) {
			$this->showError( 'centralauth-admin-multi-notfound' );
			return;
		}

		$sca = new SpecialCentralAuth;

		$this->showTableHeader();

		foreach ( $this->mGlobalUsers as $globalUser ) {
			$guName = '';
			$guLocked = false;
			$guHidden = '';

			$rowtext = Xml::openElement( 'tr' );

			if ( $globalUser === false ) {
				continue;
			} elseif ( $globalUser instanceof CentralAuthUser ) {
				$guName = $globalUser->getName();
				$guHidden = $sca->formatHiddenLevel( $globalUser->getHiddenLevel() );
				$guWiki = $globalUser->getHomeWiki();
				$guRegister = $sca->prettyTimespan(
					wfTimestamp( TS_UNIX ) - wfTimestamp( TS_UNIX, $globalUser->getRegistration() )
				);
				$guLocked = $this->msg('centralauth-admin-status-locked-no')->escaped();
				if ( $globalUser->isLocked() ) {
					$guLocked = $this->msg('centralauth-admin-status-locked-yes')->escaped();
				}
				$rowtext .= Html::rawElement( 'td', array(),
					Html::input(
						'wpActionTarget['.$guName.']',
						$guName,
						'checkbox',
						array('checked' => 'checked')
					)
				);
				$rowtext .= Html::element( 'td', array(), $guName );
				$rowtext .= Html::element( 'td', array(), $guWiki );
				$rowtext .= Html::element( 'td', array(), $guRegister );
				$rowtext .= Html::element( 'td', array(), $guLocked );
				$rowtext .= Html::element( 'td', array(), $guHidden );
			} else {
				$rowtext .= Html::element(
					'td',
					array( 'colspan' => 6 ),
					$globalUser
				);
			}

			$rowtext .= Xml::closeElement( 'tr' );
			$out->addHTML( $rowtext );
		}

		$out->addHTML( '</tbody></table>' );
		$this->showStatusForm();
	}

	/**
	 * Lock and/or hide global users and log the activity (if any)
	 */
	private function setStatus() {

		if ( !$this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) ) ) {
			$this->showError( 'centralauth-token-mismatch' );
			return;
		}

		if ( $this->mActionHide != 'nochange' && !$this->mCanOversight ) {
			$this->showError( 'centralauth-admin-not-authorized' );
			return;
		}

		$sca = new SpecialCentralAuth;
		$added = array();
		$removed = array();
		$setLocked = null;
		$setHidden = null;

		if ( $this->mActionLock != 'nochange' ) {
			$setLocked = ( $this->mActionLock == 'lock' );
		}

		if ( $this->mActionHide != 'nochange' ) {
			$setHidden = $this->mActionHide;
		}

		foreach ( $this->mGlobalUsers as $globalUser ) {

			if ( !$globalUser instanceof CentralAuthUser ) {
				// Somehow the user submitted a bad user name
				$this->showStatusError( $globalUser );
				continue;
			}

			$status = $globalUser->adminLockHide(
				$setLocked,
				$setHidden,
				$this->mReason,
				$this->getContext()
			);

			if ( !$status->isGood() ) {
				$this->showStatusError( $status->getWikiText() );
			} elseif ( $status->successCount > 0 ) {
				$sca->logAction(
					'setstatus',
					$globalUser->getName(),
					$this->mReason,
					$status->success,
					$setHidden != CentralAuthUser::HIDDEN_NONE
				);
				$this->showSuccess( 'centralauth-admin-setstatus-success', $globalUser->getName() );
			}
		}
	}

	/**
	 * @param $wikitext string
	 */
	function showStatusError( $wikitext ) {
		$wrap = Xml::tags( 'div', array( 'class' => 'error' ), $wikitext );
		$this->getOutput()->addHTML( $this->getOutput()->parse( $wrap, /*linestart*/true, /*uilang*/true ) );
	}

	function showError( /* varargs */ ) {
		$args = func_get_args();
		$this->getOutput()->wrapWikiMsg( '<div class="error">$1</div>', $args );
	}

	function showSuccess( /* varargs */ ) {
		$args = func_get_args();
		$this->getOutput()->wrapWikiMsg( '<div class="success">$1</div>', $args );
	}

	function showUsernameForm() {

		if ( is_array( $this->mUserNames ) ) {
			$this->mUserNames = implode( "\n", $this->mUserNames );
		}

		$form = Xml::tags( 'form',
			array(
				'method' => 'post',
				'action' => $this->getTitle()->getLocalUrl()
			),
			Xml::tags( 'fieldset', array(),
				Xml::element( 'legend', array(), $this->msg( 'centralauth-admin-manage' )->text() ) .
				Html::hidden( 'wpMethod', 'search' ) .
				Xml::element( 'p', array(),
					$this->msg( 'centralauth-admin-multi-username' )->text()
				) .
				Xml::textarea( 'wpTarget', ( $this->mPrefixSearch ? '' : $this->mUserNames ), 25, 20 ) .
				Xml::element( 'p', array(),
					$this->msg( 'centralauth-admin-multi-searchprefix' )->text()
				) .
				Html::input( 'wpSearchTarget', $this->mPrefixSearch ) .
				Xml::tags( 'p', array(),
					Xml::submitButton( $this->msg( 'centralauth-admin-lookup-ro' )->text() )
				)
			)
		);
		$this->getOutput()->addHTML( $form );
	}

	/**
	 * Show the last 50 log entries
	 */
	function showLogExtract() {
		$text = '';
		$numRows = LogEventsList::showLogExtract(
			$text,
			array( 'globalauth', 'suppress' ),
			'',
			'',
			array( 'showIfEmpty' => true ) );
		if ( $numRows ) {
			$this->getOutput()->addHTML(
				Xml::fieldset( $this->msg( 'centralauth-admin-logsnippet' )->text(), $text )
			);
		}
	}
}
