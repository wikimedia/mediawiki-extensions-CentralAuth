ALTER TABLE /*_*/global_user_groups
  ADD COLUMN gug_expiry varbinary(14) NULL default NULL,
  ADD INDEX gug_expiry (gug_expiry);