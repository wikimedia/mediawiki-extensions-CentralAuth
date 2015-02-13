-- This patch allows storing global groups in the database.
-- Andrew Garrett (Werdna), April 2008.

-- Global user groups.
CREATE TABLE /*_*/global_user_groups (
  gug_user int(11) not null,
  gug_group varchar(255) not null,
  PRIMARY KEY (gug_user, gug_group)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/gug_user ON /*_*/global_user_groups (gug_user);
CREATE INDEX /*i*/gug_group ON /*_*/global_user_groups (gug_group);

-- Global group permissions.
CREATE TABLE /*_*/global_group_permissions (
  ggp_group varchar(255) not null,
  ggp_permission varchar(255) not null,
  PRIMARY KEY (ggp_group, ggp_permission)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ggp_group ON /*_*/global_group_permissions (ggp_group);
CREATE INDEX /*i*/ggp_permission ON /*_*/global_group_permissions (ggp_permission);

-- Create a starter group, which users can be added to.
INSERT INTO global_group_permissions (ggp_group,ggp_permission) VALUES ('steward','globalgrouppermissions'),('steward','globalgroupmembership');
