#!/bin/bash

dir=`dirname "$0"`
echo $dir
for db in mysql postgres sqlite
do
	for schema in cs_comments cs_replies cs_votes cs_watchlist
	do
		echo $db : $schema

		php $dir/../../../maintenance/generateSchemaSql.php --json $schema.json --sql $db/$schema.sql --type=$db
	done
	for patch in cs_votes_comment_id_patch cs_watchlist_comment_id_patch
  	do
  		echo $db : $schema

  		php $dir/../../../maintenance/generateSchemaChangeSql.php --json $patch.json --sql $db/$patch.sql --type=$db
  	done
done
