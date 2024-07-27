<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
$extensions = [ "Echo", "SocialProfile" ];
$dirList = array_map( fn( $dir ): string => "../../extensions/$dir", $extensions );

$cfg['directory_list'] = array_merge( $cfg['directory_list'] ?? [], $dirList );
$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'] ?? [], $dirList
);
$cfg['exclude_file_list'][] = '.phan/stubs/wAvatar.php';

return $cfg;
