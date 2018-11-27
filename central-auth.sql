-- -- Some example steps for creating a new database for testing this:
-- CREATE DATABASE centralauth;
-- USE centralauth;
-- GRANT all on centralauth.* to 'wikiuser'@'localhost';
-- source central-auth.sql

-- IMPORTANT: If you want to run the AntiSpoof Extention with
-- CentralAuth, you must run patch-antispoof-global.mysql.sql,
-- located in the AntiSpoof folder of this extension.

-- This table simply lists all known usernames in the system.
-- If no record is present here when migration processing begins,
-- we know we have to sweep all the local databases and populate
-- the localnames table.
--
CREATE TABLE /*_*/globalnames (
  gn_name varchar(255) binary not null,
  primary key (gn_name)
) /*$wgDBTableOptions*/;

--
-- For each known username in globalnames, the presence of an acount
-- on each local database is listed here.
--
-- Email and password information used for migration checks are grabbed
-- from local databases on demand when needed.
--
-- This is an optimization measure, so we don't have to poke on 600+
-- separate databases to look for unmigrated accounts every time we log in;
-- only existing databases not yet migrated have to be loaded.
--
CREATE TABLE /*_*/localnames (
  ln_wiki varchar(255) binary not null,
  ln_name varchar(255) binary not null,

  primary key (ln_wiki, ln_name)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ln_name_wiki ON /*_*/localnames (ln_name, ln_wiki);

--
-- Global account data.
--
CREATE TABLE /*_*/globaluser (
  -- Internal unique ID for the authentication server
  gu_id int primary key auto_increment,

  -- Username.
  gu_name varchar(255) binary,

  -- Timestamp and method used to create the global account
  gu_enabled varchar(14) not null default '',
  gu_enabled_method enum('opt-in', 'batch', 'auto', 'admin') default null,

  -- Local database name of the user's 'home' wiki.
  -- By default, the 'winner' of a migration check for old accounts
  -- or the account the user was first registered at for new ones.
  -- May be changed over time.
  gu_home_db varchar(255) binary,

  -- Registered email address, may be empty.
  gu_email varchar(255) binary,

  -- Timestamp when the address was confirmed as belonging to the user.
  -- NULL if not confirmed.
  gu_email_authenticated char(14) binary,

  -- Salt and hashed password
  -- For migrated passwords, the salt is the local user_id.
  gu_salt varchar(16) binary,
  gu_password tinyblob,

  -- If true, this account cannot be used to log in on any wiki.
  gu_locked bool not null default 0,

  -- If true, this account should be hidden from most public user lists.
  -- Used for "deleting" accounts without breaking referential integrity.
  gu_hidden varbinary(255) not null default '',

  -- Registration time
  gu_registration varchar(14) binary,

  -- Random key for password resets
  gu_password_reset_key tinyblob,
  gu_password_reset_expiration varchar(14) binary,

  -- Random key for crosswiki authentication tokens
  gu_auth_token varbinary(32) NULL,

  -- Value used for CAS operations
  gu_cas_token integer unsigned NOT NULL default 1
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/gu_name ON /*_*/globaluser (gu_name);
CREATE INDEX /*i*/gu_email ON /*_*/globaluser (gu_email);
CREATE INDEX /*i*/gu_locked ON /*_*/globaluser ( gu_name(255), gu_locked );
CREATE INDEX /*i*/gu_hidden ON /*_*/globaluser ( gu_name(255), gu_hidden(255) );

--
-- Local linkage info, listing which wikis the username is attached
-- to the global account.
--
-- All local DBs will be swept on an opt-in check event.
--
CREATE TABLE /*_*/localuser (
  lu_wiki varchar(255) binary not null,
  lu_name varchar(255) binary not null,

  -- Migration status/logging information, to help diagnose issues
  lu_attached_timestamp varchar(14) binary,
  lu_attached_method enum (
    'primary',
    'empty',
    'mail',
    'password',
    'admin',
    'new',
    'login'
  ),
  lu_local_id int(10) unsigned default null,
  lu_global_id int(10) unsigned default null,

  primary key (lu_wiki, lu_name)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/lu_name_wiki ON /*_*/localuser (lu_name, lu_wiki);

-- Global user groups.
CREATE TABLE /*_*/global_user_groups (
  gug_user int(11) not null,
  gug_group varchar(255) not null,
  gug_expiry varbinary(14) NULL default NULL,

  PRIMARY KEY (gug_user,gug_group)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/gug_user ON /*_*/global_user_groups (gug_user);
CREATE INDEX /*i*/gug_group ON /*_*/global_user_groups (gug_group);
CREATE INDEX /*i*/gug_expiry ON /*_*/global_user_groups (gug_expiry);

-- Global group permissions.
CREATE TABLE /*_*/global_group_permissions (
  ggp_group varchar(255) not null,
  ggp_permission varchar(255) not null,

  PRIMARY KEY (ggp_group, ggp_permission)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ggp_group ON /*_*/global_group_permissions (ggp_group);
CREATE INDEX /*i*/ggp_permission ON /*_*/global_group_permissions (ggp_permission);

-- Sets of wikis (for things like restricting global groups)
-- May be defined in two ways: only specified wikis or all wikis except opt-outed
CREATE TABLE /*_*/wikiset (
  -- ID of wikiset
  ws_id int primary key auto_increment,
  -- Display name of wikiset
  ws_name varchar(255) not null,
  -- Type of set: opt-in or opt-out
  ws_type enum ('optin', 'optout'),
  -- Wikis in that set. Why isn't it a separate table?
  -- Because we can just use such simple list, we don't need complicated queries on it
  -- Let's suppose that max length of db name is 31 (32 with ","), then we have space for
  -- 2048 wikis. More than we need
  ws_wikis blob not null
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ws_name ON /*_*/wikiset (ws_name);

-- Allow certain global groups to have their permissions only on certain wikis
CREATE TABLE /*_*/global_group_restrictions (
  -- Group to restrict
  ggr_group varchar(255) not null,
  -- Wikiset to use
  ggr_set int not null,

  PRIMARY KEY (ggr_group)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ggr_set ON /*_*/global_group_restrictions (ggr_set);

-- Table for global rename status
CREATE TABLE /*_*/renameuser_status (
  -- Old name being renamed from
  ru_oldname varchar(255) binary not null,
  -- New name being renamed to
  ru_newname varchar(255) binary not null,
  -- WikiID
  ru_wiki varchar(255) binary not null,
  -- current state of the renaming
  ru_status enum ('queued', 'inprogress', 'failed')
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ru_oldname ON /*_*/renameuser_status (ru_oldname, ru_wiki);

-- Request queue for global account renames.
-- Used to power special pages for requesting a global rename from a user's
-- home wiki and a work queue of pending renames for stewards.
CREATE TABLE /*_*/renameuser_queue (
  -- Internal unique ID for the authentication server
  rq_id int primary key auto_increment,

  -- User requesting to be renamed
  -- Not a gu_id because user may not be global yet
  rq_name varchar(255) binary not null,

  -- WikiID of home wiki for requesting user
  -- Will be null if user is a CentralAuth account
  rq_wiki varchar(255) binary,

  -- New name being requested
  rq_newname varchar(255) binary not null,

  -- Reason given by the user for the rename request
  rq_reason blob,

  -- Request timestamp
  rq_requested_ts varchar(14) binary,

  -- Current state of the request
  rq_status enum ('pending', 'approved', 'rejected') not null,

  -- Completion timestamp
  rq_completed_ts varchar(14) binary,

  -- Delete/suppress flag
  rq_deleted tinyint unsigned not null default '0',

  -- User who completed the request (foreign key to globaluser.gu_id)
  rq_performer int,

  -- Steward's comments on the request
  rq_comments blob

) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/rq_oldstatus ON /*_*/renameuser_queue (rq_name, rq_wiki, rq_status);
CREATE INDEX /*i*/rq_newstatus ON /*_*/renameuser_queue (rq_newname, rq_status);
CREATE INDEX /*i*/rq_requested_ts ON /*_*/renameuser_queue (rq_requested_ts);

-- Table to store a list of users
-- who will be renamed in the
-- glorious finalization.
CREATE TABLE /*_*/users_to_rename (
  -- id
  utr_id int primary key auto_increment,

  -- username
  utr_name varchar(255) binary not null,

  -- wiki the user is on
  utr_wiki varchar(255) binary not null,

  -- bitfield of a user's status
  -- could be: notified via email, talk page, and finally: renamed
  utr_status int default 0
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/utr_user ON /*_*/users_to_rename (utr_name, utr_wiki);
CREATE INDEX /*i*/utr_notif ON /*_*/users_to_rename (utr_status);
CREATE INDEX /*i*/utr_wiki ON /*_*/users_to_rename (utr_wiki);
