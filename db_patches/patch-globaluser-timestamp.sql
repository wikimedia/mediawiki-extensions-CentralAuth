-- Standardise type for timestamp columns
ALTER TABLE  /*_*/globaluser
CHANGE  gu_enabled gu_enabled BINARY(14) NOT NULL,
CHANGE  gu_registration gu_registration BINARY(14),
CHANGE  gu_password_reset_expiration gu_password_reset_expiration BINARY(14);
