-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/CentralAuth/schema/abstractSchemaChanges/patch-drop-gu_enabled.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE  globaluser
DROP  gu_enabled;
ALTER TABLE  globaluser
DROP  gu_enabled_method;