-- This file is automatically generated using maintenance/generateSchemaChangeSql.php.
-- Source: cs_votes_comment_id_patch.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
DROP INDEX cst_v_index;
ALTER TABLE cs_votes
  RENAME COLUMN cst_v_page_id TO cst_v_comment_id;

CREATE UNIQUE INDEX cst_v_index ON cs_votes (cst_v_comment_id, cst_v_user_id);
