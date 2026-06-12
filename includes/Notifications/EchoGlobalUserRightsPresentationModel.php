<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\CentralAuth\Notifications;

use MediaWiki\Extension\Notifications\DiscussionParser;
use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Language\RawMessage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * Formatter for 'centralauth-global-user-rights' notifications.
 */
class EchoGlobalUserRightsPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'user-rights';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		[ , $genderName ] = $this->getAgentForOutput();
		$viewingUser = $this->getViewingUserForGender();
		$add = array_map(
			[ $this->language, 'embedBidi' ],
			$this->getLocalizedGroupNames( $this->event->getExtraParam( 'add', [] ), $viewingUser )
		);
		$remove = array_map(
			[ $this->language, 'embedBidi' ],
			$this->getLocalizedGroupNames( $this->event->getExtraParam( 'remove', [] ), $viewingUser )
		);
		$expiryChanged = array_map(
			[ $this->language, 'embedBidi' ],
			$this->getLocalizedGroupNames( $this->event->getExtraParam( 'expiry-changed', [] ), $viewingUser )
		);

		if ( $expiryChanged ) {
			$msg = $this->msg( 'notification-header-centralauth-global-user-rights-expiry-change' );
			$msg->params( $genderName );
			$msg->params( $this->language->commaList( $expiryChanged ) );
			$msg->numParams( count( $expiryChanged ) );
			$msg->params( $viewingUser );
			return $msg;
		} elseif ( $add && !$remove ) {
			$msg = $this->msg( 'notification-header-centralauth-global-user-rights-add-only' );
			$msg->params( $genderName );
			$msg->params( $this->language->commaList( $add ) );
			$msg->numParams( count( $add ) );
			$msg->params( $viewingUser );
			return $msg;
		} elseif ( !$add && $remove ) {
			$msg = $this->msg( 'notification-header-centralauth-global-user-rights-remove-only' );
			$msg->params( $genderName );
			$msg->params( $this->language->commaList( $remove ) );
			$msg->numParams( count( $remove ) );
			$msg->params( $viewingUser );
			return $msg;
		} else {
			$msg = $this->msg( 'notification-header-centralauth-global-user-rights-add-and-remove' );
			$msg->params( $genderName );
			$msg->params( $this->language->commaList( $add ) );
			$msg->numParams( count( $add ) );
			$msg->params( $this->language->commaList( $remove ) );
			$msg->numParams( count( $remove ) );
			$msg->params( $viewingUser );
			return $msg;
		}
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$reason = $this->event->getExtraParam( 'reason' );
		if ( $reason ) {
			$text = DiscussionParser::getTextSnippet( $reason, $this->language );
			return new RawMessage( '$1', [ $text ] );
		}
		return false;
	}

	/**
	 * @param string[] $names
	 * @param string $genderName
	 * @return string[]
	 */
	private function getLocalizedGroupNames( array $names, string $genderName ): array {
		return array_map(
			fn ( $name ) => $this->language->getGroupMemberName( $name, $genderName ),
			array_values( $names )
		);
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		$addedGroups = array_values( $this->event->getExtraParam( 'add', [] ) );
		$removedGroups = array_values( $this->event->getExtraParam( 'remove', [] ) );
		if ( $addedGroups !== [] && $removedGroups === [] ) {
			$fragment = $addedGroups[0];
		} elseif ( $addedGroups === [] && $removedGroups !== [] ) {
			$fragment = $removedGroups[0];
		} else {
			$fragment = '';
		}

		return [
			'url' => SpecialPage::getTitleFor( 'GlobalGroupPermissions', false, $fragment )->getFullURL(),
			'label' => $this->msg( 'echo-learn-more' )->text(),
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		return [ $this->getAgentLink(), $this->getLogLink() ];
	}

	private function getLogLink(): array {
		$targetUser = $this->event->getExtraParam( 'target-user' );
		$query = [
			'type' => 'gblrights',
			'page' => Title::makeTitle( NS_USER, $targetUser )->getPrefixedText(),
			'user' => $this->event->getAgent()->getName(),
		];

		return [
			'label' => $this->msg( 'echo-log' )->text(),
			'url' => SpecialPage::getTitleFor( 'Log' )->getFullURL( $query ),
			'description' => '',
			'icon' => false,
			'prioritized' => true,
		];
	}

	/** @inheritDoc */
	protected function getSubjectMessageKey() {
		return 'notification-centralauth-global-user-rights-email-subject';
	}
}
