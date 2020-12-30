<?php

namespace SMW;

use SMWDataItem;
use Title;

class DIWikiPage extends SMWDataItem {
	/**
	 * @var Title
	 */
	private $title;

	/**
	 * @param Title $title
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
	}

	/**
	 * @param Title $title
	 * @return DIWikiPage
	 */
	public static function newFromTitle( Title $title ) : self {
		return new self( $title );
	}

	/**
	 * @return Title
	 */
	public function getTitle() {
		return $this->title;
	}
}
