-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: schema/abstractSchemaChanges/patch-renameuser_status-unique-to-pk.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP  INDEX ru_oldname;
CREATE TEMPORARY TABLE /*_*/__temp__renameuser_status AS
SELECT  ru_oldname,  ru_newname,  ru_wiki,  ru_status
FROM  /*_*/renameuser_status;
DROP  TABLE  /*_*/renameuser_status;
CREATE TABLE  /*_*/renameuser_status (    ru_oldname BLOB NOT NULL,    ru_wiki BLOB NOT NULL,    ru_newname BLOB NOT NULL,    ru_status TEXT DEFAULT NULL,    PRIMARY KEY(ru_oldname, ru_wiki)  );
INSERT INTO  /*_*/renameuser_status (    ru_oldname, ru_newname, ru_wiki, ru_status  )
SELECT  ru_oldname,  ru_newname,  ru_wiki,  ru_status
FROM  /*_*/__temp__renameuser_status;
DROP  TABLE /*_*/__temp__renameuser_status;