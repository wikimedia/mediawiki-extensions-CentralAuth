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
  gu_password char(32),
  
  -- If true, this account cannot be used to log in on any wiki.
  gu_locked bool not null default 0,
  
  -- If true, this account should be hidden from most public user lists.
  -- Used for "deleting" accounts without breaking referential integrity.
  gu_hidden bool not null default 0,
  
  -- Registration time
  gu_registration char(14) binary,
  
  -- Random key for password resets
  gu_password_reset_key char(32),
  gu_password_reset_expiration char(14) binary,
  
  primary key (gu_id),
  unique key (gu_name)
) TYPE=InnoDB;


-- Migration state table
CREATE TABLE localuser (
  -- gu_id key number of global account this local account has been
  -- successfully attached to.
  -- May be NULL if account is not yet attached.
  lu_global_id int,
  
  -- Database name of the wiki
  lu_dbname varchar(32) binary,
  
  -- user_id on the local wiki
  lu_local_id int,
  
  -- Fields copied from local wikis, used for migration checks...
    -- Username at migration time
    lu_migrated_name varchar(255) binary,
    
    -- User'd old password hash; salt is lu_id
    lu_migrated_password varchar(255) binary,
    
    -- The user_email and user_email_authenticated state from local wiki
    lu_migrated_email varchar(255) binary,
    lu_migrated_email_authenticated char(14) binary,
    
    -- A count of revisions and/or other actions made during migration
    -- May be null if it hasn't yet been checked
    lu_migrated_editcount int,
  --
  
  primary key (lu_dbname,lu_local_id),
  key (lu_global_id, lu_dbname),
  unique key (lu_dbname,lu_migrated_name),
  key (lu_migrated_name,lu_dbname)
) TYPE=InnoDB;
