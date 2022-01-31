-- Standardise type for timestamp columns
ALTER TABLE  /*_*/localuser
CHANGE  lu_attached_timestamp lu_attached_timestamp BINARY(14);
