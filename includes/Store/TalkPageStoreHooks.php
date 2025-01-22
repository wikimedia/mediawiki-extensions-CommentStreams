<?php

namespace MediaWiki\Extension\CommentStreams\Store;

use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;

class TalkPageStoreHooks implements MediaWikiServicesHook {

	/**
	 * @param MediaWikiServices $services
	 * @return void
	 */
	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			static function ( SlotRoleRegistry $registry ) {
				if ( $registry->isDefinedRole( SLOT_COMMENTSTREAMS_COMMENTS ) ) {
					return;
				}
				$registry->defineRoleWithModel(
					SLOT_COMMENTSTREAMS_COMMENTS,
					CONTENT_MODEL_JSON,
					[
						'display' => 'section'
					]
				);
			}
		);
	}
}
