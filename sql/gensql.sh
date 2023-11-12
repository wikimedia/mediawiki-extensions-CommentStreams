#!/bin/bash

dir=$(dirname "$0")
MW_INSTALL_PATH=${MW_INSTALL_PATH:-$dir/../../..}
for db in mysql postgres sqlite
do
	for schema in cs_comments cs_replies cs_votes cs_watchlist
	do
		echo $db : $schema

		php "${MW_INSTALL_PATH}/maintenance/generateSchemaSql.php" --json $dir/$schema.json --sql $dir/$db/$schema.sql --type=$db
	done
done
