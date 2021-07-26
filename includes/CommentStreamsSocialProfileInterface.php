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

namespace MediaWiki\Extension\CommentStreams;

use ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;
use wAvatar;

class CommentStreamsSocialProfileInterface {
	public const CONSTRUCTOR_OPTIONS = [
		'UploadPath'
	];

	/**
	 * @var bool
	 */
	private $isLoaded;

	/**
	 * @var string
	 */
	private $uploadPath;

	/**
	 * @param ServiceOptions $options
	 * @throws ConfigException
	 */
	public function __construct( ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->isLoaded = class_exists( 'wAvatar' );
		$this->uploadPath = $options->get( 'UploadPath' );
	}

	/**
	 * @param UserIdentity $user
	 * @return ?string
	 */
	public function getAvatar( UserIdentity $user ): ?string {
		if ( !$this->isLoaded ) {
			return null;
		}
		$avatar = new wAvatar( $user->getId(), 'l' );
		return $this->uploadPath . '/avatars/' . $avatar->getAvatarImage();
	}
}
