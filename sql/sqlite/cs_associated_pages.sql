-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: cs_associated_pages.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/cs_associated_pages (
  csa_comment_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  csa_page_id INTEGER UNSIGNED NOT NULL
);
