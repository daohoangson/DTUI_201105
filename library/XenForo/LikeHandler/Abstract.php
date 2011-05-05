<?php

/**
 * Abstract handling for the content-specific aspects of liking content.
 *
 * @package XenForo_Like
 */
abstract class XenForo_LikeHandler_Abstract
{
	/**
	 * Increments the like counter for a particular piece of content.
	 *
	 * @param integer $contentId
	 * @param array $latestLikes A list of the latest likes this content has received.
	 * @param integer $adjustAmount Adjusts the number of likes by this amount
	 */
	abstract public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1);

	/**
	 * Gets data for specified content IDs. This must check viewing permissions!
	 *
	 * @param array $contentIds
	 * @param array $viewingUser
	 *
	 * @return array Keyed by content ID
	 */
	abstract public function getContentData(array $contentIds, array $viewingUser);

	/**
	 * Gets the name of the template that will be used when listing likes of this type.
	 *
	 * @return string news_feed_item_{$contentType}_like
	 */
	abstract public function getListTemplateName();
}