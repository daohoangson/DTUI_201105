<?php

/**
 * Abstract moderation queue handler.
 *
 * @package XenForo_Moderation
 */
abstract class XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * Returns all the moderation queue entry for this content type that are visible/manageable to the viewing user.
	 *
	 * @param array $contentIds Array of queue content IDs
	 * @param array $viewingUser Viewing user array
	 *
	 * @return array List of entries that can be seen/managed, [content id] => [message, user, contentTypeTitle, title, titleEdit, link]
	 */
	abstract public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser);

	/**
	 * Approves the specified entry in the moderation queue. The title param may be ignored
	 * if the type does not have a title.
	 *
	 * @param integer $contentId
	 * @param string $message
	 * @param string $title May be ignored
	 *
	 * @return boolean
	 */
	abstract public function approveModerationQueueEntry($contentId, $message, $title);

	/**
	 * Deletes the specified moderation queue entry. Note that this should only do a soft-delete if available.
	 *
	 * @param integer $contentId
	 *
	 * @return boolean
	 */
	abstract public function deleteModerationQueueEntry($contentId);
}