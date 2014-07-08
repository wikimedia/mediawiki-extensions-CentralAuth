<?php

/**
 * A no-op logger so we don't add local log entries
 * when doing a global user merge
 */
class UserMergeNoopLogger implements IUserMergeLogger {

	/**
	 * @param User $performer
	 * @param User $oldUser
	 * @param User $newUser
	 */
	public function addMergeEntry( User $performer, User $oldUser, User $newUser ) {
	}

	/**
	 * @param User $perfomer
	 * @param User $oldUser
	 */
	public function addDeleteEntry( User $perfomer, User $oldUser ) {
	}
}
