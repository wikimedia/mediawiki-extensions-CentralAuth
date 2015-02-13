ALTER TABLE /*_*/localnames CHANGE ln_dbname ln_wiki varchar(255) binary NOT NULL DEFAULT '';

ALTER TABLE /*_*/localuser CHANGE lu_dbname lu_wiki varchar(255) binary NOT NULL DEFAULT '';
