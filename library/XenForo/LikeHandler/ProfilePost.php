<?php

/**
 * Handler for the specific profile post-related like aspects.
 *
 * @package XenForo_Like
 */
class XenForo_LikeHandler_ProfilePost extends XenForo_LikeHandler_Abstract
{
	/**
	 * Increments the like counter.
	 * @see XenForo_LikeHandler_Abstract::incrementLikeCounter()
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
		$dw->setExistingData($contentId);
		$dw->set('likes', $dw->get('likes') + $adjustAmount);
		$dw->set('like_users', $latestLikes);
		$dw->save();
	}

	/**
	 * Gets content data (if viewable).
	 * @see XenForo_LikeHandler_Abstract::getContentData()
	 */
	public function getContentData(array $contentIds, array $viewingUser)
	{
		$profilePostModel = XenForo_Model::create('XenForo_Model_ProfilePost');

		$profilePosts = $profilePostModel->getProfilePostsByIds($contentIds, array(
			'join' => XenForo_Model_ProfilePost::FETCH_USER_RECEIVER
		));

		$userIds = array();
		foreach ($profilePosts AS $profilePost)
		{
			$userIds[$profilePost['profile_user_id']] = true;
		}
		$users = $profilePostModel->getModelFromCache('XenForo_Model_User')->getUsersByIds(array_keys($userIds), array(
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
				$user = $users[$profilePost['profile_user_id']];
				if (!$profilePostModel->canViewProfilePostAndContainer(
					$profilePost, $user, $null, $viewingUser
				))
				{
					unset($profilePosts[$key]);
				}
				else
				{
					$profilePost['profileUser'] = $user;
				}
			}
		}

		return $profilePosts;
	}

	/**
	 * Gets the name of the template that will be used when listing likes of this type.
	 *
	 * @return string news_feed_item_profile_post_like
	 */
	public function getListTemplateName()
	{
		return 'news_feed_item_profile_post_like';
	}
}