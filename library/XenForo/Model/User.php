<?php

/**
 * Model for users.
 *
 * @package XenForo_Users
 */
class XenForo_Model_User extends XenForo_Model
{
	const FETCH_USER_PROFILE     = 0x01;
	const FETCH_USER_OPTION      = 0x02;
	const FETCH_USER_PRIVACY     = 0x04;
	const FETCH_USER_PERMISSIONS = 0x08;
	const FETCH_LAST_ACTIVITY    = 0x10;

	/**
	 * Quick constant for fetching, profile, option, and privacy data.
	 *
	 * @var integer
	 */
	const FETCH_USER_FULL        = 0x07;

	/**
	 * Special value to use for a permanent ban
	 *
	 * @var integer
	 */
	const PERMANENT_BAN = 0;

	public static $defaultGuestGroupId = 1;
	public static $defaultRegisteredGroupId = 2;
	public static $defaultAdminGroupId = 3;
	public static $defaultModeratorGroupId = 4;

	/**
	 * Simple way to update user data fields.
	 *
	 * @param integer|array $userId|$user
	 * @param array|string Either the name of a single field, or an array of field-name => field-value pairs
	 * @param mixed If the previous parameter is a string, use this as the field value
	 *
	 * @return XenForo_DataWriter_User
	 */
	public function update($user, $field, $value = null)
	{
		$userId = $this->getUserIdFromUser($user);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$writer->setExistingData($userId);

		if ($value === null)
		{
			if (is_array($field))
			{
				$writer->bulkSet($field);
			}
		}
		else if (is_string($field))
		{
			$writer->set($field, $value);
		}

		$writer->save();

		return $writer;
	}

	/**
	 * Fetches the user_id index from a user record
	 *
	 * @param integer|array $userId|$user
	 *
	 * @return integer User ID
	 */
	public static function getUserIdFromUser($user)
	{
		if (is_scalar($user))
		{
			return $user;
		}

		if (is_array($user) && isset($user['user_id']))
		{
			return $user['user_id'];
		}

		throw new XenForo_Exception('Unable to derive User ID from provided parameters.');
		return false;
	}

	/**
	 * Checks to see if the input string *might* be an email address - contains '@' after its first character
	 *
	 * @param String $email
	 *
	 * @return boolean
	 */
	public function couldBeEmail($email)
	{
		if (strlen($email) < 1)
		{
			return false;
		}

		return (strpos($email, '@', 1) !== false);
	}

	/**
	 * Gets all users. Can be restricted to valid users only with the
	 * validOnly fetch option.
	 *
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array Format: [user id] => user info
	 */
	public function getAllUsers(array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');
		$whereClause = (!empty($fetchOptions['validOnly']) ? 'WHERE user.user_state = \'valid\' AND user.is_banned = 0' : '');

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT user.*
					' . $joinOptions['selectFields'] . '
				FROM xf_user AS user
				' . $joinOptions['joinTables'] . '
				' . $whereClause . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_id');
	}

	/**
	 * Returns user records based on a list of usernames.
	 *
	 * @param array $usernames
	 * @param array $fetchOptions User fetch options
	 * @param array $invalidNames Returns a list of usernames that could not be found
	 *
	 * @return array Format: [user id] => info
	 */
	public function getUsersByNames(array $usernames, array $fetchOptions = array(), &$invalidNames = array())
	{
		$usernames = array_map('trim', $usernames);
		foreach ($usernames AS $key => $username)
		{
			if ($username === '')
			{
				unset($usernames[$key]);
			}
		}

		$invalidNames = array();

		if (!$usernames)
		{
			return array();
		}

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);
		$validOnlyClause = (!empty($fetchOptions['validOnly']) ? 'AND user.user_state = \'valid\' AND user.is_banned = 0' : '');

		$users = $this->fetchAllKeyed('
			SELECT user.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE user.username IN (' . $this->_getDb()->quote($usernames) . ')
				' . $validOnlyClause . '
		', 'user_id');

		if (count($users) != count($usernames))
		{
			$usernamesLower = array_map('strtolower', $usernames);
			$invalidNames = $usernames;

			foreach ($users AS $user)
			{
				$foundKey = array_search(strtolower($user['username']), $usernamesLower);
				if ($foundKey !== false)
				{
					unset($invalidNames[$foundKey]);
				}
			}
		}

		return $users;
	}

	/**
	 * Get users with specified user IDs.
	 *
	 * @param array $userIds
	 * @param array $fetchOptions
	 *
	 * @return array Format: [user id] => user info
	 */
	public function getUsersByIds(array $userIds, array $fetchOptions = array())
	{
		if (!$userIds)
		{
			return array();
		}

		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
				SELECT user.*
					' . $joinOptions['selectFields'] . '
				FROM xf_user AS user
				' . $joinOptions['joinTables'] . '
				WHERE user.user_id IN (' . $this->_getDb()->quote($userIds) . ')
				' . $orderClause . '
		', 'user_id');
	}

	/**
	 * Gets users that match the specified conditions.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array Format: [user id] => user info
	 */
	public function getUsers(array $conditions, array $fetchOptions = array())
	{
		$whereClause = $this->prepareUserConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT user.*
					' . $joinOptions['selectFields'] . '
				FROM xf_user AS user
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_id');
	}

	/**
	 * Gets the count of users that match the specified conditions.
	 *
	 * @param array $conditions
	 *
	 * @return array Format: [user id] => user info
	 */
	public function countUsers(array $conditions)
	{
		$fetchOptions = array();
		$whereClause = $this->prepareUserConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	/**
	 * Gets the specified user by ID.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getUserById($userId, array $fetchOptions = array())
	{
		if (empty($userId))
		{
			return false;
		}

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT user.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE user.user_id = ?
		', $userId);
	}

	/**
	 * Returns a user record based on an input username
	 *
	 * @param string $username
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getUserByName($username, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT user.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE user.username = ?
		', $username);
	}

	/**
	 * Returns a user record based on an input email
	 *
	 * @param string $email
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getUserByEmail($email, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT user.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE user.email = ?
		', $email);
	}

	/**
	 * Returns a user record based on an input username OR email
	 *
	 * @param string $input
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getUserByNameOrEmail($input, array $fetchOptions = array())
	{
		if ($this->couldBeEmail($input))
		{
			if ($user = $this->getUserByEmail($input, $fetchOptions))
			{
				return $user;
			}
		}

		return $this->getUserByName($input, $fetchOptions);
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function prepareUserFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER_PROFILE)
			{
				$selectFields .= ',
					user_profile.*';
				$joinTables .= '
					INNER JOIN xf_user_profile AS user_profile ON
						(user_profile.user_id = user.user_id)';
			}

			// TODO: optimise the join on user_option with serialization to user or user_profile
			if ($fetchOptions['join'] & self::FETCH_USER_OPTION)
			{
				$selectFields .= ',
					user_option.*';
				$joinTables .= '
					INNER JOIN xf_user_option AS user_option ON
						(user_option.user_id = user.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER_PRIVACY)
			{
				$selectFields .= ',
					user_privacy.*';
				$joinTables .= '
					INNER JOIN xf_user_privacy AS user_privacy ON
						(user_privacy.user_id = user.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER_PERMISSIONS)
			{
				$selectFields .= ',
					permission_combination.cache_value AS global_permission_cache';
				$joinTables .= '
					LEFT JOIN xf_permission_combination AS permission_combination ON
						(permission_combination.permission_combination_id = user.permission_combination_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_LAST_ACTIVITY)
			{
				$selectFields .= ',
					IF (session_activity.view_date IS NULL, user.last_activity, session_activity.view_date) AS effective_last_activity,
					session_activity.view_date, session_activity.controller_name, session_activity.controller_action, session_activity.params, session_activity.ip';
				$joinTables .= '
					LEFT JOIN xf_session_activity AS session_activity ON
						(session_activity.user_id = user.user_id AND session_activity.unique_key = user.user_id)';
			}
		}

		if (isset($fetchOptions['followingUserId']))
		{
			$fetchOptions['followingUserId'] = intval($fetchOptions['followingUserId']);
			if ($fetchOptions['followingUserId'])
			{
				// note: quoting is skipped; intval'd above
				$selectFields .= ',
					IF(user_follow.user_id IS NOT NULL, 1, 0) AS following_' . $fetchOptions['followingUserId'];
				$joinTables .= '
					LEFT JOIN xf_user_follow AS user_follow ON
						(user_follow.user_id = user.user_id AND user_follow.follow_user_id = ' . $fetchOptions['followingUserId'] . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS following_0';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Prepares a set of conditions to select users against.
	 *
	 * @param array $conditions List of conditions. (TODO: make list)
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareUserConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['username']))
		{
			if (is_array($conditions['username']))
			{
				$sqlConditions[] = 'user.username LIKE ' . XenForo_Db::quoteLike($conditions['username'][0], $conditions['username'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'user.username LIKE ' . XenForo_Db::quoteLike($conditions['username'], 'lr', $db);
			}
		}

		// this is mainly for dynamically filtering a search that already matches user names
		if (!empty($conditions['username2']))
		{
			if (is_array($conditions['username2']))
			{
				$sqlConditions[] = 'user.username LIKE ' . XenForo_Db::quoteLike($conditions['username2'][0], $conditions['username2'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'user.username LIKE ' . XenForo_Db::quoteLike($conditions['username2'], 'lr', $db);
			}
		}

		if (!empty($conditions['usernames']) && is_array($conditions['usernames']))
		{
			$sqlConditions[] = 'user.username IN (' . $db->quote($conditions['usernames']) . ')';
		}

		if (!empty($conditions['email']))
		{
			if (is_array($conditions['email']))
			{
				$sqlConditions[] = 'user.email LIKE ' . XenForo_Db::quoteLike($conditions['email'][0], $conditions['email'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'user.email LIKE ' . XenForo_Db::quoteLike($conditions['email'], 'lr', $db);
			}
		}
		if (!empty($conditions['emails']) && is_array($conditions['emails']))
		{
			$sqlConditions[] = 'user.email IN (' . $db->quote($conditions['emails']) . ')';
		}

		if (!empty($conditions['user_group_id']))
		{
			if (is_array($conditions['user_group_id']))
			{
				$sqlConditions[] = 'user.user_group_id IN (' . $db->quote($conditions['user_group_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'user.user_group_id = ' . $db->quote($conditions['user_group_id']);
			}
		}

		if (!empty($conditions['secondary_group_ids']))
		{
			if (is_array($conditions['secondary_group_ids']))
			{
				$groupConds = array();
				foreach ($conditions['secondary_group_ids'] AS $groupId)
				{
					$groupConds[] = 'FIND_IN_SET(' . $db->quote($groupId) . ', user.secondary_group_ids)';
				}
				$sqlConditions[] = '(' . implode(' OR ', $groupConds) . ')';
			}
			else
			{
				$sqlConditions[] = 'FIND_IN_SET(' . $db->quote($conditions['secondary_group_ids']) . ', user.secondary_group_ids)';
			}
		}

		if (!empty($conditions['last_activity']) && is_array($conditions['last_activity']))
		{
			list($operator, $cutOff) = $conditions['last_activity'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "user.last_activity $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['message_count']) && is_array($conditions['message_count']))
		{
			list($operator, $cutOff) = $conditions['message_count'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "user.message_count $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['user_state']) && $conditions['user_state'] !== 'any')
		{
			if (is_array($conditions['user_state']))
			{
				$sqlConditions[] = 'user.user_state IN (' . $db->quote($conditions['user_state']) . ')';
			}
			else
			{
				$sqlConditions[] = 'user.user_state = ' . $db->quote($conditions['user_state']);
			}
		}

		if (isset($conditions['is_admin']))
		{
			$sqlConditions[] = 'user.is_admin = ' . ($conditions['is_admin'] ? 1 : 0);
		}

		if (isset($conditions['is_moderator']))
		{
			$sqlConditions[] = 'user.is_moderator = ' . ($conditions['is_moderator'] ? 1 : 0);
		}

		if (isset($conditions['is_banned']))
		{
			$sqlConditions[] = 'user.is_banned = ' . ($conditions['is_banned'] ? 1 : 0);
		}

		if (!empty($conditions['receive_admin_email']))
		{
			$sqlConditions[] = 'user_option.receive_admin_email = 1';
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER_OPTION);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Construct 'ORDER BY' clause
	 *
	 * @param array $fetchOptions (uses 'order' key)
	 * @param string $defaultOrderSql Default order SQL
	 *
	 * @return string
	 */
	public function prepareUserOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			'username' => 'user.username',
			'register_date' => 'user.register_date',
			'message_count' => 'user.message_count',
			'last_activity' => 'user.last_activity'
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

	/**
	 * Returns a full user record based on an input user ID. Equivalent to
	 * calling getUserById including the FETCH_USER_FULL constanct
	 *
	 * @param integer $userId
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getFullUserById($userId, array $fetchOptions = array())
	{
		if (!empty($fetchOptions['join']))
		{
			$fetchOptions['join'] |= self::FETCH_USER_FULL;
		}
		else
		{
			$fetchOptions['join'] = self::FETCH_USER_FULL;
		}

		return $this->getUserById($userId, $fetchOptions);
	}

	/**
	 * Gets the visiting user's information based on their user ID.
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getVisitingUserById($userId)
	{
		$userinfo = $this->getUserById($userId, array(
			'join' => self::FETCH_USER_FULL | self::FETCH_USER_PERMISSIONS
		));
		if (!$userinfo)
		{
			return false;
		}

		$userinfo['csrf_token_page'] = $userinfo['user_id'] . ',' . XenForo_Application::$time
			. ',' . sha1(XenForo_Application::$time . $userinfo['csrf_token']);

		if ($userinfo['user_state'] != 'valid')
		{
			// user is not valid yet, give them guest permissions
			$userinfo = $this->setPermissionsOnVisitorArray($userinfo);
		}

		return $userinfo;
	}

	/**
	 * Get the visiting user information for a guest.
	 *
	 * @return array
	 */
	public function getVisitingGuestUser()
	{
		$options = XenForo_Application::get('options');

		$userinfo = array(
			// xf_user
			'user_id' => 0,
			'username' => '',
			'email' => '',
			'gender' => '',
			'language_id' => 0,
			'style_id' => 0,
			'timezone' => $options->guestTimeZone,
			'visible' => 1,
			'user_group_id' => 1,
			'secondary_group_ids' => '',
			'display_style_group_id' => 1,
			'permission_combination_id' => 0,
			'message_count' => 0,
			'conversations_unread' => 0,
			'register_date' => 0,
			'last_activity' => 0,
			'trophy_points' => 0,
			'alerts_unread' => 0,
			'avatar_date' => 0,
			'avatar_width' => 0,
			'avatar_height' => 0,
			'user_state' => 'valid',
			'is_moderator' => 0,
			'is_admin' => 0,
			'is_banned' => 0,
			'like_count' => 0,

			// xf_user_profile
			'dob_day' => 0,
			'dob_month' => 0,
			'dob_year' => 0,
			'csrf_token' => '',
			'facebook_auth_id' => 0,

			// xf_user_option
			'show_dob_year' => 0,
			'show_dob_date' => 0,
			'content_show_signature' => $options->guestShowSignatures,
			'receive_admin_email' => 0,
			'default_watch_state' => '',
			'is_discouraged' => 0,
			'enable_rte' => 1,

			// TODO: expand to cover all data ?

			// other tables/data
			'csrf_token_page' => '',
			'global_permission_cache' => ''
		);

		$userinfo = $this->setPermissionsOnVisitorArray($userinfo);
		return $userinfo;
	}

	/**
	 * Sets the specified permissions (combination and permissions string) on visitor array.
	 * Defaults to setting guest permissions.
	 *
	 * @param array $userinfo Visitor record
	 *
	 * @return array Visitor record with permissions
	 */
	public function setPermissionsOnVisitorArray(array $userinfo, $permissionCombinationId = false)
	{
		if (!$permissionCombinationId)
		{
			$permissionCombinationId = XenForo_Application::get('options')->guestPermissionCombinationId;
		}

		$userinfo['permission_combination_id'] = $permissionCombinationId;

		$userinfo['global_permission_cache'] = $this->_getDb()->fetchOne('
			SELECT cache_value
			FROM xf_permission_combination
			WHERE permission_combination_id = ?
		', $permissionCombinationId);

		return $userinfo;
	}

	/**
	 * Sets the permission info from the specified user ID into an array of user info
	 * (likely for a visitor array).
	 *
	 * @param array $userInfo
	 * @param integer $permUserId
	 *
	 * @return array User info with changed permissions
	 */
	public function setPermissionsFromUserId(array $userInfo, $permUserId)
	{
		$permUser = $this->getUserById($permUserId, array(
			'join' => self::FETCH_USER_PERMISSIONS
		));
		if ($permUser)
		{
			$userInfo['permission_combination_id'] = $permUser['permission_combination_id'];
			$userInfo['global_permission_cache'] = $permUser['global_permission_cache'];
		}

		return $userInfo;
	}

	/**
	 * Updates the session activity of a user.
	 *
	 * @param integer $userId
	 * @param string $ip IP of visiting user
	 * @param string $controllerName Last controller class that was invoked
	 * @param string $action Last action that was invoked
	 * @param string $viewState Either "valid" or "error"
	 * @param array $inputParams List of special input params, to include to help get more info on current activity
	 * @param integer|null $viewDate The timestamp of the last page view; defaults to now
	 */
	public function updateSessionActivity($userId, $ip, $controllerName, $action, $viewState, array $inputParams, $viewDate = null)
	{
		$userId = intval($userId);
		$ipNum = sprintf('%u', ip2long($ip));
		$uniqueKey = ($userId ? $userId : $ipNum);

		if (!$viewDate)
		{
			$viewDate = XenForo_Application::$time;
		}

		$logParams = array();
		foreach ($inputParams AS $paramKey => $paramValue)
		{
			if ($paramKey[0] == '_')
			{
				continue;
			}

			$logParams[] = "$paramKey=" . urlencode($paramValue);
		}
		$paramList = implode('&', $logParams);
		$paramList = substr($paramList, 0, 100);

		$controllerName = substr($controllerName, 0, 50);
		$action = substr($action, 0, 50);

		try
		{
			$this->_getDb()->query('
				INSERT INTO xf_session_activity
					(user_id, unique_key, ip, controller_name, controller_action, view_state, params, view_date)
				VALUES
					(?, ?, ?, ?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					ip = VALUES(ip),
					controller_name = VALUES(controller_name),
					controller_action = VALUES(controller_action),
					view_state = VALUES(view_state),
					params = VALUES(params),
					view_date = VALUES(view_date)
			', array($userId, $uniqueKey, $ipNum, $controllerName, $action, $viewState, $paramList, $viewDate));
		}
		catch (Zend_Db_Exception $e) {} // ignore db errors here, not that important
	}

	/**
	 * Deletes the session activity record for the specified user / IP address
	 *
	 * @param integer $userId
	 * @param string $ip
	 */
	public function deleteSessionActivity($userId, $ip)
	{
		$userId = intval($userId);
		$ipNum = sprintf('%u', ip2long($ip));
		$uniqueKey = ($userId ? $userId : $ipNum);

		$db = $this->_getDb();
		$db->delete('xf_session_activity', 'user_id = ' . $db->quote($userId) . ' AND unique_key = ' . $db->quote($uniqueKey));
	}

	/**
	 * Gets the latest (valid) user to join.
	 *
	 * @return array|false
	 */
	public function getLatestUser()
	{
		return $this->_getDb()->fetchRow($this->limitQueryResults('
			SELECT *
			FROM xf_user
			WHERE user_state = \'valid\'
				 AND is_banned = 0
			ORDER BY register_date DESC
		', 1));
	}

	/**
	 * Fetch the most recently-registered users
	 *
	 * @param array $criteria
	 * @param array $fetchOptions
	 *
	 * @return array User records
	 */
	public function getLatestUsers(array $criteria, array $fetchOptions = array())
	{
		$fetchOptions['order'] = 'register_date';
		$fetchOptions['direction'] = 'desc';

		return $this->getUsers($criteria, $fetchOptions);
	}

	/**
	 * Fetch the most active users
	 *
	 * @param array $criteria
	 * @param array $fetchOptions
	 *
	 * @return array User records
	 */
	public function getMostActiveUsers(array $criteria, array $fetchOptions = array())
	{
		$fetchOptions['order'] = 'message_count';
		$fetchOptions['direction'] = 'desc';

		return $this->getUsers($criteria, $fetchOptions);
	}

	/**
	 * Gets the count of total users.
	 *
	 * @return integer
	 */
	public function countTotalUsers()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user
			WHERE user_state = \'valid\'
				 AND is_banned = 0
		');
	}

	/**
	 * Gets the user authentication record by user ID.
	 *
	 * @param integer $userId
	 *
	 * @return array|false
	 */
	public function getUserAuthenticationRecordByUserId($userId)
	{
		return $this->_getDb()->fetchRow('

			SELECT *
			FROM xf_user_authenticate
			WHERE user_id = ?

		', $userId);
	}

	/**
	 * Returns an auth object based on an input userid
	 *
	 * @param integer Userid
	 *
	 * @return XenForo_Authentication_Abstract|false
	 */
	public function getUserAuthenticationObjectByUserId($userId)
	{
		$authenticate = $this->getUserAuthenticationRecordByUserId($userId);
		if (!$authenticate)
		{
			return false;
		}

		$auth = XenForo_Authentication_Abstract::create($authenticate['scheme_class']);
		if (!$auth)
		{
			return false;
		}

		$auth->setData($authenticate['data']);
		return $auth;
	}

	/**
	 * Logs the given user in (as the visiting user). Exceptions are thrown on errors.
	 *
	 * @param string $nameOrEmail User name or email address
	 * @param string $password
	 * @param string $error Error string (by ref)
	 *
	 * @return integer|false User ID auth'd as; false on failure
	 */
	public function validateAuthentication($nameOrEmail, $password, &$error = '')
	{
		$user = $this->getUserByNameOrEmail($nameOrEmail);
		if (!$user)
		{
			$error = new XenForo_Phrase('requested_user_x_not_found', array('name' => $nameOrEmail));
			return false;
		}

		$authentication = $this->getUserAuthenticationObjectByUserId($user['user_id']);
		if (!$authentication->authenticate($user['user_id'], $password))
		{
			$error = new XenForo_Phrase('incorrect_password');
			return false;
		}

		return $user['user_id'];
	}

	/**
	 * Logs a user in based on their remember key from a cookie.
	 *
	 * @param integer $userId
	 * @param string $rememberKey
	 * @param array|false|null $auth User's auth record (retrieved if null)
	 *
	 * @return boolean
	 */
	public function loginUserByRememberKeyFromCookie($userId, $rememberKey, $auth = null)
	{
		if ($auth === null)
		{
			$auth = $this->getUserAuthenticationRecordByUserId($userId);
		}

		if (!$auth || $this->prepareRememberKeyForCookie($auth['remember_key']) !== $rememberKey)
		{
			return false;
		}

		return true;
	}

	/**
	 * Logs a user in based on the raw value of the remember cookie.
	 *
	 * @param string $userCookie
	 *
	 * @return false|integer
	 */
	public function loginUserByRememberCookie($userCookie)
	{
		if (!$userCookie)
		{
			return false;
		}

		$userCookieParts = explode(',', $userCookie);
		if (count($userCookieParts) < 2)
		{
			return false;
		}

		$userId = intval($userCookieParts[0]);
		$rememberKey = $userCookieParts[1];
		if (!$userId || !$rememberKey)
		{
			return false;
		}

		$auth = $this->getUserAuthenticationRecordByUserId($userId);
		$loggedIn = $this->loginUserByRememberKeyFromCookie($userId, $rememberKey, $auth);
		if ($loggedIn)
		{
			$this->setUserRememberCookie($userId, $auth);
			return $userId;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets the user remember cookie for the specified user ID.
	 *
	 * @param integer $userId
	 * @param array|false|null $auth User's auth record (retrieved if null)
	 *
	 * @return boolean
	 */
	public function setUserRememberCookie($userId, $auth = null)
	{
		if ($auth === null)
		{
			$auth = $this->getUserAuthenticationRecordByUserId($userId);
		}

		if (!$auth)
		{
			return false;
		}

		$value = intval($userId) . ',' . $this->prepareRememberKeyForCookie($auth['remember_key']);

		XenForo_Helper_Cookie::setCookie('user', $value, 7 * 86400, true);

		return true;
	}

	/**
	 * Prepares the remember key for use in a cookie (or for comparison against the cookie).
	 *
	 * @param string $rememberKey Key from DB
	 *
	 * @return string
	 */
	public function prepareRememberKeyForCookie($rememberKey)
	{
		return sha1(XenForo_Application::get('config')->globalSalt . $rememberKey);
	}

	/**
	 * Prepares a user record for display.
	 *
	 * @param array $user User info
	 *
	 * @return array Prepared user info
	 */
	public function prepareUser(array $user)
	{
		if (empty($user['user_id']))
		{
			$user['display_style_group_id'] = self::$defaultGuestGroupId;
		}

		// "trusted" user check - used to determine if no follow is enabled
		if (empty($user['user_id']))
		{
			$user['isTrusted'] = false;
		}
		else
		{
			$user['isTrusted'] = ($user['is_admin'] || $user['is_moderator']);
		}

		return $user;
	}

	/**
	 * Prepares the data needed for the simple user card-like output.
	 *
	 * @param array $user
	 *
	 * @return array
	 */
	public function prepareUserCard(array $user)
	{
		$user['age'] = $this->_getUserProfileModel()->getUserAge($user);

		return $user;
	}

	/**
	 * Prepares a batch of user cards.
	 *
	 * @param array $users
	 *
	 * @return array
	 */
	public function prepareUserCards(array $users)
	{
		foreach ($users AS &$user)
		{
			$user = $this->prepareUserCard($user);
		}

		return $users;
	}

	/**
	 * Inserts (or updates an existing) user group change set.
	 *
	 * @param integer $userId
	 * @param string $key Unique identifier for change set
	 * @param string|array $addGroups Comma delimited string or array of user groups to add
	 *
	 * @return boolean True on change success
	 */
	public function addUserGroupChange($userId, $key, $addGroups)
	{
		if (is_array($addGroups))
		{
			$addGroups = implode(',', $addGroups);
		}

		$oldGroups = $this->getUserGroupChangesForUser($userId);

		$newGroups = $oldGroups;

		if (isset($newGroups[$key]) && !$addGroups)
		{
			// already exists and we're removing the groups, so we can just remove the record
			return $this->removeUserGroupChange($userId, $key);
		}

		$newGroups[$key] = $addGroups;

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->query('
			INSERT INTO xf_user_group_change
				(user_id, change_key, group_ids)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE
				group_ids = VALUES(group_ids)
		', array($userId, $key, $addGroups));

		$success = $this->_applyUserGroupChanges($userId, $oldGroups, $newGroups);

		XenForo_Db::commit($db);

		return $success;
	}

	/**
	 * Removes the specified user group change set.
	 *
	 * @param integer $userId
	 * @param string $key Change set key
	 *
	 * @return boolean True on success
	 */
	public function removeUserGroupChange($userId, $key)
	{
		$oldGroups = $this->getUserGroupChangesForUser($userId);
		if (!isset($oldGroups[$key]))
		{
			// already removed?
			return true;
		}

		$newGroups = $oldGroups;
		unset($newGroups[$key]);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->delete('xf_user_group_change',
			'user_id = ' . $db->quote($userId) . ' AND change_key = ' . $db->quote($key)
		);

		$success = $this->_applyUserGroupChanges($userId, $oldGroups, $newGroups);

		XenForo_Db::commit($db);

		return $success;
	}

	/**
	 * Gets the user group change sets for the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return array [change key] => comma list of group IDs
	 */
	public function getUserGroupChangesForUser($userId)
	{
		return $this->_getDb()->fetchPairs('
			SELECT change_key, group_ids
			FROM xf_user_group_change
			WHERE user_id = ?
		', $userId);
	}

	/**
	 * Applies a set of user group changes.
	 *
	 * @param integer $userId
	 * @param array $oldGroupStrings Array of comma-delimited strings of existing (accounted for) user group change sets
	 * @param array $newGroupStrings Array of comma-delimited strings for new list of user group change sets
	 *
	 * @return boolean
	 */
	protected function _applyUserGroupChanges($userId, array $oldGroupStrings, array $newGroupStrings)
	{
		$oldGroups = array();
		foreach ($oldGroupStrings AS $string)
		{
			$oldGroups = array_merge($oldGroups, explode(',', $string));
		}
		$oldGroups = array_unique($oldGroups);

		$newGroups = array();
		foreach ($newGroupStrings AS $string)
		{
			$newGroups = array_merge($newGroups, explode(',', $string));
		}
		$newGroups = array_unique($newGroups);

		$addGroups = array_diff($newGroups, $oldGroups);
		$removeGroups = array_diff($oldGroups, $newGroups);

		if (!$addGroups && !$removeGroups)
		{
			return true;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
		if (!$dw->setExistingData($userId))
		{
			return false;
		}

		$secondaryGroups = explode(',', $dw->get('secondary_group_ids'));
		if ($removeGroups)
		{
			foreach ($secondaryGroups AS $key => $secondaryGroup)
			{
				if (in_array($secondaryGroup, $removeGroups))
				{
					unset($secondaryGroups[$key]);
				}
			}
		}
		if ($addGroups)
		{
			$secondaryGroups = array_merge($secondaryGroups, $addGroups);
		}

		$dw->setSecondaryGroups($secondaryGroups);
		$dw->save();

		return true;
	}

	/**
	 * Determines if a user is a member of a particular user group
	 *
	 * @param array $user
	 * @param integer $userGroupId
	 * @param boolean Also check secondary groups
	 *
	 * @return boolean
	 */
	public function isMemberOfUserGroup(array $user, $userGroupId, $includeSecondaryGroups = true)
	{
		if ($user['user_group_id'] == $userGroupId)
		{
			return true;
		}

		if ($includeSecondaryGroups && strpos(",{$user['secondary_group_ids']},", ",{$userGroupId},") !== false)
		{
			return true;
		}

		return false;
	}

	/**
	 * Creates a new follower record for $userId following $followUserId(s)
	 *
	 * @param array Users being followed
	 * @param boolean Check for and prevent duplicate followers
	 * @param array $user User doing the following
	 *
	 * @return string Comma-separated list of all users now being followed by $userId
	 */
	public function follow(array $followUsers, $dupeCheck = true, array $user = null)
	{
		if ($user === null)
		{
			$user = XenForo_Visitor::getInstance();
		}

		// if we have only a single user, build the multi-user array structure
		if (isset($followUsers['user_id']))
		{
			$followUsers = array($followUsers['user_id'] => $followUsers);
		}

		if ($dupeCheck)
		{
			$followUsers = $this->removeDuplicateFollowUserIds($user['user_id'], $followUsers);
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		foreach ($followUsers AS $followUser)
		{
			if ($user['user_id'] == $followUser['user_id'])
			{
				continue;
			}

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Follower', XenForo_DataWriter::ERROR_SILENT);
			$writer->setOption(XenForo_DataWriter_Follower::OPTION_POST_WRITE_UPDATE_USER_FOLLOWING, false);
			$writer->set('user_id', $user['user_id']);
			$writer->set('follow_user_id', $followUser['user_id']);
			$success = $writer->save();

			if ($success && XenForo_Model_Alert::userReceivesAlert($followUser, 'user', 'following'))
			{
				XenForo_Model_Alert::alert(
					$followUser['user_id'],
					$user['user_id'], $user['username'],
					'user', $followUser['user_id'],
					'following'
				);
			}
		}

		$return = $this->updateFollowingDenormalizedValue($user['user_id']);

		XenForo_Db::commit($db);

		return $return;
	}

	/**
	 * Deletes an existing follower record for $userId following $followUserId
	 *
	 * @param integer $followUserId User being followed
	 * @param integer $userId User doing the following
	 *
	 * @return string Comma-separated list of all users now being followed by $userId
	 */
	public function unfollow($followUserId, $userId = null)
	{
		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Follower', XenForo_DataWriter::ERROR_SILENT);
		$writer->setOption(XenForo_DataWriter_Follower::OPTION_POST_WRITE_UPDATE_USER_FOLLOWING, false);
		$writer->setExistingData(array($userId, $followUserId));
		$writer->delete();

		$value = $this->updateFollowingDenormalizedValue($userId);

		// delete alerts
		$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('user', $followUserId, $userId, 'follow');

		XenForo_Db::commit($db);

		return $value;
	}

	/**
	 * Compares an array of user IDs to be followed with the existing value and removes any duplicates
	 * to prevent duplicate key errors on insertion
	 *
	 * @param integer $userId
	 * @param array $newUsers (full user arrays)
	 * @param string $existingUserIds '3,6,42,....'
	 *
	 * @return array
	 */
	public function removeDuplicateFollowUserIds($userId, array $newUsers, $existingUserIds = null)
	{
		if ($existingUserIds === null)
		{
			$existingUserIds = $this->getFollowingDenormalizedValue($userId);
		}

		$existingUserIds = explode(',', $existingUserIds);

		foreach ($newUsers AS $i => $newUser)
		{
			if (in_array($newUser['user_id'], $existingUserIds))
			{
				unset($newUsers[$i]);
			}
		}

		return $newUsers;
	}

	/**
	 * Fetches a single user-following-user record.
	 *
	 * @param integer|array $userId - the user doing the following
	 * @param integer|array $followUserId - the user being followed
	 *
	 * @return array
	 */
	public function getFollowRecord($userId, $followUserId)
	{
		return $this->_getDb()->fetchRow('

			SELECT *
			FROM xf_user_follow
			WHERE user_id = ?
			AND follow_user_id = ?

		', array(
			$this->getUserIdFromUser($userId),
			$this->getUserIdFromUser($followUserId)
		));
	}

	/**
	 * Gets an array of all users being followed by the specified user
	 *
	 * @param integer|array $userId|$user
	 * @param integer $maxResults (0 = all)
	 * @param string $orderBy
	 *
	 * @return array
	 */
	public function getFollowedUserProfiles($userId, $maxResults = 0, $orderBy = 'user.username')
	{
		$sql = '
			SELECT
				user.*,
				user_profile.*,
				user_option.*
			FROM xf_user_follow AS user_follow
			INNER JOIN xf_user AS user ON
				(user.user_id = user_follow.follow_user_id)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			WHERE user_follow.user_id = ?
			ORDER BY ' . $orderBy . '
		';

		if ($maxResults)
		{
			$sql = $this->limitQueryResults($sql, $maxResults);
		}

		return $this->fetchAllKeyed($sql, 'user_id', $this->getUserIdFromUser($userId));
	}

	/**
	 * Generates the denormalized, comma-separated version of a user's following
	 *
	 * @param $userId
	 *
	 * @return string
	 */
	public function getFollowingDenormalizedValue($userId)
	{
		return implode(',', $this->_getDb()->fetchCol('

			SELECT follow_user_id
			FROM xf_user_follow AS user_follow
			INNER JOIN xf_user AS user ON
				(user.user_id = user_follow.follow_user_id)
			WHERE user_follow.user_id = ?
			ORDER BY user.username

		', $this->getUserIdFromUser($userId)));
	}

	/**
	 * Returns whether or not the specified user is being followed by the follower
	 *
	 * @param integer $userId User being followed
	 * @param array $follower User doing the following
	 *
	 * @return boolean
	 */
	public function isFollowing($userId, array $follower = null)
	{
		if ($follower === null)
		{
			$follower = XenForo_Visitor::getInstance();
		}

		if (!$follower['user_id'] || $follower['user_id'] == $userId)
		{
			return false;
		}

		return (strpos(",{$follower['following']},", ",{$userId},") !== false);
	}

	/**
	 * Updates the denormalized, comma-separated version of a user's following.
	 * Will query for the value if it is not provided
	 *
	 * @param integer|array $userId|$user
	 * @param string Denormalized following value
	 *
	 * @return string
	 */
	public function updateFollowingDenormalizedValue($userId, $following = false)
	{
		$userId = $this->getUserIdFromUser($userId);

		if ($following === false)
		{
			$following = $this->getFollowingDenormalizedValue($userId);
		}

		$this->update($userId, 'following', $following);

		return $following;
	}

	/**
	 * Gets the user information for all users following the specified user.
	 *
	 * @param integer $userId
	 * @param integer $maxResults (0 = all)
	 * @param string $orderBy
	 *
	 * @return array Format: [user id] => following user info
	 */
	public function getUsersFollowingUserId($userId, $maxResults = 0, $orderBy = 'user.username')
	{
		$sql = '
			SELECT user.*,
				user_profile.*,
				user_option.*
			FROM xf_user_follow AS user_follow
			INNER JOIN xf_user AS user ON
				(user.user_id = user_follow.user_id)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			WHERE user_follow.follow_user_id = ?
			ORDER BY ' . $orderBy . '
		';

		if ($maxResults)
		{
			$sql = $this->limitQueryResults($sql, $maxResults);
		}

		return $this->fetchAllKeyed($sql, 'user_id', $userId);
	}

	/**
	 * Gets the count of users following the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return array Format: [user id] => following user info
	 */
	public function countUsersFollowingUserId($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_follow
			WHERE follow_user_id = ?
		', $userId);
	}

	/**
	 * Returns a keyed array of all available identity services
	 *
	 * @return array identityServiceId => record of xf_identity_service
	 */
	public function getIdentityServices()
	{
		$localCacheKey = 'identityServices';
		if (($identityServices = $this->_getLocalCacheData($localCacheKey)) === false)
		{
			$identityServices = $this->fetchAllKeyed('

				SELECT *
				FROM xf_identity_service

			', 'identity_service_id');

			$this->setLocalCacheData($localCacheKey, $identityServices);
		}

		return $identityServices;
	}

	/**
	 * Fetches a single identity service record
	 *
	 * @param string $serviceId
	 *
	 * @return array
	 */
	public function getIdentityService($serviceId)
	{
		return $this->_getDb()->fetchRow('

			SELECT *
			FROM xf_identity_service
			WHERE identity_service_id = ?

		', $serviceId);
	}

	/**
	 * Returns an array of instant messaging accounts registered for a user
	 *
	 * @param integer|array $userId|$user
	 *
	 * @return array
	 */
	public function getIdentities($userId)
	{
		//TODO: Make use of the denormalized field xf_user_profile.identities
		//note: If this changes from returning fetchPairs, change XenForo_Model_User->getIdentitiesDenormalizedField() accordingly.
		return $this->_getDb()->fetchPairs('

			SELECT identity_service_id, account_name
			FROM xf_user_identity
			WHERE user_id = ?

		', $this->getUserIdFromUser($userId));
	}

	/**
	 * Fetches an array of identity services including account names for the specified user, and phrase keys for an editing interface.
	 *
	 * @param array $identities
	 *
	 * @return array (serviceId => (accountName, identityServiceId, labelPhrase, hintPhrase))
	 */
	public function getIdentityServicesEditingData(array $identities = array())
	{
		$identityServices = $this->getIdentityServices();

		foreach ($identityServices AS $identityServiceId => $identityService)
		{
			$identityServices[$identityServiceId] = $this->getIdentityServiceEditingData($identityService,
				isset($identities[$identityServiceId]) ? $identities[$identityServiceId] : ''
			);
		}

		return $identityServices;
	}

	/**
	 * Fetches an array with which one can build the editor for a single identity service account
	 *
	 * @param array|string $identityService Result from XenForo_Model_User->getIdentityService() OR identity_service_id string
	 * @param string $accountName (optional)
	 *
	 * @return array (accountName, identityServiceId, labelPhrase, hintPhrase)
	 */
	public function getIdentityServiceEditingData($identityService, $accountName = '')
	{
		if (!is_array($identityService))
		{
			$identityService = $this->getIdentityService($identityService);
		}

		return array_merge($identityService, array(
			'account_name' => $accountName,
			'name' => new XenForo_Phrase($this->getIdentityServiceNamePhraseName($identityService['identity_service_id'])),
			'hint' => new XenForo_Phrase($this->getIdentityServiceHintPhraseName($identityService['identity_service_id']))
		));
	}

	public function getIdentityServiceNamePhraseName($identityServiceId)
	{
		return 'identity_service_name_' . $identityServiceId;
	}

	public function getIdentityServiceHintPhraseName($identityServiceId)
	{
		return 'identity_service_hint_' . $identityServiceId;
	}

	/**
	 * Gets a list of identities as a printable list.
	 *
	 * @param string|array $identityValues List of identity key-value pairs
	 *
	 * @return array Format: [identity id] => [title, value]
	 */
	public function getPrintableIdentityList($identityValues)
	{
		if (!is_array($identityValues))
		{
			$identityValues = unserialize($identityValues);
		}

		if (!$identityValues)
		{
			return array();
		}

		$output = array();
		foreach ($identityValues AS $key => $value)
		{
			$output[$key] = array(
				'title' => new XenForo_Phrase($this->getIdentityServiceNamePhraseName($key)),
				'value' => $value
			);
		}

		return $output;
	}

	/**
	 * Updates a user's identity records
	 *
	 * @param $userId
	 * @param $identities
	 * @return unknown_type
	 */
	public function updateIdentities($userId, $identities)
	{
		$db = $this->_getDb();

		$db->delete('xf_user_identity', 'user_id = ' . $this->_getDb()->quote($userId));

		if (is_array($identities))
		{
			foreach ($identities AS $identityServiceId => $accountName)
			{
				$db->insert('xf_user_identity', array(
					'user_id'       => $userId,
					'identity_service_id' => $identityServiceId,
					'account_name'   => $accountName
				));
			}
		}

		return true;
	}

	/**
	 * Verifies that a particular account name is valid for a given instant messaging service
	 *
	 * @param string|array $identityServiceId (aim, gtalk, skype etc.), or complete array from xf_identity_service
	 * @param string $accountName Can be modified to contain what is expected
	 * @param string Reference: If this function returns false, an error will be stored here.
	 *
	 * @return boolean
	 */
	public function verifyIdentity($identityServiceId, &$accountName, &$error)
	{
		if (is_array($identityServiceId) && isset($identityServiceId['model_class']))
		{
			$identityService = $identityServiceId;
		}
		else
		{
			$identityService = $this->getModelFromCache('XenForo_Model_User')->getIdentityService($identityServiceId);
		}

		if (empty($identityService['model_class']))
		{
			$error = new XenForo_Phrase('specified_instant_messaging_service_was_not_recognised');
			return false;
		}

		if (!$this->getModelFromCache($identityService['model_class'])->verifyAccountName($accountName, $accountNameError))
		{
			$error = $accountNameError;
			return false;
		}

		return true;
	}

	/**
	 * Returns an array containing the user ids found from the complete result given the range specified,
	 * along with the total number of users found.
	 *
	 * @param integer Find users with user_id greater than...
	 * @param integer Maximum users to return at once
	 *
	 * @return array
	 */
	public function getUserIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT user_id
			FROM xf_user
			WHERE user_id > ?
			ORDER BY user_id
		', $limit), $start);
	}

	/**
	 * Returns the number of unread alerts belonging to a user - following fresh recalculation
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function getUnreadAlertsCount($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*) AS total
			FROM xf_user_alert
			WHERE alerted_user_id = ?
				AND view_date = 0
		', $userId);
	}

	/**
	 * Determines if the user has permission to bypass users' privacy preferences, including online status and activity feed
	 *
	 * @param string $errorPhraseKey
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canBypassUserPrivacy(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'bypassUserPrivacy'))
		{
			return true;
		}

		return false;
	}

	/**
	 * Determines if permissions are sufficient to view on the specified
	 * user's online status.
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canViewUserOnlineStatus(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!$user['user_id'] || !$user['last_activity'])
		{
			return false;
		}
		else if ($user['visible'])
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);

		if ($user['user_id'] == $viewingUser['user_id'])
		{
			// can always view own
			return true;
		}

		return $this->canBypassUserPrivacy($errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if the viewing user can start a conversation with the given user.
	 * Does not check standard conversation permissions.
	 *
	 * @param array $user
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStartConversationWithUser(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!$user['user_id'])
		{
			return false;
		}

		return $this->getModelFromCache('XenForo_Model_Conversation')->canStartConversationWithUser(
			$user, $errorPhraseKey, $viewingUser
		);
	}

	/**
	 * Determines if the viewing user can view IPs logged with posts, profile posts etc.
	 *
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewIps(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'viewIps'));
	}

	/**
	 * Determines if the viewing user passes the specified privacy check.
	 * This must include the following status for the viewing user.
	 *
	 * @param string $privacyRequirement The required privacy: everyone, none, members, followed
	 * @param array $user User info, including following status for viewing user
	 * @param array|null $viewingUser Viewing user ref
	 * @return unknown_type
	 */
	public function passesPrivacyCheck($privacyRequirement, array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!isset($user['following_' . $viewingUser['user_id']]) && !isset($user['following']))
		{
			throw new XenForo_Exception('Missing following state for user ID ' . $viewingUser['user_id'] . ' in user ' . $user['user_id']);
		}

		if ($user['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if ($this->canBypassUserPrivacy($viewingUser))
		{
			return true;
		}

		/*if ($viewingUser['is_admin'] || $viewingUser['is_moderator'])
		{
			return true;
		}*/

		switch ($privacyRequirement)
		{
			case 'everyone': return true;
			case 'none':     return false;
			case 'members':  return ($viewingUser['user_id'] > 0);

			case 'followed':
				if (isset($user['following_' . $viewingUser['user_id']]))
				{
					return ($user['following_' . $viewingUser['user_id']] > 0);
				}
				else if (!empty($user['following']))
				{
					return in_array($viewingUser['user_id'], explode(',', $user['following']));
				}
				else
				{
					return false;
				}


			default:
				return true;
		}
	}

	/**
	 * Fetches the logged registration IP addresses for the specified user, if available.
	 *
	 * @param integer $userId
	 *
	 * @return array [ register: string, account-confirmation: string ]
	 */
	public function getRegistrationIps($userId)
	{
		$ips = $this->_getDb()->fetchPairs('
			SELECT action, ip
			FROM xf_ip
			WHERE user_id = ?
			AND content_type = \'user\'
			AND action IN(\'register\', \'account-confirmation\')
		', $userId);

		return array_map('long2ip', $ips);
	}

	/**
	 * Determines whether or not the specified user may have the spam cleaner applied against them.
	 *
	 * @param array $user
	 * @param string|array Error phrase key - may become an array if the phrase requires parameters
	 *
	 * @return boolean
	 */
	public function couldBeSpammer(array $user, &$errorKey = '')
	{
		// self
		if ($user['user_id'] == XenForo_Visitor::getUserId())
		{
			$errorKey = 'sorry_dave';
			return false;
		}

		// staff
		if ($user['is_admin'] || $user['is_moderator'])
		{
			$errorKey = 'spam_cleaner_no_admins_or_mods';
			return false;
		}

		$criteria = XenForo_Application::get('options')->spamUserCriteria;

		if ($criteria['message_count'] && $user['message_count'] > $criteria['message_count'])
		{
			$errorKey = array('spam_cleaner_too_many_messages', 'message_count' => $criteria['message_count']);
			return false;
		}

		if ($criteria['register_date'] && $user['register_date'] < (XenForo_Application::$time - $criteria['register_date'] * 86400))
		{
			$errorKey = array('spam_cleaner_registered_too_long', 'register_days' => $criteria['register_date']);
			return false;
		}

		if ($criteria['like_count'] && $user['like_count'] > $criteria['like_count'])
		{
			$errorKey = array('spam_cleaner_too_many_likes', 'like_count' => $criteria['like_count']);
			return false;
		}

		return true;
	}

	/**
	 * Bans a user or updates an existing ban.
	 *
	 * @param integer $userId ID of user to ban
	 * @param integer $endDate Date at which ban will be lifted. Use XenForo_Model_User::PERMANENT_BAN for a permanent ban.
	 * @param $reason
	 * @param $update
	 * @param $errorKey
	 * @param $viewingUser
	 *
	 * @return boolean
	 */
	public function ban($userId, $endDate, $reason, $update = false, &$errorKey, array $viewingUser = null)
	{
		if ($endDate < XenForo_Application::$time && $endDate !== self::PERMANENT_BAN)
		{
			$errorKey = 'please_enter_a_date_in_the_future';
			return false;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserBan');
		if ($update)
		{
			$dw->setExistingData($userId);
		}
		else
		{
			$dw->set('user_id', $userId);
			$dw->set('ban_user_id', XenForo_Visitor::getUserId());
		}

		$dw->set('end_date', $endDate);
		$dw->set('user_reason', $reason);
		$dw->preSave();

		if ($dw->hasErrors())
		{
			$errors = $dw->getErrors();
			$errorKey = reset($errors);
			return false;
		}

		$dw->save();
		return true;
	}

	/**
	 * Lifts the ban on the specified user
	 *
	 * @param integer $userId
	 *
	 * @return boolean
	 */
	public function liftBan($userId)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserBan', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($userId);
		return $dw->delete();
	}

	/**
	 * @return XenForo_Model_UserProfile
	 */
	protected function _getUserProfileModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserProfile');
	}

	/**
	 * @return XenForo_Model_Ip
	 */
	protected function _getIpModel()
	{
		return $this->getModelFromCache('XenForo_Model_Ip');
	}
}