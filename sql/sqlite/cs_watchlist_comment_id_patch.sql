-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: cs_watchlist_comment_id_patch.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TEMPORARY TABLE /*_*/__temp__cs_watchlist AS
SELECT
  cst_wl_page_id,
  cst_wl_user_id
FROM /*_*/cs_watchlist;
DROP TABLE /*_*/cs_watchlist;


CREATE TABLE /*_*/cs_watchlist (
    cst_wl_comment_id INTEGER UNSIGNED NOT NULL,
    cst_wl_user_id INTEGER UNSIGNED NOT NULL
  );
INSERT INTO /*_*/cs_watchlist (
    cst_wl_comment_id, cst_wl_user_id
  )
SELECT
  cst_wl_page_id,
  cst_wl_user_id
FROM
  /*_*/__temp__cs_watchlist;
DROP TABLE /*_*/__temp__cs_watchlist;

CREATE UNIQUE INDEX cst_wl_index ON /*_*/cs_watchlist (
    cst_wl_comment_id, cst_wl_user_id
  );
