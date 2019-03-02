<?php

use MediaWiki\Logger\LoggerFactory;

class SpecialGlobalRenameProgress extends FormSpecialPage {
	/**
	 * @var GlobalRenameUserStatus
	 */
	private $renameuserStatus;

	public function __construct() {
		parent::__construct( 'GlobalRenameProgress' );
	}

	function getFormFields() {
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

	function alterForm( HTMLForm $htmlForm ) {
		$htmlForm
			->setMethod( 'get' )
			->setAction( $this->getPageTitle()->getLocalURL() )
			->setSubmitText( $this->msg( 'centralauth-rename-viewprogress' )->text() )
			->setWrapperLegendMsg( 'globalrenameprogress-legend' );
	}

	function showLogExtract( $name ) {
		$caTitle = Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $name );
		$out = $this->getOutput();
		$logs = '';
		LogEventsList::showLogExtract( $logs, 'gblrename', $caTitle, '', [
			'showIfEmpty' => true,
		] );

		$formDescriptor = [
			'logs' => [
				'type' => 'info',
				'raw' => true,
				'default' => $logs,
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->suppressDefaultSubmit()
			->setWrapperLegendMsg( 'centralauth-rename-progress-logs-fieldset' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * There's a race condition of some kind in cache purging (T94491),
	 * so see if the cache still thinks they're being renamed and purge
	 * it if it's wrong
	 *
	 * @param string $name
	 */
	function checkCachePurge( $name ) {
		$ca = CentralAuthUser::getInstanceByName( $name );
		if ( $ca->renameInProgress() ) {
			$ca->quickInvalidateCache();
		}
	}

	function showCurrentRenames() {
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

	function onSubmit( array $data ) {
		if ( !isset( $data['username'] ) ) {
			$this->showCurrentRenames();
			return false;
		}
		$name = User::getCanonicalName( $data['username'], 'usable' );
		if ( !$name ) {
			$this->showCurrentRenames();
			return false;
		}

		$out = $this->getOutput();
		$out->addBacklinkSubtitle( $this->getPageTitle() );

		$this->renameuserStatus = new GlobalRenameUserStatus( $name );
		$names = $this->renameuserStatus->getNames();
		if ( !$names ) {
			$this->checkCachePurge( $name );
			$out->addWikiMsg( 'centralauth-rename-notinprogress', $name );
			$this->getForm()->prepareForm()->displayForm( false );
			$this->showLogExtract( $name );
			return true;
		}

		list( $oldName, $newName ) = $names;

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
		foreach ( $statuses as $wiki => $status ) {
			$wikiReference = WikiMap::getWiki( $wiki );
			if ( !$wikiReference ) {
				LoggerFactory::getInstance( 'CentralAuth' )->warning( __METHOD__ .
					': invalid wiki: ' . $wiki );
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

		$this->showLogExtract( $newName );
		$out->addHTML( $fieldset );
		return true;
	}

	public function requiresWrite() {
		return false;
	}

	public function requiresUnblock() {
		return false;
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}
}
