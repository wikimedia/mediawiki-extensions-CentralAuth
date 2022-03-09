-- Add default value back to globaluser.gu_enabled
ALTER TABLE  /*_*/globaluser
CHANGE  gu_enabled gu_enabled BINARY(14) NOT NULL DEFAULT '';
