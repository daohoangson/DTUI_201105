<?php

/**
 * Handler class for user profile post alerts
 *
 * @package XenForo_Alert
 */
class XenForo_AlertHandler_DiscussionMessage_ProfilePost extends XenForo_AlertHandler_DiscussionMessage
{
	/**
	 * Fetches related content (user profile posts) by IDs
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model Alert model invoking this
	 * @param integer $userId User ID the alerts are for
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		return $model->getModelFromCache('XenForo_Model_ProfilePost')->getProfilePostsByIds($contentIds, array(
			'join' => XenForo_Model_ProfilePost::FETCH_USER_RECEIVER
		));
	}
}