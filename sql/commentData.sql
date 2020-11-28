CREATE TABLE IF NOT EXISTS /*_*/cs_comment_data
(
cst_page_id int(10) unsigned,
cst_assoc_page_id int(10) unsigned,
cst_parent_page_id int(10) unsigned,
cst_comment_title varbinary(255),
cst_id varchar(50) DEFAULT "cs-comments",
PRIMARY KEY (cst_page_id)
);
