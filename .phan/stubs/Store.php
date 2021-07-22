<?php

namespace SMW;

class Store {
	/**
	 * @param DIWikiPage $subject
	 * @param string[]|bool $filter
	 * @return SemanticData
	 */
	public function getSemanticData( DIWikiPage $subject, $filter = false ): SemanticData {
		return new SemanticData();
	}
}
