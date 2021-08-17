ALTER TABLE  /*_*/globaluser
	ADD COLUMN gu_hidden_level INT NOT NULL DEFAULT 0 AFTER gu_hidden;

CREATE INDEX /*i*/gu_hidden_level ON /*_*/globaluser (gu_name(255), gu_hidden_level);
