<?php

class XenForo_ControllerPublic_Member extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Member list
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionIndex()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		if ($userId)
		{
			return $this->responseReroute(__CLASS__, 'member');
		}
		else if ($this->_input->inRequest('user_id'))
		{
			return $this->responseError(new XenForo_Phrase('posted_by_guest_no_profile'));
		}

		$userModel = $this->_getUserModel();

		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
		if ($username !== '')
		{
			$user = $userModel->getUserByName($username);
			if ($user)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('members', $user)
				);
			}
			else
			{
				$userNotFound = true;
			}
		}
		else
		{
			$userNotFound = false;
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$usersPerPage = XenForo_Application::get('options')->membersPerPage;

		$criteria = array(
			'user_state' => 'valid'
		);

		// users for the member list
		$users = $userModel->getUsers($criteria, array(
			'join' => XenForo_Model_User::FETCH_USER_FULL,
			'perPage' => $usersPerPage,
			'page' => $page
		));

		// most recent registrations
		$latestCriteria = $criteria;
		$latestCriteria['is_banned'] = 0; // remove banned members from latest
		$latestUsers = $userModel->getLatestUsers($latestCriteria, array('limit' => 8));

		// most active users (highest post count)
		$activeUsers = $userModel->getMostActiveUsers($criteria, array('limit' => 12));

		$viewParams = array(
			'users' => $users,

			'totalUsers' => $userModel->countUsers($criteria),
			'page' => $page,
			'usersPerPage' => $usersPerPage,

			'latestUsers' => $latestUsers,
			'activeUsers' => $activeUsers,

			'userNotFound' => $userNotFound
		);

		return $this->responseView('XenForo_ViewPublic_Member_List', 'member_list', $viewParams);
	}

	/**
	 * Member profile page
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionMember()
	{
		if ($this->_input->filterSingle('card', XenForo_Input::UINT))
		{
			return $this->responseReroute(__CLASS__, 'card');
		}

		$visitor = XenForo_Visitor::getInstance();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$userFetchOptions = array(
			'join' => XenForo_Model_User::FETCH_LAST_ACTIVITY
		);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId, $userFetchOptions);

		// get last activity details
		$user['activity'] = ($user['view_date'] ? $this->getModelFromCache('XenForo_Model_Session')->getSessionActivityDetails($user) : false);

		$userModel = $this->_getUserModel();
		$userProfileModel = $this->_getUserProfileModel();

		// profile posts
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$profilePostsPerPage = XenForo_Application::get('options')->messagesPerPage;

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('members', $user, array('page' => $page))
		);

		if ($userProfileModel->canViewProfilePosts($user))
		{
			$profilePostModel = $this->_getProfilePostModel();

			$profilePostConditions = $profilePostModel->getPermissionBasedProfilePostConditions($user);
			$profilePostFetchOptions = array(
				'join' => XenForo_Model_ProfilePost::FETCH_USER_POSTER,
				'likeUserId' => XenForo_Visitor::getUserId(),
				'perPage' => $profilePostsPerPage,
				'page' => $page
			);
			if (!empty($profilePostConditions['deleted']))
			{
				$profilePostFetchOptions['join'] |= XenForo_Model_ProfilePost::FETCH_DELETION_LOG;
			}

			$totalProfilePosts = $profilePostModel->countProfilePostsForUserId($userId, $profilePostConditions);

			$profilePosts = $profilePostModel->getProfilePostsForUserId($userId, $profilePostConditions, $profilePostFetchOptions);
			$profilePosts = $profilePostModel->prepareProfilePosts($profilePosts, $user);
			$inlineModOptions = $profilePostModel->addInlineModOptionToProfilePosts($profilePosts, $user);

			$profilePosts = $profilePostModel->addProfilePostCommentsToProfilePosts($profilePosts, array(
				'join' => XenForo_Model_ProfilePost::FETCH_COMMENT_USER
			));
			foreach ($profilePosts AS &$profilePost)
			{
				if (empty($profilePost['comments']))
				{
					continue;
				}

				foreach ($profilePost['comments'] AS &$comment)
				{
					$comment = $profilePostModel->prepareProfilePostComment($comment, $profilePost, $user);
				}
			}

			$canViewProfilePosts = true;
			if ($user['user_id'] == $visitor['user_id'])
			{
				$canPostOnProfile = $visitor->canUpdateStatus();
			}
			else
			{
				$canPostOnProfile = $userProfileModel->canPostOnProfile($user);
			}
		}
		else
		{
			$totalProfilePosts = 0;
			$profilePosts = array();
			$inlineModOptions = array();

			$canViewProfilePosts = false;
			$canPostOnProfile = false;
		}

		// identities
		if ($userProfileModel->canViewIdentities($user))
		{
			$identities = $userModel->getPrintableIdentityList($user['identities']);
		}
		else
		{
			$identities = false;
		}

		// misc
		if ($user['following'])
		{
			$followingCount = substr_count($user['following'], ',') + 1;

			$following = $userModel->getFollowedUserProfiles($userId, 6, 'RAND()');
		}
		else
		{
			$followingCount = 0;

			$following = array();
		}

		$followersCount = $userModel->countUsersFollowingUserId($userId);
		$followers = $userModel->getUsersFollowingUserId($userId, 6, 'RAND()');

		$birthday = $userProfileModel->getUserBirthdayDetails($user);
		$user['age'] = $birthday['age'];

		$user['isFollowingVisitor'] = $userModel->isFollowing($visitor['user_id'], $user);

		$viewParams = array(
			'user' => $user,
			'canViewOnlineStatus' => $userModel->canViewUserOnlineStatus($user),
			'canCleanSpam' => (XenForo_Permission::hasPermission($visitor['permissions'], 'general', 'cleanSpam') && $userModel->couldBeSpammer($user)),
			'canViewIps'   => (XenForo_Permission::hasPermission($visitor['permissions'], 'general', 'viewIps')),

			'followingCount' => $followingCount,
			'followersCount' => $followersCount,

			'following' => $following,
			'followers' => $followers,

			'birthday' => $birthday,

			'identities' => $identities,
			'canStartConversation' => $userModel->canStartConversationWithUser($user),

			'canViewProfilePosts' => $canViewProfilePosts,
			'canPostOnProfile' => $canPostOnProfile,
			'profilePosts' => $profilePosts,
			'inlineModOptions' => $inlineModOptions,
			'page' => $page,
			'profilePostsPerPage' => $profilePostsPerPage,
			'totalProfilePosts' => $totalProfilePosts,

			'showRecentActivity' => $userProfileModel->canViewRecentActivity($user),
		);

		return $this->responseView('XenForo_ViewPublic_Member_View', 'member_view', $viewParams);
	}

	public function actionFollowing()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$userModel = $this->_getUserModel();

		// TODO: pagination?

		$viewParams = array(
			'user' => $user,
			'following' => $userModel->getFollowedUserProfiles($user['user_id'])
		);

		return $this->responseView('XenForo_ViewPublic_Member_Following', 'member_following', $viewParams);
	}

	public function actionFollowers()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$userModel = $this->_getUserModel();

		// TODO: pagination?

		$viewParams = array(
			'user' => $user,
			'followers' => $userModel->getUsersFollowingUserId($user['user_id'])
		);

		return $this->responseView('XenForo_ViewPublic_Member_Followers', 'member_followers', $viewParams);
	}

	public function actionFollow()
	{
		$this->_assertRegistrationRequired();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if (!$user = $this->_getUserModel()->getUserById($userId, array('join' => XenForo_Model_User::FETCH_USER_OPTION)))
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$visitor = XenForo_Visitor::getInstance();

		if ($this->isConfirmedPost())
		{
			$this->_getUserModel()->follow($user);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user),
				null,
				array(
					'linkPhrase' => new XenForo_Phrase('unfollow'),
					'linkUrl' => XenForo_Link::buildPublicLink('members/unfollow', $user, array('_xfToken' => $visitor['csrf_token_page']))
				)
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array('user' => $user);

			return $this->responseView('XenForo_ViewPublic_Member_Follow', 'member_follow', $viewParams);
		}
	}

	public function actionUnfollow()
	{
		$this->_assertRegistrationRequired();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if (!$user = $this->_getUserModel()->getUserById($userId))
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$visitor = XenForo_Visitor::getInstance();

		if ($this->isConfirmedPost())
		{
			$this->_getUserModel()->unfollow($userId);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user),
				null,
				array(
					'linkPhrase' => new XenForo_Phrase('follow'),
					'linkUrl' => XenForo_Link::buildPublicLink('members/follow', $user, array('_xfToken' => $visitor['csrf_token_page']))
				)
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array('user' => $user);

			return $this->responseView('XenForo_ViewPublic_Member_Follow', 'member_unfollow', $viewParams);
		}
	}

	public function actionTrophies()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$trophyModel = $this->_getTrophyModel();
		$trophies = $trophyModel->prepareTrophies($trophyModel->getTrophiesForUserId($userId));

		$viewParams = array(
			'user' => $user,
			'trophies' => $trophies
		);

		return $this->responseView('XenForo_ViewPublic_Member_Trophies', 'member_trophies', $viewParams);
	}

	public function actionMiniStats()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$user = XenForo_Application::arrayFilterKeys($user, array(
			'user_id',
			'username',
			'message_count',
			'like_count',
			'trophy_points',
			'register_date',
		));

		$viewParams = array('user' => $user);

		return $this->responseView('XenForo_ViewPublic_Member_MiniStats', '', $viewParams);
	}

	/**
	 * Gets recent content for the specified member
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionRecentContent()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$results = XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler()->executeSearchByUserId(
			$userId, 0, 15
		);
		$results = $this->getModelFromCache('XenForo_Model_Search')->getSearchResultsForDisplay($results);
		if (!$results)
		{
			return $this->responseMessage(new XenForo_Phrase('this_member_does_not_have_any_recent_content'));
		}

		$viewParams = array(
			'user' => $user,
			'results' => $results
		);

		return $this->responseView('XenForo_ViewPublic_Member_RecentContent', 'member_recent_content', $viewParams);
	}

	public function actionRecentActivity()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		if (!$this->_getUserProfileModel()->canViewRecentActivity($user))
		{
			return $this->responseView(
				'XenForo_ViewPublic_Member_RecentActivity_Restricted',
				'member_recent_activity',
				array('user' => $user, 'restricted' => true)
			);
		}

		$newsFeedId = $this->_input->filterSingle('news_feed_id', XenForo_Input::UINT);
		$conditions = array('user_id' => $userId);

		$feed = $this->getModelFromCache('XenForo_Model_NewsFeed')->getNewsFeed($conditions, $newsFeedId);
		$feed['user'] = $user;
		$feed['startNewsFeedId'] = $newsFeedId;

		return $this->responseView(
			'XenForo_ViewPublic_Member_RecentActivity',
			'member_recent_activity',
			$feed
		);
	}

	/**
	 * Member mini-profile (for popup)
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionCard()
	{
		// TODO: unsure whether this should respect profile viewing perms
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$userFetchOptions = array(
			'join' => XenForo_Model_User::FETCH_LAST_ACTIVITY
		);
		$user = $this->getHelper('UserProfile')->getUserOrError($userId, $userFetchOptions);

		// get last activity details
		$user['activity'] = ($user['view_date'] ? $this->getModelFromCache('XenForo_Model_Session')->getSessionActivityDetails($user) : false);

		$visitor = XenForo_Visitor::getInstance();

		$userModel = $this->_getUserModel();

		$user['isFollowingVisitor'] = $userModel->isFollowing($visitor['user_id'], $user);

		$canCleanSpam = (XenForo_Permission::hasPermission($visitor['permissions'], 'general', 'cleanSpam') && $userModel->couldBeSpammer($user));

		$viewParams = array(
			'user' => $user,
			'canCleanSpam' => $canCleanSpam,
			'canViewOnlineStatus' => $userModel->canViewUserOnlineStatus($user),
			'canStartConversation' => $userModel->canStartConversationWithUser($user)
		);

		return $this->responseView('XenForo_ViewPublic_Member_Card', 'member_card', $viewParams);
	}

	public function actionPost()
	{
		$this->_assertPostOnly();

		$data = $this->_input->filter(array(
			'message' => XenForo_Input::STRING,
		));

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$visitor = XenForo_Visitor::getInstance();

		if ($visitor['user_id'] == $user['user_id'])
		{
			if (!$visitor->canUpdateStatus())
			{
				return $this->responseNoPermission();
			}

			if ($data['message'] !== '')
			{
				$this->assertNotFlooding('post');
			}

			$profilePostId = $this->_getUserProfileModel()->updateStatus($data['message']);

			if ($this->_input->filterSingle('return', XenForo_Input::UINT))
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$this->getDynamicRedirect(),
					new XenForo_Phrase('your_status_has_been_updated')
				);
			}

			$hash = '';
		}
		else
		{
			if (!$this->_getUserProfileModel()->canPostOnProfile($user, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');

			$writer->set('user_id', $visitor['user_id']);
			$writer->set('username', $visitor['username']);
			$writer->set('message', $data['message']);
			$writer->set('profile_user_id', $user['user_id']);
			$writer->set('message_state', $this->_getProfilePostModel()->getProfilePostInsertMessageState($user));
			$writer->setExtraData(XenForo_DataWriter_DiscussionMessage_ProfilePost::DATA_PROFILE_USER, $user);
			$writer->preSave();

			if (!$writer->hasErrors())
			{
				$this->assertNotFlooding('post');
			}

			$writer->save();

			$profilePostId = $writer->get('profile_post_id');

			$hash = '#profile-post-' . $profilePostId;
		}

		if ($this->_noRedirect())
		{
			$profilePostModel = $this->_getProfilePostModel();

			$profilePost = $profilePostModel->getProfilePostById($profilePostId, array(
				'join' => XenForo_Model_ProfilePost::FETCH_USER_POSTER
			));
			$profilePost = $profilePostModel->prepareProfilePost($profilePost, $user);
			$profilePostModel->addInlineModOptionToProfilePost($profilePost, $user);

			$viewParams = array(
				'profilePost' => $profilePost,
				'isStatus' =>  ($visitor['user_id'] == $user['user_id']),
			);

			return $this->responseView(
				'XenForo_ViewPublic_Member_Post',
				'profile_post',
				$viewParams
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user) . $hash
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionNewsFeed()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildPublicLink('recent-activity')
		);
	}

	/**
	 * Finds valid members matching the specified username prefix.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionFind()
	{
		$q = $this->_input->filterSingle('q', XenForo_Input::STRING);

		if ($q !== '')
		{
			$users = $this->_getUserModel()->getUsers(
				array('username' => array($q , 'r'), 'user_state' => 'valid'),
				array('limit' => 10)
			);
		}
		else
		{
			$users = array();
		}

		$viewParams = array(
			'users' => $users
		);

		return $this->responseView(
			'XenForo_ViewPublic_Member_Find',
			'member_autocomplete',
			$viewParams
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		if (!XenForo_Visitor::getInstance()->hasPermission('general', 'viewProfile'))
		{
			return new XenForo_Phrase('viewing_members');
		}

		$userIds = array();
		foreach ($activities AS $activity)
		{
			if (!empty($activity['params']['user_id']))
			{
				$userIds[$activity['params']['user_id']] = $activity['params']['user_id'];
			}
		}

		$userData = array();

		if ($userIds)
		{
			/* @var $userModel XenForo_Model_User */
			$userModel = XenForo_Model::create('XenForo_Model_User');

			$users = $userModel->getUsersByIds($userIds, array(
				'join' => XenForo_Model_User::FETCH_USER_PRIVACY
			));
			foreach ($users AS $user)
			{
				$userData[$user['user_id']] = array(
					'username' => $user['username'],
					'url' => XenForo_Link::buildPublicLink('members', $user),
				);
			}
		}

		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$user = false;
			if (!empty($activity['params']['user_id']))
			{
				$userId = $activity['params']['user_id'];
				if (isset($userData[$userId]))
				{
					$user = $userData[$userId];
				}
			}

			if ($user)
			{
				$output[$key] = array(
					new XenForo_Phrase('viewing_member_profile'),
					$user['username'],
					$user['url'],
					false
				);
			}
			else
			{
				$output[$key] = new XenForo_Phrase('viewing_members');
			}
		}

		return $output;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_UserProfile
	 */
	protected function _getUserProfileModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserProfile');
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		return $this->getModelFromCache('XenForo_Model_ProfilePost');
	}

	/**
	 * @return XenForo_Model_Trophy
	 */
	protected function _getTrophyModel()
	{
		return $this->getModelFromCache('XenForo_Model_Trophy');
	}
}
