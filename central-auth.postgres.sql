-- PostgreSQL schema for the CentralAuth extension
-- Please refer to the MySQL version for field documentation etc.

-- IMPORTANT: If you want to run the AntiSpoof extension with
-- CentralAuth, you must run patch-antispoof-global.postgres.sql,
-- located in the AntiSpoof folder of this extension.

CREATE TABLE globalnames (
  gn_name TEXT NOT NULL PRIMARY KEY
);

CREATE TABLE localnames (
  ln_wiki TEXT NOT NULL,
  ln_name TEXT NOT NULL,
  PRIMARY KEY (ln_wiki, ln_name)
);

CREATE INDEX ln_name_wiki ON localnames (ln_name, ln_wiki);

DROP SEQUENCE IF EXISTS globaluser_gu_id_seq CASCADE;
CREATE SEQUENCE globaluser_gu_id_seq;

CREATE TYPE gu_enabled_method AS ENUM('opt-in', 'batch', 'auto', 'admin');

CREATE TABLE globaluser (
  gu_id INTEGER PRIMARY KEY DEFAULT nextval('globaluser_gu_id_seq'),
  gu_name TEXT,
  -- TODO FIXME/CHECKME: these two fields sound like legacy fields and nothing
  -- except one test appears to be using 'em.
  gu_enabled TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  gu_enabled_method gu_enabled_method DEFAULT NULL,
  gu_home_db TEXT,
  gu_email TEXT,
  gu_email_authenticated TIMESTAMPTZ,
  gu_salt TEXT,
  gu_password TEXT,
  gu_locked SMALLINT NOT NULL DEFAULT 0,
  gu_hidden TEXT NOT NULL DEFAULT '',
  gu_registration TIMESTAMPTZ,
  -- TODO FIXME/CHECKME: Are the next two fields used? Quick codesearch on 19 February 2020
  -- suggests they aren't, in which case they should be dropped (also from the MySQL schema)
  gu_password_reset_key TEXT,
  gu_password_reset_expiration TIMESTAMPTZ,
  gu_auth_token TEXT NULL,
  gu_cas_token INTEGER NOT NULL default 1
);

ALTER SEQUENCE globaluser_gu_id_seq OWNED BY globaluser.gu_id;

CREATE UNIQUE INDEX gu_name ON globaluser (gu_name);
CREATE INDEX gu_email ON globaluser (gu_email);
CREATE INDEX gu_locked ON globaluser (gu_name, gu_locked);
CREATE INDEX gu_hidden ON globaluser (gu_name, gu_hidden);

CREATE TYPE lu_attached_method AS ENUM(
    'primary',
    'empty',
    'mail',
    'password',
    'admin',
    'new',
    'login'
);

CREATE TABLE localuser (
  lu_wiki TEXT NOT NULL,
  lu_name TEXT NOT NULL,
  lu_attached_timestamp TIMESTAMPTZ,
  lu_attached_method lu_attached_method,
  lu_local_id INTEGER DEFAULT NULL,
  lu_global_id INTEGER DEFAULT NULL,

  PRIMARY KEY (lu_wiki, lu_name)
);

CREATE INDEX lu_name_wiki ON localuser (lu_name, lu_wiki);

-- Global user groups.
CREATE TABLE global_user_groups (
  gug_user INTEGER NOT NULL,
  gug_group TEXT NOT NULL,
  PRIMARY KEY (gug_user, gug_group)
);

CREATE INDEX gug_user ON global_user_groups (gug_user);
CREATE INDEX gug_group ON global_user_groups (gug_group);

CREATE TABLE global_group_permissions (
  ggp_group TEXT NOT NULL,
  ggp_permission TEXT NOT NULL,
  PRIMARY KEY (ggp_group, ggp_permission)
);

CREATE INDEX ggp_group ON global_group_permissions (ggp_group);
CREATE INDEX ggp_permission ON global_group_permissions (ggp_permission);

DROP SEQUENCE IF EXISTS wikiset_ws_id_seq CASCADE;
CREATE SEQUENCE wikiset_ws_id_seq;

CREATE TYPE ws_type AS ENUM('optin', 'optout');

CREATE TABLE wikiset (
  ws_id INTEGER PRIMARY KEY DEFAULT nextval('wikiset_ws_id_seq'),
  ws_name TEXT NOT NULL,
  ws_type ws_type,
  ws_wikis TEXT NOT NULL
);

ALTER SEQUENCE wikiset_ws_id_seq OWNED BY wikiset.ws_id;

CREATE UNIQUE INDEX ws_name ON wikiset (ws_name);

CREATE TABLE global_group_restrictions (
  ggr_group TEXT NOT NULL PRIMARY KEY,
  ggr_set INTEGER NOT NULL
);

CREATE INDEX ggr_set ON global_group_restrictions (ggr_set);

CREATE TYPE ru_status AS ENUM('queued', 'inprogress', 'failed');

CREATE TABLE renameuser_status (
  ru_oldname TEXT NOT NULL,
  ru_newname TEXT NOT NULL,
  ru_wiki TEXT NOT NULL,
  ru_status ru_status
);

CREATE UNIQUE INDEX ru_oldname ON renameuser_status (ru_oldname, ru_wiki);

DROP SEQUENCE IF EXISTS renameuser_queue_rq_id_seq CASCADE;
CREATE SEQUENCE renameuser_queue_rq_id_seq;

CREATE TYPE rq_status AS ENUM('pending', 'approved', 'rejected');

CREATE TABLE renameuser_queue (
  rq_id INTEGER PRIMARY KEY DEFAULT nextval('renameuser_queue_rq_id_seq'),
  rq_name TEXT NOT NULL,
  rq_wiki TEXT,
  rq_newname TEXT NOT NULL,
  rq_reason TEXT,
  rq_requested_ts TIMESTAMPTZ,
  rq_status rq_status NOT NULL,
  rq_completed_ts TIMESTAMPTZ,
  rq_deleted SMALLINT NOT NULL DEFAULT 0,
  rq_performer INTEGER,-- REFERENCES globaluser(gu_id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
  rq_comments TEXT
);

ALTER SEQUENCE renameuser_queue_rq_id_seq OWNED BY renameuser_queue.rq_id;

CREATE INDEX rq_oldstatus ON renameuser_queue (rq_name, rq_wiki, rq_status);
CREATE INDEX rq_newstatus ON renameuser_queue (rq_newname, rq_status);
CREATE INDEX rq_requested_ts ON renameuser_queue (rq_requested_ts);

DROP SEQUENCE IF EXISTS users_to_rename_utr_id_seq CASCADE;
CREATE SEQUENCE users_to_rename_utr_id_seq;

CREATE TABLE users_to_rename (
  utr_id INTEGER PRIMARY KEY DEFAULT nextval('users_to_rename_utr_id_seq'),
  utr_name TEXT NOT NULL,
  utr_wiki TEXT NOT NULL,
  utr_status INTEGER DEFAULT 0
);

ALTER SEQUENCE users_to_rename_utr_id_seq OWNED BY users_to_rename.utr_id;

CREATE UNIQUE INDEX utr_user ON users_to_rename (utr_name, utr_wiki);
CREATE INDEX utr_notif ON users_to_rename (utr_status);
CREATE INDEX utr_wiki ON users_to_rename (utr_wiki);
