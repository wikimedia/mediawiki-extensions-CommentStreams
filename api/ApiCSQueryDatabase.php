<?php
/*
 * Copyright (c) 2016 The MITRE Corporation
 *
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

class ApiCSQueryDatabase extends ApiBase {

	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}
	public function execute() {
		$query = $this->getMain()->getVal( 'query' );
		if($query == 'childrenCount') {
			$parent = $this->getMain()->getVal( 'parentCommentId' );
			if(!$parent) {
				$this->getResult()->addValue(null, "error", array('code' => 'missingparent', 
																	'info' => 'you must provide a parent comment page ID'));
				return true;
			}
			$childrenCount = DatabaseQuerier::numberOfChildCommentsForParentCommentId($parent);
			$this->getResult()->addValue( null, $this->getModuleName(), array('query' => $query,
																				'childrenCount' => $childrenCount));
		}
		else {
			$this->getResult()->addValue(null, "error", array('code' => 'unrecognizedparameter', 'info' => 'query parameter unrecognized'));
		}
		return true;
	}
	public function getDescription() {
		return 'Query for CommentStreams information.';
	}
	public function getAllowedParams() {
		return array(
			'query' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'parentCommentId' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			)
		);
	}
	public function getParamDescription() {
		return array(
			'query' => 'type of query. Allowed values: childrenCount',
			'parentCommentId' => 'page ID of the parent comment'
		);
	}
	public function getExamples() {
		return null;
	}
	public function getHelpUrls() {
		return '';
	}
}
