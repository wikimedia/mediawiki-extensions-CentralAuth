<?php

namespace MediaWiki\Extension\CentralAuth\Special;

use MediaWiki\Extension\CentralAuth\CentralAuthUIService;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameUserStatus;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\User\UserNameUtils;
use MediaWiki\WikiMap\WikiMap;
use MediaWiki\Xml\Xml;

class SpecialGlobalRenameProgress extends FormSpecialPage {

	private UserNameUtils $userNameUtils;
	private CentralAuthUIService $uiService;
	private GlobalRenameFactory $globalRenameFactory;

	/**
	 * @var GlobalRenameUserStatus
	 */
	private $renameuserStatus;

	public function __construct(
		UserNameUtils $userNameUtils,
		CentralAuthUIService $uiService,
		GlobalRenameFactory $globalRenameFactory
	) {
		parent::__construct( 'GlobalRenameProgress' );
		$this->userNameUtils = $userNameUtils;
		$this->uiService = $uiService;
		$this->globalRenameFactory = $globalRenameFactory;
	}

	/** @inheritDoc */
	public function getFormFields() {
		return [
			'username' => [
				'id' => 'mw-renameprogress-username',
				'label-message' => 'centralauth-rename-progress-username',
				'type' => 'text',
				'name' => 'username',
				'default' => $this->getRequest()->getVal( 'username', $this->par ),
			]
		];
	}

	/** @inheritDoc */
	public function alterForm( HTMLForm $htmlForm ) {
		$this->addHelpLink( 'Extension:CentralAuth' );
		$htmlForm
			->setMethod( 'get' )
			->setTitle( $this->getPageTitle() )
			->setSubmitText( $this->msg( 'centralauth-rename-viewprogress' )->text() )
			->setWrapperLegendMsg( 'globalrenameprogress-legend' );
	}

	/**
	 * There's a race condition of some kind in cache purging (T94491),
	 * so see if the cache still thinks they're being renamed and purge
	 * it if it's wrong
	 *
	 * @param string $name
	 */
	public function checkCachePurge( $name ) {
		$ca = CentralAuthUser::getInstanceByName( $name );
		if ( $ca->renameInProgress() ) {
			$ca->quickInvalidateCache();
		}
	}

	private function showCurrentRenames() {
		$renames = GlobalRenameUserStatus::getInProgressRenames( $this->getUser() );

		if ( !$renames ) {
			return;
		}

		$html = "<ul>\n";
		foreach ( $renames as $oldname => $newname ) {
			$html .= '<li>' .
				$this->msg( 'centralauth-rename-progress-item' )
					->params( $oldname, $newname )->parse() .
				"</li>\n";
		}

		$html .= "</ul>\n";
		$html = $this->msg( 'centralauth-rename-progress-list-header' )->escaped() . $html;
		$this->getOutput()->addHTML( $html );
	}

	/** @inheritDoc */
	public function onSubmit( array $data ) {
		if ( !isset( $data['username'] ) ) {
			$this->showCurrentRenames();
			return false;
		}
		$name = $this->userNameUtils->getCanonical( $data['username'], UserNameUtils::RIGOR_USABLE );
		if ( !$name ) {
			$this->showCurrentRenames();
			return false;
		}

		$out = $this->getOutput();
		$out->addBacklinkSubtitle( $this->getPageTitle() );

		$this->renameuserStatus = $this->globalRenameFactory->newGlobalRenameUserStatus( $name );
		$names = $this->renameuserStatus->getNames();
		if ( !$names ) {
			$this->checkCachePurge( $name );
			$out->addWikiMsg( 'centralauth-rename-notinprogress', $name );
			$this->getForm()->prepareForm()->displayForm( false );
			$this->uiService->showRenameLogExtract( $this->getContext(), $name );
			return true;
		}

		[ $oldName, $newName ] = $names;

		$statuses = $this->renameuserStatus->getStatuses();

		$this->getForm()->prepareForm()->displayForm( false );
		// $newname will always be defined since we check
		// for 0 result rows above
		$caUser = CentralAuthUser::getInstanceByName( $newName );
		$attached = $caUser->listAttached();
		foreach ( $attached as $wiki ) {
			// If it's not in the db table, and there is
			// an attached acount, assume it's done.
			if ( !isset( $statuses[$wiki] ) ) {
				$statuses[$wiki] = 'done';
			}
		}
		ksort( $statuses );
		$table = Html::openElement( 'table', [ 'class' => 'wikitable sortable' ] );
		$table .= Html::openElement( 'thead' );
		$table .= Html::openElement( 'tr' );
		$table .= Html::element( 'th', [],
			$this->msg( 'centralauth-rename-table-domain' )->text() );
		$table .= Html::element( 'th', [],
			$this->msg( 'centralauth-rename-table-status' )->text() );
		$table .= Html::closeElement( 'tr' );
		$table .= Html::closeElement( 'thead' );
		$table .= Html::openElement( 'tbody' );
		$logger = LoggerFactory::getInstance( 'CentralAuth' );
		foreach ( $statuses as $wiki => $status ) {
			$wikiReference = WikiMap::getWiki( $wiki );
			if ( !$wikiReference ) {
				$logger->warning( __METHOD__ . ': invalid wiki: ' . $wiki );
				continue;
			}

			$table .= Html::openElement( 'tr' );
			$table .= Html::element( 'td', [], $wikiReference->getDisplayName() );
			// Messages used: centralauth-rename-table-status-inprogress
			// centralauth-rename-table-status-queued, centralauth-rename-table-status-done
			$table .= Html::rawElement( 'td', [],
				$this->msg( "centralauth-rename-table-status-$status" )->parse() );
			$table .= Html::closeElement( 'tr' );
		}
		$table .= Html::closeElement( 'tbody' );
		$table .= Html::closeElement( 'table' );
		$fieldset = Xml::fieldset(
			$this->msg( 'centralauth-rename-progress-fieldset' )->text(), $table );

		$this->uiService->showRenameLogExtract( $this->getContext(), $newName );
		$out->addHTML( $fieldset );
		$out->addModuleStyles( 'jquery.tablesorter.styles' );
		$out->addModules( 'jquery.tablesorter' );
		return true;
	}

	/** @inheritDoc */
	public function requiresWrite() {
		return false;
	}

	/** @inheritDoc */
	public function requiresUnblock() {
		return false;
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}
}
