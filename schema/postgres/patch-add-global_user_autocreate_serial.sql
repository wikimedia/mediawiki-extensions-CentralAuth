-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: schema/abstractSchemaChanges/patch-add-global_user_autocreate_serial.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE global_user_autocreate_serial (
  uas_shard INT NOT NULL,
  uas_value INT NOT NULL,
  PRIMARY KEY(uas_shard)
);
