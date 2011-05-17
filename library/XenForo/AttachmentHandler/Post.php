<?php

/**
 * Post-specific attachment handler.
 *
 * @package XenForo_Attachment
 */
class XenForo_AttachmentHandler_Post extends XenForo_AttachmentHandler_Abstract
{
	protected $_postModel = null;

	/**
	 * Key of primary content in content data array.
	 *
	 * @var string
	 */
	protected $_contentIdKey = 'post_id';

	/**
	 * Determines if attachments and be uploaded and managed in this context.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canUploadAndManageAttachments()
	 */
	protected function _canUploadAndManageAttachments(array $contentData, array $viewingUser)
	{
		$postModel = $this->_getPostModel();

		if (!empty($contentData['post_id']))
		{
			$post = $postModel->getPostById($contentData['post_id']);
			if ($post)
			{
				$contentData['thread_id'] = $post['thread_id'];
			}
		}

		if (!empty($contentData['thread_id']))
		{
			$thread = XenForo_Model::create('XenForo_Model_Thread')->getThreadById($contentData['thread_id']);
			if ($thread)
			{
				$contentData['node_id'] = $thread['node_id'];
			}
		}

		if (!empty($contentData['node_id']))
		{
			$forumModel = XenForo_Model::create('XenForo_Model_Forum');
			$forum = $forumModel->getForumById($contentData['node_id'], array(
				'permissionCombinationId' => $viewingUser['permission_combination_id']
			));
			if ($forum)
			{
				$permissions = XenForo_Permission::unserializePermissions($forum['node_permission_cache']);

				if (!empty($contentData['post_id']))
				{
					// editing a post, have to be able to edit this post first
					if (!$postModel->canViewPost($post, $thread, $forum, $null, $permissions, $viewingUser)
						|| !$postModel->canEditPost($post, $thread, $forum, $null, $permissions, $viewingUser))
					{
						return false;
					}
				}

				return (
					$forumModel->canViewForum($forum, $null, $permissions, $viewingUser)
					&& $forumModel->canUploadAndManageAttachment($forum, $null, $permissions, $viewingUser)
				);
			}
		}

		return false; // invalid content data
	}

	/**
	 * Determines if the specified attachment can be viewed.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canViewAttachment()
	 */
	protected function _canViewAttachment(array $attachment, array $viewingUser)
	{
		$postModel = $this->_getPostModel();

		$post = $postModel->getPostById($attachment['content_id'], array(
			'join' => XenForo_Model_Post::FETCH_THREAD | XenForo_Model_Post::FETCH_FORUM | XenForo_Model_Post::FETCH_USER,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		if (!$post)
		{
			return false;
		}

		$permissions = XenForo_Permission::unserializePermissions($post['node_permission_cache']);

		$canViewPost = $postModel->canViewPostAndContainer(
			$post, $post, $post, $null, $permissions, $viewingUser
		);
		if (!$canViewPost)
		{
			return false;
		}

		return $postModel->canViewAttachmentOnPost(
			$post, $post, $post, $null, $permissions, $viewingUser
		);
	}

	/**
	 * Code to run after deleting an associated attachment.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::attachmentPostDelete()
	 */
	public function attachmentPostDelete(array $attachment, Zend_Db_Adapter_Abstract $db)
	{
		$db->query('
			UPDATE xf_post
			SET attach_count = IF(attach_count > 0, attach_count - 1, 0)
			WHERE post_id = ?
		', $attachment['content_id']);
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
}