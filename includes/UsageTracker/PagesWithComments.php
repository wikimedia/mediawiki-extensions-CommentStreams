<?php

namespace MediaWiki\Extension\CommentStreams\UsageTracker;

use BS\UsageTracker\CollectorResult;
use BS\UsageTracker\Collectors\Base as UsageTrackerBase;

class PagesWithComments extends UsageTrackerBase {

	/**
	 * @return string
	 */
	public function getDescription() {
		return 'Number of pages with comments';
	}

	/**
	 * @return string
	 */
	public function getIdentifier() {
		return 'commentstreams-number-of-pages-with-comments';
	}

	/**
	 * @return CollectorResult
	 */
	public function getUsageData() {
		$res = new CollectorResult( $this );

		$db = $this->services->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$row = $db->newSelectQueryBuilder()
			->select( 'COUNT( distinct csa_page_id ) as count' )
			->from( 'cs_associated_pages' )
			->caller( __METHOD__ )
			->fetchRow();

		$res->count = $row ? $row->count : 0;
		return $res;
	}
}
