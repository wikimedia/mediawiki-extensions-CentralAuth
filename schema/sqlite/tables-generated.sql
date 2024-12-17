-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: schema/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/globalnames (
  gn_name BLOB NOT NULL,
  PRIMARY KEY(gn_name)
);


CREATE TABLE /*_*/localnames (
  ln_wiki BLOB NOT NULL,
  ln_name BLOB NOT NULL,
  PRIMARY KEY(ln_wiki, ln_name)
);

CREATE INDEX ln_name_wiki ON /*_*/localnames (ln_name, ln_wiki);


CREATE TABLE /*_*/globaluser (
  gu_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  gu_name BLOB DEFAULT NULL, gu_home_db BLOB DEFAULT NULL,
  gu_email BLOB DEFAULT NULL, gu_email_authenticated BLOB DEFAULT NULL,
  gu_password BLOB DEFAULT NULL, gu_locked SMALLINT DEFAULT 0 NOT NULL,
  gu_hidden_level INTEGER DEFAULT 0 NOT NULL,
  gu_registration BLOB DEFAULT NULL,
  gu_password_reset_key BLOB DEFAULT NULL,
  gu_password_reset_expiration BLOB DEFAULT NULL,
  gu_auth_token BLOB DEFAULT NULL, gu_cas_token INTEGER UNSIGNED DEFAULT 1 NOT NULL
);

CREATE UNIQUE INDEX gu_name ON /*_*/globaluser (gu_name);

CREATE INDEX gu_email ON /*_*/globaluser (gu_email);

CREATE INDEX gu_locked ON /*_*/globaluser (gu_name, gu_locked);

CREATE INDEX gu_hidden_level ON /*_*/globaluser (gu_name, gu_hidden_level);


CREATE TABLE /*_*/localuser (
  lu_wiki BLOB NOT NULL,
  lu_name BLOB NOT NULL,
  lu_attached_timestamp BLOB DEFAULT NULL,
  lu_attached_method TEXT DEFAULT NULL,
  lu_attachment_method SMALLINT UNSIGNED DEFAULT NULL,
  lu_local_id INTEGER UNSIGNED DEFAULT NULL,
  lu_global_id INTEGER UNSIGNED DEFAULT NULL,
  PRIMARY KEY(lu_wiki, lu_name)
);

CREATE INDEX lu_name_wiki ON /*_*/localuser (lu_name, lu_wiki);


CREATE TABLE /*_*/global_user_groups (
  gug_user INTEGER NOT NULL,
  gug_group VARCHAR(255) NOT NULL,
  gug_expiry BLOB DEFAULT NULL,
  PRIMARY KEY(gug_user, gug_group)
);

CREATE INDEX gug_group ON /*_*/global_user_groups (gug_group);

CREATE INDEX gug_expiry ON /*_*/global_user_groups (gug_expiry);


CREATE TABLE /*_*/global_group_permissions (
  ggp_group VARCHAR(255) NOT NULL,
  ggp_permission VARCHAR(255) NOT NULL,
  PRIMARY KEY(ggp_group, ggp_permission)
);

CREATE INDEX ggp_permission ON /*_*/global_group_permissions (ggp_permission);


CREATE TABLE /*_*/wikiset (
  ws_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  ws_name VARCHAR(255) NOT NULL,
  ws_type TEXT DEFAULT NULL,
  ws_wikis BLOB NOT NULL
);

CREATE UNIQUE INDEX ws_name ON /*_*/wikiset (ws_name);


CREATE TABLE /*_*/global_group_restrictions (
  ggr_group VARCHAR(255) NOT NULL,
  ggr_set INTEGER NOT NULL,
  PRIMARY KEY(ggr_group)
);

CREATE INDEX ggr_set ON /*_*/global_group_restrictions (ggr_set);


CREATE TABLE /*_*/renameuser_status (
  ru_oldname BLOB NOT NULL,
  ru_wiki BLOB NOT NULL,
  ru_newname BLOB NOT NULL,
  ru_status TEXT DEFAULT NULL,
  PRIMARY KEY(ru_oldname, ru_wiki)
);


CREATE TABLE /*_*/renameuser_queue (
  rq_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  rq_name BLOB NOT NULL, rq_wiki BLOB DEFAULT NULL,
  rq_newname BLOB NOT NULL, rq_reason BLOB DEFAULT NULL,
  rq_requested_ts BLOB DEFAULT NULL,
  rq_status TEXT NOT NULL, rq_completed_ts BLOB DEFAULT NULL,
  rq_deleted SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
  rq_performer INTEGER DEFAULT NULL,
  rq_comments BLOB DEFAULT NULL, rq_type SMALLINT UNSIGNED DEFAULT 0 NOT NULL
);

CREATE INDEX rq_oldstatus ON /*_*/renameuser_queue (rq_name, rq_wiki, rq_status);

CREATE INDEX rq_newstatus ON /*_*/renameuser_queue (rq_newname, rq_status);

CREATE INDEX rq_requested_ts ON /*_*/renameuser_queue (rq_requested_ts);


CREATE TABLE /*_*/users_to_rename (
  utr_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  utr_name BLOB NOT NULL, utr_wiki BLOB NOT NULL,
  utr_status INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX utr_user ON /*_*/users_to_rename (utr_name, utr_wiki);

CREATE INDEX utr_notif ON /*_*/users_to_rename (utr_status);

CREATE INDEX utr_wiki ON /*_*/users_to_rename (utr_wiki);


CREATE TABLE /*_*/global_edit_count (
  gec_user INTEGER NOT NULL,
  gec_count INTEGER NOT NULL,
  PRIMARY KEY(gec_user)
);


CREATE TABLE /*_*/global_user_autocreate_serial (
  uas_shard INTEGER UNSIGNED NOT NULL,
  uas_year SMALLINT UNSIGNED NOT NULL,
  uas_value INTEGER UNSIGNED NOT NULL,
  PRIMARY KEY(uas_shard, uas_year)
);
