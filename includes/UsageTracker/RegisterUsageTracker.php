<?php

namespace MediaWiki\Extension\CommentStreams\UsageTracker;

class RegisterUsageTracker {

	/**
	 * @param array &$collectorConfig
	 * @return void
	 */
	public function onBSUsageTrackerRegisterCollectors( array &$collectorConfig ) {
		$collectorConfig['commentstreams-number-of-pages-with-comments'] = [
			'class' => PagesWithComments::class,
			'config' => []
		];
	}
}
