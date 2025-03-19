<?php

namespace SMW\MediaWiki\Jobs;

use IJobSpecification;
use MediaWiki\Title\Title;

class UpdateJob implements IJobSpecification {

	/**
	 * @param Title $title
	 * @param array $params
	 */
	public function __construct( Title $title, $params = [] ) {
	}

	/**
	 * @return bool|void
	 */
	public function run(): bool {
	}

	/**
	 * @return string
	 */
	public function getType(): string {
	}

	/**
	 * @return array
	 */
	public function getParams(): array {
	}

	/**
	 * @return int|null
	 */
	public function getReleaseTimestamp(): ?int {
	}

	/**
	 * @return bool
	 */
	public function ignoreDuplicates(): bool {
	}

	/**
	 * @return array
	 */
	public function getDeduplicationInfo(): array {
	}

	/**
	 * @return array
	 */
	public function getRootJobParams(): array {
	}

	/**
	 * @return bool
	 */
	public function hasRootJobParams(): bool {
	}

	/**
	 * @return bool
	 */
	public function isRootJob(): bool {
	}
}
