<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use Html;
use LogEventsList;
use LogPage;
use ManualLogEntry;
use MediaWiki\Extension\CentralAuth\CentralAuthWikiListService;
use MediaWiki\Extension\CentralAuth\WikiSet;
use PermissionsError;
use SpecialPage;
use Title;
use Xml;
use XmlSelect;

/**
 * Special page to allow to edit "wikisets" which are used to restrict
 * specific global group permissions to certain wikis.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialWikiSets extends SpecialPage {
	/** @var bool */
	private $mCanEdit;

	/** @var CentralAuthWikiListService */
	private $wikiListService;

	public function __construct( CentralAuthWikiListService $wikiListService ) {
		parent::__construct( 'WikiSets' );

		$this->wikiListService = $wikiListService;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'centralauth-editset' )->text();
	}

	/**
	 * @param string|null $subpage
	 * @return void
	 */
	public function execute( $subpage ) {
		$this->mCanEdit = $this->getContext()->getAuthority()->isAllowed( 'globalgrouppermissions' );
		$req = $this->getRequest();
		$tokenOk = $req->wasPosted()
			&& $this->getUser()->matchEditToken( $req->getVal( 'wpEditToken' ) );

		$this->setHeaders();

		if ( $subpage === null ) {
			$this->buildMainView();
			return;
		}

		if ( str_starts_with( $subpage, 'delete/' ) ) {
			if ( !$this->mCanEdit ) {
				$this->showNoPermissionsView();
			}

			$subpage = substr( $subpage, 7 ); // Remove delete/ part

			if ( is_numeric( $subpage ) ) {
				if ( $tokenOk ) {
					$this->doDelete( $subpage );
					return;
				}

				$this->buildDeleteView( $subpage );
				return;
			}
		}

		$set = null;
		if ( $subpage !== '0' ) {
			$set = is_numeric( $subpage ) ? WikiSet::newFromId( $subpage ) : WikiSet::newFromName( $subpage );
			if ( !$set ) {
				$this->getOutput()->setPageTitle( $this->msg( 'error' ) );
				$error = $this->msg( 'centralauth-editset-notfound', $subpage )->escaped();
				$this->buildMainView( Html::errorBox( $error ) );
				return;
			}
		} elseif ( !$this->mCanEdit ) {
			$this->showNoPermissionsView();
		}

		if ( $tokenOk ) {
			if ( !$this->mCanEdit ) {
				$this->showNoPermissionsView();
			}

			$this->doSubmit( $set );
			return;
		}

		$this->buildSetView( $set );
	}

	/**
	 * @param string|null $msg Output directly as HTML. Caller must escape.
	 */
	private function buildMainView( string $msg = null ) {
		// Give grep a chance to find the usages: centralauth-editset-legend-rw,
		// centralauth-editset-legend-ro
		$msgPostfix = $this->mCanEdit ? 'rw' : 'ro';
		$legend = $this->msg( "centralauth-editset-legend-{$msgPostfix}" )->escaped();
		$this->getOutput()->addHTML( "<fieldset><legend>{$legend}</legend>" );
		if ( $msg ) {
			$this->getOutput()->addHTML( $msg );
		}
		// Give grep a chance to find the usages: centralauth-editset-intro-rw,
		// centralauth-editset-intro-ro
		$this->getOutput()->addWikiMsg( "centralauth-editset-intro-{$msgPostfix}" );
		$this->getOutput()->addHTML( '<ul>' );

		// Give grep a chance to find the usages: centralauth-editset-item-rw,
		// centralauth-editset-item-ro
		$sets = WikiSet::getAllWikiSets();
		/**
		 * @var $set WikiSet
		 */
		foreach ( $sets as $set ) {
			$text = $this->msg( "centralauth-editset-item-{$msgPostfix}",
				$set->getName(), $set->getID() )->parse();
			$this->getOutput()->addHTML( "<li>{$text}</li>" );
		}

		if ( $this->mCanEdit ) {
			$target = SpecialPage::getTitleFor( 'WikiSets', '0' );
			$newlink = $this->getLinkRenderer()->makeLink(
				$target,
				$this->msg( 'centralauth-editset-new' )->text()
			);
			$this->getOutput()->addHTML( "<li>{$newlink}</li>" );
		}

		$this->getOutput()->addHTML( '</ul></fieldset>' );
	}

	/**
	 * @param WikiSet|null $set wiki set to operate on
	 * @param bool|string $error False or raw html to output as error
	 * @param string|null $name (Optional) Name of WikiSet
	 * @param string|null $type WikiSet::OPTIN or WikiSet::OPTOUT
	 * @param array|null $wikis
	 * @param string|null $reason
	 */
	private function buildSetView(
		?WikiSet $set, $error = false, $name = null, $type = null, $wikis = null, $reason = null
	) {
		$this->getOutput()->setSubtitle( $this->msg( 'centralauth-editset-subtitle' )->parse() );

		if ( !$name ) {
			$name = $set ? $set->getName() : '';
		}
		if ( !$type ) {
			$type = $set ? $set->getType() : WikiSet::OPTIN;
		}
		if ( !$wikis ) {
			$wikis = $set ? $set->getWikisRaw() : [];
		}

		sort( $wikis );
		$wikis = implode( "\n", $wikis );

		$url = SpecialPage::getTitleFor( 'WikiSets', (string)( $set->getId() ?? 0 ) )
			->getLocalUrl();

		if ( $this->mCanEdit ) {
			// Give grep a chance to find the usages:
			// centralauth-editset-legend-edit, centralauth-editset-legend-new
			$legend = $this->msg(
				'centralauth-editset-legend-' . ( $set ? 'edit' : 'new' ),
				$name
			)->escaped();
		} else {
			$legend = $this->msg( 'centralauth-editset-legend-view', $name )->escaped();
		}

		$this->getOutput()->addHTML( "<fieldset><legend>{$legend}</legend>" );

		if ( $set ) {
			$groups = $set->getRestrictedGroups();
			if ( $groups ) {
				$usage = "<ul>\n";
				foreach ( $groups as $group ) {
					$usage .= "<li>" . $this->msg( 'centralauth-editset-grouplink', $group )
						->parse() . "</li>\n";
				}
				$usage .= "</ul>";
			} else {
				$usage = $this->msg( 'centralauth-editset-nouse' )->parseAsBlock();
			}
			$sortedWikis = $set->getWikisRaw();
			sort( $sortedWikis );
		} else {
			$usage = '';
			$sortedWikis = [];
		}

		# Make an array of the opposite list of wikis
		# (all databases *excluding* the defined ones)
		$restWikis = [];
		foreach ( $this->getConfig()->get( 'LocalDatabases' ) as $wiki ) {
			if ( !in_array( $wiki, $sortedWikis ) ) {
				$restWikis[] = $wiki;
			}
		}
		sort( $restWikis );

		if ( $this->mCanEdit ) {
			if ( $error ) {
				$this->getOutput()->addHTML( Html::errorBox( $error ) );
			}
			$this->getOutput()->addHTML(
				Html::openElement(
					'form',
					[ 'action' => $url, 'method' => 'POST' ]
				)
			);

			$form = [];
			$form['centralauth-editset-name'] = Xml::input( 'wpName', false, $name );
			if ( $usage ) {
				$form['centralauth-editset-usage'] = $usage;
			}
			$form['centralauth-editset-type'] = $this->buildTypeSelector( 'wpType', $type );
			$form['centralauth-editset-wikis'] = Xml::textarea( 'wpWikis', $wikis );
			$form['centralauth-editset-restwikis'] = Xml::textarea( 'wpRestWikis',
				implode( "\n", $restWikis ), 40, 5, [ 'readonly' => true ] );
			$form['centralauth-editset-reason'] = Xml::input( 'wpReason', 50, $reason );

			// @phan-suppress-next-line SecurityCheck-DoubleEscaped taint-check tracks keys and values together
			$this->getOutput()->addHTML( Xml::buildForm( $form, 'centralauth-editset-submit' ) );

			$edittoken = Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
			$this->getOutput()->addHTML( "<p>{$edittoken}</p></form></fieldset>" );
		} else {
			// Give grep a chance to find the usages: centralauth-editset-optin,
			// centralauth-editset-optout
			$form = [];
			$form['centralauth-editset-name'] = htmlspecialchars( $name );
			$form['centralauth-editset-usage'] = $usage;
			$form['centralauth-editset-type'] = $this->msg( "centralauth-editset-{$type}" )
				->escaped();
			$form['centralauth-editset-wikis'] = self::buildTableByList(
				$sortedWikis, 3, [ 'style' => 'width:100%;' ]
			);
			$form['centralauth-editset-restwikis'] = self::buildTableByList(
				$restWikis, 3, [ 'style' => 'width:100%;' ]
			);

			// @phan-suppress-next-line SecurityCheck-DoubleEscaped taint-check tracks keys and values together
			$this->getOutput()->addHTML( Xml::buildForm( $form ) );
		}

		if ( $set ) {
			$this->showLogFragment( (string)$set->getId() );
		}
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return string
	 */
	private function buildTypeSelector( $name, $value ) {
		// Give grep a chance to find the usages: centralauth-editset-optin,
		// centralauth-editset-optout
		$select = new XmlSelect( $name, 'set-type', $value );
		foreach ( [ WikiSet::OPTIN, WikiSet::OPTOUT ] as $type ) {
			$select->addOption( $this->msg( "centralauth-editset-{$type}" )->text(), $type );
		}
		return $select->getHTML();
	}

	/**
	 * Builds a table of several columns, and divides the items of
	 * $list equally among each column. All items are escaped.
	 *
	 * Could in the future be replaced by CSS column-count.
	 *
	 * @param array $list
	 * @param int $columns number of columns
	 * @param array $tableAttribs <table> attributes
	 * @return string Table
	 */
	private function buildTableByList( $list, $columns = 2, $tableAttribs = [] ) {
		if ( !is_array( $list ) ) {
			return '';
		}

		$count = count( $list );
		if ( $count === 0 ) {
			return $this->msg( 'centralauth-editset-nowikis' )->parse();
		}

		# If there are less items than columns, limit the number of columns
		$columns = $count < $columns ? $count : $columns;
		$itemsPerCol = (int)ceil( $count / $columns );
		$i = 0;
		$splitLists = [];
		while ( $i < $columns ) {
			$splitLists[$i] = array_slice( $list, $itemsPerCol * $i, $itemsPerCol );
			$i++;
		}

		$body = '';
		foreach ( $splitLists as $splitList ) {
			$body .= '<td style="width:' . round( 100 / $columns ) . '%;"><ul>';
			foreach ( $splitList as $listitem ) {
				$body .= Html::element( 'li', [], $listitem );
			}
			$body .= '</ul></td>';
		}
		return Html::rawElement( 'table', $tableAttribs,
			'<tbody>' .
				Html::rawElement( 'tr', [ 'style' => 'vertical-align:top;' ], $body ) .
			'</tbody>'
		);
	}

	/**
	 * @param string $subpage
	 */
	private function buildDeleteView( $subpage ) {
		$this->getOutput()->setSubtitle( $this->msg( 'centralauth-editset-subtitle' )->parse() );

		$set = WikiSet::newFromID( $subpage );
		if ( !$set ) {
			$this->buildMainView( Html::errorBox( $this->msg( 'centralauth-editset-notfound', $subpage )->escaped() ) );
			return;
		}

		$legend = $this->msg( 'centralauth-editset-legend-delete', $set->getName() )->escaped();
		$form = [ 'centralauth-editset-reason' => Xml::input( 'wpReason' ) ];
		$url = htmlspecialchars(
			SpecialPage::getTitleFor( 'WikiSets', "delete/{$subpage}" )->getLocalUrl()
		);
		$edittoken = Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );

		$this->getOutput()->addHTML(
			"<fieldset><legend>{$legend}</legend><form action=\"{$url}\" method='post'>"
		);
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped taint-check tracks keys and values together
		$this->getOutput()->addHTML( Xml::buildForm( $form, 'centralauth-editset-submit-delete' ) );
		$this->getOutput()->addHTML( "<p>{$edittoken}</p></form></fieldset>" );
	}

	/**
	 * Log action to 'gblrights' log
	 *
	 * @param string $action Type of action
	 * @param Title $title
	 * @param string $reason
	 * @param array $params
	 */
	private function addEntry( $action, $title, $reason, $params ): void {
		$entry = new ManualLogEntry( 'gblrights', $action );
		$entry->setTarget( $title );
		$entry->setPerformer( $this->getUser() );
		$entry->setComment( $reason );
		$entry->setParameters( $params );
		$logid = $entry->insert();
		$entry->publish( $logid );
	}

	/**
	 * @param WikiSet|null $set wiki set to operate on
	 */
	private function doSubmit( ?WikiSet $set ) {
		$name = $this->getContentLanguage()->ucfirst( $this->getRequest()->getVal( 'wpName' ) );
		$type = $this->getRequest()->getVal( 'wpType' );
		$wikis = array_unique( preg_split(
			'/(\s+|\s*\W\s*)/', $this->getRequest()->getVal( 'wpWikis' ), -1, PREG_SPLIT_NO_EMPTY )
		);
		$reason = $this->getRequest()->getVal( 'wpReason' );

		if ( !Title::newFromText( $name ) ) {
			$this->buildSetView( $set, $this->msg( 'centralauth-editset-badname' )->escaped(),
				$name, $type, $wikis, $reason );
			return;
		}
		if ( ( !$set || $set->getName() != $name ) && WikiSet::newFromName( $name ) ) {
			$this->buildSetView( $set, $this->msg( 'centralauth-editset-setexists' )->escaped(),
				$name, $type, $wikis, $reason );
			return;
		}
		if ( !in_array( $type, [ WikiSet::OPTIN, WikiSet::OPTOUT ] ) ) {
			$this->buildSetView( $set, $this->msg( 'centralauth-editset-badtype' )->escaped(),
				$name, $type, $wikis, $reason );
			return;
		}
		if ( !$wikis ) {
			$this->buildSetView( $set, $this->msg( 'centralauth-editset-zerowikis' )->escaped(),
				$name, $type, $wikis, $reason );
			return;
		}

		$badwikis = [];
		$allwikis = $this->wikiListService->getWikiList();
		foreach ( $wikis as $wiki ) {
			if ( !in_array( $wiki, $allwikis ) ) {
				$badwikis[] = $wiki;
			}
		}
		if ( $badwikis ) {
			$this->buildSetView( $set, $this->msg(
				'centralauth-editset-badwikis',
				implode( ', ', $badwikis ) )
				->numParams( count( $badwikis ) )
				->escaped(),
				$name, $type, $wikis, $reason
			);
			return;
		}

		if ( $set ) {
			$oldname = $set->getName();
			$oldtype = $set->getType();
			$oldwikis = $set->getWikisRaw();
		} else {
			$set = new WikiSet();
			$oldname = $oldtype = null;
			$oldwikis = [];
		}
		$set->setName( $name );
		$set->setType( $type );
		$set->setWikisRaw( $wikis );
		$set->saveToDB();

		// Now logging
		$title = SpecialPage::getTitleFor( 'WikiSets', (string)$set->getID() );
		if ( !$oldname ) {
			// New set
			$this->addEntry(
				'newset',
				$title,
				$reason,
				[
					'4::name' => $name,
					'5::type' => $type,
					'wikis' => $wikis,
				]
			);
		} else {
			if ( $oldname != $name ) {
				$this->addEntry(
					'setrename',
					$title,
					$reason,
					[
						'4::name' => $name,
						'5::oldName' => $oldname,
					]
				);
			}
			if ( $oldtype != $type ) {
				$this->addEntry(
					'setnewtype',
					$title,
					$reason,
					[
						'4::name' => $name,
						'5::oldType' => $oldtype,
						'6::type' => $type,
					]
				);
			}
			$added = array_diff( $wikis, $oldwikis );
			$removed = array_diff( $oldwikis, $wikis );
			if ( $added || $removed ) {
				$this->addEntry(
					'setchange',
					$title,
					$reason,
					[
						'4::name' => $name,
						'added' => $added,
						'removed' => $removed,
					]
				 );
			}
		}

		$returnLink = $this->getLinkRenderer()->makeKnownLink(
			$this->getPageTitle(), $this->msg( 'centralauth-editset-return' )->text() );

		$this->getOutput()->addHTML(
			Html::successBox( $this->msg( 'centralauth-editset-success' )->escaped() ) .
			'<p>' . $returnLink . '</p>'
		);
	}

	/**
	 * @param string $set
	 */
	private function doDelete( $set ) {
		$set = WikiSet::newFromID( $set );
		if ( !$set ) {
			$this->buildMainView( Html::errorBox( $this->msg( 'centralauth-editset-notfound', $set )->escaped() ) );
			return;
		}

		$reason = $this->getRequest()->getVal( 'wpReason' );
		$name = $set->getName();
		$set->delete();

		$title = SpecialPage::getTitleFor( 'WikiSets', (string)$set->getID() );
		$this->addEntry( 'deleteset', $title, $reason, [ '4::name' => $name ] );

		$this->buildMainView( Html::successBox( $this->msg( 'centralauth-editset-success-delete' )->escaped() ) );
	}

	/**
	 * @param string $number
	 */
	protected function showLogFragment( $number ) {
		$title = SpecialPage::getTitleFor( 'WikiSets', $number );
		$logPage = new LogPage( 'gblrights' );
		$out = $this->getOutput();
		$out->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract( $out, 'gblrights', $title->getPrefixedText() );
	}

	protected function getGroupName() {
		return 'wiki';
	}

	/**
	 * @phan-return never
	 * @return void
	 * @throws PermissionsError
	 */
	private function showNoPermissionsView() {
		throw new PermissionsError( 'globalgrouppermissions' );
	}
}
