<?php

class XenForo_Model_SpamCleaner extends XenForo_Model
{
	const FETCH_USER = 0x01;

	/**
	 * Executes the spam cleaner against the specified user
	 *
	 * @param array Spam user
	 * @param array Usually the result of the choices made on the spam cleaner options form
	 * @param array Will be populated with a log of actions performed
	 * @param string If a problem occurs, this will be populated with an error phrase key
	 *
	 * @return boolean
	 */
	public function cleanUp(array $user, array $actions, &$log = array(), &$errorKey = '')
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$options = XenForo_Application::get('options');
		$log = array();

		if (!empty($actions['ban_user']) && !$this->_banUser($user, $log, $errorKey))
		{
			return false;
		}

		foreach ($this->getSpamHandlers() AS $contentType => $spamHandler)
		{
			if ($spamHandler->cleanUpConditionCheck($user, $actions))
			{
				if (!$spamHandler->cleanUp($user, $log, $errorKey))
				{
					return false;
				}
			}
		}

		if (!empty($log)) // only email the user if something was actually done against them
		{
			if (!empty($actions['email_user']))
			{
				$this->_emailUser($user, $actions['email'], $log);
			}

			$visitor = XenForo_Visitor::getInstance();

			// log progress
			$db->insert('xf_spam_cleaner_log', array(
				'user_id' => $user['user_id'],
				'username' => $user['username'],
				'applying_user_id' => $visitor['user_id'],
				'applying_username' => $visitor['username'],
				'application_date' => XenForo_Application::$time,
				'data' => ($log ? serialize($log) : '')
			));
		}

		XenForo_Db::commit($db);
		return true;
	}

	/**
	 * Undoes the actions of cleanUp, based on the log
	 *
	 * @param array $log
	 *
	 * @return boolean
	 */
	public function restore(array $log, &$errorKey = '')
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$logData = unserialize($log['data']);

		foreach ($this->getSpamHandlers() AS $contentType => $spamHandler)
		{
			if (!empty($logData[$contentType]))
			{
				if (!$spamHandler->restore($logData[$contentType], $errorKey))
				{
					return false;
				}
			}
		}

		if (!empty($logData['user']) && $logData['user'] == 'banned')
		{
			$this->_liftBan($log);
		}

		$db->update('xf_spam_cleaner_log',
			array('restored_date' => XenForo_Application::$time),
			'spam_cleaner_log_id = ' . $db->quote($log['spam_cleaner_log_id'])
		);

		XenForo_Db::commit($db);
		return true;
	}

	/**
	 * Permanently ban spammer
	 *
	 * @param array $user
	 * @param array $log
	 * @param string $errorKey
	 *
	 * @return boolean
	 */
	protected function _banUser(array $user, array &$log, &$errorKey = '')
	{
		$log['user'] = 'banned';

		if ($ban = $this->getModelFromCache('XenForo_Model_Banning')->getBannedUserById($user['user_id']))
		{
			$existing = true;
		}
		else
		{
			$existing = false;
		}

		return $this->getModelFromCache('XenForo_Model_User')->ban(
			$user['user_id'], XenForo_Model_User::PERMANENT_BAN, 'Spam', $existing, $errorKey
		);
	}

	/**
	 * Lifts the ban on a user
	 *
	 * @param array $user
	 * @param string $errorKey
	 *
	 * @return boolean
	 */
	protected function _liftBan(array $user)
	{
		return $this->getModelFromCache('XenForo_Model_User')->liftBan($user['user_id']);
	}

	/**
	 * Send an email to notify the user that they have been spam-cleaned
	 *
	 * @param array $user
	 * @param string $emailText
	 * @param array $log
	 */
	protected function _emailUser(array $user, $emailText, array &$log)
	{
		$mail = XenForo_Mail::create('spam_cleaner_applied', array(
			'plainText' => $emailText,
			'htmlText' => nl2br($emailText)
		), $user['language_id']);

		$mail->send($user['email'], $user['username']);

		return true;
	}

	/**
	 * Searches for records of users using any of the IP addresses logged by the specified user
	 *
	 * @param integer ID of the user to check against
	 * @param integer Number of days to look back in the logs
	 *
	 * @return array
	 */
	public function checkIps($userId, $logDays)
	{
		$db = $this->_getDb();

		// written this way due to mysql's ridiculous sub-query performance
		$recentIps = $db->fetchCol('
			SELECT DISTINCT ip
			FROM xf_ip
			WHERE user_id = ?
				AND log_date > ?
		', array($userId, XenForo_Application::$time - $logDays * 86400));
		if (!$recentIps)
		{
			return array();
		}

		$ipLogs = $db->fetchAll('
			SELECT * FROM xf_ip
			WHERE ip IN (' . $db->quote($recentIps) . ')
				AND user_id <> ?
				AND user_id > 0
				AND log_date > ?
			ORDER BY log_date DESC
		', array($userId, XenForo_Application::$time - $logDays * 86400));

		$userIpLogs = array();
		foreach ($ipLogs AS $ipLog)
		{
			$ipLog['ip_address'] = long2ip($ipLog['ip']);

			$userIpLogs[$ipLog['user_id']][] = $ipLog;
		}

		$userRecords = $this->getModelFromCache('XenForo_Model_User')->getUsersByIds(
			array_keys($userIpLogs),
			array('join' => XenForo_Model_User::FETCH_LAST_ACTIVITY)
		);

		$visitor = XenForo_Visitor::getInstance();

		$users = array();

		foreach ($userIpLogs AS $userId => $ipLog)
		{
			if (!isset($userRecords[$userId]))
			{
				continue;
			}

			$users[$userId] = $userRecords[$userId];
			$users[$userId]['ipLogs'] = $ipLog;
			$users[$userId]['canCleanSpam'] = (XenForo_Permission::hasPermission($visitor['permissions'], 'general', 'cleanSpam') && $this->getModelFromCache('XenForo_Model_User')->couldBeSpammer($users[$userId]));
		}

		return $users;
	}

	/**
	 * Fetches spam cleaner log records
	 *
	 * @param array $fetchOptions Supports limit options only at present
	 *
	 * @return array
	 */
	public function getLogs(array $fetchOptions)
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$joinOptions = $this->prepareLogFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT spam_cleaner_log.*
				' . $joinOptions['selectFields'] . '
			FROM xf_spam_cleaner_log AS spam_cleaner_log
				' . $joinOptions['joinTables'] . '
			ORDER BY spam_cleaner_log.application_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'spam_cleaner_log_id');
	}

	/**
	 * Fetches a single spam cleaner log entry
	 *
	 * @param integer $logId
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getLogById($logId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareLogFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT spam_cleaner_log.*
				' . $joinOptions['selectFields'] . '
			FROM xf_spam_cleaner_log AS spam_cleaner_log
			' . $joinOptions['joinTables'] . '
			WHERE spam_cleaner_log.spam_cleaner_log_id = ?
		', $logId);
	}

	/**
	 * Prepares join options for spam cleaner log fetching
	 *
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function prepareLogFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = spam_cleaner_log.user_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Counts the total number of spam cleaner logs
	 *
	 * @return integer
	 */
	public function countLogs()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_spam_cleaner_log
		');
	}

	/**
	 * Gets the spam handler for a specific type of content.
	 *
	 * @param string $contentType
	 *
	 * @return false|XenForo_SpamHandler_Abstract
	 */
	public function getSpamHandler($contentType)
	{
		$handlerClass = $this->getContentTypeField($contentType, 'spam_handler_class');
		if (!$handlerClass)
		{
			return false;
		}

		return new $handlerClass();
	}

	/**
	 * Gets the spam handlers for all content types.
	 *
	 * @return array Array of XenForo_SpamHandler_Abstract objects
	 */
	public function getSpamHandlers()
	{
		$handlerClasses = $this->getContentTypesWithField('spam_handler_class');
		$handlers = array();
		foreach ($handlerClasses AS $contentType => $handlerClass)
		{
			$handlers[$contentType] = new $handlerClass();
		}

		return $handlers;
	}
}