<?php

namespace SMW;

use SMWDataItem;

class DIProperty extends SMWDataItem {
	/**
	 * @param string $key
	 * @param false $inverse
	 */
	public function __construct( string $key, $inverse = false ) {
	}

	/**
	 * @param string $label
	 * @param bool $inverse
	 * @param false $languageCode
	 * @return DIProperty
	 */
	public static function newFromUserLabel( string $label, bool $inverse = false, $languageCode = false ) : self {
		return new self( $label );
	}
}
