<?php

/**
 * Helper for user profile related pages.
 * Provides validation methods, amongst other things.
 *
 * @package XenForo_Thread
 */
class XenForo_ControllerHelper_UserProfile extends XenForo_ControllerHelper_Abstract
{
	/**
	 * The current visiting user.
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

	public function assertUserProfileValidAndViewable($userId, array $fetchOptions = array())
	{
		$user = $this->getUserOrError($userId, $fetchOptions);

		if (!$this->_controller->getModelFromCache('XenForo_Model_UserProfile')->canViewFullUserProfile($user, $errorPhraseKey))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		return $user;
	}

	public function assertProfilePostValidAndViewable($profilePostId, array $profilePostFetchOptions = array(),
		array $userFetchOptions = array()
	)
	{
		$profilePost = $this->getProfilePostOrError($profilePostId, $profilePostFetchOptions);
		$user = $this->assertUserProfileValidAndViewable($profilePost['profile_user_id'], $userFetchOptions);

		if (!$this->_controller->getModelFromCache('XenForo_Model_ProfilePost')->canViewProfilePost($profilePost, $user, $errorPhraseKey))
		{
			throw $this->_controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		return array($profilePost, $user);
	}

	public function assertProfilePostCommentValidAndViewable($commentId, array $commentFetchOptions = array(),
		array $profilePostFetchOptions = array(), array $userFetchOptions = array()
	)
	{
		$comment = $this->getProfilePostCommentOrError($commentId, $commentFetchOptions);
		list($profilePost, $user) = $this->assertProfilePostValidAndViewable($comment['profile_post_id'], $profilePostFetchOptions, $userFetchOptions);

		return array($comment, $profilePost, $user);
	}

	/**
	 * Gets the specified user or throws an error.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions Options that control the data fetched with the record
	 *
	 * @return array
	 */
	public function getUserOrError($userId, array $fetchOptions = array())
	{
		$fetchOptions['followingUserId'] = XenForo_Visitor::getUserId();

		$user = $this->_controller->getModelFromCache('XenForo_Model_User')->getFullUserById($userId, $fetchOptions);
		if (!$user)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('requested_member_not_found'), 404)
			);
		}

		return $user;
	}

	/**
	 * Gets the specified profile post or throws an error.
	 *
	 * @param integer $profilePostId
	 * @param array $fetchOptions Options that control the data fetched with the record
	 *
	 * @return array
	 */
	public function getProfilePostOrError($profilePostId, array $fetchOptions = array())
	{
		if (isset($fetchOptions['join']))
		{
			$fetchOptions['join'] |= XenForo_Model_ProfilePost::FETCH_USER_POSTER;
		}
		else
		{
			$fetchOptions['join'] = XenForo_Model_ProfilePost::FETCH_USER_POSTER;
		}

		$profilePost = $this->_controller->getModelFromCache('XenForo_Model_ProfilePost')->getProfilePostById($profilePostId, $fetchOptions);
		if (!$profilePost)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('requested_profile_post_not_found'), 404)
			);
		}

		return $profilePost;
	}

	/**
	 * Gets the specified profile post comment or throws an error.
	 *
	 * @param integer $commentId
	 * @param array $fetchOptions Options that control the data fetched with the record
	 *
	 * @return array
	 */
	public function getProfilePostCommentOrError($commentId, array $fetchOptions = array())
	{
		$comment = $this->_controller->getModelFromCache('XenForo_Model_ProfilePost')->getProfilePostCommentById($commentId, $fetchOptions);
		if (!$comment)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('requested_comment_not_found'), 404)
			);
		}

		return $comment;
	}
}