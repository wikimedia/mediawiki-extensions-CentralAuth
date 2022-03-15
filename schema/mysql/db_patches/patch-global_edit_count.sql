
-- Table for caching the total global edit count
CREATE TABLE /*_*/global_edit_count (
    gec_user int primary key,
    gec_count int not null
) /*$wgDBTableOptions*/;
