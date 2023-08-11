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

use Wikimedia\Rdbms\IDatabase;

$IP ??= getenv( "MW_INSTALL_PATH" ) ?: dirname( __DIR__, 3 );
require_once "$IP/maintenance/Maintenance.php";

class MigrateCommentBlock extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Rename a comment block in the database' );
		$this->addOption( 'to', 'New comment block name', true, true, 't' );
		$this->addOption( 'from', 'Old comment block name (defaults to MD5 hash of new comment block name)',
			false, true, 'f' );
	}

	/**
	 * @param IDatabase $db
	 * @param string $value
	 * @return int
	 */
	private function numRows( IDatabase $db, string $value ): int {
		return $db->selectRowCount(
			[
				'cs_comments'
			],
			'*',
			[
				'cst_c_block_name' => $value
			],
			__METHOD__
		);
	}

	/**
	 * @param string $message
	 * @param IDatabase $db
	 * @param string $from
	 * @param string $to
	 */
	private function report( string $message, IDatabase $db, string $from, string $to ): void {
		if ( $this->mQuiet ) {
			return;
		}
		$this->output( $message . ': ' . PHP_EOL );
		$this->output( $from . ': ' . $this->numRows( $db, $from ) . PHP_EOL );
		$this->output( $to . ': ' . $this->numRows( $db, $to ) . PHP_EOL );
	}

	public function execute() {
		$to = $this->getOption( 'to' );
		$from = $this->getOption( 'from', 'csc_' . md5( $to ) );
		$dbw = $this->getDB( DB_PRIMARY );
		$this->report( 'Before migration', $dbw, $from, $to );
		$dbw->update(
			'cs_comments',
			[
				'cst_c_block_name' => $to
			],
			[
				'cst_c_block_name' => $from
			],
			__METHOD__
		);
		$this->report( 'After migration', $dbw, $from, $to );
	}
}

$maintClass = MigrateCommentBlock::class;
require_once RUN_MAINTENANCE_IF_MAIN;
