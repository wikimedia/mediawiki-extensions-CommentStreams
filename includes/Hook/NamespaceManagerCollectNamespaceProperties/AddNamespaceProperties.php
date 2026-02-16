<?php

namespace MediaWiki\Extension\CommentStreams\Hook\NamespaceManagerCollectNamespaceProperties;

class AddNamespaceProperties {

	/**
	 * @inheritDoc
	 */
	public function onNamespaceManagerCollectNamespaceProperties(
		int $namespaceId,
		array $globals,
		array &$properties
	): void {
		$properties['commentstreams'] = in_array(
			$namespaceId,
			$globals['wgCommentStreamsAllowedNamespaces'] ?? []
		);
	}

}
