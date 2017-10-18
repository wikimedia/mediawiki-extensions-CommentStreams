CREATE TABLE IF NOT EXISTS /*_*/cs_votes
(
cst_v_page_id int(10) unsigned NOT NULL,
cst_v_user_id int(10) unsigned NOT NULL,
cst_v_vote tinyint NOT NULL,
INDEX (cst_v_page_id, cst_v_user_id)
);
