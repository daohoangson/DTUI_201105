<?php

/**
 * Model for sessions. Updating the session activity is done in the user model.
 * Querying for the current session is done via XenForo_Session.
 *
 * @package XenForo_Session
 */
class XenForo_Model_Session extends XenForo_Model
{
	const FETCH_USER = 1;

	/**
	 * Get session activity records matching the conditions and fetch options.
	 *
	 * @param array $conditions List of conditions to constrain results to. See prepareSessionActivityConditions.
	 * @param array $fetchOptions List of fetch options (includes limit options). See prepareSessionActivityFetchOptions
	 *
	 * @return array [] => activity record
	 */
	public function getSessionActivityRecords(array $conditions = array(), array $fetchOptions = array())
	{
		// TODO: there is value in caching the online user list (even for just a minute)

		$whereConditions = $this->prepareSessionActivityConditions($conditions, $fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$sqlClauses = $this->prepareSessionActivityFetchOptions($fetchOptions);

		return $this->_getDb()->fetchAll($this->limitQueryResults(
			'
				SELECT session_activity.*
					' . $sqlClauses['selectFields'] . '
				FROM xf_session_activity AS session_activity
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . '
				' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		));
	}

	/**
	 * Gets a quick list of session activity info, primarily useful for the list of
	 * online users with the forum list.
	 *
	 * @param array $viewingUser
	 * @param array $conditions List of conditions; should probably include cutOff
	 * @param array|null $forceInclude Forces the specified user to be included
	 *
	 * @return array Keys: guests, members, total (all including invisible), records (details of users that can be seen)
	 */
	public function getSessionActivityQuickList(array $viewingUser, array $conditions = array(), array $forceInclude = null)
	{
		$fetchOptions = array(
			'join' => self::FETCH_USER,
			'order' => 'view_date'
		);
		$conditions['getInvisible'] = true; // filtered out if needed, but included in count
		$conditions['getUnconfirmed'] = true; // also filtered out but included in count

		$records = $this->getSessionActivityRecords($conditions, $fetchOptions);

		$canBypassUserPrivacy = $this->getModelFromCache('XenForo_Model_User')->canBypassUserPrivacy();

		$forceIncludeUserId = ($forceInclude ? $forceInclude['user_id'] : 0);

		if (!empty($viewingUser['following']))
		{
			$following = explode(',', $viewingUser['following']);
		}
		else
		{
			$following = array();
		}

		// TODO: identify and count spiders
		$output = array(
			'guests' => 0,
			'members' => 0,
		);

		foreach ($records AS $key => &$record)
		{
			if ($record['user_id'] == 0)
			{
				$output['guests']++;

				unset($records[$key]);
				continue;
			}
			else if ($forceIncludeUserId == $record['user_id'])
			{
				// always include forced user
				$output['members']++;

				$forceInclude = null;
				$forceIncludeUserId = 0;
			}
			else
			{
				$output['members']++;

				if ($record['user_state'] != 'valid' || !$record['visible'])
				{
					if (!$canBypassUserPrivacy)
					{
						unset($records[$key]);
						continue;
					}
				}

				if (in_array($record['user_id'], $following))
				{
					$record['followed'] = true;
				}
			}
		}

		if ($forceInclude)
		{
			array_unshift($records, $forceInclude);
			$output['members']++;
		}

		$limit = XenForo_Application::get('options')->membersOnlineLimit;
		$totalRecords = count($records);

		// maxiumum user online
		if ($limit == 0 || $totalRecords < $limit)
		{
			$output['limit'] = $totalRecords;
		}
		else
		{
			$output['limit'] = $limit;
		}

		// total members online subtract max members to show (minimum 0)
		$output['recordsUnseen'] = ($limit ? max($totalRecords - $limit, 0) : 0);

		// total visitors
		$output['total'] = $output['guests'] + $output['members'];

		// visitor records
		$output['records'] = $records;

		return $output;
	}

	/**
	 * Counts the number of session activity records matching conditions.
	 *
	 * @param array $conditions
	 *
	 * @return integer
	 */
	public function countSessionActivityRecords(array $conditions)
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareSessionActivityConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareSessionActivityFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_session_activity AS session_activity
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions
		);
	}

	/**
	 * Prepares the possible session activity conditions.
	 *
	 * @param array $conditions List of conditions.
	 * @param array $fetchOptions By reference; may be pushed to if conditions require
	 *
	 * @return string Conditions in where clause
	 */
	public function prepareSessionActivityConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['userLimit']))
		{
			switch ($conditions['userLimit'])
			{
				case 'registered': $sqlConditions[] = 'session_activity.user_id > 0'; break;
				case 'guest': $sqlConditions[] = 'session_activity.user_id = 0'; break;
			}
		}

		if (!empty($conditions['user_id']))
		{
			$sqlConditions[] = 'session_activity.user_id = ' . $db->quote($conditions['user_id']);
		}

		if (!empty($conditions['forceInclude']))
		{
			$forceIncludeClause = ' OR user.user_id = ' . $db->quote($conditions['forceInclude']);
		}
		else
		{
			$forceIncludeClause = '';
		}

		if (empty($conditions['getInvisible']))
		{
			$sqlConditions[] = 'user.visible = 1 OR session_activity.user_id = 0' . $forceIncludeClause;
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER);
		}

		if (empty($conditions['getUnconfirmed']))
		{
			$sqlConditions[] = 'user.user_state = \'valid\' OR session_activity.user_id = 0' . $forceIncludeClause;
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER);
		}

		if (!empty($conditions['cutOff']) && is_array($conditions['cutOff']))
		{
			list($operator, $cutOff) = $conditions['cutOff'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "session_activity.view_date $operator " . $db->quote($cutOff);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Prepares the session activity fetch options, including order, joins, and extra fields.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Keys: selectFields, joinTables, orderClause
	 */
	public function prepareSessionActivityFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (!empty($fetchOptions['order']))
		{
			switch ($fetchOptions['order'])
			{
				case 'username':
					$orderBy = 'user.username';
					$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER);
					break;

				case 'view_date':
					$orderBy = 'session_activity.view_date DESC';
					break;
			}
		}

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*,
					user_profile.*,
					user_option.*';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = session_activity.user_id)
					LEFT JOIN xf_user_profile AS user_profile ON
						(user_profile.user_id = user.user_id)
					LEFT JOIN xf_user_option AS user_option ON
						(user_option.user_id = user.user_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	/**
	 * Adds details about session activity to a list of session activity records.
	 *
	 * @param array $activities
	 *
	 * @return array Activity records (in same order), with details in activityDescription/activityItemTitle/activityItemUrl keys.
	 */
	public function addSessionActivityDetailsToList(array $activities)
	{
		// TODO: in the future, probably remove dependence on the visitor object (via called controllers)

		$controllerGroups = array();
		foreach ($activities AS $key => $activity)
		{
			$activity['params'] = XenForo_Application::parseQueryString($activity['params']);

			$controllerGroups[$activity['controller_name']][$key] = $activity;
		}

		foreach ($controllerGroups AS $controller => $controllerGroup)
		{
			if ($controller && XenForo_Application::autoload($controller))
			{
				$result = call_user_func(array($controller, 'getSessionActivityDetailsForList'), $controllerGroup);
			}
			else
			{
				$result = false;
			}

			if (is_array($result))
			{
				foreach ($result AS $resultKey => $resultInfo)
				{
					if (!isset($controllerGroup[$resultKey]))
					{
						continue;
					}

					if (is_array($resultInfo))
					{
						$activities[$resultKey]['activityDescription'] = $resultInfo[0];
						$activities[$resultKey]['activityItemTitle'] = $resultInfo[1];
						$activities[$resultKey]['activityItemUrl'] = $resultInfo[2];
						$activities[$resultKey]['activityItemPreviewUrl'] = $resultInfo[3];
					}
					else
					{
						$activities[$resultKey]['activityDescription'] = $resultInfo;
						$activities[$resultKey]['activityItemTitle'] = false;
						$activities[$resultKey]['activityItemUrl'] = false;
						$activities[$resultKey]['activityItemPreviewUrl'] = false;
					}
				}
			}
			else
			{
				foreach ($controllerGroup AS $key => $activity)
				{
					$activities[$key]['activityDescription'] = $result;
					$activities[$key]['activityItemTitle'] = false;
					$activities[$key]['activityItemUrl'] = false;
					$activities[$key]['activityItemPreviewUrl'] = false;
				}
			}
		}

		return $activities;
	}

	/**
	 * Gets session activity details for a single activity record.
	 *
	 * @param array $activity
	 *
	 * @return array Keys: description, itemTitle, itemUrl
	 */
	public function getSessionActivityDetails(array $activity)
	{
		$details = $this->addSessionActivityDetailsToList(array($activity));
		$details = reset($details);

		return array(
			'description' => $details['activityDescription'],
			'itemTitle' => $details['activityItemTitle'],
			'itemUrl' => $details['activityItemUrl']
		);
	}

	/**
	 * Delete session activity records that have not been touched since the cut off date.
	 *
	 * @param integer $cutOffDate
	 */
	public function deleteSessionActivityOlderThanCutOff($cutOffDate)
	{
		$db = $this->_getDb();
		$db->delete('xf_session_activity', 'view_date < ' . $db->quote($cutOffDate));
	}

	/**
	 * Updates user last activity values from session activity records. Can be configured
	 * to only update last activity values for sessions that have not been touched recently.
	 *
	 * @param integer|null $cutOffDate If specified, only updates users that haven't been active since this timestamp
	 */
	public function updateUserLastActivityFromSessions($cutOffDate = null)
	{
		if ($cutOffDate === null)
		{
			$cutOffDate = XenForo_Application::$time;
		}

		$userSessions = $this->getSessionActivityRecords(array(
			'userLimit' => 'registered',
			'getInvisible' => true,
			'cutOff' => array('<=', $cutOffDate)
		));

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($userSessions AS $userSession)
		{
			$db->update('xf_user',
				array('last_activity' => $userSession['view_date']),
				'user_id = ' . $db->quote($userSession['user_id'])
			);
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Processes the last activity entry for a user for when they explicitly log out.
	 * This will update their last activity time now, and remove their last activity record.
	 *
	 * @param integer $userId
	 */
	public function processLastActivityUpdateForLogOut($userId)
	{
		if (!$userId)
		{
			return;
		}

		$userSessions = $this->getSessionActivityRecords(array(
			'user_id' => $userId,
			'getInvisible' => true
		));
		if (!$userSessions)
		{
			return;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		// really should only be 1 session, but hey that's the structure of the return and no harm :)
		foreach ($userSessions AS $userSession)
		{
			$db->update('xf_user',
				array('last_activity' => $userSession['view_date']),
				'user_id = ' . $db->quote($userSession['user_id'])
			);
		}

		$db->delete('xf_session_activity', 'user_id = ' . $db->quote($userId));

		XenForo_Db::commit($db);
	}

	/**
	 * Returns the length of time after the last recorded activity that a user is considered 'online'
	 *
	 * @return integer Time in seconds
	 */
	public function getOnlineStatusTimeout()
	{
		return XenForo_Application::$time - XenForo_Application::get('options')->onlineStatusTimeout * 60;
	}
}