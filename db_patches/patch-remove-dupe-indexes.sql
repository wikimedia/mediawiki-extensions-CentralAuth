ALTER TABLE global_user_groups
	DROP INDEX gug_user;

ALTER TABLE global_group_permissions
	DROP INDEX ggp_group;
