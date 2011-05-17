<?php

/**
 * Model to handle inline mod-style actions on posts. Generally, these are simply
 * bulk actions. They can be applied to other circumstances if desired.
 *
 * @package XenForo_Post
 */
class XenForo_Model_InlineMod_Post extends XenForo_Model
{
	/**
	 * Determines if the selected post IDs can be deleted.
	 *
	 * @param array $postIds List of post IDs check
	 * @param string $deleteType The type of deletion being requested (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canDeletePosts(array $postIds, $deleteType = 'soft', &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);
		return $this->canDeletePostsData($posts, $deleteType, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected posts can be deleted. This is a slightly more
	 * "internal" version of the canDeletePosts() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $posts List of information about posts to be deleted
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param array $threads List of information about threads the posts are in
	 * @param array $forums List of information about forums the threads/posts are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canDeletePostsData(array $posts, $deleteType, array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		// note: this cannot use _checkPermissionOnPosts because of extra param

		if (!$posts)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);

		$postModel = $this->_getPostModel();

		foreach ($posts AS $post)
		{
			list($thread, $forum) = $this->_getThreadAndForumFromPost($post, $threads, $forums);

			if (!$postModel->canDeletePost($post, $thread, $forum, $deleteType, $errorKey, $forum['nodePermissions'], $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes the specified posts if permissions are sufficient.
	 *
	 * @param array $postIds List of post IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function deletePosts(array $postIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'deleteType' => '',
				'reason' => ''
			), $options
		);

		if (!$options['deleteType'])
		{
			throw new XenForo_Exception('No deletion type specified.');
		}

		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canDeletePostsData($posts, $options['deleteType'], $threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$skipThreads = array();

		foreach ($posts AS $post)
		{
			if (!empty($skipThreads[$post['thread_id']]))
			{
				continue;
			}

			list($thread, $forum) = $this->_getThreadAndForumFromPost($post, $threads, $forums);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($post);
			if (!$dw->get('post_id'))
			{
				// this may happen if the post was already removed
				continue;
			}
			if ($options['deleteType'] == 'hard')
			{
				$dw->delete();

				if ($dw->discussionDeleted())
				{
					// all posts in this thread have been removed, so skip them
					$skipThreads[$post['thread_id']] = true;
				}
			}
			else
			{
				$dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_DELETE_REASON, $options['reason']);
				$dw->set('message_state', 'deleted');
				$dw->save();
			}
		}

		return true;
	}

	/**
	 * Determines if the selected post IDs can be undeleted.
	 *
	 * @param array $postIds List of post IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canUndeletePosts(array $postIds, &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);
		return $this->canUndeletePostsData($posts, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected posts can be undeleted. This is a slightly more
	 * "internal" version of the canUndeletePosts() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $posts List of information about posts to be checked
	 * @param array $threads List of information about threads the posts are in
	 * @param array $forums List of information about forums the threads/posts are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canUndeletePostsData(array $posts, array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnPosts('canUndeletePost', $posts, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Undeletes the specified posts if permissions are sufficient.
	 *
	 * @param array $postIds List of post IDs to undelete
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function undeletePosts(array $postIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canUndeletePostsData($posts, $threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updatePostsMessageState($posts, $threads, $forums, 'visible', 'deleted');

		return true;
	}

	/**
	 * Determines if the selected post IDs can be approved/unapproved.
	 *
	 * @param array $postIds List of post IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canApproveUnapprovePosts(array $postIds, &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);
		return $this->canApproveUnapprovePostsData($posts, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected posts can be approved/unapproved. This is a slightly more
	 * "internal" version of the canApproveUnapprovePosts() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $posts List of information about posts to be checked
	 * @param array $threads List of information about threads the posts are in
	 * @param array $forums List of information about forums the threads/posts are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canApproveUnapprovePostsData(array $posts, array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnPosts('canApproveUnapprovePost', $posts, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Approves the specified posts if permissions are sufficient.
	 *
	 * @param array $postIds List of post IDs to approve
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function approvePosts(array $postIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canApproveUnapprovePostsData($posts, $threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updatePostsMessageState($posts, $threads, $forums, 'visible', 'moderated');

		return true;
	}

	/**
	 * Unapproves the specified posts if permissions are sufficient.
	 *
	 * @param array $postIds List of post IDs to unapprove
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function unapprovePosts(array $postIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canApproveUnapprovePostsData($posts, $threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updatePostsMessageState($posts, $threads, $forums, 'moderated', 'visible');

		return true;
	}

	/**
	 * Determines if the selected post IDs can be moved.
	 *
	 * @param array $postIds List of post IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMovePosts(array $postIds, &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);
		return $this->canMovePostsData($posts, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected posts can be moved. This is a slightly more
	 * "internal" version of the canMovePosts() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $posts List of information about posts to be checked
	 * @param array $threads List of information about threads the posts are in
	 * @param array $forums List of information about forums the threads/posts are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMovePostsData(array $posts, array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnPosts('canMovePost', $posts, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Moves the specified posts if permissions are sufficient.
	 *
	 * @param array $postIds List of post IDs to move
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return array|false False if error; array of info about new thread otherwise
	 */
	public function movePosts(array $postIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canMovePostsData($posts, $threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$options = array_merge(
			array(
				'threadNodeId' => 0,
				'threadTitle' => ''
			),
			$options
		);

		$forum = $this->_getForumModel()->getForumById($options['threadNodeId']);
		if (!$forum)
		{
			$errorKey = 'please_select_valid_forum';
			return false;
		}

		$newThread = array(
			'node_id' => $options['threadNodeId'],
			'title' => $options['threadTitle']
		);

		return $this->_getPostModel()->movePostsToNewThread($posts, $threads, $newThread);
	}

	/**
	 * Determines if the selected post IDs can be merged.
	 *
	 * @param array $postIds List of post IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMergePosts(array $postIds, &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);
		return $this->canMergePostsData($posts, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected posts can be merged. This is a slightly more
	 * "internal" version of the canMergePosts() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $posts List of information about posts to be checked
	 * @param array $threads List of information about threads the posts are in
	 * @param array $forums List of information about forums the threads/posts are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMergePostsData(array $posts, array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		if (count($posts) <= 1)
		{
			$errorKey = 'please_select_more_one_post_merge';
			return false;
		}

		return $this->_checkPermissionOnPosts('canMergePost', $posts, $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Merges the specified posts if permissions are sufficient.
	 *
	 * @param array $postIds List of post IDs to merge
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function mergePosts(array $postIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($posts, $threads, $forums) = $this->getPostsAndParentData($postIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canMergePostsData($posts, $threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$options = array_merge(
			array(
				'targetPostId' => 0,
				'newMessage' => ''
			),
			$options
		);

		return $this->_getPostModel()->mergePosts($posts, $threads, $options['targetPostId'], $options['newMessage']);
	}

	/**
	 * Checks a standard post permission against a collection of posts.
	 * True is returned only if the action is possible on all posts.
	 *
	 * @param string $permissionMethod Name of the permission method to call in the post model
	 * @param array $posts List of posts to check
	 * @param array $threads List of threads the posts are in
	 * @param array $forums List of forums the threads are in
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	protected function _checkPermissionOnPosts($permissionMethod, array $posts, array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		if (!$posts)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);

		$postModel = $this->_getPostModel();

		foreach ($posts AS $post)
		{
			list($thread, $forum) = $this->_getThreadAndForumFromPost($post, $threads, $forums);

			if (!$postModel->$permissionMethod($post, $thread, $forum, $errorKey, $forum['nodePermissions'], $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Internal helper to update the message_state of a collection of posts.
	 *
	 * @param array $posts Information about the posts to update
	 * @param array $threads Information about the threads that the posts are in
	 * @param array $forums Information about the forums that the threads/posts are in
	 * @param string $newState New message state (visible, moderated, deleted)
	 * @param string|false $expectedOldState If specified, only updates if the old state matches
	 */
	protected function _updatePostsMessageState(array $posts, array $threads, array $forums, $newState, $expectedOldState = false)
	{
		foreach ($posts AS $post)
		{
			list($thread, $forum) = $this->_getThreadAndForumFromPost($post, $threads, $forums);

			if ($post['post_id'] == $thread['first_post_id'])
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
				$dw->setExistingData($thread);

				if ($expectedOldState && $dw->get('discussion_state') != $expectedOldState)
				{
					continue;
				}

				$dw->set('discussion_state', $newState);
				$dw->save();
			}
			else
			{
				if ($expectedOldState && $post['message_state'] != $expectedOldState)
				{
					continue;
				}

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
				$dw->setExistingData($post);
				$dw->set('message_state', $newState);
				$dw->save();
			}
		}
	}

	/**
	 * Bulk update 1 or more fields in the given threads.
	 *
	 * @param array $posts List of posts to update
	 * @param array $threads List of threads to that the posts are in
	 * @param array $forums List of forums threads are in
	 * @param array $updates Key-value pairs to update
	 */
	protected function _updatePostsBulk(array $posts, array $threads, array $forums, array $updates)
	{
		foreach ($posts AS $post)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($post);
			$dw->bulkSet($updates);
			$dw->save();
		}
	}

	/**
	 * Gets information about the thread and forum a post belongs to,
	 * from the post's info.
	 *
	 * @param array $post Info about the post
	 * @param array $threads List of threads that the post could belong to
	 * @param array $forums List of forums that the post's thread could be long to
	 *
	 * @return array Format: [0] => thread, [1] => forum
	 */
	protected function _getThreadAndForumFromPost(array $post, array $threads, array $forums)
	{
		$thread = $threads[$post['thread_id']];
		$forum = $forums[$thread['node_id']];

		return array($thread, $forum);
	}

	/**
	 * From a list of post IDs, gets info about the posts, their threads, and
	 * the forums the threads are in.
	 *
	 * @param array $postIds List of post IDs
	 * @param array|null $viewingUser
	 *
	 * @return array Format: [0] => list of posts, [1] => list of threads, [2] => list of forums
	 */
	public function getPostsAndParentData(array $postIds, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return $this->_getPostModel()->getPostsAndParentData($postIds, $viewingUser['permission_combination_id']);
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}
}