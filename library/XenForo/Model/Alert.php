<?php

/**
 * Model class for manipulating user alerts.
 *
 * @author kier
 */
class XenForo_Model_Alert extends XenForo_Model
{
	/**
	 * Fetch alerts viewed in the last options:alertsPopupExpiryHours hours
	 *
	 * @var string
	 */
	const FETCH_MODE_POPUP = 'fetchPopupItems';

	/**
	 * Fetch alerts viewed in the last options:alertExpiryDays days
	 *
	 * @var string
	 */
	const FETCH_MODE_RECENT = 'fetchRecent';

	/**
	 * Fetch alerts regardless of their view_date
	 *
	 * @var string
	 */
	const FETCH_MODE_ALL = 'fetchAll';

	/**
	 * Prevent alerts from being marked as read (debug option);
	 *
	 * @var boolean
	 */
	const PREVENT_MARK_READ = false;

	/**
	 * Array to store alert handler classes
	 *
	 * @var array
	 */
	protected $_handlerCache = array();

	/**
	 * Fetches a single alert using its ID
	 *
	 * @param integer $alertId
	 *
	 * @return array
	 */
	public function getAlertById($alertId)
	{
		return $this->_getDb()->fetchAll('

			SELECT *
			FROM xf_user_alert
			WERE alert_id = ?

		', $alertId);
	}

	/**
	 * Returns alert data for the specified user.
	 *
	 * @param integer $userId
	 * @param string $fetchMode Use one of the FETCH_x constants
	 * @param array $fetchOptions (supports page, perpage)
	 * @param array|null $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions) or null for visitor
	 *
	 * @return array
	 */
	public function getAlertsForUser($userId, $fetchMode, array $fetchOptions = array(), array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$alerts = $this->_getAlertsFromSource($userId, $fetchMode, $fetchOptions);

		$alerts = $this->_getContentForAlerts($alerts, $userId, $viewingUser);
		$alerts = $this->_getViewableAlerts($alerts, $viewingUser);

		$alerts = $this->prepareAlerts($alerts, $viewingUser);

		return array(
			'alerts' => $alerts,
			'alertHandlers' => $this->_handlerCache
		);
	}

	/**
	 * Returns true if the alert passed in is 'unread' - ie: has view_date == 0
	 *
	 * @param array $alert
	 *
	 * @return boolean
	 */
	protected function _isUnread(array $alert)
	{
		return ($alert['view_date'] === 0);
	}

	/**
	 * Returns true if the alert passed in is 'current' for the given date-cut-off.
	 *
	 * Current means unread, or viewed within the specified date cut off.
	 *
	 * @param array $alert
	 * @param integer $dateCut
	 *
	 * @return boolean
	 */
	protected function _isCurrent(array $alert, $dateCut = null)
	{
		if ($this->_isUnread($alert))
		{
			return true;
		}
		else
		{
			if ($dateCut === null)
			{
				$dateCut = $this->_getFetchModeDateCut(self::FETCH_MODE_RECENT);
			}

			if ($alert['view_date'] > XenForo_Application::$time - $dateCut)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Translates the FETCH_MODE_x constants from this class into a cut-off timestamp
	 *
	 * @param string self::FETCH_MODE_x
	 *
	 * @return integer Unix timestamp
	 */
	protected function _getFetchModeDateCut($fetchMode)
	{
		$timeNow = XenForo_Application::$time;
		$options = XenForo_Application::get('options');

		switch ($fetchMode)
		{
			case self::FETCH_MODE_ALL:
				return 0;

			case self::FETCH_MODE_POPUP:
				return $timeNow - $options->alertsPopupExpiryHours * 3600;

			case self::FETCH_MODE_RECENT:
			default:
				return $timeNow - $options->alertExpiryDays * 86400;
		}
	}

	/**
	 * Fetches raw alert records for the specified user.
	 *
	 * Includes any unviewed alerts plus any alerts that
	 * were viewed within the last $dateCut seconds.
	 *
	 * @param integer $userId User to whom the alerts belong
	 * @param string $fetchMode Fetch viewed alerts read more recently than this timestamp
	 * @param array $fetchOptions (supports page and perpage)
	 */
	protected function _getAlertsFromSource($userId, $fetchMode, array $fetchOptions = array())
	{
		if ($fetchMode == self::FETCH_MODE_POPUP)
		{
			$fetchOptions['page'] = 0;
			$fetchOptions['perPage'] = 25;
		}

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT
					alert.*,
					user.username, user.gender, user.avatar_date, user.gravatar,
					content_type_field.field_value AS alert_handler_class
				FROM xf_user_alert AS alert
				INNER JOIN xf_content_type_field AS content_type_field ON
					(content_type_field.content_type = alert.content_type
					AND content_type_field.field_name = \'alert_handler_class\')
				LEFT JOIN xf_user AS user ON
					(user.user_id = alert.user_id)
				WHERE alert.alerted_user_id = ?
					AND (alert.view_date = 0 OR alert.view_date > ?)
				ORDER BY event_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'alert_id', array($userId, $this->_getFetchModeDateCut($fetchMode)));
	}

	public function countAlertsForUser($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_alert
			WHERE alerted_user_id = ?
				AND (view_date = 0 OR view_date > ?)
		', array($userId, $this->_getFetchModeDateCut(self::FETCH_MODE_RECENT)));
	}

	/**
	 * Fetches content data for alerts
	 *
	 * @param array $data Raw alert data
	 * @param integer $userId The user ID the alerts are for
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _getContentForAlerts(array $data, $userId, array $viewingUser)
	{
		// group all content ids of each content type...
		$fetchQueue = array();
		foreach ($data AS $id => $item)
		{
			$fetchQueue[$item['alert_handler_class']][$item['alert_id']] = $item['content_id'];
		}

		// fetch content for all items of each content type in one go...
		foreach ($fetchQueue AS $handlerClass => $contentIds)
		{
			$fetchData[$handlerClass] = $this->_getAlertHandlerFromCache($handlerClass)->getContentByIds(
				$contentIds, $this, $userId, $viewingUser
			);
		}

		// attach resulting content to each alert
		foreach ($data AS $id => $item)
		{
			if (!isset($fetchData[$item['alert_handler_class']][$item['content_id']]))
			{
				// For whatever reason, there was no related content found for this alert,
				// therefore remove it from this user's alerts
				unset($data[$id]);
				continue;
			}

			$data[$id]['content'] = $fetchData[$item['alert_handler_class']][$item['content_id']];
		}

		return $data;
	}

	/**
	 * Filters out unviewable alerts and returns only those the user can view.
	 *
	 * @param array $alerts
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array Filtered items
	 */
	protected function _getViewableAlerts(array $alerts, array $viewingUser)
	{
		foreach ($alerts AS $key => $alert)
		{
			$handler = $this->_getAlertHandlerFromCache($alert['alert_handler_class']);
			if (!$handler->canViewAlert($alert, $alert['content'], $viewingUser))
			{
				unset($alerts[$key]);
			}
		}

		return $alerts;
	}

	/**
	 * Runs prepareAlert on an array of items
	 *
	 * @param array $alerts
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function prepareAlerts(array $alerts, array $viewingUser)
	{
		foreach ($alerts AS $id => $item)
		{
			$alerts[$id] = $this->prepareAlert($item, $item['alert_handler_class'], $viewingUser);
		}

		return $alerts;
	}

	/**
	 * Wraps around the prepareX functions in the handler class for each content type.
	 * Also does basic setup, moving user info to a sub-array.
	 *
	 * @param array $item
	 * @param string $handlerClassName Name of alert handler class for this item
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function prepareAlert(array $item, $handlerClassName, array $viewingUser)
	{

		$item['user'] = XenForo_Application::arrayFilterKeys($item, array(
			'user_id',
			'username',
			'gender',
			'gravatar',
			'avatar_date',
		));

		unset($item['user_id'], $item['username'], $item['gender'], $item['gravatar'], $item['avatar_date']);

		$item['new'] = ($item['view_date'] === 0 || $item['view_date'] > XenForo_Application::$time - 600);
		$item['unviewed'] = $this->_isUnread($item);

		return $this->_getAlertHandlerFromCache($handlerClassName)->prepareAlert($item, $viewingUser);
	}

	/**
	 * Marks all of a user's alerts as read.
	 *
	 * @param integer $userId
	 * @param integer|null $time
	 */
	public function markAllAlertsReadForUser($userId, $time = null)
	{
		if (self::PREVENT_MARK_READ)
		{
			return;
		}

		if ($time === null)
		{
			$time = XenForo_Application::$time;
		}

		$db = $this->_getDb();

		$condition = 'alerted_user_id = ' . $db->quote($userId) . ' AND view_date = 0';
		$db->update('xf_user_alert', array('view_date' => $time), $condition);

		$this->resetUnreadAlertsCounter($userId);
	}

	/**
	 * Resets the unviewed alerts counter to 0 for the specified user.
	 *
	 * @param integer $userId
	 */
	public function resetUnreadAlertsCounter($userId)
	{
		if (!self::PREVENT_MARK_READ)
		{
			$db = $this->_getDb();
			$db->update('xf_user', array('alerts_unread' => 0), 'user_id = ' . $db->quote($userId));

			$visitor = XenForo_Visitor::getInstance();
			if ($userId == $visitor['user_id'])
			{
				$visitor['alerts_unread'] = 0;
			}
		}
	}

	/**
	 * Deletes old viewed alerts.
	 *
	 * @param integer|null $dateCut Cut off date; if not specified, defaults to expiry setting
	 */
	public function deleteOldReadAlerts($dateCut = null)
	{
		if ($dateCut === null)
		{
			$expiryTime = XenForo_Application::get('options')->alertExpiryDays * 86400;
			$dateCut = XenForo_Application::$time - $expiryTime;
		}

		$db = $this->_getDb();
		$db->delete('xf_user_alert', 'view_date > 0 AND view_date < '. $db->quote($dateCut));
	}

	/**
	 * Deletes old unviewed alerts. The cut-off here is much longer than viewed ones.
	 *
	 * @param integer|null $dateCut Cut off date; if not specified, defaults to 30 days
	 */
	public function deleteOldUnreadAlerts($dateCut = null)
	{
		if ($dateCut === null)
		{
			$dateCut = XenForo_Application::$time - 30 * 86400;
		}

		$db = $this->_getDb();
		$db->delete('xf_user_alert', 'view_date = 0 AND event_date < '. $db->quote($dateCut));
	}

	/**
	 * Send a user alert
	 *
	 * @param integer $alertUserId
	 * @param integer $userId
	 * @param string $username
	 * @param string $contentType
	 * @param integer $contentId
	 * @param string $action
	 * @param array $extraData
	 */
	public static function alert($alertUserId, $userId, $username, $contentType, $contentId, $action, array $extraData = null)
	{
		XenForo_Model::create(__CLASS__)->alertUser(
			$alertUserId,
			$userId, $username,
			$contentType, $contentId,
			$action, $extraData
		);
	}

	/**
	 * Send a user alert
	 *
	 * @param integer $alertUserId
	 * @param integer $userId
	 * @param string $username
	 * @param string $contentType
	 * @param integer $contentId
	 * @param string $action
	 * @param array $extraData
	 */
	public function alertUser($alertUserId, $userId, $username, $contentType, $contentId, $action, array $extraData = null)
	{
		if (!$userId)
		{
			return;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Alert');

		$dw->set('alerted_user_id', $alertUserId);
		$dw->set('user_id', $userId);
		$dw->set('username', $username);
		$dw->set('content_type', $contentType);
		$dw->set('content_id', $contentId);
		$dw->set('action', $action);
		$dw->set('extra_data', $extraData);

		$dw->save();
	}

	/**
	 * Deletes the matching alerts.
	 *
	 * @param string $contentType
	 * @param integer|array $contentId
	 * @param integer|null $userId Ignored if null
	 * @param string|null $action Ignored if null
	 */
	public function deleteAlerts($contentType, $contentId, $userId = null, $action = null)
	{
		$db = $this->_getDb();


		$conditions = array();

		if (is_array($contentId))
		{
			if (!$contentId)
			{
				return;
			}

			$conditions[] = 'content_type = ' . $db->quote($contentType) . ' AND content_id IN (' . $db->quote($contentId) . ')';
		}
		else
		{
			$conditions[] = 'content_type = ' . $db->quote($contentType) . ' AND content_id = ' . $db->quote($contentId);
		}

		if ($userId !== null)
		{
			$conditions[] = 'user_id = ' . $db->quote($userId);
		}
		if ($action !== null)
		{
			$conditions[] = 'action = ' . $db->quote($action);
		}

		$alerts = $db->fetchAll('
			SELECT *
			FROM xf_user_alert
			WHERE (' . implode(') AND (', $conditions) . ')
		');

		XenForo_Db::beginTransaction($db);

		foreach ($alerts AS $alert)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Alert');
			$dw->setExistingData($alert, true);
			$dw->delete();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Returns false if the specified user has opted not to receive the specified alert type
	 *
	 * @param array $user
	 * @param string $contentType
	 * @param string $action
	 */
	public static function userReceivesAlert(array $user, $contentType, $action)
	{
		$optOuts = XenForo_Model::create(__CLASS__)->getAlertOptOuts($user);

		return (empty($optOuts["{$contentType}_{$action}"]));
	}

	/**
	 * Fetches an array containing the names of alert types the specified user
	 * has opted not to receive.
	 *
	 * @param array $user - defaults to visitor if null
	 * @param boolean If true, the $user array must contain the alert_optout key from the user_option table. If false, queries for the data.
	 *
	 * @return array [ a => true, b => true, c => true ]
	 */
	public function getAlertOptOuts(array $user = null, $useDenormalized = true)
	{
		if ($user === null)
		{
			$user = XenForo_Visitor::getInstance();
		}

		if (!$user['user_id'])
		{
			return array();
		}
		else if ($useDenormalized && isset($user['alert_optout']))
		{
			$optOuts = preg_split('/\s*,\s*/', $user['alert_optout'], -1, PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$optOuts = $this->_getDb()->fetchCol('
				SELECT alert
				FROM xf_user_alert_optout
				WHERE user_id = ?
			', $user['user_id']);
		}

		return array_fill_keys($optOuts, true);
	}

	/**
	 * Fetches an instance of the user model
	 *
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * Fetches an instance of the specified alert handler
	 *
	 * @param string $class
	 *
	 * @return XenForo_AlertHandler_Abstract
	 */
	protected function _getAlertHandlerFromCache($class)
	{
		if (!isset($this->_handlerCache[$class]))
		{
			$this->_handlerCache[$class] = XenForo_AlertHandler_Abstract::create($class);
		}

		return $this->_handlerCache[$class];
	}
}