-- Convert index to unique index
-- See T243853
ALTER TABLE /*_*/localnames
DROP INDEX /*i*/ln_name_wiki,
CREATE UNIQUE INDEX /*i*/ln_name_wiki_unique ON /*_*/localnames (ln_name,ln_wiki);
