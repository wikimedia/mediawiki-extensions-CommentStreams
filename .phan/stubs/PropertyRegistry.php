<?php

namespace SMW;

class PropertyRegistry {
	/**
	 * @return PropertyRegistry
	 */
	public static function getInstance(): self {
		return new self();
	}

	/**
	 * @param string $id
	 * @param string $valueType SMW type id
	 * @param string|bool $label user label or false (internal property)
	 * @param bool $isVisible only used if label is given, see isShown()
	 * @param bool $isAnnotable
	 * @param bool $isDeclarative
	 */
	public function registerProperty(
		string $id,
		string $valueType,
		$label = false,
		bool $isVisible = false,
		bool $isAnnotable = true,
		bool $isDeclarative = false
	) {
	}
}
