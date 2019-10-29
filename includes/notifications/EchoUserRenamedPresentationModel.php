<?php

use EchoEventPresentationModel;

class EchoUserRenamedPresentationModel extends EchoEventPresentationModel {

    /**
     * @inheritDoc
     */
    public function getIconType() {
        return 'placeholder';
    }

    /**
     * @inheritDoc
     */
    public function getHeaderMessage() {
        return $this->msg( $this->event->getExtraParam( 'approved', true ) ?
            'globalrenamequeue-email-subject-approved' :
            'globalrenamequeue-email-subject-rejected' );
    }

    /**
     * @inheritDoc
     */
    public function getBodyMessage() {
        $comment = $this->event->getExtraParam( 'comment' );
        $newUser = User::newFromId( $this->event->getExtraParam( 'newuser' ) );
        $oldUser = User::newFromId( $this->event->getExtraParam( 'olduser' ) );
        if ( $comment ) {
            $msgName = $this->event->getExtraParam( 'approved', true ) ?
            'globalrenamequeue-email-body-approved-with-note' :
            'globalrenamequeue-email-body-rejected';
        } else {
            $msgName = $this->event->getExtraParam( 'approved', true ) ?
            'globalrenamequeue-email-body-approved' :
            'globalrenamequeue-email-body-rejected';
        }
        return $this->msg( $msgName )
            ->params(
                $oldUser->getName(),
                $newUser->getName(),
                $comment
            );
    }
}