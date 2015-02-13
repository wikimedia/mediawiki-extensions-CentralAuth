-- Fix a problem in the original renameuser_queue patch.
-- We intended renameuser_queue.rq_wiki to be nullable.
ALTER TABLE /*_*/renameuser_queue CHANGE COLUMN rq_wiki rq_wiki varchar(255) binary;
