<?php

namespace MediaWiki\Extension\CommentStreams\HistoryHandler;

use JsonSerializable;

class UrlHistoryHandler implements JsonSerializable {

	/**
	 * @param string $url
	 */
	public function __construct(
		private readonly string $url
	) {
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): mixed {
		return [
			'type' => 'url',
			'url' => $this->url
		];
	}
}
