-- This patch adds an objectcache table to the centralauth database
-- used for locking and storing temp data related to renames. This table
-- should be identical to the table in core.
CREATE TABLE objectcache (
  keyname varbinary(255) NOT NULL default '' PRIMARY KEY,
  value mediumblob,
  exptime datetime
) /*$wgDBTableOptions*/;
CREATE INDEX exptime ON objectcache (exptime);
