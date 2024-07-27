<?php

class wAvatar {
	/**
	 * @param int $userId User's internal ID number
	 * @param string $size Avatar image size
	 * - 's' for small (16x16px)
	 * - 'm' for medium (30x30px)
	 * - 'ml' for medium-large (50x50px)
	 * - 'l' for large (75x75px)
	 */
	public function __construct( $userId, $size ) {
	}

	/**
	 * Fetches the avatar image's name from the file backend
	 *
	 * @return string Avatar image's file name i.e. default_l.gif or wikidb_3_l.jpg;
	 * - First part for non-default images is the database name
	 * - Second part is the user's ID number
	 * - Third part is the letter for image size (s, m, ml or l)
	 */
	public function getAvatarImage() {
	}
}
