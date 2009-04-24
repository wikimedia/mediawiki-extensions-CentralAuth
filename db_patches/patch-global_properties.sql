-- Allow users to share preferences across wikis
CREATE TABLE global_user_properties (
	gp_user int(11) NOT NULL,
	gp_property varbinary(255) NOT NULL,
	gp_value BLOB,
	
	PRIMARY KEY (gp_user,gp_property),
	KEY (gp_property)
) /*$wgDBTableOptions*/;
