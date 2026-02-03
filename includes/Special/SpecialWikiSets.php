<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\CentralAuth\CentralAuthWikiListService;
use MediaWiki\Extension\CentralAuth\WikiSet;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * Special page to allow to edit "wikisets" which are used to restrict
 * specific global group permissions to certain wikis.
 *
 * @ingroup Extensions
 */
class SpecialWikiSets extends SpecialPage {

	/** @var bool */
	private $mCanEdit;

	private CentralAuthWikiListService $wikiListService;

	public function __construct( CentralAuthWikiListService $wikiListService ) {
		parent::__construct( 'WikiSets' );

		$this->wikiListService = $wikiListService;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'centralauth-editset' );
	}

	/**
	 * @param string|null $subpage
	 * @return void
	 */
	public function execute( $subpage ) {
		$this->getOutput()->addModuleStyles( [
			'mediawiki.codex.messagebox.styles',
			'ext.centralauth.misc.styles',
		] );
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

			// Remove delete/ part
			$subpage = substr( $subpage, 7 );

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
				$this->getOutput()->setPageTitleMsg( $this->msg( 'errorpagetitle' ) );
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
	private function buildMainView( ?string $msg = null ) {
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
		foreach ( WikiSet::getAllWikiSets() as $set ) {
			$text = $this->msg( "centralauth-editset-item-{$msgPostfix}",
				$set->getName(), $set->getID() )->parse();
			$this->getOutput()->addHTML( "<li>{$text}</li>" );
		}

		if ( $this->mCanEdit ) {
			$target = $this->getPageTitle( '0' );
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
	 * @param string[]|null $wikis
	 * @param string|null $reason
	 */
	private function buildSetView(
		?WikiSet $set, $error = false, $name = null, $type = null, $wikis = null, $reason = null
	) {
		$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );

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

		$url = $this->getPageTitle( (string)( $set ? $set->getId() : 0 ) )
			->getLocalUrl();

		if ( $this->mCanEdit ) {
			// Give grep a chance to find the usages:
			// centralauth-editset-legend-edit, centralauth-editset-legend-new
			$legend = $this->msg(
				'centralauth-editset-legend-' . ( $set ? 'edit' : 'new' ),
				$name
			);
		} else {
			$legend = $this->msg( 'centralauth-editset-legend-view', $name );
		}

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
		foreach ( $this->getConfig()->get( MainConfigNames::LocalDatabases ) as $wiki ) {
			if ( !in_array( $wiki, $sortedWikis ) ) {
				$restWikis[] = $wiki;
			}
		}
		sort( $restWikis );

		if ( $this->mCanEdit ) {
			if ( $error ) {
				$this->getOutput()->addHTML( Html::errorBox( $error ) );
			}
			$formDescriptor = [
				'Name' => [
					'type' => 'text',
					'label-message' => 'centralauth-editset-name',
					'default' => $name,
				],
			];
			if ( $usage ) {
				$formDescriptor['Usage'] = [
					'type' => 'info',
					'label-message' => 'centralauth-editset-usage',
					'raw' => true,
					'default' => $usage,
				];
			}
			$formDescriptor += [
				'Type' => [
					'type' => 'select',
					'id' => 'set-type',
					'label-message' => 'centralauth-editset-type',
					'default' => $type,
					'options-messages' => [
						'centralauth-editset-optin' => WikiSet::OPTIN,
						'centralauth-editset-optout' => WikiSet::OPTOUT,
					],
				],
				'Wikis' => [
					'type' => 'textarea',
					'label-message' => 'centralauth-editset-wikis',
					'rows' => 5,
					'default' => $wikis,
				],
				'RestWikis' => [
					'type' => 'textarea',
					'label-message' => 'centralauth-editset-restwikis',
					'rows' => 5,
					'default' => implode( "\n", $restWikis ),
					'readonly' => true,
				],
				'Reason' => [
					'type' => 'text',
					'label-message' => 'centralauth-editset-reason',
					'default' => $reason ?? '',
					'size' => 50,
				],
			];

			HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
				->setSubmitText( $this->msg( 'centralauth-editset-submit' )->escaped() )
				->setWrapperLegend( $legend->text() )
				->setTitle( $this->getPageTitle( (string)( $set ? $set->getId() : 0 ) ) )
				->prepareForm()
				->displayForm( false );
		} else {
			$this->getOutput()->enableOOUI();

			$headerItems = [
				new \OOUI\FieldLayout(
					new \OOUI\LabelWidget( [
						'label' => $name,
					] ),
					[
						'label' => $this->msg( 'centralauth-editset-name' )->text(),
					]
				),
				new \OOUI\FieldLayout(
					new \OOUI\LabelWidget( [
						'label' => new \OOUI\HtmlSnippet( $usage ),
					] ),
					[
						'label' => $this->msg( 'centralauth-editset-usage' )->text(),
					]
				),
				new \OOUI\FieldLayout(
					new \OOUI\LabelWidget( [
						// Give grep a chance to find the usages: centralauth-editset-optin,
						// centralauth-editset-optout
						'label' => $this->msg( "centralauth-editset-$type" )->text(),
					] ),
					[
						'label' => $this->msg( 'centralauth-editset-type' )->text(),
					]
				),
			];
			$this->getOutput()->addHTML( new \OOUI\PanelLayout( [
				'expanded' => false,
				'padded' => true,
				'framed' => true,
				'content' => new \OOUI\FieldsetLayout( [
					'items' => $headerItems,
					'label' => $legend->text(),
				] )
			] ) );

			$wikiItems = [
				new \OOUI\FieldLayout(
					new \OOUI\LabelWidget( [
						'label' => new \OOUI\HtmlSnippet( self::buildWikiList( $sortedWikis ) ),
					] )
				),
			];
			$this->getOutput()->addHTML( new \OOUI\FieldsetLayout( [
				'items' => $wikiItems,
				'label' => $this->msg( 'centralauth-editset-wikis' )->text(),
			] ) );

			$restWikiItems = [
				new \OOUI\FieldLayout(
					new \OOUI\LabelWidget( [
						'label' => new \OOUI\HtmlSnippet( self::buildWikiList( $restWikis ) ),
					] )
				),
			];
			$this->getOutput()->addHTML( new \OOUI\FieldsetLayout( [
				'items' => $restWikiItems,
				'label' => $this->msg( 'centralauth-editset-restwikis' )->text(),
			] ) );
		}

		if ( $set ) {
			$this->showLogFragment( (string)$set->getId() );
		}
	}

	/**
	 * Builds a list of several columns with CSS. All items are escaped.
	 *
	 * @param string[] $list
	 */
	private function buildWikiList( array $list ): string {
		if ( $list === [] ) {
			return $this->msg( 'centralauth-editset-nowikis' )->parse();
		}

		$body = '';
		foreach ( $list as $listitem ) {
			$body .= Html::element( 'li', [], $listitem );
		}
		return Html::rawElement( 'ul', [ 'class' => 'mw-centralauth-wikisets-wikis' ], $body );
	}

	/**
	 * @param string $subpage
	 */
	private function buildDeleteView( $subpage ) {
		$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );

		$set = WikiSet::newFromID( $subpage );
		if ( !$set ) {
			$this->buildMainView( Html::errorBox( $this->msg( 'centralauth-editset-notfound', $subpage )->escaped() ) );
			return;
		}

		$formDescriptor = [
			'Reason' => [
				'type' => 'text',
				'label-message' => 'centralauth-editset-reason',
			]
		];

		HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setSubmitText( $this->msg( 'centralauth-editset-submit-delete' )->escaped() )
			->setSubmitDestructive()
			->setWrapperLegend( $this->msg( 'centralauth-editset-legend-delete', $set->getName() )->text() )
			->setTitle( $this->getPageTitle( 'delete/' . $subpage ) )
			->prepareForm()
			->displayForm( false );
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
			'/\W+/', $this->getRequest()->getVal( 'wpWikis' ), -1, PREG_SPLIT_NO_EMPTY )
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
		$title = $this->getPageTitle( (string)$set->getID() );
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
	 * @param string $setId
	 */
	private function doDelete( $setId ) {
		$set = WikiSet::newFromID( $setId );
		if ( !$set ) {
			$this->buildMainView( Html::errorBox( $this->msg( 'centralauth-editset-notfound', $setId )->escaped() ) );
			return;
		}

		$reason = $this->getRequest()->getVal( 'wpReason' );
		$name = $set->getName();
		$set->delete();

		$title = $this->getPageTitle( (string)$set->getID() );
		$this->addEntry( 'deleteset', $title, $reason, [ '4::name' => $name ] );

		$this->buildMainView( Html::successBox( $this->msg( 'centralauth-editset-success-delete' )->escaped() ) );
	}

	/**
	 * @param string $number
	 */
	protected function showLogFragment( $number ) {
		$title = $this->getPageTitle( $number );
		$logPage = new LogPage( 'gblrights' );
		$out = $this->getOutput();
		$out->addHTML( Html::element( 'h2', [], $logPage->getName()->text() . "\n" ) );
		LogEventsList::showLogExtract( $out, 'gblrights', $title->getPrefixedText() );
	}

	/** @inheritDoc */
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
