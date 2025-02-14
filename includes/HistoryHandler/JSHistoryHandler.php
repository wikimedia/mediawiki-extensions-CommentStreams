<?php

namespace MediaWiki\Extension\CommentStreams\HistoryHandler;

use JsonSerializable;

class JSHistoryHandler implements JsonSerializable {

	/**
	 * @param string $callback
	 * @param array $rlModules
	 */
	public function __construct(
		private readonly string $callback,
		private readonly array $rlModules
	) {
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): mixed {
		return [
			'type' => 'js',
			'callback' => $this->callback,
			'rlModules' => $this->rlModules
		];
	}
}
