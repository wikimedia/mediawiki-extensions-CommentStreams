-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: cs_votes_comment_id_patch.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP INDEX cst_v_index ON /*_*/cs_votes;
ALTER TABLE /*_*/cs_votes
  CHANGE cst_v_page_id cst_v_comment_id INT UNSIGNED NOT NULL;

CREATE UNIQUE INDEX cst_v_index ON /*_*/cs_votes (cst_v_comment_id, cst_v_user_id);
