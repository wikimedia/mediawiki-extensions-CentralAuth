<?php

class SpecialCentralAuth extends SpecialPage {
	var $mUserName, $mCanUnmerge, $mCanLock, $mCanOversight, $mCanEdit;

	/**
	 * @var CentralAuthUser
	 */
	var $mGlobalUser;

	var $mAttachedLocalAccounts, $mUnattachedLocalAccounts;

	var $mMethod, $mPosted, $mWikis;

	function __construct() {
		parent::__construct( 'CentralAuth' );
	}

	function execute( $subpage ) {
		global $wgContLang;
		$this->setHeaders();

		$this->mCanUnmerge = $this->getUser()->isAllowed( 'centralauth-unmerge' );
		$this->mCanLock = $this->getUser()->isAllowed( 'centralauth-lock' );
		$this->mCanOversight = $this->getUser()->isAllowed( 'centralauth-oversight' );
		$this->mCanEdit = $this->mCanUnmerge || $this->mCanLock || $this->mCanOversight;

		$this->getOutput()->addModules( 'ext.centralauth' );
		$this->getOutput()->addModuleStyles( 'ext.centralauth.noflash' );
		$this->getOutput()->addJsConfigVars( 'wgMergeMethodDescriptions', $this->getMergeMethodDescriptions() );

		$this->mUserName =
			trim(
				str_replace( '_', ' ',
					$this->getRequest()->getText( 'target', $subpage ) ) );

		$this->mUserName = $wgContLang->ucfirst( $this->mUserName );

		$this->mPosted = $this->getRequest()->wasPosted();
		$this->mMethod = $this->getRequest()->getVal( 'wpMethod' );
		$this->mWikis = (array)$this->getRequest()->getArray( 'wpWikis' );

		// Possible demo states

		// success, all accounts merged
		// successful login, some accounts merged, others left
		// successful login, others left
		// not account owner, others left

		// is owner / is not owner
		// did / did not merge some accounts
		// do / don't have more accounts to merge

		if ( $this->mUserName === '' ) {
			# First time through
			$this->getOutput()->addWikiMsg( 'centralauth-admin-intro' );
			$this->showUsernameForm();
			return;
		}

		$this->mGlobalUser = $globalUser = new CentralAuthUser( $this->mUserName );

		if ( !$globalUser->exists() ||
			( ( $globalUser->isOversighted() || $globalUser->isHidden() ) && !$this->mCanOversight ) ) {
			$this->showError( 'centralauth-admin-nonexistent', $this->mUserName );
			$this->showUsernameForm();
			return;
		}

		$continue = true;
		if ( $this->mCanEdit && $this->mPosted ) {
			$continue = $this->doSubmit();
		}

		$this->mAttachedLocalAccounts = $this->mGlobalUser->queryAttached();
		$this->mUnattachedLocalAccounts = $this->mGlobalUser->queryUnattached();

		$this->showUsernameForm();
		if ( $continue ) {
			$this->showInfo();
			if ( $this->mCanLock ) {
				$this->showStatusForm();
			}
			if ( $this->mCanUnmerge ) {
				$this->showActionForm( 'delete' );
			}
			if ( $this->mCanEdit ) {
				$this->showLogExtract();
			}
			$this->showWikiLists();
		}
	}

	/**
	 * @return bool Returns true if the normal form should be displayed
	 */
	function doSubmit() {
		$deleted = false;
		$globalUser = $this->mGlobalUser;
		$stateCheck = ( $this->getRequest()->getVal( 'wpUserState' ) === $globalUser->getStateHash( true ) );

		if ( !$this->getUser()->matchEditToken( $this->getRequest()->getVal( 'wpEditToken' ) ) ) {
			$this->showError( 'centralauth-token-mismatch' );
		} elseif ( $this->mMethod == 'unmerge' && $this->mCanUnmerge ) {
			$status = $globalUser->adminUnattach( $this->mWikis );
			if ( !$status->isGood() ) {
				$this->showStatusError( $status->getWikiText() );
			} else {
				$this->showSuccess( 'centralauth-admin-unmerge-success',
					$this->getLanguage()->formatNum( $status->successCount ),
					/* deprecated */ $status->successCount );
			}
		} elseif ( $this->mMethod == 'delete' && $this->mCanUnmerge ) {
			$status = $globalUser->adminDelete( $this->getRequest()->getVal( 'reason' ) );
			if ( !$status->isGood() ) {
				$this->showStatusError( $status->getWikiText() );
			} else {
				$this->showSuccess( 'centralauth-admin-delete-success', $this->mUserName );
				$deleted = true;
			}
		} elseif ( $this->mMethod == 'set-status' && !$stateCheck ) {
			$this->showError( 'centralauth-state-mismatch' );
		} elseif ( $this->mMethod == 'set-status' && $this->mCanLock ) {
			$setLocked = $this->getRequest()->getBool( 'wpStatusLocked' );
			$setHidden = $this->getRequest()->getVal( 'wpStatusHidden' );
			$reason = $this->getRequest()->getText( 'wpReasonList' );
			$reasonDetail = $this->getRequest()->getText( 'wpReason' );

			if ( $reason == 'other' ) {
				$reason = $reasonDetail;
			} elseif ( $reasonDetail ) {
				$reason .= $this->msg( 'colon-separator' )->inContentLanguage()->text() . $reasonDetail;
			}

			$status = $globalUser->adminLockHide(
				$setLocked,
				$setHidden,
				$reason,
				$this->getContext()
			);

			// Tell the user what happened
			if ( !$status->isGood() ) {
				$this->showStatusError( $status->getWikiText() );
			} elseif ( $status->successCount > 0 ) {
				$this->showSuccess( 'centralauth-admin-setstatus-success', $this->mUserName );
			}
		} else {
			$this->showError( 'centralauth-admin-bad-input' );
		}
		return !$deleted;
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
		$lookup = $this->mCanEdit ?
			$this->msg( 'centralauth-admin-lookup-rw' )->text() :
			$this->msg( 'centralauth-admin-lookup-ro' )->text();
		$this->getOutput()->addHTML(
			Xml::openElement( 'form', array(
				'method' => 'get',
				'action' => $wgScript ) ) .
			'<fieldset>' .
			Xml::element( 'legend', array(), $this->msg( 'centralauth-admin-manage' )->text() ) .
			Html::hidden( 'title', $this->getTitle()->getPrefixedText() ) .
			'<p>' .
			Xml::inputLabel( $this->msg( 'centralauth-admin-username' )->text(),
				'target', 'target', 25, $this->mUserName ) .
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

	function showInfo() {
		$globalUser = $this->mGlobalUser;

		$reg = $globalUser->getRegistration();
		$age = $this->prettyTimespan( wfTimestamp( TS_UNIX ) - wfTimestamp( TS_UNIX, $reg ) );
		$attribs = array(
			'username' => $globalUser->getName(),
			'id' => $globalUser->getId(),
			'registered' => htmlspecialchars( $this->getLanguage()->timeanddate( $reg, true ) . " ($age)" ),
			'home' => $this->determineHomeWiki(),
			'editcount' => htmlspecialchars( $this->getLanguage()->formatNum( $this->evaluateTotalEditcount() ) ),
			'locked' => $this->msg( $globalUser->isLocked() ? 'centralauth-admin-yes' : 'centralauth-admin-no' )->escaped(),
			'hidden' => $this->formatHiddenLevel( $globalUser->getHiddenLevel() )
		);

		// Give grep a chance to find the usages:
		// centralauth-admin-info-username, centralauth-admin-info-id, centralauth-admin-info-registered,
		// centralauth-admin-info-home, centralauth-admin-info-editcount, centralauth-admin-info-locked,
		// centralauth-admin-info-hidden
		$content = Xml::openElement( "ul" );
		foreach ( $attribs as $tag => $data ) {
			$content .= Xml::openElement( "li" ) . Xml::openElement( "strong" );
			$content .= $this->msg( "centralauth-admin-info-$tag" )->escaped();
			$content .= Xml::closeElement( "strong" ) . ' ' . $data . Xml::closeElement( "li" );
		}
		$out = Xml::fieldset(
			$this->msg( 'centralauth-admin-info-header' )->escaped(),
			$content,
			array( "id" => "mw-centralauth-info" )
		);
		$this->getOutput()->addHTML( $out );
	}

	function showWikiLists() {
		$merged = $this->mAttachedLocalAccounts;
		$remainder = $this->mUnattachedLocalAccounts;

		$legend = $this->mCanUnmerge ?
			$this->msg( 'centralauth-admin-list-legend-rw' )->escaped() :
			$this->msg( 'centralauth-admin-list-legend-ro' )->escaped();

		$this->getOutput()->addHTML( "<fieldset><legend>{$legend}</legend>" );
		$this->getOutput()->addHTML( $this->listHeader() );
		$this->getOutput()->addHTML( $this->listMerged( $merged ) );
		if ( $remainder ) {
			$this->getOutput()->addHTML( $this->listRemainder( $remainder ) );
		}
		$this->getOutput()->addHTML( $this->listFooter() );
		$this->getOutput()->addHTML( '</fieldset>' );
	}

	/**
	 * @return string
	 */
	function listHeader() {
		return
			Xml::openElement( 'form',
				array(
					'method' => 'post',
					'action' =>
						$this->getTitle( $this->mUserName )->getLocalUrl( 'action=submit' ),
					'id' => 'mw-centralauth-merged' ) ) .
			Html::hidden( 'wpMethod', 'unmerge' ) .
			Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
			Xml::openElement( 'table', array( 'class' => 'wikitable sortable mw-centralauth-wikislist' ) ) . "\n" .
			'<thead><tr>' .
				( $this->mCanUnmerge ? '<th></th>' : '' ) .
				'<th>' . $this->msg( 'centralauth-admin-list-localwiki' )->escaped() . '</th>' .
				'<th>' . $this->msg( 'centralauth-admin-list-attached-on' )->escaped() . '</th>' .
				'<th>' . $this->msg( 'centralauth-admin-list-method' )->escaped() . '</th>' .
				'<th>' . $this->msg( 'centralauth-admin-list-blocked' )->escaped() . '</th>' .
				'<th>' . $this->msg( 'centralauth-admin-list-editcount' )->escaped() . '</th>' .
			'</tr></thead>' .
			'<tbody>';
	}

	/**
	 * @return string
	 */
	function listFooter() {
		$footer = '';
		if ( $this->mCanUnmerge )
			$footer .=
				'<tr>' .
				'<td style="border-right: none"></td>' .
				'<td style="border-left: none" colspan="5">' .
				Xml::submitButton( $this->msg( 'centralauth-admin-unmerge' )->text() ) .
				'</td>' .
				'</tr>';
		$footer .= '</tbody></table></form>';
		return $footer;
	}

	/**
	 * @param $list
	 * @return string
	 */
	function listMerged( $list ) {
		ksort( $list );
		return implode( "\n", array_map( array( $this, 'listMergedWikiItem' ), $list ) );
	}

	/**
	 * @param $list
	 * @return string
	 */
	function listRemainder( $list ) {
		ksort( $list );
		$notMerged = $this->msg( 'centralauth-admin-unattached' )->parse();
		$rows = array();
		foreach ( $list as $row ) {
			$rows[] = '<tr class="unattached-row"><td>' .
				( $this->mCanUnmerge ? '</td><td>' : '' ) .
				$this->foreignUserLink( $row['wiki'] ) .
				"</td><td colspan='4'>{$notMerged}</td></tr>\n";
		}
		return implode( "\n", $rows );
	}

	/**
	 * @param $row
	 * @return string
	 */
	function listMergedWikiItem( $row ) {
		if ( $row === null ) {
			// https://bugzilla.wikimedia.org/show_bug.cgi?id=28767
			// It seems sometimes local accounts aren't correctly created
			// Revisiting the wiki solves the issue
			return '';
		}
		return '<tr>' .
			( $this->mCanUnmerge ? '<td>' . $this->adminCheck( $row['wiki'] ) . '</td>' : '' ) .
			'<td>' . $this->foreignUserLink( $row['wiki'] ) . '</td>' .
			'<td data-sort-value="' . htmlspecialchars( $row['attachedTimestamp'] ) . '">' .
				// visible date and time in users preference
				htmlspecialchars( $this->getLanguage()->timeanddate( $row['attachedTimestamp'], true ) ) .
			'</td>' .
			'<td style="text-align: center">' . $this->formatMergeMethod( $row['attachedMethod'] ) . '</td>' .
			'<td>' . $this->formatBlockStatus( $row ) . '</td>' .
			'<td style="text-align: right">' . $this->formatEditcount( $row ) . '</td>' .
			'</tr>';
	}

	/**
	 * @param $method
	 * @return string
	 */
	function formatMergeMethod( $method ) {
		global $wgExtensionAssetsPath;

		// Give grep a chance to find the usages:
		// centralauth-merge-method-primary, centralauth-merge-method-empty, centralauth-merge-method-mail,
		// centralauth-merge-method-password, centralauth-merge-method-admin, centralauth-merge-method-new,
		// centralauth-merge-method-login
		$brief = wfMessage( "centralauth-merge-method-{$method}" )->text();
		$html =
			Html::element(
				'img', array(
					'src' => "{$wgExtensionAssetsPath}/CentralAuth/icons/merged-{$method}.png",
					'alt' => $brief,
					'title' => $brief,
				)
			)
			. Html::element(
				'span', array(
					'class' => 'merge-method-help',
					'title' => $brief,
					'data-centralauth-mergemethod' => $method
				),
				$this->msg( 'centralauth-merge-method-questionmark' )->text()
			);

		return $html;
	}

	/**
	 * @param $row
	 * @return String
	 */
	function formatBlockStatus( $row ) {
		if ( $row['blocked'] ) {
			if ( $row['block-expiry'] == 'infinity' ) {
			$reason = $row['block-reason'];
				return $this->msg( 'centralauth-admin-blocked-indef', array( $reason ) )->parse();
			} else {
				$expiry = $this->getLanguage()->timeanddate( $row['block-expiry'], true );
				$expiryd = $this->getLanguage()->date( $row['block-expiry'], true );
				$expiryt = $this->getLanguage()->time( $row['block-expiry'], true );
				$reason = $row['block-reason'];

				$text = $this->msg( 'centralauth-admin-blocked', $expiry, $reason, $expiryd, $expiryt )->parse();
			}
		} else {
			$text = $this->msg( 'centralauth-admin-notblocked' )->parse();
		}

		return $this->foreignLink(
			$row['wiki'],
			'Special:Log/block',
			$text,
			$this->msg( 'centralauth-admin-blocklog' )->text(),
			'page=User:' . urlencode( $this->mUserName ) );
	}

	/**
	 * @param $row
	 * @return string
	 * @throws MWException
	 */
	function formatEditcount( $row ) {
		$wiki = WikiMap::getWiki( $row['wiki'] );
		if ( !$wiki ) {
			throw new MWException( "Invalid wiki: {$row['wiki']}" );
		}
		$wikiname = $wiki->getDisplayName();
		$editCount = $this->getLanguage()->formatNum( intval( $row['editCount'] ) );
		return $this->foreignLink(
			$row['wiki'],
			'Special:Contributions/' . $this->mUserName,
			$editCount,
			$this->msg( 'centralauth-foreign-contributions' )
				->numParams( $editCount )->params( $wikiname )->parse()
		);
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
	 * @param $element
	 * @param $cols
	 * @return string
	 */
	function tableRow( $element, $cols ) {
		return "<tr><$element>" .
			implode( "</$element><$element>", $cols ) .
			"</$element></tr>";
	}

	/**
	 * @param $wikiID
	 * @param $title
	 * @param $text
	 * @param string $hint
	 * @param string $params
	 * @return string
	 * @throws MWException
	 */
	function foreignLink( $wikiID, $title, $text, $hint = '', $params = '' ) {
		if ( $wikiID instanceof WikiReference ) {
			$wiki = $wikiID;
		} else {
			$wiki = WikiMap::getWiki( $wikiID );
			if ( !$wiki ) {
				throw new MWException( "Invalid wiki: $wikiID" );
			}
		}

		$url = $wiki->getFullUrl( $title );
		if ( $params ) {
			$url .= '?' . $params;
		}
		return Xml::element( 'a',
			array(
				'href' => $url,
				'title' => $hint,
			),
			$text );
	}

	/**
	 * @param $wikiID
	 * @return string
	 * @throws MWException
	 */
	function foreignUserLink( $wikiID ) {
		$wiki = WikiMap::getWiki( $wikiID );
		if ( !$wiki ) {
			throw new MWException( "Invalid wiki: $wikiID" );
		}

		$wikiname = $wiki->getDisplayName();
		return $this->foreignLink(
			$wiki,
			'User:' . $this->mUserName,
			$wikiname,
			$this->msg( 'centralauth-foreign-link', $this->mUserName, $wikiname )->text() );
	}

	/**
	 * @param $wikiID
	 * @return string
	 */
	function adminCheck( $wikiID ) {
		return Xml::check( 'wpWikis[]', false, array( 'value' => $wikiID ) );
	}

	/**
	 * @param $action String: Only 'delete' supported
	 */
	function showActionForm( $action ) {
		$this->getOutput()->addHTML(
			# to be able to find messages: centralauth-admin-delete-title,
			# centralauth-admin-delete-description, centralauth-admin-delete-button
			Xml::fieldset( $this->msg( "centralauth-admin-{$action}-title" )->text() ) .
			Xml::openElement( 'form', array(
				'method' => 'POST',
				'action' => $this->getTitle()->getFullUrl( 'target=' . urlencode( $this->mUserName ) ),
				'id' => "mw-centralauth-$action" ) ) .
			Html::hidden( 'wpMethod', $action ) .
			Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
				$this->msg( "centralauth-admin-{$action}-description" )->parseAsBlock() .
			Xml::buildForm(
				array( 'centralauth-admin-reason' => Xml::input( 'reason',
					false, false, array( 'id' => "{$action}-reason" ) ) ),
				"centralauth-admin-{$action}-button"
			) .
			'</form></fieldset>' );
	}

	function showStatusForm() {
		// Allows locking, hiding, locking and hiding.
		$form = '';
		$form .= Xml::fieldset( $this->msg( 'centralauth-admin-status' )->text() );
		$form .= Html::hidden( 'wpMethod', 'set-status' );
		$form .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		$form .= Html::hidden( 'wpUserState', $this->mGlobalUser->getStateHash( false ) );
		$form .= $this->msg( 'centralauth-admin-status-intro' )->parseAsBlock();

		// Radio buttons
		$radioLocked =
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-status-locked-no' )->parse(),
				'wpStatusLocked',
				'0',
				'mw-centralauth-status-locked-no',
				!$this->mGlobalUser->isLocked() ) .
			'<br />' .
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-status-locked-yes' )->parse(),
				'wpStatusLocked',
				'1',
				'mw-centralauth-status-locked-yes',
				$this->mGlobalUser->isLocked() );
		$radioHidden =
			Xml::radioLabel(
				$this->msg( 'centralauth-admin-status-hidden-no' )->parse(),
				'wpStatusHidden',
				CentralAuthUser::HIDDEN_NONE,
				'mw-centralauth-status-hidden-no',
				$this->mGlobalUser->getHiddenLevel() == CentralAuthUser::HIDDEN_NONE );
		if ( $this->mCanOversight ) {
			$radioHidden .= '<br />' .
				Xml::radioLabel(
					$this->msg( 'centralauth-admin-status-hidden-list' )->parse(),
					'wpStatusHidden',
					CentralAuthUser::HIDDEN_LISTS,
					'mw-centralauth-status-hidden-list',
					$this->mGlobalUser->getHiddenLevel() == CentralAuthUser::HIDDEN_LISTS
				) .
				'<br />' .
				Xml::radioLabel(
					$this->msg( 'centralauth-admin-status-hidden-oversight' )->parse(),
					'wpStatusHidden',
					CentralAuthUser::HIDDEN_OVERSIGHT,
					'mw-centralauth-status-hidden-oversight',
					$this->mGlobalUser->getHiddenLevel() == CentralAuthUser::HIDDEN_OVERSIGHT
				);
		}

		// Reason
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
				'centralauth-admin-reason-other' => $reasonField ),
				'centralauth-admin-status-submit'
		);

		$form .= '</fieldset>';
		$form = Xml::tags(
			'form',
			array(
				'method' => 'POST',
				'action' => $this->getTitle()->getFullURL(
					array( 'target' => $this->mUserName )
				),
			),
			$form
		);
		$this->getOutput()->addHTML( $form );
	}

	/**
	 *
	 */
	function showLogExtract() {
		$user = $this->mGlobalUser->getName();
		$text = '';
		$numRows = LogEventsList::showLogExtract(
			$text,
			array( 'globalauth', 'suppress' ),
			Title::newFromText( "User:{$user}@global" )->getPrefixedText(),
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
	 * @return int|string
	 */
	function determineHomeWiki() {
		foreach ( $this->mAttachedLocalAccounts as $wiki => $acc ) {
			if ( $acc['attachedMethod'] == 'primary' || $acc['attachedMethod'] == 'new' ) {
				return $this->foreignUserLink( $wiki );
			}
		}

		// Home account can be renamed or unmerged
		return $this->msg( 'centralauth-admin-nohome' )->escaped();
	}

	/**
	 * @return int
	 */
	function evaluateTotalEditcount() {
		$total = 0;
		foreach ( $this->mAttachedLocalAccounts as $acc ) {
			$total += $acc['editCount'];
		}
		return $total;
	}

	/**
	 * @return array
	 */
	function getMergeMethodDescriptions() {
		// Give grep a chance to find the usages:
		//  centralauth-merge-method-primary, centralauth-merge-method-new, centralauth-merge-method-empty,
		//  centralauth-merge-method-password, centralauth-merge-method-mail, centralauth-merge-method-admin,
		//  centralauth-merge-method-login
		// Give grep a chance to find the usages:
		//  centralauth-merge-method-primary-desc, centralauth-merge-method-new-desc,
		//  centralauth-merge-method-empty-desc, centralauth-merge-method-password-desc,
		//  centralauth-merge-method-mail-desc, centralauth-merge-method-admin-desc,
		//  centralauth-merge-method-login-desc
		$mergeMethodDescriptions = array();
		foreach ( array( 'primary', 'new', 'empty', 'password', 'mail', 'admin', 'login' ) as $method ) {
			$mergeMethodDescriptions[$method] = array(
				'short' => $this->getLanguage()->ucfirst( $this->msg( "centralauth-merge-method-{$method}" )->escaped() ),
				'desc' => $this->msg( "centralauth-merge-method-{$method}-desc" )->escaped()
			);
		}
		return $mergeMethodDescriptions;
	}
}
