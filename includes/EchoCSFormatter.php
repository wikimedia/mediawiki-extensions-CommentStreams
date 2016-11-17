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

class EchoCSFormatter extends EchoBasicFormatter {

	/**
	 * @param EchoEvent $event the event for which the notification is being
	 * formatted
	 * @param string $param the parameter being formatted
	 * @param Message $message Message the message object to add the parameter to
	 * @param User $user the user the notification is being sent to
	 */
	protected function processParam( $event, $param, $message, $user ) {
		if ( $param === 'comment_author_username' ) {
			$message->params( $event->getExtraParam( 'comment_author_username' ) );
		} elseif ( $param === 'comment_author_display_name' ) {
			$message->params( $event->getExtraParam(
				'comment_author_display_name' ) );
		} elseif ( $param === 'associated_page_display_title' ) {
			$message->params( $event->getExtraParam(
				'associated_page_display_title' ) );
		} elseif ( $param === 'comment_title' ) {
			$message->params( $event->getExtraParam( 'comment_title' ) );
		} elseif ( $param === 'comment_wikitext' ) {
			$message->params( $event->getExtraParam( 'comment_wikitext' ) );
		} else {
			parent::processParam( $event, $param, $message, $user );
		}
	}
}
