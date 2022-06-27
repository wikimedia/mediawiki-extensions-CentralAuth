-- Table copied from core to allow global allocation of serial numbers for temporary users
CREATE TABLE /*_*/global_user_autocreate_serial (
  uas_shard INT UNSIGNED NOT NULL,
  uas_value INT UNSIGNED NOT NULL,
  PRIMARY KEY(uas_shard)
) /*$wgDBTableOptions*/;
