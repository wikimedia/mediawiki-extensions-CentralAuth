-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: schema/abstractSchemaChanges/patch-renameuser_status-unique-to-pk.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP INDEX ru_oldname;
ALTER TABLE renameuser_status
  ADD PRIMARY KEY (ru_oldname, ru_wiki);
