<?php

/**
 * Controller for managing profile posts.
 *
 * @package XenForo_ProfilePost
 */
class XenForo_ControllerPublic_ProfilePost extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Redirects to the profile post on the specified user's profile.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		return $this->getProfilePostSpecificRedirect(
			$profilePost, $user, XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT
		);
	}

	/**
	 * Helper to get the correct redirect to a specific profile post.
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param constant $redirectType
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function getProfilePostSpecificRedirect(array $profilePost, array $user,
		$redirectType = XenForo_ControllerResponse_Redirect::SUCCESS
	)
	{
		if ($profilePost['post_date'] < XenForo_Application::$time)
		{
			$profilePostModel = $this->_getProfilePostModel();

			$conditions = $profilePostModel->getPermissionBasedProfilePostConditions($user) + array(
				'post_date' => array('>', $profilePost['post_date'])
			);

			$count = $profilePostModel->countProfilePostsForUserId($user['user_id'], $conditions);

			$page = floor($count / XenForo_Application::get('options')->messagesPerPage) + 1;
		}
		else
		{
			$page = 1;
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('members', $user, array('page' => $page)) . '#profile-post-' . $profilePost['profile_post_id']
		);
	}

	/**
	 * Displays a form to edit a profile post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		$profilePostModel = $this->_getProfilePostModel();
		if (!$profilePostModel->canEditProfilePost($profilePost, $user, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$viewParams = array(
			'profilePost' => $profilePost,
			'user' => $user,
			'canDeletePost' => $profilePostModel->canDeleteProfilePost($profilePost, $user, 'soft'),
			'canHardDeletePost' => $profilePostModel->canDeleteProfilePost($profilePost, $user, 'hard'),
		);

		return $this->responseView('XenForo_ViewPublic_ProfilePost_Edit', 'profile_post_edit', $viewParams);
	}

	/**
	 * Edits a profile post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		if (!$this->_getProfilePostModel()->canEditProfilePost($profilePost, $user, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$inputMessage = $this->_input->filterSingle('message', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
		$dw->setExistingData($profilePostId);
		$dw->set('message', $inputMessage);
		$dw->save();

		return $this->getProfilePostSpecificRedirect($profilePost, $user);
	}

	/**
	 * Deletes a profile post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
		$deleteType = ($hardDelete ? 'hard' : 'soft');

		$profilePostModel = $this->_getProfilePostModel();

		if (!$profilePostModel->canDeleteProfilePost($profilePost, $user, $deleteType, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($this->isConfirmedPost()) // delete profile post
		{
			$reason = $this->_input->filterSingle('reason', XenForo_Input::STRING);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
			$dw->setExistingData($profilePostId);
			if ($deleteType == 'hard')
			{
				$dw->delete();
			}
			else
			{
				$dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_DELETE_REASON, $reason);
				$dw->set('message_state', 'deleted');
				$dw->save();
			}

			XenForo_Helper_Cookie::clearIdFromCookie($profilePostId, 'inlinemod_profilePosts');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user)
			);
		}
		else // show delete confirmation dialog
		{
			$viewParams = array(
				'profilePost' => $profilePost,
				'user' => $user,
				'canHardDelete' => $profilePostModel->canDeleteProfilePost($profilePost, $user, 'hard'),
			);

			return $this->responseView('XenForo_ViewPublic_ProfilePost_Delete', 'profile_post_delete', $viewParams);
		}
	}

	/**
	 * Shows the specified profile post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionShow()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		$profilePostModel = $this->_getProfilePostModel();

		$profilePost = $profilePostModel->prepareProfilePost($profilePost, $user);
		$profilePostModel->addInlineModOptionToProfilePost($profilePost, $user);

		$profilePosts = array($profilePostId => $profilePost);

		$profilePosts = $profilePostModel->addProfilePostCommentsToProfilePosts($profilePosts, array(
			'join' => XenForo_Model_ProfilePost::FETCH_COMMENT_USER
		));
		foreach ($profilePosts AS &$pp)
		{
			if (empty($pp['comments']))
			{
				continue;
			}

			foreach ($pp['comments'] AS &$comment)
			{
				$comment = $profilePostModel->prepareProfilePostComment($comment, $pp, $user);
			}
		}

		$profilePost = reset($profilePosts);

		$viewParams = array(
			'profilePost' => $profilePost,
			'user' => $user,
		);

		return $this->responseView('XenForo_ViewPublic_ProfilePost_Show', 'profile_post', $viewParams);
	}

	/**
	 * Reports this profile post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionReport()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$message)
			{
				return $this->responseError(new XenForo_Phrase('please_enter_reason_for_reporting_this_message'));
			}

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent('profile_post', $profilePost, $message);

			$controllerResponse = $this->getProfilePostSpecificRedirect($profilePost, $user);
			$controllerResponse->redirectMessage = new XenForo_Phrase('thank_you_for_reporting_this_message');
			return $controllerResponse;
		}
		else
		{
			$viewParams = array(
				'profilePost' => $profilePost,
				'user' => $user,
			);

			return $this->responseView('XenForo_ViewPublic_ProfilePost_Report', 'profile_post_report', $viewParams);
		}
	}

	/**
	 * Displays a form to like a profile post or likes a profile post (via, uhh, POST).
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLike()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		if (!$this->_getProfilePostModel()->canLikeProfilePost($profilePost, $user, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$likeModel = $this->getModelFromCache('XenForo_Model_Like');

		$existingLike = $likeModel->getContentLikeByLikeUser('profile_post', $profilePostId, XenForo_Visitor::getUserId());

		if ($this->_request->isPost())
		{
			if ($existingLike)
			{
				$latestUsers = $likeModel->unlikeContent($existingLike);
			}
			else
			{
				$latestUsers = $likeModel->likeContent('profile_post', $profilePostId, $profilePost['user_id']);
			}

			$liked = ($existingLike ? false : true);

			if ($this->_noRedirect() && $latestUsers !== false)
			{
				$profilePost['likeUsers'] = $latestUsers;
				$profilePost['likes'] += ($liked ? 1 : -1);
				$profilePost['like_date'] = ($liked ? XenForo_Application::$time : 0);

				$viewParams = array(
					'profilePost' => $profilePost,
					'user' => $user,
					'liked' => $liked,
				);

				return $this->responseView('XenForo_ViewPublic_ProfilePost_LikeConfirmed', '', $viewParams);
			}
			else
			{
				return $this->getProfilePostSpecificRedirect($profilePost, $user);
			}
		}
		else
		{
			$viewParams = array(
				'profilePost' => $profilePost,
				'user' => $user,
				'like' => $existingLike
			);

			return $this->responseView('XenForo_ViewPublic_ProfilePost_Like', 'profile_post_like', $viewParams);
		}
	}

	/**
	 * List of everyone that liked this profile post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLikes()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		$likes =  $this->getModelFromCache('XenForo_Model_Like')->getContentLikes('profile_post', $profilePostId);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('no_one_has_liked_this_post_yet'));
		}

		$viewParams = array(
			'profilePost' => $profilePost,
			'user' => $user,

			'likes' => $likes
		);

		return $this->responseView('XenForo_ViewPublic_ProfilePost_Likes', 'profile_post_likes', $viewParams);
	}

	public function actionComment()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		$profilePostModel = $this->_getProfilePostModel();

		if (!$profilePostModel->canCommentOnProfilePost($profilePost, $user, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			$visitor = XenForo_Visitor::getInstance();

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment');
			$dw->setExtraData(XenForo_DataWriter_ProfilePostComment::DATA_PROFILE_USER, $user);
			$dw->setExtraData(XenForo_DataWriter_ProfilePostComment::DATA_PROFILE_POST, $profilePost);
			$dw->bulkSet(array(
				'profile_post_id' => $profilePostId,
				'user_id' => $visitor['user_id'],
				'username' => $visitor['username'],
				'message' => $message
			));
			$dw->save();

			if ($this->_noRedirect())
			{
				$comment = $profilePostModel->getProfilePostCommentById($dw->get('profile_post_comment_id'), array(
					'join' => XenForo_Model_ProfilePost::FETCH_COMMENT_USER
				));

				$viewParams = array(
					'comment' => $profilePostModel->prepareProfilePostComment($comment, $profilePost, $user),
					'profilePost' => $profilePost,
					'user' => $user
				);

				return $this->responseView('XenForo_ViewPublic_ProfilePost_Comment', '', $viewParams);
			}
			else
			{
				return $this->getProfilePostSpecificRedirect($profilePost, $user);
			}
		}
		else
		{
			$viewParams = array(
				'profilePost' => $profilePost,
				'user' => $user
			);

			return $this->responseView('XenForo_ViewPublic_ProfilePost_CommentPost', 'profile_post_comment_post', $viewParams);
		}
	}

	public function actionCommentDelete()
	{
		$commentId = $this->_input->filterSingle('comment', XenForo_Input::UINT);
		list($comment, $profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostCommentValidAndViewable($commentId);

		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		if ($profilePostId != $comment['profile_post_id'])
		{
			return $this->responseNoPermission();
		}

		if (!$this->_getProfilePostModel()->canDeleteProfilePostComment($comment, $profilePost, $user, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment');
			$dw->setExistingData($commentId);
			$dw->delete();

			return $this->getProfilePostSpecificRedirect($profilePost, $user);
		}
		else
		{
			$viewParams = array(
				'comment' => $comment,
				'profilePost' => $profilePost,
				'user' => $user
			);

			return $this->responseView('XenForo_ViewPublic_ProfilePost_CommentDelete', 'profile_post_comment_delete', $viewParams);
		}
	}

	public function actionComments()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		$beforeDate = $this->_input->filterSingle('before', XenForo_Input::UINT);

		$profilePostModel = $this->_getProfilePostModel();

		$comments = $profilePostModel->getProfilePostCommentsByProfilePost($profilePostId, $beforeDate, array(
			'join' => XenForo_Model_ProfilePost::FETCH_COMMENT_USER,
			'limit' => 50
		));

		if (!$comments)
		{
			return $this->responseMessage(new XenForo_Phrase('no_comments_to_display'));
		}

		foreach ($comments AS &$comment)
		{
			$comment = $profilePostModel->prepareProfilePostComment($comment, $profilePost, $user);
		}

		$firstCommentShown = reset($comments);
		$lastCommentShown = end($comments);

		$viewParams = array(
			'comments' => $comments,
			'firstCommentShown' => $firstCommentShown,
			'lastCommentShown' => $lastCommentShown,
			'profilePost' => $profilePost,
			'user' => $user
		);

		return $this->responseView('XenForo_ViewPublic_ProfilePost_Comments', 'profile_post_comments', $viewParams);
	}

	public function actionIp()
	{
		$profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);

		list($profilePost, $user) = $this->getHelper('UserProfile')->assertProfilePostValidAndViewable($profilePostId);

		if (!$this->_getProfilePostModel()->canViewIps($profilePost, $user, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if (!$profilePost['ip_id'])
		{
			return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
		}

		$viewParams = array(
			'user' => $user,
			'profilePost' => $profilePost,
			'ipInfo' => $this->getModelFromCache('XenForo_Model_Ip')->getContentIpInfo($profilePost)
		);

		return $this->responseView('XenForo_ViewPublic_ProfilePost_Ip', 'profile_post_ip', $viewParams);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_members');
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		return $this->getModelFromCache('XenForo_Model_ProfilePost');
	}
}