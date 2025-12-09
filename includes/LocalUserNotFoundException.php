<?php
/**
 * @section LICENSE
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\CentralAuth;

use Exception;
use Wikimedia\NormalizedException\INormalizedException;
use Wikimedia\NormalizedException\NormalizedExceptionTrait;

/**
 * @copyright © 2016 Wikimedia Foundation and contributors
 */
class LocalUserNotFoundException extends Exception implements INormalizedException {
	use NormalizedExceptionTrait;

	public function __construct(
		string $normalizedMessage = '',
		array $messageContext = []
	) {
		$this->normalizedMessage = $normalizedMessage;
		$this->messageContext = $messageContext;
		parent::__construct( self::getMessageFromNormalizedMessage( $normalizedMessage, $messageContext ) );
	}
}
