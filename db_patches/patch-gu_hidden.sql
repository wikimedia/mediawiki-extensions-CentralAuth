-- This patch switches gu_hidden from boolean to string (so multiple hiding
-- levels may be applied). It also creates indexes on those columns so we can easily
-- get a list of hidden or locked users.
-- Victor Vasiliev, January 2010.

ALTER TABLE /*_*/globaluser
	MODIFY COLUMN gu_hidden VARBINARY(255) NOT NULL DEFAULT '';

-- Not hidden (was 0)
UPDATE /*_*/globaluser SET gu_hidden = '' WHERE gu_hidden = '0';
-- Hidden from public lists, but remains visible in Special:CentralAuth log
UPDATE /*_*/globaluser SET gu_hidden = 'lists' WHERE gu_hidden = '1';
-- There's also "suppressed" level, which wasn't used before this schema change

ALTER TABLE /*_*/globaluser
	ADD INDEX /*i*/gu_locked(gu_name(255), gu_locked),
	ADD INDEX /*i*/gu_hidden(gu_name(255), gu_hidden(255));
