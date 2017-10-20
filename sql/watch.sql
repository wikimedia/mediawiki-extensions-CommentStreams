CREATE TABLE IF NOT EXISTS /*_*/cs_watchlist
(
cst_wl_page_id int(10) unsigned NOT NULL,
cst_wl_user_id int(10) unsigned NOT NULL,
INDEX (cst_wl_page_id, cst_wl_user_id)
);
