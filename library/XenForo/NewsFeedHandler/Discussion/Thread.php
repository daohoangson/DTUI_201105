<?php

/**
 * Class to handle turning raw thread news feed events into renderable output
 *
 * @author kier
 *
 */
class XenForo_NewsFeedHandler_Discussion_Thread extends XenForo_NewsFeedHandler_Discussion
{
	/**
	 * @var XenForo_Model_Thread
	 */
	protected $_threadModel = null;

	/**
	 * Fetches related content (threads) by IDs
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_NewsFeed $model
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		$threadModel = $this->_getThreadModel();

		$threads = $threadModel->getThreadsByIds($contentIds, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_FIRSTPOST,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));

		$hasAttachments = array();

		foreach ($threads AS &$thread)
		{
			$thread['hasPreview'] = $threadModel->hasPreview($thread);

			if ($thread['attach_count'])
			{
				$hasAttachments[$thread['first_post_id']] = $thread['thread_id'];
				$thread['attachments'] = array();
			}
		}

		if ($hasAttachments)
		{
			$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');

			foreach ($attachmentModel->getAttachmentsByContentIds('post', array_keys($hasAttachments)) AS $attachmentId => $attachment)
			{
				$threadId = $hasAttachments[$attachment['content_id']];
				$threads[$threadId]['attachments'][$attachmentId] = $attachmentModel->prepareAttachment($attachment);
			}
		}

		return $model->unserializePermissionsInList($threads, 'node_permission_cache');
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
		return $this->_getThreadModel()->canViewThreadAndContainer(
			$content, $content, $null, $content['permissions'], $viewingUser
		);
	}

	/**
	 * Returns the primary key names for threads
	 *
	 * @return array thread_id, forum_id
	 */
	protected function _getContentPrimaryKeynames()
	{
		return array('thread_id', 'node_id', 'node_title', 'message', 'hasPreview', 'attach_count', 'attachments', 'first_post_id');
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