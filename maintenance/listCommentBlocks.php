<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

$IP = dirname( __DIR__, 3 );
require_once "$IP/maintenance/Maintenance.php";

class ListCommentBlocks extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'List names of all comment blocks in database' );
		$this->addOption( 'pages', 'List page name', false, false, 'p' );
	}

	public function execute() {
		$includePages = $this->hasOption( 'pages' );
		$columns = [
			'cst_c_block_name'
		];
		$options = [
			'DISTINCT'
		];
		if ( $includePages ) {
			$columns[] = 'cst_assoc_page_id';
			$options[ 'ORDER BY' ] = 'cst_assoc_page_id';
		}
		$rows = $this->getDB( DB_REPLICA )->select(
			[
				'cs_comments'
			],
			$columns,
			[],
			__METHOD__,
			$options
		);
		foreach ( $rows as $row ) {
			if ( $includePages ) {
				$this->output( Title::newFromID( $row->cst_assoc_page_id )->getPrefixedText() . ': ' );
			}
			$this->output( $row->cst_c_block_name . PHP_EOL );
		}
	}
}

$maintClass = ListCommentBlocks::class;
require_once RUN_MAINTENANCE_IF_MAIN;
