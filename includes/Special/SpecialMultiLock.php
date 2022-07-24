<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use Html;
use LogEventsList;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthUIService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use SpecialPage;
use Xml;

/**
 * Special page to allow locking and hiding multiple users
 * at one time. Lots of code derived from Special:CentralAuth.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialMultiLock extends SpecialPage {
	/** @var bool */
	private $mCanSuppress;
	/** @var string[] */
	private $mGlobalUsers;
	/** @var string[]|string|null */
	private $mUserNames;
	/** @var string */
	private $mPrefixSearch;
	/** @var bool */
	private $mPosted;
	/** @var string */
	private $mMethod;
	/** @var string */
	private $mActionLock;
	/** @var int */
	private $mActionHide;
	/** @var string */
	private $mReason;
	/** @var string[] */
	private $mActionUserNames;
	/** @var CentralAuthDatabaseManager */
	private $databaseManager;
	/** @var CentralAuthUIService */
	private $uiService;

	/**
	 * @param CentralAuthDatabaseManager $databaseManager
	 * @param CentralAuthUIService $uiService
	 */
	public function __construct(
		CentralAuthDatabaseManager $databaseManager,
		CentralAuthUIService $uiService
	) {
		parent::__construct( 'MultiLock', 'centralauth-lock' );
		$this->databaseManager = $databaseManager;
		$this->uiService = $uiService;
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $subpage ) {
		$this->setHeaders();
		$this->checkPermissions();

		$this->mCanSuppress = $this->getContext()->getAuthority()->isAllowed( 'centralauth-suppress' );
		$this->getOutput()->addModules( 'ext.centralauth' );
		$this->getOutput()->addModuleStyles( 'ext.centralauth.noflash' );
		$this->mMethod = $this->getRequest()->getVal( 'wpMethod', '' );
		$this->mActionLock = $this->getRequest()->getVal( 'wpActionLock', 'nochange' );
		$this->mActionHide = $this->getRequest()->getInt( 'wpActionHide', -1 );
		$this->mUserNames = $this->getRequest()->getVal( 'wpTarget', '' );
		$this->mPrefixSearch = $this->getRequest()->getVal( 'wpSearchTarget', '' );
		$this->mActionUserNames = $this->getRequest()->getArray( 'wpActionTarget' );
		$this->mPosted = $this->getRequest()->wasPosted();

		$this->mReason = $this->getRequest()->getText( 'wpReasonList' );
		$reasonDetail = $this->getRequest()->getText( 'wpReason' );

		if ( $this->mReason == 'other' ) {
			$this->mReason = $reasonDetail;
		} elseif ( $reasonDetail ) {
			$this->mReason .= $this->msg( 'colon-separator' )->inContentLanguage()->text() .
				$reasonDetail;
		}

		if ( $this->mUserNames !== '' ) {
			$this->mUserNames = explode( "\n", $this->mUserNames );
		} else {
			$this->mUserNames = [];
		}

		if ( $this->mPrefixSearch !== '' ) {
			$this->mPrefixSearch = $this->getLanguage()->ucfirst( trim( $this->mPrefixSearch ) );
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
		} elseif ( $this->mPosted && $this->mMethod == 'set-status' &&
			is_array( $this->mActionUserNames )
		) {
			$this->mGlobalUsers = array_unique(
				$this->getGlobalUsers( $this->mActionUserNames, true ), SORT_REGULAR
			);
			$this->setStatus();
			$this->showUserTable();
		} else {
			$this->showError( 'centralauth-admin-multi-username' );
		}

		$this->showUsernameForm();

		$this->showLogExtract();
	}

	/**
	 * Get the CentralAuthUsers from lines of text
	 *
	 * @param string[] $usernames
	 * @param bool $fromPrimaryDb
	 * @return (CentralAuthUser|string|false)[] User object, a HTML error string, or false.
	 */
	private function getGlobalUsers( $usernames, $fromPrimaryDb = false ) {
		$ret = [];
		foreach ( $usernames as $username ) {
			$username = str_replace( '_', ' ', $username ); // T270954
			$username = trim( $username );
			if ( $username === '' ) {
				$ret[] = false;
				continue;
			}
			$username = $this->getLanguage()->ucfirst( $username );

			$globalUser = $fromPrimaryDb
				? CentralAuthUser::getPrimaryInstanceByName( $username )
				: CentralAuthUser::getInstanceByName( $username );
			if ( !$globalUser->exists()
				|| ( !$this->mCanSuppress &&
					( $globalUser->isSuppressed() || $globalUser->isHidden() ) )
			) {
				$ret[] = $this->msg( 'centralauth-admin-nonexistent', $username )->parse();
			} else {
				$ret[] = $globalUser;
			}
		}
		return $ret;
	}

	/**
	 * Search the CentralAuth db for all usernames prefixed with mPrefixSearch
	 */
	private function searchForUsers() {
		$dbr = $this->databaseManager->getCentralDB( DB_REPLICA );

		$where = [ 'gu_name' . $dbr->buildLike( $this->mPrefixSearch, $dbr->anyString() ) ];
		if ( !$this->mCanSuppress ) {
			$where['gu_hidden_level'] = CentralAuthUser::HIDDEN_LEVEL_NONE;
		}

		$result = $dbr->selectFieldValues(
			[ 'globaluser' ],
			'gu_name',
			$where,
			__METHOD__,
			[ 'LIMIT' => 100 ]
		);

		foreach ( $result as $name ) {
			$this->mUserNames[] = $name;
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
				$this->msg( 'centralauth-admin-action-lock-nochange' )->text(),
				'wpActionLock',
				'nochange',
				'mw-centralauth-status-locked-no',
				true ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-lock-unlock' )->text(),
				'wpActionLock',
				'unlock',
				'centralauth-admin-action-lock-unlock',
				false ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-lock-lock' )->text(),
				'wpActionLock',
				'lock',
				'centralauth-admin-action-lock-lock',
				false );

		$radioHidden =
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-hide-nochange' )->text(),
				'wpActionHide',
				'-1',
				'mw-centralauth-status-hidden-nochange',
				true ) .
			'<br />';
		if ( $this->mCanSuppress ) {
			$radioHidden .= Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-hide-none' )->text(),
				'wpActionHide',
				(string)CentralAuthUser::HIDDEN_LEVEL_NONE,
				'mw-centralauth-status-hidden-no',
				false ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-hide-lists' )->text(),
				'wpActionHide',
				(string)CentralAuthUser::HIDDEN_LEVEL_LISTS,
				'mw-centralauth-status-hidden-list',
				false ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-action-hide-oversight' )->text(),
				'wpActionHide',
				(string)CentralAuthUser::HIDDEN_LEVEL_SUPPRESSED,
				'mw-centralauth-status-hidden-oversight',
				false
			);
		}

		$reasonList = Xml::listDropDown(
			'wpReasonList',
			$this->msg( 'centralauth-admin-status-reasons' )->inContentLanguage()->text(),
			$this->msg( 'centralauth-admin-reason-other-select' )->inContentLanguage()->text()
		);
		$reasonField = Xml::input( 'wpReason', 45, false );
		$botField = Xml::check( 'markasbot' ) .
			$this->msg( 'centralauth-admin-multi-botcheck' )->parse();

		// @phan-suppress-next-line SecurityCheck-DoubleEscaped taint-check tracks keys and values together
		$form .= Xml::buildForm(
			[
				'centralauth-admin-status-locked' => $radioLocked,
				'centralauth-admin-status-hidden' => $radioHidden,
				'centralauth-admin-reason' => $reasonList,
				'centralauth-admin-reason-other' => $reasonField,
				'centralauth-admin-multi-bot' => $botField
			],
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
			[
				'method' => 'POST',
				'action' => $this->getPageTitle()->getFullUrl()
			]
		);

		$header .= Xml::fieldset( $this->msg( 'centralauth-admin-status' )->text() );
		$header .= Html::hidden( 'wpMethod', 'set-status' );
		$header .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		$header .= $this->msg( 'centralauth-admin-status-intro' )->parseAsBlock();

		$header .= Xml::openElement(
			'table',
			[ 'class' => 'wikitable sortable mw-centralauth-wikislist' ]
		);

		$header .= '<thead><tr>' .
				'<th></th>' .
				'<th>' .
				$out->getContext()->msg( 'centralauth-admin-username' )->escaped() .
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
				'<th>' .
				$out->getContext()->msg( 'centralauth-admin-info-editcount' )->escaped() .
				'</th>' .
				'<th>' .
				$out->getContext()->msg( 'centralauth-admin-info-attached' )->escaped() .
				'</th>' .
				'<th>' .
				$out->getContext()->msg( 'centralauth-multilock-homewiki' )->escaped() .
				'</th>' .
			'</tr></thead>' .
			'<tbody>';

		$out->addHTML( $header );
		$out->addModuleStyles( 'jquery.tablesorter.styles' );
		$out->addModules( 'jquery.tablesorter' );
	}

	/**
	 * Build the table of users to lock and/or hide
	 */
	private function showUserTable() {
		$this->mGlobalUsers = array_unique(
			$this->getGlobalUsers( $this->mUserNames ), SORT_REGULAR
		);

		$out = $this->getOutput();

		if ( count( $this->mGlobalUsers ) < 1 ) {
			$this->showError( 'centralauth-admin-multi-notfound' );
			return;
		}

		$this->showTableHeader();

		foreach ( $this->mGlobalUsers as $globalUser ) {
			$rowtext = Xml::openElement( 'tr' );

			if ( $globalUser === false ) {
				continue;
			} elseif ( $globalUser instanceof CentralAuthUser ) {
				$rowtext .= $this->getUserTableRow( $globalUser );
			} else {
				$rowtext .= Html::rawElement(
					'td',
					[ 'colspan' => 8 ],
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
	 * @param CentralAuthUser $globalUser
	 * @return string
	 */
	private function getUserTableRow( CentralAuthUser $globalUser ) {
		$rowHtml = '';

		$guName = $globalUser->getName();
		$guLink = $this->getLinkRenderer()->makeLink(
			SpecialPage::getTitleFor( 'CentralAuth', $guName ),
			// Names are known to exist, so this is not really needed
			$guName
		);
		// formatHiddenLevel html escapes its output
		$guHidden = $this->uiService->formatHiddenLevel( $this->getContext(), $globalUser->getHiddenLevelInt() );
		$accountAge = time() - (int)wfTimestamp( TS_UNIX, $globalUser->getRegistration() );
		$guRegister = $this->uiService->prettyTimespan( $this->getContext(), $accountAge );
		$guLocked = $this->msg( 'centralauth-admin-status-locked-no' )->text();
		if ( $globalUser->isLocked() ) {
			$guLocked = $this->msg( 'centralauth-admin-status-locked-yes' )->text();
		}
		$guEditCount = $this->getLanguage()->formatNum( $globalUser->getGlobalEditCount() );
		$guAttachedLocalAccounts = $this->getLanguage()
			->formatNum( count( $globalUser->listAttached() ) );
		$guHomeWiki = $globalUser->getHomeWiki() ?? '';

		$rowHtml .= Html::rawElement( 'td', [],
			Html::input(
				'wpActionTarget[' . $guName . ']',
				$guName,
				'checkbox',
				[ 'checked' => 'checked' ]
			)
		);
		$rowHtml .= Html::rawElement( 'td', [], $guLink );
		$rowHtml .= Html::element( 'td', [ 'data-sort-value' => $accountAge ], $guRegister );
		$rowHtml .= Html::element( 'td', [], $guLocked );
		$rowHtml .= Html::rawElement( 'td', [], $guHidden );
		$rowHtml .= Html::element( 'td', [], $guEditCount );
		$rowHtml .= Html::element( 'td', [], $guAttachedLocalAccounts );
		$rowHtml .= Html::element( 'td', [], $guHomeWiki );

		return $rowHtml;
	}

	/**
	 * Lock and/or hide global users and log the activity (if any)
	 */
	private function setStatus() {
		if ( !$this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) ) ) {
			$this->showError( 'centralauth-token-mismatch' );
			return;
		}

		if ( $this->mActionHide !== -1 && !$this->mCanSuppress ) {
			$this->showError( 'centralauth-admin-not-authorized' );
			return;
		}

		$setLocked = null;
		$setHidden = null;

		if ( $this->mActionLock != 'nochange' ) {
			$setLocked = ( $this->mActionLock == 'lock' );
		}

		if ( $this->mActionHide !== -1 ) {
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
				$this->getContext(),
				$this->getRequest()->getCheck( 'markasbot' )
			);

			if ( !$status->isGood() ) {
				$this->showStatusError( $status->getWikiText() );
			} elseif ( $status->successCount > 0 ) {
				$this->showSuccess( 'centralauth-admin-setstatus-success', $globalUser->getName() );
			}
		}
	}

	/**
	 * @param string $wikitext
	 */
	private function showStatusError( $wikitext ) {
		$out = $this->getOutput();
		$out->addHTML(
			Html::errorBox(
				$out->parseAsInterface( $wikitext )
			)
		);
	}

	private function showError( ...$args ) {
		$this->getOutput()->addHTML( Html::errorBox( $this->msg( ...$args )->parse() ) );
	}

	private function showSuccess( ...$args ) {
		$this->getOutput()->addHTML( Html::successBox( $this->msg( ...$args )->parse() ) );
	}

	private function showUsernameForm() {
		if ( is_array( $this->mUserNames ) ) {
			$this->mUserNames = implode( "\n", $this->mUserNames );
		}

		$form = Xml::tags( 'form',
			[
				'method' => 'post',
				'action' => $this->getPageTitle()->getLocalUrl()
			],
			Xml::tags( 'fieldset', [],
				Xml::element( 'legend', [], $this->msg( 'centralauth-admin-manage' )->text() ) .
				Html::hidden( 'wpMethod', 'search' ) .
				Xml::element( 'p', [],
					$this->msg( 'centralauth-admin-multi-username' )->text()
				) .
				Xml::textarea( 'wpTarget',
					( $this->mPrefixSearch ? '' : $this->mUserNames ), 25, 20 ) .
				Xml::element( 'p', [],
					$this->msg( 'centralauth-admin-multi-searchprefix' )->text()
				) .
				Html::input( 'wpSearchTarget', $this->mPrefixSearch ) .
				Xml::tags( 'p', [],
					Xml::submitButton( $this->msg( 'centralauth-admin-lookup-ro' )->text() )
				)
			)
		);
		$this->getOutput()->addHTML( $form );
	}

	/**
	 * Show the last 50 log entries
	 */
	private function showLogExtract() {
		$text = '';
		$numRows = LogEventsList::showLogExtract(
			$text,
			[ 'globalauth', 'suppress' ],
			'',
			'',
			[
				'conds' => [
					'log_action' => 'setstatus' // T59253
				],
				'showIfEmpty' => true
			] );
		if ( $numRows ) {
			$this->getOutput()->addHTML(
				Xml::fieldset( $this->msg( 'centralauth-admin-logsnippet' )->text(), $text )
			);
		}
	}

	protected function getGroupName() {
		return 'users';
	}
}
