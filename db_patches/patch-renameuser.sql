CREATE TABLE renameuser_status (
	-- Old name being renamed from
	ru_oldname varchar(255) binary not null,
	-- New name being renamed to
	ru_newname varchar(255) binary not null,
	-- WikiID
	ru_wiki varchar(255) binary not null,
	-- current state of the renaming
	ru_status enum ('queued', 'inprogress', 'failed'),

	UNIQUE KEY `ru_oldname` (`ru_oldname`, `ru_wiki`)
) /*$wgDBTableOptions*/;
