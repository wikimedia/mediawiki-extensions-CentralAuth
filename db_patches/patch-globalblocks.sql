CREATE TABLE /*$wgDBprefix*/globalblock (
  gb_id int NOT NULL auto_increment,
  gb_user int unsigned NOT NULL default '0',
  gb_user_text tinyblob NOT NULL,
  gb_by_text varchar(255) binary NOT NULL default '',
  gb_reason tinyblob NOT NULL,
  gb_timestamp binary(14) NOT NULL default '',
  -- May be "infinity"
  gb_expiry varbinary(14) NOT NULL default '',
  gb_block_email bool NOT NULL default 0,
  
  PRIMARY KEY gb_id (gb_id),
  UNIQUE INDEX gb_user_text (gb_user_text(255), gb_user),

  INDEX gb_user (gb_user),
  INDEX gb_timestamp (gb_timestamp),
  INDEX gb_expiry (gb_expiry)

) /*$wgDBTableOptions*/;