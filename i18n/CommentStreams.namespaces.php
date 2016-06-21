<?php
/**
 * Translations of the namespaces introduced by CommentStreams
 *
 * @file
 */
 
$namespaceNames = array();
 
// For wikis where the CommentStreams extension is not installed.
if( !defined( 'NS_COMMENTSTREAMS' ) ) {
	define( 'NS_COMMENTSTREAMS', 1000 );
}
 
if( !defined( 'NS_COMMENTSTREAMS_TALK' ) ) {
	define( 'NS_COMMENTSTREAMS_TALK', 1001 );
}
 
/** English */
$namespaceNames['en'] = array(
	NS_COMMENTSTREAMS => 'CommentStreams',
	NS_COMMENTSTREAMS_TALK => 'CommentStreams_talk',
);