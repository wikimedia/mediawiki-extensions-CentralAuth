-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: extensions/CentralAuth/schema/abstractSchemaChanges/patch-add-gu_attachment_method.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
ALTER TABLE  localuser
ADD  lu_attachment_method SMALLINT DEFAULT NULL;