<?php

namespace SMW;

use SMWDataItem;

class SemanticData {
	/**
	 * @param DIProperty $property
	 * @return SMWDataItem[]
	 */
	public function getPropertyValues( DIProperty $property ) {
		return [];
	}

	/**
	 * @return DIWikiPage subject
	 */
	public function getSubject() {
		return new DIWikiPage();
	}

	/**
	 * @param DIProperty $property
	 * @param SMWDataItem $dataItem
	 */
	public function addPropertyObjectValue( DIProperty $property, SMWDataItem $dataItem ) {
	}
}
