<?php

/**
 * Controller for handling the users section and actions on users in the
 * admin control panel.
 *
 * @package XenForo_Users
 */
class XenForo_ControllerAdmin_User extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		switch (strtolower($action))
		{
			case 'index':
			case 'searchname':
				break;

			default:
				$this->assertAdminPermission('user');
		}
	}

	/**
	 * Section splash page.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		/*
		 * Fetch:
		 *
		 * Total members
		 * Members awaiting approval
		 * Administrators
		 * Moderators
		 * Banned members
		 *
		 * Banned IPs
		 * Banned emails
		 */

		$userModel = $this->_getUserModel();
		$banningModel = $this->getModelFromCache('XenForo_Model_Banning');

		$visitor = XenForo_Visitor::getInstance();

		$boardTotals = $this->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
		if (!$boardTotals)
		{
			$boardTotals = $this->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
		}

		$viewParams = array(
			'canManageUsers' => $visitor->hasAdminPermission('user'),
			'canManageBans' => $visitor->hasAdminPermission('ban'),
			'canManageUserGroups' => $visitor->hasAdminPermission('userGroup'),
			'canManageIdentityServices' => $visitor->hasAdminPermission('identityService'),
			'canManageTrophies' => $visitor->hasAdminPermission('trophy'),
			'canManageUserUpgrades' => $visitor->hasAdminPermission('userUpgrade'),

			'users' => array(
				'total' => $boardTotals['users'],
				'awaitingApproval' => $userModel->countUsers(array('user_state' => 'moderated')),
				'admins' => $this->getModelFromCache('XenForo_Model_Admin')->countAdmins(),
				'moderators' => $this->getModelFromCache('XenForo_Model_Moderator')->countModerators(),
				'banned' => $banningModel->countBannedUsers()
			),
			'bannedIps' => $banningModel->countBannedIps(),
			'bannedEmails' => $banningModel->countBannedEmails(),
		);

		return $this->responseView('XenForo_ViewAdmin_User_Splash', 'user_splash', $viewParams);
	}

	protected function _filterUserSearchCriteria(array $criteria)
	{
		foreach ($criteria AS $key => $value)
		{
			if ($value === '')
			{
				unset($criteria[$key]);
			}
			else
			{
				switch ($key)
				{
					case 'user_group_id':
					case 'message_count':
						if ($value === '0' || $value === 0)
						{
							unset($criteria[$key]);
						}
				}
			}
		}

		if (isset($criteria['user_state']) && is_array($criteria['user_state']) && count($criteria['user_state']) == 4)
		{
			// all types selected, no filtering
			unset($criteria['user_state']);
		}
		if (isset($criteria['is_banned']) && is_array($criteria['is_banned']) && count($criteria['is_banned']) == 2)
		{
			// both options selected, no filtering
			unset($criteria['is_banned']);
		}

		return $criteria;
	}

	protected function _prepareUserSearchCriteria(array $criteria)
	{
		if (!empty($criteria['last_activity']))
		{
			$criteria['last_activity'] = array('>=',
				XenForo_Input::rawFilter($criteria['last_activity'], XenForo_Input::DATE_TIME)
			);
		}

		if (!empty($criteria['message_count']))
		{
			$criteria['message_count'] = array('>=', $criteria['message_count']);
		}

		if (isset($criteria['is_banned']) && is_array($criteria['is_banned']))
		{
			$criteria['is_banned'] = reset($criteria['is_banned']);
		}

		foreach (array('username', 'username2', 'email') AS $field)
		{
			if (isset($criteria[$field]) && is_string($criteria[$field]))
			{
				$criteria[$field] = trim($criteria[$field]);
			}
		}

		return $criteria;
	}

	/**
	 * Shows a list of users.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionList()
	{
		$criteria = $this->_input->filterSingle('criteria', XenForo_Input::ARRAY_SIMPLE);
		$criteria = $this->_filterUserSearchCriteria($criteria);

		$filter = $this->_input->filterSingle('_filter', XenForo_Input::ARRAY_SIMPLE);
		if ($filter && isset($filter['value']))
		{
			$criteria['username2'] = array($filter['value'], empty($filter['prefix']) ? 'lr' : 'r');
			$filterView = true;
		}
		else
		{
			$filterView = false;
		}

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
		$direction = $this->_input->filterSingle('direction', XenForo_Input::STRING);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$usersPerPage = 20;

		$fetchOptions = array(
			'perPage' => $usersPerPage,
			'page' => $page,

			'order' => $order,
			'direction' => $direction
		);

		$userModel = $this->_getUserModel();

		$criteriaPrepared = $this->_prepareUserSearchCriteria($criteria);

		$totalUsers = $userModel->countUsers($criteriaPrepared);
		if (!$totalUsers)
		{
			return $this->responseError(new XenForo_Phrase('no_users_matched_specified_criteria'));
		}

		$users = $userModel->getUsers($criteriaPrepared, $fetchOptions);

		if ($totalUsers == 1 && ($user = reset($users)) && !$filterView)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/edit', $user)
			);
		}

		// TODO: show more structured info: username, email, last activity, messages?

		$viewParams = array(
			'users' => $users,
			'totalUsers' => $totalUsers,

			'linkParams' => array('criteria' => $criteria, 'order' => $order, 'direction' => $direction),
			'page' => $page,
			'usersPerPage' => $usersPerPage,

			'filterView' => $filterView,
			'filterMore' => ($filterView && $totalUsers > $usersPerPage)
		);

		return $this->responseView('XenForo_ViewAdmin_User_List', 'user_list', $viewParams);
	}

	/**
	 * Search for users form.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearch()
	{
		$viewParams = array(
			'lastUser' => $this->_getUserModel()->getUserById($this->_input->filterSingle('last_user_id', XenForo_Input::UINT)),
			'userGroups' => $this->_getUserGroupModel()->getAllUserGroupTitles(),
			'criteria' => array(
				'user_state' => array('valid' => true, 'email_confirm' => true, 'email_confirm_edit' => true, 'moderated' => true),
				'is_banned' => array(0 => true, 1 => true)
			)
		);

		return $this->responseView('XenForo_ViewAdmin_User_Search', 'user_search', $viewParams);
	}

	/**
	 * Searches for a user by the left-most prefix of a name (for auto-complete(.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearchName()
	{
		$q = $this->_input->filterSingle('q', XenForo_Input::STRING);

		if ($q !== '')
		{
			$users = $this->_getUserModel()->getUsers(
				array('username' => array($q , 'r')),
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
			'XenForo_ViewAdmin_User_SearchName',
			'',
			$viewParams
		);
	}

	protected function _getUserAddEditResponse(array $user)
	{
		$userModel = $this->_getUserModel();

		if ($user['user_id'])
		{
			$user['is_super_admin'] = $this->getModelFromCache('XenForo_Model_Admin')->isSuperAdmin($user['user_id']);
			$identities = $userModel->getIdentities($user['user_id']);
		}
		else
		{
			$user['is_supder_admin'] = false;
			$identities = array();
		}

		$viewParams = array(
			'user' => $user,
			'timeZones'	=> XenForo_Helper_TimeZone::getTimeZones(),
			'userGroups' => $this->_getUserGroupModel()->getAllUserGroupTitles(),
			'idServices'
				=> $userModel->getIdentityServicesEditingData($identities),
			'styles'
				=> $this->getModelFromCache('XenForo_Model_Style')->getStylesForOptionsTag($user['style_id']),
			'languages'
				=> $this->getModelFromCache('XenForo_Model_Language')->getLanguagesForOptionsTag($user['language_id']),
			'lastHash' => $this->getLastHash($user['user_id'])
		);

		return $this->responseView('XenForo_ViewAdmin_User_Edit', 'user_edit', $viewParams);
	}

	/**
	 * Form to add a user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$user = array(
			'user_id' => 0,
			'timezone' => XenForo_Application::get('options')->guestTimeZone,
			'user_group_id' => XenForo_Model_User::$defaultRegisteredGroupId,
			'style_id' => 0,
			'language_id' => XenForo_Application::get('options')->defaultLanguageId,
			'user_state' => 'valid',
			'enable_rte' => 1,
			'message_count' => 0,
			'like_count' => 0,
			'trophy_points' => 0
		);
		$user = array_merge($user, XenForo_Application::get('options')->registrationDefaults);

		return $this->_getUserAddEditResponse($user);
	}

	protected function _checkSuperAdminEdit(array $user)
	{
		if ($user['is_admin'] && !XenForo_Visitor::getInstance()->isSuperAdmin())
		{
			$superAdmins = preg_split(
				'#\s*,\s*#', XenForo_Application::get('config')->superAdmins,
				-1, PREG_SPLIT_NO_EMPTY
			);
			if (in_array($user['user_id'], $superAdmins))
			{
				throw $this->responseException(
					$this->responseError(new XenForo_Phrase('you_must_be_super_administrator_to_edit_user'))
				);
			}
		}
	}

	/**
	 * Form to edit an existing user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		return $this->_getUserAddEditResponse($user);
	}

	/**
	 * Validate a single field
	 *
	 * @return XenForo_ControllerResponse_View|XenForo_ControllerResponse_Error
	 */
	public function actionValidateField()
	{
		$this->_assertPostOnly();

		return $this->_validateField('XenForo_DataWriter_User', array(
			'existingDataKey' => $this->_input->filterSingle('user_id', XenForo_Input::UINT)
		));
	}

	/**
	 * Inserts a new user or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if ($userId)
		{
			$user = $this->_getUserOrError($userId);
			$this->getHelper('Admin')->checkSuperAdminEdit($user);
		}

		$userInput = $this->_input->filter(array(

			// essentials
			'username'               => XenForo_Input::STRING,
			'email'                  => XenForo_Input::STRING,

			'user_group_id'          => XenForo_Input::UINT,
			'user_state'             => XenForo_Input::STRING,
			'is_discouraged'         => XenForo_Input::UINT,

			// personal details
			'gender'                 => XenForo_Input::STRING,
			'dob_day'                => XenForo_Input::UINT,
			'dob_month'              => XenForo_Input::UINT,
			'dob_year'               => XenForo_Input::UINT,
			'location'               => XenForo_Input::STRING,
			'occupation'             => XenForo_Input::STRING,

			// profile info
			'custom_title'           => XenForo_Input::STRING,
			'homepage'               => XenForo_Input::STRING,
			'about'                  => XenForo_Input::STRING,
			'signature'              => XenForo_Input::STRING,

			'message_count'          => XenForo_Input::UINT,
			'like_count'             => XenForo_Input::UINT,
			'trophy_points'          => XenForo_Input::UINT,

			// preferences
			'style_id'               => XenForo_Input::UINT,
			'language_id'            => XenForo_Input::UINT,
			'timezone'               => XenForo_Input::STRING,
			'content_show_signature' => XenForo_Input::UINT,
			'enable_rte'             => XenForo_Input::UINT,

			// privacy
			'visible'                 => XenForo_Input::UINT,
			'receive_admin_email'     => XenForo_Input::UINT,
			'show_dob_date'           => XenForo_Input::UINT,
			'show_dob_year'           => XenForo_Input::UINT,
			'allow_view_profile'      => XenForo_Input::STRING,
			'allow_post_profile'      => XenForo_Input::STRING,
			'allow_send_personal_conversation' => XenForo_Input::STRING,
			'allow_view_identities'   => XenForo_Input::STRING,
			'allow_receive_news_feed' => XenForo_Input::STRING,
		));

		$secondaryGroupIds = $this->_input->filterSingle('secondary_group_ids', XenForo_Input::UINT, array('array' => true));

		$userInput['about'] = XenForo_Helper_String::autoLinkBbCode($userInput['about']);

		if ($this->_input->filterSingle('clear_status', XenForo_Input::UINT))
		{
			//TODO: clear status
		}

		$identities = $this->_input->filterSingle('identity', XenForo_Input::STRING, array('array' => true));
		foreach ($identities AS $_key => $_value)
		{
			if ($_value === '')
			{
				unset($identities[$_key]);
			}
		}

		/* @var $writer XenForo_DataWriter_User */
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($userId)
		{
			$writer->setExistingData($userId);
		}
		$writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);

		$writer->bulkSet($userInput);
		$writer->setIdentities($identities);
		$writer->setSecondaryGroups($secondaryGroupIds);

		$password = $this->_input->filterSingle('password', XenForo_Input::STRING);
		if ($password !== '')
		{
			$writer->setPassword($password);
		}

		$writer->save();

		$userId = $writer->get('user_id');

		// TODO: redirect to previous search if possible?

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('users/search', null, array('last_user_id' => $userId)) . $this->getLastHash($userId)
		);
	}

	/**
	 * Displays a form to edit a user's avatar.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAvatar()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		$viewParams = array(
			'user' => $user,
		);

		return $this->responseView('XenForo_ViewAdmin_User_Avatar', 'user_avatar', $viewParams);
	}

	/**
	 * Updates a user's avatar.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAvatarUpload()
	{
		$this->_assertPostOnly();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		$avatars = XenForo_Upload::getUploadedFiles('avatar');
		$avatar = reset($avatars);

		/* @var $avatarModel XenForo_Model_Avatar */
		$avatarModel = $this->getModelFromCache('XenForo_Model_Avatar');

		if ($avatar)
		{
			$avatarModel->uploadAvatar($avatar, $user['user_id'], false);
		}
		else if ($this->_input->filterSingle('delete', XenForo_Input::UINT))
		{
			$avatarModel->deleteAvatar($user['user_id']);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('users/edit', $user)
		);
	}

	/**
	 * Deletes the specified user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_EXCEPTION);
		$writer->setExistingData($user);
		$writer->preDelete();

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_User', 'user_id',
				XenForo_Link::buildAdminLink('users')
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array(
				'user' => $user
			);

			return $this->responseView('XenForo_ViewAdmin_User_Delete',
				'user_delete', $viewParams
			);
		}
	}

	/**
	 * Shows a list of moderated users and allows them to be managed.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionModerated()
	{
		$users = $this->_getUserModel()->getUsers(array(
			'user_state' => 'moderated'
		));
		if (!$users)
		{
			return $this->responseMessage(new XenForo_Phrase('no_users_awaiting_approval'));
		}

		$viewParams = array(
			'users' => $users
		);

		return $this->responseView('XenForo_ViewAdmin_User_Moderated', 'user_moderated', $viewParams);
	}

	/**
	 * Processes moderated users.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionModeratedUpdate()
	{
		$this->_assertPostOnly();

		$usersInput = $this->_input->filterSingle('users', XenForo_Input::ARRAY_SIMPLE);
		$users = $this->_getUserModel()->getUsersByIds(array_keys($usersInput));

		foreach ($users AS $user)
		{
			if (!isset($usersInput[$user['user_id']]))
			{
				continue;
			}

			$userControl = $usersInput[$user['user_id']];
			if (empty($userControl['action']) || $userControl['action'] == 'none')
			{
				continue;
			}

			$notify = (!empty($userControl['notify']) ? true : false);
			$rejectionReason = (!empty($userControl['reject_reason']) ? $userControl['reject_reason'] : '');

			$this->getModelFromCache('XenForo_Model_UserConfirmation')->processUserModeration(
				$user, $userControl['action'], $notify, $rejectionReason
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('users/moderated')
		);
	}

	/**
	 * Displays the form to setup the email, or to confirm sending of it.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEmail()
	{
		if ($this->isConfirmedPost())
		{
			$criteria = $this->_input->filterSingle('criteria', XenForo_Input::JSON_ARRAY);
			$criteria = $this->_filterUserSearchCriteria($criteria);
			$criteriaPrepared = $this->_prepareUserSearchCriteria($criteria);

			if ($this->_input->filterSingle('list_only', XenForo_Input::UINT))
			{
				$users = $this->_getUserModel()->getUsers($criteriaPrepared);
				if (!$users)
				{
					return $this->responseError(new XenForo_Phrase('no_users_matched_specified_criteria'));
				}

				$viewParams = array(
					'users' => $users
				);

				return $this->responseView('XenForo_ViewAdmin_User_EmailList', 'user_email_list', $viewParams);
			}

			$email = $this->_input->filter(array(
				'from_name' => XenForo_Input::STRING,
				'from_email' => XenForo_Input::STRING,

				'email_title' => XenForo_Input::STRING,
				'email_format' => XenForo_Input::STRING,
				'email_body' => XenForo_Input::STRING
			));

			if (!$email['from_name'] || !$email['from_email'] || !$email['email_title'] || !$email['email_body'])
			{
				return $this->responseError(new XenForo_Phrase('please_complete_required_fields'));
			}

			$total = $this->_getUserModel()->countUsers($criteriaPrepared);
			if (!$total)
			{
				return $this->responseError(new XenForo_Phrase('no_users_matched_specified_criteria'));
			}

			$viewParams = array(
				'test' => $this->_input->filterSingle('test', XenForo_Input::STRING),
				'total' => $total,
				'criteria' => $criteria,
				'email' => $email
			);

			return $this->responseView('XenForo_ViewAdmin_User_EmailConfirm', 'user_email_confirm', $viewParams);
		}
		else
		{
			$viewParams = array(
				'userGroups' => $this->_getUserGroupModel()->getAllUserGroupTitles(),
				'criteria' => array(
					'user_state' => array('valid' => true),
					'is_banned' => array(0 => true)
				),
				'sent' => $this->_input->filterSingle('sent', XenForo_Input::UINT)
			);

			return $this->responseView('XenForo_ViewAdmin_User_Email', 'user_email', $viewParams);
		}
	}

	/**
	 * Sends the specified email.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEmailSend()
	{
		$this->_assertPostOnly();

		$criteria = $this->_input->filterSingle('criteria', XenForo_Input::JSON_ARRAY);
		$criteria = $this->_filterUserSearchCriteria($criteria);
		$criteriaPrepared = $this->_prepareUserSearchCriteria($criteria);

		$email = $this->_input->filter(array(
			'from_name' => XenForo_Input::STRING,
			'from_email' => XenForo_Input::STRING,

			'email_title' => XenForo_Input::STRING,
			'email_format' => XenForo_Input::STRING,
			'email_body' => XenForo_Input::STRING
		));

		$total = $this->_input->filterSingle('total', XenForo_Input::UINT);

		$transport = XenForo_Mail::getDefaultTransport();

		if ($this->_input->filterSingle('test', XenForo_Input::STRING))
		{
			$this->_sendEmail(XenForo_Visitor::getInstance()->toArray(), $email, $transport);

			return $this->responseReroute(__CLASS__, 'email');
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = 100;

		$users = $this->_getUserModel()->getUsers($criteriaPrepared, array(
			'page' => $page,
			'perPage' => $perPage
		));
		if (!$users)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/email', false, array('sent' => $total))
			);
		}
		else
		{
			foreach ($users AS $user)
			{
				$this->_sendEmail($user, $email, $transport);
			}

			$viewParams = array(
				'total' => $total,
				'completed' => ($page - 1) * $perPage + count($users),
				'nextPage' => $page + 1,

				'criteria' => $criteria,
				'email' => $email
			);
			return $this->responseView('XenForo_ViewAdmin_User_Email_Send', 'user_email_send', $viewParams);
		}
	}

	protected function _sendEmail(array $user, array $email, Zend_Mail_Transport_Abstract $transport)
	{
		if (!$user['email'])
		{
			return false;
		}

		$mailObj = new Zend_Mail('utf-8');
		$mailObj->setSubject($email['email_title'])
			->addTo($user['email'], $user['username'])
			->setFrom($email['from_email'], $email['from_name']);

		$options = XenForo_Application::get('options');
		$bounceEmailAddress = $options->bounceEmailAddress;
		if (!$bounceEmailAddress)
		{
			$bounceEmailAddress = $options->defaultEmailAddress;
		}
		$mailObj->setReturnPath($bounceEmailAddress);

		if ($email['email_format'] == 'html')
		{
			$replacements = array(
				'{name}' => htmlspecialchars($user['username']),
				'{email}' => htmlspecialchars($user['email']),
				'{id}' => $user['user_id']
			);
			$email['email_body'] = strtr($email['email_body'], $replacements);

			$text = trim(
				htmlspecialchars_decode(strip_tags($email['email_body']))
			);

			$mailObj->setBodyHtml($email['email_body'])
				->setBodyText($text);
		}
		else
		{
			$replacements = array(
				'{name}' => $user['username'],
				'{email}' => $user['email'],
				'{id}' => $user['user_id']
			);
			$email['email_body'] = strtr($email['email_body'], $replacements);

			$mailObj->setBodyText($email['email_body']);
		}

		try
		{
			$mailObj->send($transport);
		}
		catch (Exception $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Gets the specified user or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getUserOrError($id)
	{
		$userModel = $this->_getUserModel();

		return $this->getRecordOrError(
			$id, $userModel, 'getFullUserById',
			'requested_user_not_found'
		);
	}

	/**
	 * Gets the user model.
	 *
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * Gets the user model.
	 *
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}
}