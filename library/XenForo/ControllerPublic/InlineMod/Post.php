<?php

/**
 * Inline moderation actions for posts
 *
 * @package XenForo_Post
 */
class XenForo_ControllerPublic_InlineMod_Post extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'posts';

	/**
	 * @return XenForo_Model_InlineMod_Post
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Post');
	}

	/**
	 * Post deletion handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING)
			);

			return $this->executeInlineModAction('deletePosts', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$postIds = $this->getInlineModIds();

			$handler = $this->_getInlineModPostModel();
			if (!$handler->canDeletePosts($postIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$postIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$posts = $this->_getPostModel()->getPostsByIds($postIds, array(
				'join' => XenForo_Model_Post::FETCH_THREAD
			));
			$firstPostCount = 0;
			foreach ($posts AS $post)
			{
				if ($post['post_id'] == $post['first_post_id'])
				{
					$firstPostCount++;
				}
			}

			$viewParams = array(
				'postIds' => $postIds,
				'postCount' => count($postIds),
				'canHardDelete' => $handler->canDeletePosts($postIds, 'hard'),
				'redirect' => $redirect,
				'firstPostCount' => $firstPostCount
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Post_Delete', 'inline_mod_post_delete', $viewParams);
		}
	}

	/**
	 * Undeletes the specified posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeletePosts');
	}

	/**
	 * Approves the specified posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approvePosts');
	}

	/**
	 * Unapproves the specified posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapprovePosts');
	}

	/**
	 * Post move handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMove()
	{
		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'node_id' => XenForo_Input::UINT,
				'title' => XenForo_Input::STRING
			));

			$viewableNodes = $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList();
			if (!isset($viewableNodes[$input['node_id']]))
			{
				return $this->responseNoPermission();
			}

			$options = array(
				'threadNodeId' => $input['node_id'],
				'threadTitle' => $input['title']
			);

			$this->_assertPostOnly();

			$ids = $this->getInlineModIds(false);

			$newThread = $this->getInlineModTypeModel()->movePosts($ids, $options, $errorPhraseKey);
			if (!$newThread)
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('threads', $newThread)
			);
		}
		else // show confirmation dialog
		{
			$postIds = $this->getInlineModIds();

			$handler = $this->_getInlineModPostModel();
			if (!$handler->canMovePosts($postIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$postIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$firstPost = $this->_getPostModel()->getPostById(reset($postIds), array(
				'join' => XenForo_Model_Post::FETCH_THREAD
			));

			$viewParams = array(
				'postIds' => $postIds,
				'postCount' => count($postIds),
				'firstPost' => $firstPost,
				'nodes' => $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList(),
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Post_Move', 'inline_mod_post_move', $viewParams);
		}
	}

	/**
	 * Post merge handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMerge()
	{
		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'target_post_id' => XenForo_Input::UINT,
			));
			$input['new_message'] = $this->getHelper('Editor')->getMessageText('new_message', $this->_input);
			$input['new_message'] = XenForo_Helper_String::autoLinkBbCode($input['new_message']);

			$options = array(
				'targetPostId' => $input['target_post_id'],
				'newMessage' => $input['new_message']
			);

			return $this->executeInlineModAction('mergePosts', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$postIds = $this->getInlineModIds();

			$handler = $this->_getInlineModPostModel();
			if (!$handler->canMergePosts($postIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$posts = $this->_getPostModel()->getPostsByIds($postIds);
			ksort($posts);

			$newMessage = array();
			foreach ($posts AS $post)
			{
				$newMessage[] = $post['message'];
			}

			$redirect = $this->getDynamicRedirect();

			if (!$postIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'postIds' => $postIds,
				'postCount' => count($postIds),
				'posts' => $posts,
				'newMessage' => implode("\n\n", $newMessage),
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Post_Merge', 'inline_mod_post_merge', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_InlineMod_Post
	 */
	public function _getInlineModPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Post');
	}

	/**
	 * @return XenForo_Model_Post
	 */
	public function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}
}