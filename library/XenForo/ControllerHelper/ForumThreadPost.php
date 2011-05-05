<?php

/**
 * Helper for forum, thread, and post related pages.
 * Provides validation methods, amongst other things.
 *
 * @package XenForo_Thread
 */
class XenForo_ControllerHelper_ForumThreadPost extends XenForo_ControllerHelper_Abstract
{
	/**
	 * The current browsing user.
	 *
	 * @var XenForo_Visitor
	 */
	protected $_visitor;

	/**
	 * Additional constructor setup behavior.
	 */
	protected function _constructSetup()
	{
		$this->_visitor = XenForo_Visitor::getInstance();
	}

	/**
	 * Checks that a forum is valid and viewable, before returning the forum's info.
	 *
	 * @param integer|string $forumIdOrName ID or node name of forum
	 * @param array $fetchOptions Extra data to fetch wtih the forum
	 *
	 * @return array Forum info
	 */
	public function assertForumValidAndViewable($forumIdOrName, array $fetchOptions = array())
	{
		$fetchOptions += array('permissionCombinationId' => $this->_visitor['permission_combination_id']);

		$forum = $this->getForumOrError($forumIdOrName, $fetchOptions);
		if (isset($forum['node_permission_cache']))
		{
			$this->_visitor->setNodePermissions($forum['node_id'], $forum['node_permission_cache']);
			unset($forum['node_permission_cache']);
		}

		if (!$this->_controller->getModelFromCache('XenForo_Model_Forum')->canViewForum($forum, $errorPhraseKey))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($forum['effective_style_id'])
		{
			$this->_controller->setViewStateChange('styleId', $forum['effective_style_id']);
		}

		return $forum;
	}

	/**
	 * Checks that a thread is valid and viewable, before returning the thread
	 * and containing forum's info.
	 *
	 * @param integer $threadId
	 * @param array $threadFetchOptions Extra data to fetch with the thread
	 * @param array $forumFetchOptions Extra data to fetch wtih the forum
	 *
	 * @return array Format: [0] => thread info, [1] => forum info
	 */
	public function assertThreadValidAndViewable($threadId,
		array $threadFetchOptions = array(), array $forumFetchOptions = array()
	)
	{
		$thread = $this->getThreadOrError($threadId, $threadFetchOptions);
		$forum = $this->assertForumValidAndViewable($thread['node_id'], $forumFetchOptions);

		$threadModel = $this->_controller->getModelFromCache('XenForo_Model_Thread');

		if (!$threadModel->canViewThread($thread, $forum, $errorPhraseKey))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$thread = $threadModel->prepareThread($thread, $forum);

		return array($thread, $forum);
	}

	/**
	 * Checks that a thread is valid and viewable, before returning the post, thread,
	 * and containing forum's info.
	 *
	 * @param integer $postId
	 * @param array $postFetchOptions Extra data to fetch with the post
	 * @param array $threadFetchOptions Extra data to fetch with the thread
	 * @param array $forumFetchOptions Extra data to fetch wtih the forum
	 *
	 * @return array Format: [0] => post info, [1] => thread info, [2] => forum info
	 */
	public function assertPostValidAndViewable($postId, array $postFetchOptions = array(),
		array $threadFetchOptions = array(), array $forumFetchOptions = array()
	)
	{
		$post = $this->getPostOrError($postId, $postFetchOptions);
		list($thread, $forum) = $this->assertThreadValidAndViewable(
			$post['thread_id'], $threadFetchOptions, $forumFetchOptions
		);

		$postModel = $this->_controller->getModelFromCache('XenForo_Model_Post');

		if (!$postModel->canViewPost($post, $thread, $forum, $errorPhraseKey))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$post = $postModel->preparePost($post, $thread, $forum);

		return array($post, $thread, $forum);
	}

	/**
	 * Gets the specified post or throws an error.
	 *
	 * @param integer $postId
	 * @param array $fetchOptions Options that control the data fetched with the post
	 *
	 * @return array
	 */
	public function getPostOrError($postId, array $fetchOptions = array())
	{
		$post = $this->_controller->getModelFromCache('XenForo_Model_Post')->getPostById($postId, $fetchOptions);
		if (!$post)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('requested_post_not_found'), 404)
			);
		}

		return $post;
	}

	/**
	 * Gets the specified thread or throws an error.
	 *
	 * @param integer $threadId
	 * @param array $fetchOptions Options that control the data fetched with the thread
	 *
	 * @return array
	 */
	public function getThreadOrError($threadId, array $fetchOptions = array())
	{
		$thread = $this->_controller->getModelFromCache('XenForo_Model_Thread')->getThreadById($threadId, $fetchOptions);
		if (!$thread)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('requested_thread_not_found'), 404)
			);
		}

		return $thread;
	}

	/**
	 * Gets the specified forum or throws an error.
	 *
	 * @param integer|string $forumIdOrName Forum ID or node name
	 * @param array $fetchOptions Options that control the data fetched with the forum
	 *
	 * @return array
	 */
	public function getForumOrError($forumIdOrName, array $fetchOptions = array())
	{
		if (is_int($forumIdOrName) || $forumIdOrName === strval(intval($forumIdOrName)))
		{
			$forum = $this->_controller->getModelFromCache('XenForo_Model_Forum')->getForumById(
				$forumIdOrName, $fetchOptions
			);
		}
		else
		{
			$forum = $this->_controller->getModelFromCache('XenForo_Model_Forum')->getForumByNodeName(
				$forumIdOrName, $fetchOptions
			);
		}

		if (!$forum)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('requested_forum_not_found'), 404)
			);
		}

		return $forum;
	}

	/**
	 * Gets the breadcrumbs that relate to the specified node, including
	 * the supplied node.
	 *
	 * @param array $forum Information about the node
	 * @param boolean $includeSelf Whether to include this node in the bread crumbs
	 *
	 * @return unknown_type
	 */
	public function getNodeBreadCrumbs(array $forum, $includeSelf = true)
	{
		return $this->_controller->getModelFromCache('XenForo_Model_Node')->getNodeBreadCrumbs($forum, $includeSelf);
	}
}