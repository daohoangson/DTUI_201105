<?php

/**
 * Handler for the specific post-related like aspects.
 *
 * @package XenForo_Like
 */
class XenForo_LikeHandler_Post extends XenForo_LikeHandler_Abstract
{
	/**
	 * Increments the like counter.
	 * @see XenForo_LikeHandler_Abstract::incrementLikeCounter()
	 */
	public function incrementLikeCounter($contentId, array $latestLikes, $adjustAmount = 1)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
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
		$postModel = XenForo_Model::create('XenForo_Model_Post');
		$posts = $postModel->getPostsByIds($contentIds, array(
			'join' => XenForo_Model_Post::FETCH_THREAD | XenForo_Model_Post::FETCH_FORUM,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		$posts = $postModel->unserializePermissionsInList($posts, 'node_permission_cache');

		$output = array();
		foreach ($posts AS $postId => $post)
		{
			if (!$postModel->canViewPostAndContainer(
				$post, $post, $post, $null, $post['permissions'], $viewingUser
			))
			{
				continue;
			}

			$output[$postId] = $post;
		}

		return $output;
	}

	/**
	 * Gets the name of the template that will be used when listing likes of this type.
	 *
	 * @return string news_feed_item_post_like
	 */
	public function getListTemplateName()
	{
		return 'news_feed_item_post_like';
	}
}