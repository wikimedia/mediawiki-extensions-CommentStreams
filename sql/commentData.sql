CREATE TABLE IF NOT EXISTS cs_comment_data
(
page_id int(10) unsigned,
assoc_page_id int(10) unsigned,
parent_page_id int(10) unsigned,
comment_title varbinary(255),
PRIMARY KEY (page_id),
FOREIGN KEY (assoc_page_id) REFERENCES page(page_id)
);