ALTER TABLE localuser
  ADD COLUMN lu_local_id INT(10) UNSIGNED DEFAULT NULL,
  ADD COLUMN lu_global_id INT(10) UNSIGNED DEFAULT NULL,
  ADD INDEX lu_lid_wiki (lu_local_id, lu_wiki),
  ADD INDEX lu_gid_wiki (lu_global_id, lu_wiki)
;
