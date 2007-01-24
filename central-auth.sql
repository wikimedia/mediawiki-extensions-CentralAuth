
-- -- Some example steps for creating a new database for testing this:
-- CREATE DATABASE centralauth;
-- USE centralauth;
-- GRANT all on centralauth.* to 'wikiuser'@'localhost';
-- source central-auth.sql
-- source sample-data.sql

--
-- Global account data.
--
CREATE TABLE globaluser (
  -- Internal unique ID for the authentication server
  gu_id int auto_increment,
  
  -- Username. [Could change... or not? How to best handle renames...]
  gu_name varchar(255) binary,
  
  -- Registered email address, may be empty.
  gu_email varchar(255) binary,
  
  -- Timestamp when the address was confirmed as belonging to the user.
  -- NULL if not confirmed.
  gu_email_authenticated char(14) binary,
  
  -- Salt and hashed password
  gu_salt char(16), -- or should this be an int? usually the old user_id
  gu_password tinyblob,
  
  -- If true, this account cannot be used to log in on any wiki.
  gu_locked bool not null default 0,
  
  -- If true, this account should be hidden from most public user lists.
  -- Used for "deleting" accounts without breaking referential integrity.
  gu_hidden bool not null default 0,
  
  -- Registration time
  gu_registration char(14) binary,
  
  -- Random key for password resets
  gu_password_reset_key tinyblob,
  gu_password_reset_expiration char(14) binary,
  
  primary key (gu_id),
  unique key (gu_name),
  key (gu_email)
) TYPE=InnoDB;


--
-- Local linkage table, to determine whether a given local account
-- is attached to the global system, and to which global account.
--
-- Note there are no usernames in this table!
-- Linkages are by id. Naming consistency after
-- renames should be enforced by double-checks
-- at login time.
--
CREATE TABLE localuser (
  -- gu_id key number of global account this local account has been
  -- successfully attached to.
  lu_global_id int not null,
  
  -- Database name of the wiki
  lu_dbname varchar(32) binary not null,
  
  -- user_id on the local wiki
  lu_local_id int not null,
  
  -- Migration status/logging information, to help diagnose issues
  lu_attached_timestamp char(14) binary,
  lu_attached_method enum (
    'primary',
    'empty',
    'mail',
    'password',
    'admin',
    'new'),
  
  primary key (lu_dbname, lu_local_id),
  unique key (lu_global_id, lu_dbname)
) TYPE=InnoDB;


-- Migration state table
--
-- Lists registered usernames on various wikis, used to optimize migration
-- checks by only having to check dbs where the name is actually registered.
--
-- May be batch-initialized and/or lazy-initialized.
-- Once migration is complete, this data can be ignored/discarded.
--
CREATE TABLE migrateuser (
  -- Database name of the wiki
  mu_dbname varchar(32) binary,
  
  -- user_id on the local wiki
  mu_local_id int,
  
  -- Username at migration time
  mu_name varchar(255) binary,
  
  primary key (mu_dbname, mu_local_id),
  unique key (mu_dbname, mu_name),
  key (mu_name, mu_dbname)
) TYPE=InnoDB;

-- Q: any point adding autoincrement keys to localuser or migrateuser?