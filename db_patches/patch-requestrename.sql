-- Request queue for global account renames.
-- Used to power special pages for requesting a global rename from a user's
-- home wiki and a work queue of pending renames for stewards.
CREATE TABLE /*_*/renameuser_queue (
  -- Internal unique ID for the authentication server
  rq_id int primary key auto_increment,

  -- User requesting to be renamed
  -- Not a gu_id because user may not be global yet
  rq_name varchar(255) binary not null,

  -- WikiID of home wiki for requesting user
  rq_wiki varchar(255) binary not null,

  -- New name being requested
  rq_newname varchar(255) binary not null,

  -- Reason given by the user for the rename request
  rq_reason blob,

  -- Request timestamp
  rq_request_timestamp varchar(14) binary,

  -- Current state of the request
  rq_status enum ('pending', 'approved', 'rejected'),

  -- Completion timestamp
  rq_completed_timestamp varchar(14) binary,

  -- Steward who completed the request (foreign key to globaluser.gu_id)
  rq_steward int,

  -- Steward's comments on the request
  rq_comments blob

) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/rq_oldstatus ON /*_*/renameuser_queue (rq_name, rq_wiki, rq_status);
CREATE INDEX /*i*/rq_newstatus ON /*_*/renameuser_queue (rq_newname, rq_status);CREATE INDEX /*i*/rq_request_timestamp ON /*_*/renameuser_queue (rq_request_timestamp);
