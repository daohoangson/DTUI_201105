<?php

/**
 * Class to handle turning raw post news feed events into renderable output
 *
 * @author kier
 *
 */
class XenForo_NewsFeedHandler_DiscussionMessage_Post extends XenForo_NewsFeedHandler_DiscussionMessage
{
	/**
	 * @var XenForo_Model_Post
	 */
	protected $_postModel = null;

	/**
	 * @var XenForo_Model_Thread
	 */
	protected $_threadModel = null;

	/**
	 * Fetches related content (posts) by IDs
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_NewsFeed $model
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$postModel = $this->_getPostModel();
		$threadModel = $this->_getThreadModel();

		$posts = $postModel->getPostsByIds($contentIds, array(
			'join' => XenForo_Model_Post::FETCH_THREAD | XenForo_Model_Post::FETCH_FORUM,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));

		$hasAttachments = false;

		foreach ($posts AS &$post)
		{
			$post['hasPreview'] = $threadModel->hasPreview($post);

			if ($post['attach_count'])
			{
				$hasAttachments = true;
				$post['attachments'] = array();
			}
		}

		if ($hasAttachments)
		{
			$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');

			foreach ($attachmentModel->getAttachmentsByContentIds('post', $contentIds) AS $attachmentId => $attachment)
			{
				$posts[$attachment['content_id']]['attachments'][$attachmentId] = $attachmentModel->prepareAttachment($attachment);
			}
		}

		return $postModel->unserializePermissionsInList($posts, 'node_permission_cache');
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
		return $this->_getPostModel()->canViewPostAndContainer(
			$content, $content, $content, $null, $content['permissions'], $viewingUser
		);
	}

	/**
	 * Returns the primary key names for posts
	 *
	 * @return array post_id, thread_id
	 */
	protected function _getContentPrimaryKeynames()
	{
		return array('post_id', 'thread_id', 'title', 'message', 'hasPreview', 'attach_count', 'attachments', 'user_id', 'username');
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		if (!$this->_postModel)
		{
			$this->_postModel = XenForo_Model::create('XenForo_Model_Post');
		}

		return $this->_postModel;
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		if (!$this->_threadModel)
		{
			$this->_threadModel = XenForo_Model::create('XenForo_Model_Thread');
		}

		return $this->_threadModel;
	}
}