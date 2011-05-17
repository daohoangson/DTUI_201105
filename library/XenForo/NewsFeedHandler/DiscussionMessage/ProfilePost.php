<?php

/**
 * Class to handle turning raw post news feed events into renderable output
 *
 * @package XenForo_NewsFeed
 */
class XenForo_NewsFeedHandler_DiscussionMessage_ProfilePost extends XenForo_NewsFeedHandler_DiscussionMessage
{
	protected $_profilePostModel = null;

	/**
	 * Fetches related content (profile posts) by IDs
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_NewsFeed $model
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$profilePosts = $this->_getProfilePostModel()->getProfilePostsByIds($contentIds, array(
			'join' => XenForo_Model_ProfilePost::FETCH_USER_RECEIVER
		));

		$userIds = array();
		foreach ($profilePosts AS $profilePost)
		{
			$userIds[$profilePost['profile_user_id']] = true;
		}
		$users = $model->getModelFromCache('XenForo_Model_User')->getUsersByIds(array_keys($userIds), array(
			'join' => XenForo_Model_User::FETCH_USER_PRIVACY,
			'followingUserId' => $viewingUser['user_id']
		));

		foreach ($profilePosts AS $key => &$profilePost)
		{
			if (!isset($users[$profilePost['profile_user_id']]))
			{
				unset($profilePosts[$key]);
			}
			else
			{
				$profilePost['profileUser'] = $users[$profilePost['profile_user_id']];
			}
		}

		return $profilePosts;
	}

	/**
	 * Determines if the given news feed item is viewable.
	 *
	 * @param array $item
	 * @param mixed $content
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		return $this->_getProfilePostModel()->canViewProfilePostAndContainer(
			$content, $content['profileUser'], $null, $viewingUser
		);
	}

	/**
	 * Returns the primary key names for user profile posts
	 *
	 * @return array profile_post_id
	 */
	protected function _getContentPrimaryKeynames()
	{
		return array('profile_post_id', 'message', 'user_id', 'username', 'profile_username', 'profile_user_id');
	}

	/**
	 * Creates a 'userReceiver' key containing 'user_id' and 'username' in the item array
	 *
	 * @param array $item
	 *
	 * @return array $item
	 */
	protected function _prepareInsert(array $item)
	{
		$item['userReceiver'] = array(
			'user_id' => $item['content']['profile_user_id'],
			'username' => $item['content']['profile_username']
		);

		return $item;
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		if (!$this->_profilePostModel)
		{
			$this->_profilePostModel = XenForo_Model::create('XenForo_Model_ProfilePost');
		}

		return $this->_profilePostModel;
	}
}