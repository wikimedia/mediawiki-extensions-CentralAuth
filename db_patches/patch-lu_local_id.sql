ALTER TABLE localuser
  ADD COLUMN lu_local_id INT(10) UNSIGNED NOT NULL,
  ADD COLUMN lu_global_id INT(10) UNSIGNED NOT NULL,
  ADD UNIQUE INDEX lu_lid_wiki (lu_local_id, lu_wiki),
  ADD UNIQUE INDEX lu_gid_wiki (lu_global_id, lu_wiki)
;
