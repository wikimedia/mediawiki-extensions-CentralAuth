-- AntiSpoof (for making the AntiSpoof extension work on the global users)
-- Copied from AntiSpoof's /sql/patch-antispoof.mysql.sql
-- MUST be kept consistant with it
CREATE TABLE spoofuser (
  -- Equivalent to user_name, but not guaranteed to be in sync.
  -- Do a join against user_name to confirm that an account hasn't
  -- been renamed or deleted away.
  su_name VARCHAR(255),

  -- Normalized form of name for similarity-spoofing checks
  su_normalized VARCHAR(255),

  -- ok/not-ok according to the looks-like-a-valid-name check
  su_legal BOOL,

  -- error message that came out of the unicode check, if any
  su_error TEXT,

  -- unique record per username
  PRIMARY KEY (su_name),

  -- for checking matching possible spoofs
  KEY(su_normalized, su_name)
  ) /*$wgDBTableOptions*/;
