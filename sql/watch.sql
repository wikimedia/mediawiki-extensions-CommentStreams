CREATE TABLE IF NOT EXISTS cs_watchlist
(
page_id int(10) unsigned NOT NULL,
user_id int(10) unsigned NOT NULL,
INDEX (page_id, user_id)
);
