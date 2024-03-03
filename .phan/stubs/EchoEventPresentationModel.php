<?php

abstract class EchoEventPresentationModel {
	/**
	 * @var EchoEvent
	 */
	protected $event;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @param string|string[]|MessageSpecifier $key
	 * @param mixed ...$params
	 * @return Message
	 */
	public function msg( $key, ...$params ) {
	}

	/**
	 * @return string
	 */
	final protected function getViewingUserForGender() {
	}
}
