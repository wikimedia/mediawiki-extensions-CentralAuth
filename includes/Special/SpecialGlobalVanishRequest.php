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

use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\CentralAuth\Config\CAMainConfigNames;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameDenylist;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameFactory;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameJob\GlobalVanishJob;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequest;
use MediaWiki\Extension\CentralAuth\GlobalRename\GlobalRenameRequestStore;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Parser\Parser;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Request an account vanish.
 */
class SpecialGlobalVanishRequest extends FormSpecialPage {

	private LoggerInterface $logger;
	private GlobalRenameDenylist $globalRenameDenylist;
	private GlobalRenameRequestStore $globalRenameRequestStore;
	private GlobalRenameFactory $globalRenameFactory;
	private JobQueueGroupFactory $jobQueueGroupFactory;
	private HttpRequestFactory $httpRequestFactory;
	private UserIdentityLookup $userIdentityLookup;

	public function __construct(
		GlobalRenameDenylist $globalRenameDenylist,
		GlobalRenameRequestStore $globalRenameRequestStore,
		GlobalRenameFactory $globalRenameFactory,
		JobQueueGroupFactory $jobQueueGroupFactory,
		HttpRequestFactory $httpRequestFactory,
		UserIdentityLookup $userIdentityLookup
	) {
		parent::__construct( 'GlobalVanishRequest' );

		$this->logger = LoggerFactory::getInstance( 'CentralAuth' );
		$this->globalRenameDenylist = $globalRenameDenylist;
		$this->globalRenameRequestStore = $globalRenameRequestStore;
		$this->globalRenameFactory = $globalRenameFactory;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->userIdentityLookup = $userIdentityLookup;
	}

	/** @inheritDoc */
	public function onSubmit( array $data ): Status {
		$newUsername = $this->generateUsername();
		if ( !$newUsername ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-save-error' ) );
		}

		// Verify that the user is a global user.
		$causer = $this->getGlobalUser();
		if ( !$causer ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-globaluser-error' ) );
		}

		// Disallow for users that have blocks on any connected wikis.
		if ( $causer->isBlocked() ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-blocked-error' ) );
		}

		// Disallow duplicate rename / vanish requests.
		$username = $this->getUser()->getName();
		if ( $this->globalRenameRequestStore->currentNameHasPendingRequest( $username ) ) {
			return Status::newFatal( $this->msg( 'globalvanishrequest-pending-request-error' ) );
		}

		$request = $this->globalRenameRequestStore
			->newBlankRequest()
			->setName( $username )
			->setNewName( $newUsername )
			->setReason( $data['reason'] ?? null )
			->setType( GlobalRenameRequest::VANISH );

		$automaticVanishPerformerName = $this->getConfig()
			->get( CAMainConfigNames::CentralAuthAutomaticVanishPerformer );
		$automaticVanishPerformer = $automaticVanishPerformerName !== null
			? CentralAuthUser::getInstanceByName( $automaticVanishPerformerName )
			: null;
		$localAutomaticVanishPerformer = $this->userIdentityLookup
			->getUserIdentityByName( $automaticVanishPerformerName );

		// Immediately start the vanish if we already know that the user is
		// eligible for approval without a review.
		if (
			$automaticVanishPerformer !== null &&
			$automaticVanishPerformer->exists() &&
			$automaticVanishPerformer->isAttached() &&
			$localAutomaticVanishPerformer !== null &&
			$this->eligibleForAutomaticVanish()
		) {
			$request
				->setStatus( GlobalRenameRequest::APPROVED )
				->setPerformer( $automaticVanishPerformer->getId() )
				->setComments(
					$this->msg( 'globalvanishrequest-autoapprove-public-note' )
						->inContentLanguage()->text() );

			// Save the rename request to the queue before initiating the job.
			if ( !$this->globalRenameRequestStore->save( $request ) ) {
				return Status::newFatal( $this->msg( 'globalvanishrequest-save-error' ) );
			}

			// Determine which wiki to run the job on. If the config var is
			// defined then we use that wiki, otherwise the wiki that the
			// request is being made on is used as a fallback.
			$vanishWiki = $this->getConfig()
				->get( CAMainConfigNames::CentralAuthAutomaticVanishWiki )
					?? WikiMap::getCurrentWikiId();

			$this->jobQueueGroupFactory
				->makeJobQueueGroup( $vanishWiki )
				->push( GlobalVanishJob::newSpec( $request, $automaticVanishPerformerName ) );
		} else {
			// Save the request to the database for it to be processed later.
			if ( !$this->globalRenameRequestStore->save( $request ) ) {
				return Status::newFatal( $this->msg( 'globalvanishrequest-save-error' ) );
			}
		}

		return Status::newGood();
	}

	/** @inheritDoc */
	public function onSuccess(): void {
		$isVanished = $this->globalRenameRequestStore
			->currentNameHasApprovedVanish(
				$this->getUser()->getName(), IDBAccessObject::READ_LATEST );

		$destination = $isVanished ? 'vanished' : 'status';

		$this->getOutput()->redirect(
			$this->getPageTitle( $destination )->getFullURL(), '303'
		);
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->addHelpLink( 'Help:Extension:CentralAuth/Global Vanishing' );

		$out = $this->getOutput();

		if ( $subPage === 'vanished' ) {
			$out->setPageTitleMsg( $this->msg( 'globalvanishrequest-vanished-title' ) );
			$out->addWikiMsg( 'globalvanishrequest-vanished-text' );
			return;
		}

		$this->requireNamedUser();
		$username = $this->getUser()->getName();
		$hasPending = $this->globalRenameRequestStore->currentNameHasPendingRequest( $username );

		if ( $subPage === 'status' ) {
			if ( !$hasPending ) {
				$out->redirect( $this->getPageTitle()->getFullURL(), '303' );
				return;
			}

			$out->setPageTitleMsg( $this->msg( 'globalvanishrequest-status-title' ) );
			$out->addWikiMsg( 'globalvanishrequest-status-text' );
			return;
		}

		// Preemptively check if the user has any blocks, and if so prevent the
		// form from rendering and give them a link to appeal.
		$causer = $this->getGlobalUser();
		if ( $causer ) {
			$blockedWikiIds = [];
			foreach ( $causer->getBlocks() as $wikiId => $blocks ) {
				if ( count( $blocks ) > 0 ) {
					$blockedWikiIds[] = $wikiId;
				}
			}

			if ( count( $blockedWikiIds ) > 0 ) {
				$out->setPageTitleMsg( $this->msg( 'globalvanishrequest-blocked-title' ) );

				$sitelinks = $this->getUserBlockAppealSitelinks( $blockedWikiIds );
				if ( count( $sitelinks ) > 0 ) {
					$out->addWikiMsg( 'globalvanishrequest-blocked-text' );
				} else {
					$out->addWikiMsg( 'globalvanishrequest-blocked-text-minimal' );
				}

				// Create an unordered list of appeal links that are relevant to
				// the user. For each wiki that the user is blocked in, the
				// relevant appeal page on that wiki is added.
				$appealListItems = array_map(
					static fn ( $sitelink ) => Html::rawElement( 'li', [],
						Parser::stripOuterParagraph( $out->parseAsContent( $sitelink ) )
					),
					$sitelinks
				);
				$out->addHTML( Html::rawElement( 'ul', [], implode( '', $appealListItems ) ) );

				return;
			}
		}

		if ( $hasPending ) {
			$out = $this->getOutput();
			$out->redirect( $this->getPageTitle( 'status' )->getFullURL(), '303' );
			return;
		}

		$out->addModules( 'ext.centralauth.globalvanishrequest' );

		parent::execute( $subPage );
	}

	/** @inheritDoc */
	public function getFormFields(): array {
		return [
			'username' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'default'       => $this->getUser()->getName(),
				'label-message' => 'globalvanishrequest-username-label',
				'required'      => true,
				'type'          => 'text',
				'disabled'      => true,
			],
			'reason' => [
				'cssclass'      => 'mw-globalvanishrequest-field',
				'id'            => 'mw-vanishrequest-reason',
				'label-message' => 'globalvanishrequest-reason-label',
				'name'          => 'reason',
				'rows'          => 3,
				'type'          => 'textarea',
			],
		];
	}

	/** @inheritDoc */
	public function doesWrites(): bool {
		return true;
	}

	/**
	 * Blocked users should not be able to request a vanish.
	 */
	public function requiresUnblock(): bool {
		return true;
	}

	/** @inheritDoc */
	public function userCanExecute( User $user ): bool {
		return $this->globalRenameDenylist->checkUser( $user->getName() );
	}

	/** @inheritDoc */
	public function displayRestrictionError(): void {
		throw new PermissionsError( null, [ 'centralauth-badaccess-blacklisted' ] );
	}

	/** @inheritDoc */
	protected function alterForm( HTMLForm $form ): void {
		$form
			->setSubmitTextMsg( 'globalvanishrequest-submit-text' )
			->setSubmitID( 'mw-vanishrequest-submit' );
	}

	/** @inheritDoc */
	protected function getDisplayFormat(): string {
		return 'ooui';
	}

	/** @inheritDoc */
	protected function preHtml(): string {
		return $this->msg( 'globalvanishrequest-pretext' )->parse();
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'login';
	}

	/**
	 * Return the global user if the authenticated user has a global account.
	 * @return CentralAuthUser|false
	 */
	private function getGlobalUser() {
		$user = $this->getUser();
		$causer = CentralAuthUser::getInstance( $user );

		if ( $causer->exists() && $causer->isAttached() ) {
			return $causer;
		}
		return false;
	}

	/**
	 * Generate a random username that the user requesting a vanish would be
	 * renamed to if the request is accepted.
	 *
	 * @return string|false contains a string if successful
	 */
	private function generateUsername() {
		$attempts = 0;

		do {
			$random = wfRandomString();
			$candidate = "Renamed user {$random}";
			if ( GlobalRenameRequest::isNameAvailable( $candidate, IDBAccessObject::READ_NORMAL )->isOK() ) {
				return $candidate;
			}
			$attempts++;
		} while ( $attempts < 5 );

		return false;
	}

	/**
	 * Checks if the currently authenticated user is eligible for automatic vanishing.
	 */
	private function eligibleForAutomaticVanish(): bool {
		$causer = $this->getGlobalUser();
		if ( !$causer ) {
			return false;
		}

		return $causer->getGlobalEditCount() === 0 &&
			!$causer->isBlocked() &&
			!$causer->hasPublicLogs();
	}

	/**
	 * Retrieve the block appeal links most relevant for the user.
	 *
	 * For each wiki the user is blocked on, the appeal link for that wiki will
	 * be returned. If that page is not available, then fallback pages will be
	 * attempted as well if configured.
	 *
	 * @param array $wikiIds a list of wikis to fetch links from
	 * @return array sitelinks to the most relevant appeal pages for the user
	 */
	private function getUserBlockAppealSitelinks( array $wikiIds ): array {
		$sitelinks = [];

		$entityIds = $this->getConfig()->get( CAMainConfigNames::CentralAuthBlockAppealWikidataIds );
		if ( $entityIds !== null && count( $entityIds ) > 0 ) {
			// Fetch block appeal and block policy pages from the Wikidata API.
			$parameters = [
				"action" => "wbgetentities",
				"format" => "json",
				"ids" => implode( '|', $entityIds ),
				"props" => "sitelinks|sitelinks/urls",
				"formatversion" => "2",
			];
			$wikidataResult = $this->queryWikidata( $parameters );
			if ( !$wikidataResult->isGood() ) {
				return [];
			}
			$wikidataResponse = $wikidataResult->getValue();
			$entities = $wikidataResponse['entities'];

			// Iterate through every wiki with blocks and find the most
			// relevant page for appealing blocks on an account.
			foreach ( $wikiIds as $wikiId ) {
				foreach ( $entityIds as $entityId ) {
					if ( isset( $entities[$entityId]['sitelinks'][$wikiId]['url'] ) ) {
						$sitelink = $entities[$entityId]['sitelinks'][$wikiId];
						$sitelinks[] = "[{$sitelink['url']} {$sitelink['title']}]";
						break;
					}
				}
			}
		}

		// Fallback to showing a fallback URL (if configured) in the event that
		// no appeal links were able to be found from the Wikidata API.
		if ( count( $sitelinks ) === 0 ) {
			$appealUrl = $this->getConfig()->get( CAMainConfigNames::CentralAuthFallbackAppealUrl );
			$appealTitle = $this->getConfig()->get( CAMainConfigNames::CentralAuthFallbackAppealTitle );

			if ( $appealUrl !== null && $appealTitle !== null ) {
				$sitelinks[] = "[{$appealUrl} {$appealTitle}]";
			}
		}

		return $sitelinks;
	}

	/**
	 * Retrieve entity data from the Wikidata API.
	 *
	 * @param array $parameters
	 * @return Status
	 */
	private function queryWikidata( array $parameters ): Status {
		$options = [
			'method' => 'GET',
			'userAgent' => "{$this->httpRequestFactory->getUserAgent()} CentralAuth",
		];
		$url = $this->getConfig()->get( CAMainConfigNames::CentralAuthWikidataApiUrl );
		if ( $url === null ) {
			return Status::newFatal(
				'Cannot make Wikidata request for entities as $wgCentralAuthWikidataApiUrl is unset.'
			);
		}
		$encodedParameters = wfArrayToCgi( $parameters );
		$request = $this->httpRequestFactory->create( "{$url}?{$encodedParameters}", $options, __METHOD__ );

		$httpResult = $request->execute();
		if ( $httpResult->isOK() ) {
			$httpResult->merge( FormatJson::parse( $request->getContent(), FormatJson::FORCE_ASSOC ), true );
		}

		[ $errorsOnlyStatus, $warningsOnlyStatus ] = $httpResult->splitByErrorType();
		if ( !$warningsOnlyStatus->isGood() ) {
			LoggerFactory::getInstance( 'CentralAuth' )->warning(
				$warningsOnlyStatus->getWikiText( false, false, 'en' ),
				[ 'exception' => new RuntimeException ]
			);
		}
		return $errorsOnlyStatus;
	}
}
