ALTER TABLE /*_*/global_user_groups
	DROP INDEX /*i*/gug_user;

ALTER TABLE /*_*/global_group_permissions
	DROP INDEX /*i*/ggp_group;
