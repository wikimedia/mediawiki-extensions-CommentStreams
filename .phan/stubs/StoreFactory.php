<?php

namespace SMW;

class StoreFactory {
	/**
	 * @param string|null $class
	 * @return Store
	 */
	public static function getStore( $class = null ) : Store {
		return new Store();
	}
}
