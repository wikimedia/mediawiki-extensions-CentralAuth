<?php

interface IGlobalRenameUserLogger {

	/**
	 * @param string $oldName
	 * @param string $newName
	 * @param string $reason
	 */
	public function log( $oldName, $newName, $reason );
}
