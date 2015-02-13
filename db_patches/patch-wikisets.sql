-- Sets of wikis (for things like restricting global groups)
-- May be defined in two ways: only specified wikis or all wikis except opt-outed
CREATE TABLE /*_*/wikiset (
  -- ID of wikiset
  ws_id int PRIMARY KEY AUTO_INCREMENT,
  -- Display name of wikiset
  ws_name varchar(255) not null,
  -- Type of set: opt-in or opt-out
  ws_type enum ('optin', 'optout'),
  -- Wikis in that set. Why isn't it a separate table?
  -- Because we can just use such simple list, we don't need complicated queries on it
  -- Let's suppose that max length of db name is 31 (32 with ","), then we have space for
  -- 2048 wikis. More than we need
  ws_wikis blob not null
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/ws_name ON /*_*/wikiset (ws_name);


-- Allow certain global groups to have their permissions only on certain wikis
CREATE TABLE /*_*/global_group_restrictions (
  -- Group to restrict
  ggr_group varchar(255) not null PRIMARY KEY,
  -- Wikiset to use
  ggr_set int not null
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/ggr_set ON /*_*/global_group_restrictions (ggr_set);
