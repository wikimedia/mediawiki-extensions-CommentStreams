CREATE TABLE IF NOT EXISTS /*_*/cs_votes
(
page_id int(10) unsigned NOT NULL,
user_id int(10) unsigned NOT NULL,
vote tinyint NOT NULL,
INDEX (page_id, user_id)
);
