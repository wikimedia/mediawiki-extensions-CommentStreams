ALTER TABLE /*_*/cs_comment_data CHANGE page_id cst_page_id int(10) unsigned;
ALTER TABLE /*_*/cs_comment_data CHANGE assoc_page_id cst_assoc_page_id int(10) unsigned;
ALTER TABLE /*_*/cs_comment_data CHANGE parent_page_id cst_parent_page_id int(10) unsigned;
ALTER TABLE /*_*/cs_comment_data CHANGE comment_title cst_comment_title varbinary(255);
ALTER TABLE /*_*/cs_votes CHANGE page_id cst_v_page_id int(10) unsigned NOT NULL;
ALTER TABLE /*_*/cs_votes CHANGE user_id cst_v_user_id int(10) unsigned NOT NULL;
ALTER TABLE /*_*/cs_votes CHANGE vote cst_v_vote tinyint NOT NULL;
ALTER TABLE /*_*/cs_watchlist CHANGE page_id cst_wl_page_id int(10) unsigned NOT NULL;
ALTER TABLE /*_*/cs_watchlist CHANGE user_id cst_wl_user_id int(10) unsigned NOT NULL;
