<?php
/**
 * Special page to allow locking and hiding multiple users
 * at one time. Lots of code derived from Special:CentralAuth.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialMultiLock extends SpecialPage {
	var $mCanLock, $mCanOversight;
	var $mGlobalUsers, $mUserNames, $mPrefixSearch, $mPosted, $mMethod;
	var $mActionLock, $mActionHide, $mReason;

	function __construct() {
		parent::__construct( 'MultiLock', 'centralauth-lock' );
	}

	function execute( $subpage ) {
		$this->setHeaders();
		$this->checkPermissions();

		$this->mCanLock = $this->getUser()->isAllowed( 'centralauth-lock' );
		$this->mCanOversight = $this->getUser()->isAllowed( 'centralauth-oversight' );
		$this->getOutput()->addModules( 'ext.centralauth' );
		$this->getOutput()->addModuleStyles( 'ext.centralauth.noflash' );
		$this->mMethod = $this->getRequest()->getVal( 'wpMethod', '' );
		$this->mActionLock = $this->getRequest()->getVal( 'wpActionLock', 'nochange' );
		$this->mActionHide = $this->getRequest()->getVal( 'wpActionHide', 'nochange' );
		$this->mReason = $this->getRequest()->getVal( 'wpReason' );
		$this->mUserNames = $this->getRequest()->getVal( 'wpTarget', '' );
		$this->mPrefixSearch = $this->getRequest()->getVal( 'wpSearchTarget', '' );
		$this->mActionUserNames = $this->getRequest()->getArray( 'wpActionTarget' );
		$this->mPosted = $this->getRequest()->wasPosted();

		if ( $this->mUserNames != '' ) {
			$this->mUserNames = explode( "\n", $this->mUserNames );
		} else {
			$this->mUserNames = array();
		}

		if ( $this->mPrefixSearch != '' ) {
			$this->mPrefixSearch = $this->getLang()->ucfirst( trim( $this->mPrefixSearch ) );
		}

		if ( $this->mMethod == '' ) {
			$this->getOutput()->addWikiMsg( 'centralauth-admin-multi-intro' );
			$this->showUsernameForm();
			return;
		} elseif ( $this->mPosted && $this->mMethod == 'search' && count( $this->mUserNames ) > 0 ) {
			$this->showUserTable();
		} elseif ( $this->mPosted && $this->mMethod == 'search' && $this->mPrefixSearch !== '' ) {
			$this->searchForUsers();
			$this->showUserTable();
		} elseif ( $this->mPosted && $this->mMethod == 'set-status' && is_array( $this->mActionUserNames ) ) {
			$this->mGlobalUsers = array_map( "self::getGlobalUser", $this->mActionUserNames );
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
	public function getGlobalUser( $username ) {
		$username = trim( $username );
		if ( $username == '' ) {
			return false;
		}
		$username = $this->getLang()->ucfirst( $username );

		$globalUser = new CentralAuthUser( $username );
		if ( !$globalUser->exists() ||
			( $globalUser->isOversighted() && !$this->mCanOversight ) ) {
			return $this->msg( 'centralauth-admin-nonexistent', $username )->parse();
		}
		return $globalUser;
	}


	private function searchForUsers() {

		$dbr = CentralAuthUser::getCentralSlaveDB();

		$where = array( 'gu_name' . $dbr->buildLike( $this->mPrefixSearch, $dbr->anyString() ) );
		if ( !$this->mCanOversight ) {
			$where[] = 'gu_hidden != \'suppressed\'';
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


	private function showStatusForm() {

		if ( !$this->mCanLock && !$this->mCanOversight ) {
			return;
		}

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
			'<br />' .
			Xml::radioLabel(
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
			'<br />';
		if ( $this->mCanOversight ) {
			$radioHidden .= Xml::radioLabel(
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

		$form .= '</fieldset>';

		$searchlist = $this->mUserNames;
		if ( is_array( $this->mUserNames ) ) {
			$searchlist = implode( "\n", $this->mUserNames );
		}
		$form .= Html::hidden( 'wpTarget', $searchlist );

		$form = Xml::tags(
			'form',
			array(
				'method' => 'POST',
				'action' => $this->getTitle()->getFullURL(),
			),
			$form
		);

		$this->getOutput()->addHTML( $form );
	}

	function showTableHeader() {

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

		$this->mGlobalUsers = array_map( "self::getGlobalUser", $this->mUserNames );
		$out = $this->getOutput();

		if ( count( $this->mGlobalUsers ) < 1 ) {
			$this->showError( 'centralauth-admin-multi-notfound' );
			return;
		}

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
				$guHidden = $this->formatHiddenLevel( $globalUser->getHiddenLevel() );
				$guWiki = $globalUser->getHomeWiki();
				$guRegister = $this->prettyTimespan(
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

		$out->addHTML( "</tbody></table>" );
		$this->showStatusForm();
	}

	/**
	 * Lock / hide global users and log the activity (if any)
	 */
	private function setStatus() {

		if ( !$this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) ) ) {
			$this->showError( 'centralauth-token-mismatch' );
			return;
		}

		// Check the easy permissions here. Permissions per global user
		// are checked in CentralAuthUser::adminLockHide().
		if ( $this->mActionLock != 'unchanged' && !$this->mCanLock ) {
			$this->showError( 'centralauth-admin-not-authorized' );
			return;
		}
		if ( $this->mActionHide == CentralAuthUser::HIDDEN_OVERSIGHT && !$this->mCanOversight ) {
			$this->showError( 'centralauth-admin-not-authorized' );
			return;
		}

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

			$status = $globalUser->adminLockHide(
				$setLocked,
				$setHidden,
				$this->mReason,
				$this->getContext()
			);

			if ( !$status->isGood() ) {
				$this->showStatusError( $status->getWikiText() );
			} elseif ( $status->successCount > 0 ) {
				$this->logAction(
						'setstatus',
						$globalUser->getName(),
						$this->mReason,
						$status->success,
						$setHidden == CentralAuthUser::HIDDEN_OVERSIGHT
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
		global $wgScript;

		if ( is_array( $this->mUserNames ) ) {
			$this->mUserNames = implode( "\n", $this->mUserNames );
		}

		$lookup = $this->msg( 'centralauth-admin-lookup-ro' )->text();
		$this->getOutput()->addHTML(
			Xml::openElement( 'form', array(
				'method' => 'post',
				'action' => $wgScript . "?title=Special:MultiLock" ) ) .
			'<fieldset>' .
			Xml::element( 'legend', array(), $this->msg( 'centralauth-admin-manage' )->text() ) .
			Html::hidden( 'wpMethod', 'search' ) .
			'<p>' .
			$this->msg( 'centralauth-admin-multi-username' )->escaped() .
			'</p><p>' .
			Xml::textarea( 'wpTarget', ( $this->mPrefixSearch ? '' : $this->mUserNames ), 25, 20 ) .
			'</p>' .
			'<p>' .
			$this->msg( 'centralauth-admin-multi-searchprefix' )->escaped() .
			'</p>' .
			'<p>' .
			Html::input( 'wpSearchTarget', $this->mPrefixSearch ) .
			'</p>' .
			'<p>' .
			Xml::submitButton( $lookup ) .
			'</p>' .
			'</fieldset>' .
			'</form>'
		);
	}

	/**
	 * @param $span
	 * @return String
	 */
	function prettyTimespan( $span ) {
		$units = array(
			'seconds' => 60,
			'minutes' => 60,
			'hours' => 24,
			'days' => 30.417,
			'months' => 12,
			'years' => 1 );
		foreach ( $units as $unit => $chunk ) {
			// Used messaged (to make sure that grep finds them):
			// 'centralauth-seconds-ago', 'centralauth-minutes-ago', 'centralauth-hours-ago'
			// 'centralauth-days-ago', 'centralauth-months-ago', 'centralauth-years-ago'
			if ( $span < 2 * $chunk ) {
				return $this->msg( "centralauth-$unit-ago" )->numParams( $span )->text();
			}
			$span = intval( $span / $chunk );
		}
		return $this->msg( "centralauth-$unit-ago" )->numParams( $span )->text();
	}

	/**
	 * @param $level
	 * @return String
	 */
	function formatHiddenLevel( $level ) {
		switch( $level ) {
			case CentralAuthUser::HIDDEN_NONE:
				return $this->msg( 'centralauth-admin-no' )->escaped();
			case CentralAuthUser::HIDDEN_LISTS:
				return $this->msg( 'centralauth-admin-hidden-list' )->escaped();
			case CentralAuthUser::HIDDEN_OVERSIGHT:
				return $this->msg( 'centralauth-admin-hidden-oversight' )->escaped();
		}
		return '';
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
			$this->getOutput()->addHTML( Xml::fieldset(
				$this->msg( 'centralauth-admin-logsnippet' )->text(),
				$text
			) );
		}
	}

	/**
	 * @param $action
	 * @param $target
	 * @param $reason string
	 * @param $params array
	 * @param $suppressLog bool
	 */
	function logAction( $action, $target, $reason = '', $params = array(), $suppressLog = false ) {
		$logType = $suppressLog ? 'suppress' : 'globalauth';	// Not centralauth because of some weird length limitiations
		$log = new LogPage( $logType );
		$log->addEntry( $action, Title::newFromText( "User:{$target}@global" ), $reason, $params );
	}
}
