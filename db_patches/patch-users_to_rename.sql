-- Table to store a list of users
-- who will be renamed in the
-- glorious finalization.
CREATE TABLE /*_*/users_to_rename (
  -- id
  utr_id int PRIMARY KEY AUTO_INCREMENT,

  -- username
  utr_name varchar(255) binary not null,

  -- wiki the user is on
  utr_wiki varchar(255) binary not null,

  -- bitfield of a user's status
  -- could be: notified via email, talk page, and finally: renamed
  utr_status int default 0
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/utr_user ON /*_*/users_to_rename (utr_name, utr_wiki);
CREATE INDEX /*i*/utr_notif ON /*_*/users_to_rename (utr_status);
CREATE INDEX /*i*/utr_wiki ON /*_*/users_to_rename (utr_wiki);
