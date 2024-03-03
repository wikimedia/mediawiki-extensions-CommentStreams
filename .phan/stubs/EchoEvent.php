<?php

class EchoEvent {
	/**
	 * @param string $key
	 * @param mixed|null $default
	 * @return mixed|null
	 */
	public function getExtraParam( $key, $default = null ) {
	}

	/**
	 * @param array $info Named arguments:
	 * @return EchoEvent|false
	 */
	public static function create( $info = [] ) {
	}

	/**
	 * @param bool $fromPrimary
	 * @return null|Title
	 */
	public function getTitle( $fromPrimary = false ) {
	}
}
