-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/CentralAuth/schema/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/globalnames (
  gn_name VARBINARY(255) NOT NULL,
  PRIMARY KEY(gn_name)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/localnames (
  ln_wiki VARBINARY(255) NOT NULL,
  ln_name VARBINARY(255) NOT NULL,
  INDEX ln_name_wiki (ln_name, ln_wiki),
  PRIMARY KEY(ln_wiki, ln_name)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/globaluser (
  gu_id INT AUTO_INCREMENT NOT NULL,
  gu_name VARBINARY(255) DEFAULT NULL,
  gu_home_db VARBINARY(255) DEFAULT NULL,
  gu_email VARBINARY(255) DEFAULT NULL,
  gu_email_authenticated BINARY(14) DEFAULT NULL,
  gu_salt VARBINARY(16) DEFAULT NULL,
  gu_password TINYBLOB DEFAULT NULL,
  gu_locked TINYINT(1) DEFAULT 0 NOT NULL,
  gu_hidden_level INT DEFAULT 0 NOT NULL,
  gu_registration BINARY(14) DEFAULT NULL,
  gu_password_reset_key TINYBLOB DEFAULT NULL,
  gu_password_reset_expiration BINARY(14) DEFAULT NULL,
  gu_auth_token VARBINARY(32) DEFAULT NULL,
  gu_cas_token INT UNSIGNED DEFAULT 1 NOT NULL,
  UNIQUE INDEX gu_name (gu_name),
  INDEX gu_email (gu_email),
  INDEX gu_locked (
    gu_name(255),
    gu_locked
  ),
  INDEX gu_hidden_level (
    gu_name(255),
    gu_hidden_level
  ),
  PRIMARY KEY(gu_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/localuser (
  lu_wiki VARBINARY(255) NOT NULL,
  lu_name VARBINARY(255) NOT NULL,
  lu_attached_timestamp BINARY(14) DEFAULT NULL,
  lu_attached_method ENUM(
    'primary', 'empty', 'mail', 'password',
    'admin', 'new', 'login'
  ) DEFAULT NULL,
  lu_attachment_method TINYINT UNSIGNED DEFAULT NULL,
  lu_local_id INT UNSIGNED DEFAULT NULL,
  lu_global_id INT UNSIGNED DEFAULT NULL,
  INDEX lu_name_wiki (lu_name, lu_wiki),
  PRIMARY KEY(lu_wiki, lu_name)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/global_user_groups (
  gug_user INT NOT NULL,
  gug_group VARCHAR(255) NOT NULL,
  gug_expiry VARBINARY(14) DEFAULT NULL,
  INDEX gug_group (gug_group),
  INDEX gug_expiry (gug_expiry),
  PRIMARY KEY(gug_user, gug_group)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/global_group_permissions (
  ggp_group VARCHAR(255) NOT NULL,
  ggp_permission VARCHAR(255) NOT NULL,
  INDEX ggp_permission (ggp_permission),
  PRIMARY KEY(ggp_group, ggp_permission)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/wikiset (
  ws_id INT AUTO_INCREMENT NOT NULL,
  ws_name VARCHAR(255) NOT NULL,
  ws_type ENUM('optin', 'optout') DEFAULT NULL,
  ws_wikis BLOB NOT NULL,
  UNIQUE INDEX ws_name (ws_name),
  PRIMARY KEY(ws_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/global_group_restrictions (
  ggr_group VARCHAR(255) NOT NULL,
  ggr_set INT NOT NULL,
  INDEX ggr_set (ggr_set),
  PRIMARY KEY(ggr_group)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/renameuser_status (
  ru_oldname VARBINARY(255) NOT NULL,
  ru_newname VARBINARY(255) NOT NULL,
  ru_wiki VARBINARY(255) NOT NULL,
  ru_status ENUM('queued', 'inprogress', 'failed') DEFAULT NULL,
  UNIQUE INDEX ru_oldname (ru_oldname, ru_wiki)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/renameuser_queue (
  rq_id INT AUTO_INCREMENT NOT NULL,
  rq_name VARBINARY(255) NOT NULL,
  rq_wiki VARBINARY(255) DEFAULT NULL,
  rq_newname VARBINARY(255) NOT NULL,
  rq_reason BLOB DEFAULT NULL,
  rq_requested_ts BINARY(14) DEFAULT NULL,
  rq_status ENUM(
    'pending', 'approved', 'rejected'
  ) NOT NULL,
  rq_completed_ts BINARY(14) DEFAULT NULL,
  rq_deleted TINYINT UNSIGNED DEFAULT 0 NOT NULL,
  rq_performer INT DEFAULT NULL,
  rq_comments BLOB DEFAULT NULL,
  INDEX rq_oldstatus (rq_name, rq_wiki, rq_status),
  INDEX rq_newstatus (rq_newname, rq_status),
  INDEX rq_requested_ts (rq_requested_ts),
  PRIMARY KEY(rq_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/users_to_rename (
  utr_id INT AUTO_INCREMENT NOT NULL,
  utr_name VARBINARY(255) NOT NULL,
  utr_wiki VARBINARY(255) NOT NULL,
  utr_status INT DEFAULT 0,
  UNIQUE INDEX utr_user (utr_name, utr_wiki),
  INDEX utr_notif (utr_status),
  INDEX utr_wiki (utr_wiki),
  PRIMARY KEY(utr_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/global_edit_count (
  gec_user INT NOT NULL,
  gec_count INT NOT NULL,
  PRIMARY KEY(gec_user)
) /*$wgDBTableOptions*/;
