-- Convert index to unique index
-- See T243853
ALTER TABLE /*_*/localnames
DROP INDEX /*i*/ln_name_wiki,
ADD UNIQUE INDEX (ln_name,ln_wiki);
