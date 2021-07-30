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

class MigrateToAbstractSchema extends LoggedUpdateMaintenance {
	/**
	 * @return bool
	 */
	protected function doDBUpdates(): bool {
		$dbw = $this->getDB( DB_PRIMARY );

		if ( !$dbw->tableExists( 'cs_comment_data', __METHOD__ ) ) {
			return true;
		}

		$rows = $dbw->select(
			[
				'cs_comment_data'
			],
			[
				'cst_page_id',
				'cst_assoc_page_id',
				'cst_comment_title',
				'cst_id'
			],
			[
				'cst_parent_page_id IS NULL'
			],
			__METHOD__
		);
		$insert = [];
		foreach ( $rows as $row ) {
			$insert[] = [
				'cst_c_comment_page_id' => $row->cst_page_id,
				'cst_c_assoc_page_id' => $row->cst_assoc_page_id,
				'cst_c_comment_title' => $row->cst_comment_title,
				'cst_c_block_name' => ( $row->cst_id === 'cs-comments' ) ? null : $row->cst_id
			];
		}
		$dbw->insert(
			'cs_comments',
			$insert,
			__METHOD__
		);

		$rows = $dbw->select(
			[
				'cs_comment_data'
			],
			[
				'cst_page_id',
				'cst_parent_page_id'
			],
			[
				'cst_parent_page_id IS NOT NULL'
			],
			__METHOD__
		);
		$insert = [];
		foreach ( $rows as $row ) {
			$insert[] = [
				'cst_r_reply_page_id' => $row->cst_page_id,
				'cst_r_comment_page_id' => $row->cst_parent_page_id
			];
		}
		$dbw->insert(
			'cs_replies',
			$insert,
			__METHOD__
		);

		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey(): string {
		return 'comment-streams-migratetoabstractschema';
	}
}
