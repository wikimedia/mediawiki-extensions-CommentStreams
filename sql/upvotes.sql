CREATE TABLE IF NOT EXISTS cs_upvotes
(
page_id int(10) unsigned,
user_id int(10) unsigned,
FOREIGN KEY (page_id) REFERENCES page(page_id),
FOREIGN KEY (user_id) REFERENCES user(user_id)
);