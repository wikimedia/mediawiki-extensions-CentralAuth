-- Standardise type for timestamp columns
ALTER TABLE  /*_*/renameuser_queue
CHANGE  rq_requested_ts rq_requested_ts BINARY(14),
CHANGE  rq_completed_ts rq_completed_ts BINARY(14);
