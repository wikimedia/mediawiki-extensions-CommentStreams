-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: cs_watchlist.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/cs_watchlist (
  cst_wl_comment_id INT UNSIGNED NOT NULL,
  cst_wl_user_id INT UNSIGNED NOT NULL,
  UNIQUE INDEX cst_wl_index (
    cst_wl_comment_id, cst_wl_user_id
  )
) /*$wgDBTableOptions*/;
