-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/CentralAuth/schema/abstractSchemaChanges/patch-rq_type.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE /*_*/renameuser_queue
  ADD rq_type TINYINT UNSIGNED DEFAULT 0 NOT NULL;
