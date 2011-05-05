<?php

class XenForo_Model_Import extends XenForo_Model
{
	/**
	 * Static array listing extra importers that aren't in the right directory.
	 *
	 * @var array
	 */
	public static $extraImporters = array();

	/**
	 * Fetches a list of available importers
	 *
	 * @return array
	 */
	public function getImporterList()
	{
		$importerDir = XenForo_Autoloader::getInstance()->getRootDir() . '/XenForo/Importer';

		$importers = array();
		foreach (glob($importerDir . '/*.php') AS $importerFile)
		{
			$key = substr(basename($importerFile), 0, -4);
			if ($key == 'Abstract')
			{
				continue;
			}

			$importers[$key] = $this->getImporterName($key);
		}
		foreach (self::$extraImporters AS $extra)
		{
			$importers[$extra] = $this->getImporterName($extra);
		}

		asort($importers);
		return $importers;
	}

	/**
	 * Gets the name of the specied importer
	 *
	 * @param string Importer ID
	 *
	 * @return string
	 */
	public function getImporterName($key)
	{
		if (strpos($key, '_') && !in_array($key, self::$extraImporters))
		{
			throw new XenForo_Exception('Trying to load a non-registered importer.');
		}

		$class = (strpos($key, '_') ? $key : 'XenForo_Importer_' . $key);
		return call_user_func(array($class, 'getName'));
	}

	/**
	 * Gets the specified importer.
	 *
	 * @param string $key Name of importer (key); just last part of name, not full path.
	 *
	 * @return XenForo_Importer_Abstract
	 */
	public function getImporter($key)
	{
		if (strpos($key, '_') && !in_array($key, self::$extraImporters))
		{
			throw new XenForo_Exception('Trying to load a non-registered importer.');
		}

		$class = (strpos($key, '_') ? $key : 'XenForo_Importer_' . $key);
		$createClass = XenForo_Application::resolveDynamicClass($class, 'importer');

		return new $createClass();
	}

	/**
	 * Determines whether or not the specified step can be run at this time.
	 *
	 * @param string $step
	 * @param array $steps
	 * @param array $runSteps
	 *
	 * @return boolean
	 */
	public function canRunStep($step, array $steps, array $runSteps)
	{
		if (!empty($runSteps[$step]['run']))
		{
			return false;
		}

		if (!isset($steps[$step]))
		{
			return true; // "hidden" steps are always runnable
		}

		if (!empty($steps[$step]['depends']))
		{
			foreach ($steps[$step]['depends'] AS $dependency)
			{
				if (empty($runSteps[$dependency]['run']))
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Prepares the list of steps with extra info about each step,
	 * such as whether or not it has already been run.
	 *
	 * @param array $steps
	 * @param array $runSteps
	 *
	 * @return array
	 */
	public function addImportStateToSteps(array $steps, array $runSteps)
	{
		foreach ($steps AS $step => &$info)
		{
			$info['runnable'] = $this->canRunStep($step, $steps, $runSteps);
			$info['hasRun'] = !empty($runSteps[$step]['run']);
			$info['importTotal'] = ($info['hasRun'] ? $runSteps[$step]['importTotal'] : null);

			if (!empty($runSteps[$step]))
			{
				$runStep = $runSteps[$step];
				$info['hasRun'] = !empty($runStep['run']);

				if (!empty($runStep['startTime']) && !empty($runStep['endTime']))
				{
					$time = $runStep['endTime'] - $runStep['startTime'];
					$info['runTime'] = array();
					if ($time >= 3600)
					{
						$info['runTime']['hours'] = floor($time / 3600);
						$time -= $info['runTime']['hours'] * 3600;
					}
					if ($time >= 60)
					{
						$info['runTime']['minutes'] = floor($time / 60);
						$time -= $info['runTime']['minutes'] * 60;
					}
					$info['runTime']['seconds'] = $time;
				}
			}
		}

		return $steps;
	}

	/**
	 * Returns true if there is data that has been imported;
	 *
	 * @return boolean
	 */
	public function hasImportedData()
	{
		$db = $this->_getDb();
		$data = $db->fetchRow($db->limit('
			SELECT *
			FROM xf_import_log
		', 1));
		return ($data ? true : false);
	}

	/**
	 * Resets the import log table.
	 */
	public function resetImportLog()
	{
		$this->_getDb()->query('
			TRUNCATE TABLE xf_import_log
		');
		$this->_getDb()->query('ALTER TABLE xf_import_log ENGINE = MyISAM');
	}

	/**
	 * Renames the import log table before creating a new empty version.
	 *
	 * @param string Archive table name
	 * @param mixed Error phrase reference
	 *
	 * @return boolean
	 */
	public function archiveImportLog($archiveTableName, &$error)
	{
		$db = $this->_getDb();

		if (preg_match('/[^a-z0-9_]/i', $archiveTableName))
		{
			$error = new XenForo_Phrase('error_table_name_illegal');
			return false;
		}

		try
		{
			$db->query("ALTER TABLE xf_import_log RENAME {$archiveTableName}");

			$tables = XenForo_Install_Data_MySql::getTables();

			$db->query($tables['xf_import_log']);
		}
		catch (Zend_Db_Exception $e)
		{
			$error = new XenForo_Phrase('error_unable_to_create_table_due_to_error', array(
				'table' => $archiveTableName,
				'error' => $e->getMessage()
			));
			return false;
		}

		return true;
	}

	/**
	 * Writes a log of old id and new id for an imported item of content
	 *
	 * @param string $contentType
	 * @param integer $oldId
	 * @param integer $newId
	 */
	public function logImportData($contentType, $oldId, $newId)
	{
		$this->_getDb()->query('
			INSERT INTO xf_import_log
				(content_type, old_id, new_id)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE new_id = VALUES(new_id)
		', array($contentType, strval($oldId), strval($newId)));
	}

	/**
	 * Array to store content maps as they are loaded
	 *
	 * @var array
	 */
	protected $_contentMapCache = array();

	/**
	 * Gets an import content map to map old IDs to new IDs for the given content type.
	 *
	 * @param string $contentType
	 * @param array $ids
	 *
	 * @return array
	 */
	public function getImportContentMap($contentType, $ids = false)
	{
		$logTable = (defined('IMPORT_LOG_TABLE') ? IMPORT_LOG_TABLE : 'xf_import_log');

		$db = $this->_getDb();

		if ($ids === false)
		{
			return $db->fetchPairs('
				SELECT old_id, new_id
				FROM ' . $logTable . '
				WHERE content_type = ?
			', $contentType);
		}

		if (!is_array($ids))
		{
			$ids = array($ids);
		}
		if (!$ids)
		{
			return array();
		}

		$final = array();
		if (isset($this->_contentMapCache[$contentType]))
		{
			$lookup = $this->_contentMapCache[$contentType];
			foreach ($ids AS $key => $id)
			{
				if (isset($lookup[$id]))
				{
					$final[$id] = $lookup[$id];
					unset($ids[$key]);
				}
			}
		}

		if (!$ids)
		{
			return $final;
		}

		foreach ($ids AS &$id)
		{
			$id = strval($id);
		}

		$merge = $db->fetchPairs('
			SELECT old_id, new_id
			FROM ' . $logTable . '
			WHERE content_type = ?
				AND old_id IN (' . $db->quote($ids) . ')
		', $contentType);

		if (isset($this->_contentMapCache[$contentType]))
		{
			$this->_contentMapCache[$contentType] += $merge;
		}
		else
		{
			$this->_contentMapCache[$contentType] = $merge;
		}

		return $final + $merge;
	}

	/**
	 * Grabs user IDs from the source array and returns a map with their new/imported ID values
	 *
	 * @param array $source
	 * @param string $key Name of the key in $source that identifies the user IDs
	 *
	 * @return array
	 */
	public function getUserIdsMapFromArray(array $source, $key)
	{
		$userIds = array();
		foreach ($source AS $data)
		{
			$userIds[] = $data[$key];
		}
		return $this->getImportContentMap('user', $userIds);
	}

	/**
	 * Grabs thread IDs from the source array and returns a map with their new/imported ID values
	 *
	 * @param array $source
	 * @param string $key Name of the key in $source that identifies the thread IDs
	 *
	 * @return array
	 */
	public function getThreadIdsMapFromArray(array $source, $key)
	{
		$userIds = array();
		foreach ($source AS $data)
		{
			$userIds[] = $data[$key];
		}
		return $this->getImportContentMap('thread', $userIds);
	}

	/**
	 * Grabs post IDs from the source array and returns a map with their new/imported ID values
	 *
	 * @param array $source
	 * @param string $key Name of the key in $source that identifies the post IDs
	 *
	 * @return array
	 */
	public function getPostIdsMapFromArray(array $source, $key)
	{
		$userIds = array();
		foreach ($source AS $data)
		{
			$userIds[] = $data[$key];
		}
		return $this->getImportContentMap('post', $userIds);
	}

	/**
	 * Maps an old user ID to a new/imported user ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapUserId($id, $default = null)
	{
		$ids = $this->getImportContentMap('user', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Maps an old thread ID to a new/imported thread ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapThreadId($id, $default = null)
	{
		$ids = $this->getImportContentMap('thread', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Maps an old post ID to a new/imported post ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapPostId($id, $default = null)
	{
		$ids = $this->getImportContentMap('post', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Maps an old node/forum ID to a new/imported node ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapNodeId($id, $default = null)
	{
		$ids = $this->getImportContentMap('node', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Maps an old attachment ID to a new/imported attachment ID
	 *
	 * @param integer $id
	 * @param integer $default
	 *
	 * @return integer
	 */
	public function mapAttachmentId($id, $default = null)
	{
		$ids = $this->getImportContentMap('attachment', $id);
		return ($ids ? reset($ids) : $default);
	}

	/**
	 * Fetches a XenForo user ID based on their email address
	 *
	 * @param string $email
	 *
	 * @return integer
	 */
	public function getUserIdByEmail($email)
	{
		return $this->_getDb()->fetchOne('
			SELECT user_id
			FROM xf_user
			WHERE email = ?
		', $email);
	}

	/**
	 * Fetches a XenForo user ID based on their user name
	 *
	 * @param string $name
	 *
	 * @return integer
	 */
	public function getUserIdByUserName($name)
	{
		return $this->_getDb()->fetchOne('
			SELECT user_id
			FROM xf_user
			WHERE username = ?
		', $name);
	}

	/**
	 * Wrapper for the XenForo DataWriter system to allow content to be imported.
	 *
	 * @param string $oldId
	 * @param string Name of the XenForo_DataWriter that will write the data
	 * @param string $contentKey
	 * @param string $idKey
	 * @param array $info
	 * @param string $errorHandler
	 *
	 * @return integer Imported content ID
	 */
	protected function _importData($oldId, $dwName, $contentKey, $idKey, array $info, $errorHandler = false)
	{
		if (!$errorHandler)
		{
			$errorHandler = XenForo_DataWriter::ERROR_ARRAY;
		}

		XenForo_Db::beginTransaction();

		$dw = XenForo_DataWriter::create($dwName, $errorHandler);
		$dw->setImportMode(true);
		$dw->bulkSet($info);
		if ($dw->save())
		{
			$newId = $dw->get($idKey);
			if ($oldId !== 0 && $oldId !== '')
			{
				$this->logImportData($contentKey, $oldId, $newId);
			}
		}
		else
		{
			$newId = false;
		}

		XenForo_Db::commit();

		return $newId;
	}

	/**
	 * Imports a user group
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer Imported user group ID
	 */
	public function importUserGroup($oldId, array $info)
	{
		if (isset($info['permissions']))
		{
			$permissions = $info['permissions'];
			unset($info['permissions']);
		}
		else
		{
			$permissions = false;
		}

		$userGroupId = $this->_importData($oldId, 'XenForo_DataWriter_UserGroup', 'userGroup', 'user_group_id', $info);
		if ($userGroupId)
		{
			if ($permissions)
			{
				$this->getModelFromCache('XenForo_Model_Permission')->updateGlobalPermissionsForUserCollection($permissions, $userGroupId);
			}
		}

		return $userGroupId;
	}

	/**
	 * Imports a user
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer Imported user ID
	 */
	public function importUser($oldId, array $info, &$failedKey = '')
	{
		$failedKey = '';

		if (strpos($info['username'], ',') !== false)
		{
			$failedKey = 'username';
			return false;
		}

		if (!empty($info['is_admin']))
		{
			$isAdmin = true;
			$adminPerms = (!empty($info['admin_permissions']) ? $info['admin_permissions'] : array());
			unset($info['admin_permissions']);
		}
		else
		{
			$isAdmin = false;
			$adminPerms = array();
		}

		if (!empty($info['ip']))
		{
			$ip = $info['ip'];
		}
		else
		{
			$ip = false;
		}
		unset($info['ip']);

		XenForo_Db::beginTransaction();

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setImportMode(true);

		if (isset($info['secondary_group_ids']))
		{
			$dw->setSecondaryGroups($info['secondary_group_ids']);
			unset($info['secondary_group_ids']);
		}

		if (isset($info['identities']))
		{
			if ($info['identities'])
			{
				$dw->setIdentities($info['identities']);
			}
			unset($info['identities']);
		}

		$dw->set('scheme_class', $info['authentication']['scheme_class']);
		$dw->set('data', serialize($info['authentication']['data']), 'xf_user_authenticate');
		unset($info['authentication']);

		$dw->bulkSet($info);
		$dw->checkDob();
		if ($dw->save())
		{
			$dw->rebuildPermissionCombinationId(false);
			// all other things will be rebuilt at the end of the import

			$newId = $dw->get('user_id');
			$this->logImportData('user', $oldId, $newId);

			if ($isAdmin)
			{
				$adminId = $this->_importData('', 'XenForo_DataWriter_Admin', '', 'user_id', array(
					'user_id' => $newId
				));
				if ($adminId && $adminPerms)
				{
					$this->getModelFromCache('XenForo_Model_Admin')->updateUserAdminPermissions($newId, $adminPerms);
				}
			}

			if ($ip)
			{
				$registerDate = !empty($info['register_date']) ? $info['register_date'] : null;
				$this->importIp($newId, 'user', $newId, 'register', $ip, $registerDate);
			}
		}
		else
		{
			$newId = false;
		}

		XenForo_Db::commit();

		return $newId;
	}

	/**
	 * Attempts to convert a time zone offset into a location string
	 *
	 * @param float Offset (in hours) from UTC
	 * @param boolean Apply daylight savings
	 *
	 * @return string Location string, such as Europe/London
	 */
	public function resolveTimeZoneOffset($offset, $useDst)
	{
		switch ($offset)
		{
			case -12: return 'Pacific/Midway'; // not right, but closest
			case -11: return 'Pacific/Midway';
			case -10: return 'Pacific/Honolulu';
			case -9.5: return 'Pacific/Marquesas';
			case -9: return 'America/Anchorage';
			case -8: return 'America/Los_Angeles';
			case -7: return ($useDst ? 'America/Denver' : 'America/Phoenix');
			case -6: return ($useDst ? 'America/Chicago' : 'America/Belize');
			case -5: return ($useDst ? 'America/New_York' : 'America/Bogota');
			case -4.5: return 'America/Caracas';
			case -4: return ($useDst ? 'America/Halifax' : 'America/La_Paz');
			case -3.5: return 'America/St_Johns';
			case -3: return ($useDst ? 'America/Argentina/Buenos_Aires' : 'America/Argentina/Mendoza');
			case -2: return 'America/Noronha';
			case -1: return ($useDst ? 'Atlantic/Azores' : 'Atlantic/Cape_Verde');
			case 0: return ($useDst ? 'Europe/London' : 'Atlantic/Reykjavik');
			case 1: return ($useDst ? 'Europe/Amsterdam' : 'Africa/Algiers');
			case 2: return ($useDst ? 'Europe/Athens' : 'Africa/Johannesburg');
			case 3: return ($useDst ? 'Europe/Moscow' : 'Africa/Nairobi');
			case 3.5: return 'Asia/Tehran';
			case 4: return ($useDst ? 'Asia/Yerevan' : 'Asia/Dubai');
			case 4.5: return 'Asia/Kabul';
			case 5: return ($useDst ? 'Indian/Mauritius' : 'Asia/Tashkent');
			case 5.5: return 'Asia/Kolkata';
			case 5.75: return 'Asia/Kathmandu';
			case 6: return ($useDst ? 'Asia/Novosibirsk' : 'Asia/Almaty');
			case 6.5: return 'Asia/Rangoon';
			case 7: return ($useDst ? 'Asia/Krasnoyarsk' : 'Asia/Bangkok');
			case 8: return ($useDst ? 'Asia/Irkutsk' : 'Asia/Hong_Kong');
			case 9: return ($useDst ? 'Asia/Yakutsk' : 'Asia/Tokyo');
			case 9.5: return ($useDst ? 'Australia/Adelaide' : 'Australia/Darwin');
			case 10: return ($useDst ? 'Australia/Hobart' : 'Australia/Brisbane');
			case 11: return ($useDst ? 'Asia/Magadan' : 'Pacific/Noumea');
			case 11.5: return 'Pacific/Norfolk';
			case 12: return ($useDst ? 'Pacific/Auckland' : 'Pacific/Fiji');
			case 12.75: return 'Pacific/Chatham';
			case 13: return 'Pacific/Tongatapu';
			case 14: return 'Pacific/Kiritimati';

			default: return 'Europe/London';
		}

	}

	/**
	 * Imports a ban for a user
	 *
	 * @param array User info
	 *
	 * @return integer Ban ID
	 */
	public function importBan(array $info)
	{
		return $this->_importData('', 'XenForo_DataWriter_UserBan', 'userBan', 'user_id', $info);
	}

	/**
	 * Imports a buddy list / friend list etc. as a XenForo 'following' list.
	 *
	 * @param integer The user doing the following
	 * @param array User IDs to follow
	 */
	public function importFollowing($userId, array $followUserIds)
	{
		if (!$followUserIds)
		{
			return;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($followUserIds AS $followUserId)
		{
			$db->query('
				INSERT IGNORE INTO xf_user_follow
					(user_id, follow_user_id, follow_date)
				VALUES
					(?, ?, ?)
			', array($userId, $followUserId, XenForo_Application::$time));
		}

		$this->getModelFromCache('XenForo_Model_User')->updateFollowingDenormalizedValue($userId);

		XenForo_Db::commit($db);
	}

	/**
	 * Imports a custom user avatar
	 *
	 * @param integer Source user ID
	 * @param integer Imported user ID
	 * @param string Path to the avatar file
	 *
	 * @return mixed User ID on success, false on failure
	 */
	public function importAvatar($oldUserId, $userId, $fileName)
	{
		try
		{
			$this->getModelFromCache('XenForo_Model_Avatar')->applyAvatar($userId, $fileName);
			$this->logImportData('avatar', $oldUserId, $userId);

			return $userId;
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Imports private messages etc. into a XenForo conversation
	 *
	 * @param integer Source ID
	 * @param array $conversation Data for XenForo_DataWriter_ConversationMaster
	 * @param array $recipients Recipient data
	 * @param array $messages Data for XenForo_DataWriter_ConversationMessage
	 *
	 * @return integer Imported conversation ID
	 */
	public function importConversation($oldId, array $conversation, array $recipients, array $messages)
	{
		if (!$messages || $conversation['title'] === '')
		{
			return false;
		}

		$hasRecipients = false;
		foreach ($recipients AS $recipient)
		{
			if ($recipient['recipient_state'] == 'active')
			{
				$hasRecipients = true;
				break;
			}
		}
		if (!$hasRecipients)
		{
			return false;
		}

		$conversation['reply_count'] = count($messages) - 1;
		$conversation['recipient_count'] = count($recipients);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$conversationId = $this->_importData($oldId, 'XenForo_DataWriter_ConversationMaster', 'conversation', 'conversation_id', $conversation);
		if ($conversationId)
		{
			$firstMessage = null;
			$lastMessage = null;

			foreach ($messages AS $message)
			{
				$message['conversation_id'] = $conversationId;
				$messageId = $this->_importData('', 'XenForo_DataWriter_ConversationMessage', '', 'message_id', $message);
				if (!$messageId)
				{
					continue;
				}
				$message['message_id'] = $messageId;

				if (!$firstMessage)
				{
					$firstMessage = $message;
				}
				$lastMessage = $message;
			}

			if (!$firstMessage)
			{
				XenForo_Db::rollback($db);
				return false;
			}

			$conversationUpdate = array(
				'first_message_id' => $firstMessage['message_id'],
				'last_message_id' => $lastMessage['message_id'],
				'last_message_date' => $lastMessage['message_date'],
				'last_message_user_id' => $lastMessage['user_id'],
				'last_message_username' => utf8_substr($lastMessage['username'], 0, 50)
			);
			$conversation += $conversationUpdate;
			$db->update('xf_conversation_master', $conversationUpdate, 'conversation_id = ' . $db->quote($conversationId));

			foreach ($recipients AS $userId => $info)
			{
				$db->insert('xf_conversation_recipient', array(
					'conversation_id' => $conversationId,
					'user_id' => $userId,
					'recipient_state' => $info['recipient_state'],
					'last_read_date' => $info['last_read_date']
				));

				if ($info['recipient_state'] == 'active')
				{
					$recipientUser = array(
						'conversation_id' => $conversationId,
						'owner_user_id' => $userId,
						'is_unread' => ($info['last_read_date'] >= $lastMessage['message_date'] ? 0 : 1),
						'reply_count' => $conversation['reply_count'],
						'last_message_date' => $conversation['last_message_date'],
						'last_message_id' => $conversation['last_message_id'],
						'last_message_user_id' => $conversation['last_message_user_id'],
						'last_message_username' => $conversation['last_message_username'],
					);

					$db->insert('xf_conversation_user', $recipientUser);
				}
			}
		}

		XenForo_Db::commit($db);

		return $conversationId;
	}

	/**
	 * Imports a profile post
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer Imported profile post ID
	 */
	public function importProfilePost($oldId, array $info)
	{
		if (isset($info['ip']))
		{
			$ip = $info['ip'];
			unset($info['ip']);
		}
		else
		{
			$ip = false;
		}

		$profilePostId = $this->_importData($oldId, 'XenForo_DataWriter_DiscussionMessage_ProfilePost', 'profilePost', 'profile_post_id', $info);
		if ($profilePostId)
		{
			if ($info['message_state'] == 'moderated')
			{
				$this->_getDb()->query('
					INSERT IGNORE INTO xf_moderation_queue
						(content_type, content_id, content_date)
					VALUES
						(?, ?, ?)
				', array('profile_post', $profilePostId, $info['post_date']));
			}

			if ($ip)
			{
				$ipId = $this->importIp($info['user_id'], 'profile_post', $profilePostId, 'insert', $ip, $info['post_date']);
				if ($ipId)
				{
					$this->_getDb()->update('xf_profile_post',
						array('ip_id' => $ipId),
						'profile_post_id = ' . $this->_getDb()->quote($profilePostId)
					);
				}
			}
		}

		return $profilePostId;
	}

	/**
	 * Imports a profile post comment
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return Imported profile post comment ID
	 */
	public function importProfilePostComment($oldId, array $info)
	{
		$profilePostCommentId = $this->_importData($oldId, 'XenForo_DataWriter_ProfilePostComment','profilePostComment', 'profile_post_comment_id', $info);

		return $profilePostCommentId;
	}

	/**
	 * Imports a forum
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer Imported node ID
	 */
	public function importForum($oldId, array $info)
	{
		return $this->_importData($oldId, 'XenForo_DataWriter_Forum', 'node', 'node_id', $info);
	}

	/**
	 * Imports a category
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer Imported node ID
	 */
	public function importCategory($oldId, array $info)
	{
		return $this->_importData($oldId, 'XenForo_DataWriter_Category', 'node', 'node_id', $info);
	}

	/**
	 * Imports a link forum
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer Imported node ID
	 */
	public function importLinkForum($oldId, array $info)
	{
		return $this->_importData($oldId, 'XenForo_DataWriter_LinkForum', 'node', 'node_id', $info);
	}

	/**
	 * Fetches node permissions
	 *
	 * @return array
	 */
	public function getNodePermissionsGrouped()
	{
		$nodeTypePermissionGroups = $this->getModelFromCache('XenForo_Model_Node')->getNodeTypesGroupedByPermissionGroup();
		$permissionsGrouped = $this->getModelFromCache('XenForo_Model_Permission')->getAllPermissionsGrouped();

		foreach ($permissionsGrouped AS $groupId => $permissions)
		{
			if (!isset($nodeTypePermissionGroups[$groupId]))
			{
				unset($permissionsGrouped[$groupId]);
			}
		}

		return $permissionsGrouped;
	}

	/**
	 * Inserts node permissions
	 *
	 * @param integer $nodeId
	 * @param integer $userGroupId
	 * @param integer $userId
	 * @param array $perms
	 */
	public function insertNodePermissionEntries($nodeId, $userGroupId, $userId, array $perms)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		foreach ($perms AS $groupId => $groupPerms)
		{
			foreach ($groupPerms AS $permId => $value)
			{
				if ($value === 'unset')
				{
					continue;
				}

				$valueInt = 0;
				if (is_int($value))
				{
					$valueInt = $value;
					$value = 'use_int';
				}

				$db->query('
					INSERT INTO xf_permission_entry_content
						(content_type, content_id, user_group_id, user_id,
						permission_group_id, permission_id, permission_value, permission_value_int)
					VALUES
						(\'node\', ?, ?, ?,
						?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						permission_value = VALUES(permission_value),
						permission_value_int = VALUES(permission_value_int)
				', array($nodeId, $userGroupId, $userId, $groupId, $permId, $value, $valueInt));
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Inserts global permission entries
	 *
	 * @param integer $userGroupId
	 * @param integer $userId
	 * @param array $perms
	 */
	public function insertGlobalPermissionEntries($userGroupId, $userId, array $perms)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		foreach ($perms AS $groupId => $groupPerms)
		{
			foreach ($groupPerms AS $permId => $value)
			{
				if ($value === 'unset')
				{
					continue;
				}

				$valueInt = 0;
				if (is_int($value))
				{
					$valueInt = $value;
					$value = 'use_int';
				}

				$db->query('
					INSERT INTO xf_permission_entry
						(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
					VALUES
						(?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						permission_value = VALUES(permission_value),
						permission_value_int = VALUES(permission_value_int)
				', array($userGroupId, $userId, $groupId, $permId, $value, $valueInt));
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Imports a global moderator
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer ID of moderating user
	 */
	public function importGlobalModerator($oldId, array $info)
	{
		$mod = $this->getModelFromCache('XenForo_Model_Moderator')->getGeneralModeratorByUserId($info['user_id']);
		if ($mod)
		{
			return false; // already exists
		}

		XenForo_Db::beginTransaction();

		$userId = $this->_importData($oldId, 'XenForo_DataWriter_Moderator', 'moderator', 'user_id', $info);
		if ($userId)
		{
			if (!empty($info['moderator_permissions']))
			{
				$finalPermissions = $this->getModelFromCache('XenForo_Model_Moderator')->getModeratorPermissionsForUpdate(
					$info['moderator_permissions'], array()
				);

				$this->getModelFromCache('XenForo_Model_Permission')->updateGlobalPermissionsForUserCollection(
					$finalPermissions, 0, $userId
				);
			}

			$db = $this->_getDb();
			$db->update('xf_user', array('is_moderator' => 1), 'user_id = ' . $db->quote($userId));
		}

		XenForo_Db::commit();

		return $userId;
	}

	/**
	 * Imports a forum/node moderator
	 *
	 * @param integer $oldNodeId
	 * @param integer $oldUserId
	 * @param array $info
	 *
	 * @return integer Imported moderator ID
	 */
	public function importNodeModerator($oldNodeId, $oldUserId, array $info)
	{
		$mod = $this->getModelFromCache('XenForo_Model_Moderator')->getContentModeratorByContentAndUserId('node', $info['content_id'], $info['user_id']);
		if ($mod)
		{
			return false; // already exists
		}

		XenForo_Db::beginTransaction();

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorContent');
		$dw->setImportMode(true);
		$dw->set('content_type', 'node');
		$dw->bulkSet($info);
		if ($dw->save())
		{
			$newId = $dw->get('moderator_id');
			$this->logImportData('moderatorNode', "n$oldNodeId-u$oldUserId", "n$info[content_id]-u$info[user_id]");

			if (!empty($info['moderator_permissions']))
			{
				$finalPermissions = $this->getModelFromCache('XenForo_Model_Moderator')->getModeratorPermissionsForUpdate(
					$info['moderator_permissions'], array(), 'content_allow'
				);

				$this->getModelFromCache('XenForo_Model_Permission')->updateContentPermissionsForUserCollection(
					$finalPermissions, $dw->get('content_type'), $dw->get('content_id'), 0, $dw->get('user_id')
				);
			}

			$db = $this->_getDb();
			$db->update('xf_user', array('is_moderator' => 1), 'user_id = ' . $db->quote($dw->get('user_id')));
		}
		else
		{
			$newId = false;
		}

		XenForo_Db::commit();

		return $newId;
	}

	/**
	 * Imports a thread
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer Imported thread ID
	 */
	public function importThread($oldId, array $info)
	{
		$threadId = $this->_importData($oldId, 'XenForo_DataWriter_Discussion_Thread', 'thread', 'thread_id', $info);
		if ($threadId)
		{
			if ($this->getModelFromCache('XenForo_Model_Thread')->isModerated($info))
			{
				$this->_getDb()->query('
					INSERT IGNORE INTO xf_moderation_queue
						(content_type, content_id, content_date)
					VALUES
						(?, ?, ?)
				', array('thread', $threadId, $info['post_date']));
			}
		}

		return $threadId;
	}

	/**
	 * Imports a thread watch/subscription
	 *
	 * @param integer User ID
	 * @param integer Thread ID
	 * @param string Subscription type
	 *
	 * @return integer Imported post ID
	 */
	public function importThreadWatch($userId, $threadId, $emailSubscribe)
	{
		$this->_getDb()->query('
			INSERT IGNORE INTO xf_thread_watch
				(user_id, thread_id, email_subscribe)
			VALUES
				(?, ?, ?)
		', array($userId, $threadId, $emailSubscribe));
	}

	/**
	 * Imports a post
	 *
	 * @param integer Source ID
	 * @param array Data to import
	 *
	 * @return integer Imported post ID
	 */
	public function importPost($oldId, array $info)
	{
		if (isset($info['ip']))
		{
			$ip = $info['ip'];
			unset($info['ip']);
		}
		else
		{
			$ip = false;
		}

		$postId = $this->_importData($oldId, 'XenForo_DataWriter_DiscussionMessage_Post', 'post', 'post_id', $info);
		if ($postId)
		{
			if ($info['message_state'] == 'moderated')
			{
				$this->_getDb()->query('
					INSERT IGNORE INTO xf_moderation_queue
						(content_type, content_id, content_date)
					VALUES
						(?, ?, ?)
				', array('post', $postId, $info['post_date']));
			}

			if ($ip)
			{
				$ipId = $this->importIp($info['user_id'], 'post', $postId, 'insert', $ip, $info['post_date']);
				if ($ipId)
				{
					$this->_getDb()->update('xf_post',
						array('ip_id' => $ipId),
						'post_id = ' . $this->_getDb()->quote($postId)
					);
				}
			}
		}

		return $postId;
	}

	/**
	 * Imports an IP log
	 *
	 * @param integer User ID
	 * @param string Content type
	 * @param string Action
	 * @param string IP address
	 * @param integer Date
	 *
	 * @return integer IP ID
	 */
	public function importIp($userId, $contentType, $contentId, $action, $ipAddress, $date)
	{
		$ipId = $this->getModelFromCache('XenForo_Model_Ip')->logIp(
			$userId, $contentType, $contentId, $action, $ipAddress, $date
		);
		return ($ipId ? $ipId : false);
	}

	/**
	 * Imports a thread poll
	 *
	 * @param integer Source poll ID
	 * @param integer Thread ID
	 * @param array Data to import
	 * @param array Responses
	 * @param array Response IDs
	 *
	 * @return integer Imported poll ID
	 */
	public function importThreadPoll($oldId, $threadId, array $info, array $responses, &$responseIds = null)
	{
		$info['content_type'] = 'thread';
		$info['content_id'] = $threadId;

		$responseIds = array();

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$pollId = $this->_importData($oldId, 'XenForo_DataWriter_Poll', 'poll', 'poll_id', $info);
		if ($pollId)
		{
			foreach ($responses AS $response)
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_PollResponse');
				$dw->setImportMode(true);
				$dw->set('poll_id', $pollId);
				$dw->set('response', $response);
				$dw->save();

				$responseIds[] = $dw->get('poll_response_id');
			}

			$this->logImportData('poll', $oldId, $pollId);

			$db->update('xf_thread',
				array('discussion_type' => 'poll'),
				'thread_id = ' . $db->quote($threadId)
			);
		}

		XenForo_Db::commit($db);

		return $pollId;
	}

	/**
	 * Imports a poll vote
	 *
	 * @param integer $pollId
	 * @param integer $userId
	 * @param integer $responseId
	 * @param integer $voteDate
	 */
	public function importPollVote($pollId, $userId, $responseId, $voteDate)
	{
		$this->_getDb()->query('
			INSERT IGNORE INTO xf_poll_vote
				(poll_id, user_id, poll_response_id, vote_date)
			VALUES
				(?, ?, ?, ?)
		', array($pollId, $userId, $responseId, $voteDate));
	}

	/**
	 * Imports a post attachment
	 *
	 * @param integer $oldAttachmentId
	 * @param string $fileName
	 * @param string $tempFile
	 * @param integer $userId
	 * @param integer $postId
	 * @param integer $date
	 * @param array $attach data to import
	 * @param function $messageCallback
	 * @param string $messageText
	 *
	 * @return Imported attachment ID
	 */
	public function importPostAttachment($oldAttachmentId, $fileName, $tempFile, $userId, $postId, $date, array $attach = array(), $messageCallback = null, &$messageText = null)
	{
		$upload = new XenForo_Upload($fileName, $tempFile);

		try
		{
			$dataExtra = array('upload_date' => $date, 'attach_count' => 1);
			$dataId = $this->getModelFromCache('XenForo_Model_Attachment')->insertUploadedAttachmentData($upload, $userId, $dataExtra);
		}
		catch (XenForo_Exception $e)
		{
			return false;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
		$dw->setImportMode(true);
		$dw->bulkSet(array(
			'data_id' => $dataId,
			'content_type' => 'post',
			'content_id' => $postId,
			'attach_date' => $date,
			'unassociated' => 0
		));
		$dw->bulkSet($attach);
		$dw->save();

		$newAttachmentId = $dw->get('attachment_id');

		if (is_callable($messageCallback) && isset($messageText))
		{
			$messageText = call_user_func($messageCallback, $oldAttachmentId, $newAttachmentId, $messageText);

			$this->_getDb()->query('
				UPDATE xf_post SET
					attach_count = IF(attach_count < 65535, attach_count + 1, 65535),
					message = ?
				WHERE post_id = ?
			', array($messageText, $postId));
		}
		else
		{
			$this->_getDb()->query('
				UPDATE xf_post SET
					attach_count = IF(attach_count < 65535, attach_count + 1, 65535)
				WHERE post_id = ?
			', $postId);
		}

		$this->logImportData('attachment', $oldAttachmentId, $newAttachmentId);

		return $newAttachmentId;
	}

	/**
	 * Array to store recognised / supported Like types for XenForo_Model_Import::importLike()
	 *
	 * @var array
	 */
	protected $_supportedLikeTypes = array(
		'post'         => array('xf_post',         'likes', 'post_id'),
		'profile_post' => array('xf_profile_post', 'likes', 'profile_post_id')
	);

	/**
	 * Import a content 'Like'
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param integer $contentUserId
	 * @param integer $likeUserId
	 * @param integer $likeDate
	 */
	public function importLike($contentType, $contentId, $contentUserId, $likeUserId, $likeDate)
	{
		if (isset($this->_supportedLikeTypes[$contentType]))
		{
			$contentInfo = $this->_supportedLikeTypes[$contentType];
		}
		else
		{
			throw new XenForo_Exception("The content type '$contentType' is not supported by XenForo_Model_Import::importLike()");
		}

		$db = $this->_getDb();

		$result = $db->query('
			INSERT IGNORE INTO xf_liked_content
				(content_type, content_id, content_user_id, like_user_id, like_date)
			VALUES
				(?, ?, ?, ?, ?)
		', array($contentType, $contentId, $contentUserId, $likeUserId, $likeDate));

		$db->query('
			UPDATE xf_user
			SET like_count = like_count + 1
			WHERE user_id = ?
		', $contentUserId);

		$db->query("
			UPDATE {$contentInfo[0]}
			SET {$contentInfo[1]} = {$contentInfo[1]} + 1
			WHERE {$contentInfo[2]} = ?
		", $contentId);
	}

	/**
	 * Gets the user IDs of the users that meet the specified conditions
	 *
	 * @param array $conditions
	 * @param string $key
	 * @param string $lowerKey
	 *
	 * @return array
	 */
	public function getUserIdsWithKey(array $conditions, $key, $lowerKey = true)
	{
		$users = $this->_getUserModel()->getUsers($conditions);
		$output = array();
		foreach ($users AS $user)
		{
			$keyValue = $user[$key];
			if ($lowerKey)
			{
				$keyValue = strtolower($user[$key]);
			}
			$output[$keyValue] = $user['user_id'];
		}
		return $output;
	}

	/**
	 * Gets the user IDs of the XenForo users with the specified email addresses
	 *
	 * @param array $emails
	 *
	 * @return array
	 */
	public function getUserIdsByEmails(array $emails)
	{
		if (!$emails)
		{
			return array();
		}

		$emails = $this->getUserIdsWithKey(array('emails' => $emails), 'email');
		unset($emails['']);
		return $emails;
	}

	/**
	 * Gets the user IDs of the XenForo users with the specified usernames
	 *
	 * @param array $names
	 *
	 * @return array
	 */
	public function getUserIdsByNames(array $names)
	{
		if (!$names)
		{
			return array();
		}

		return $this->getUserIdsWithKey(array('usernames' => $names), 'username');
	}

	/**
	 * Array to store admin permissions as they are loaded
	 *
	 * @var array
	 */
	protected $_adminPermissions = null;

	/**
	 * Fetches an array of all possible admin permissions
	 *
	 * @return array
	 */
	public function getAdminPermissionIds()
	{
		if ($this->_adminPermissions === null)
		{
			$this->_adminPermissions = array();

			$adminPermissions = $this->getModelFromCache('XenForo_Model_Admin')->getAllAdminPermissions();

			foreach ($adminPermissions AS $adminPermissionId => $adminPermission)
			{
				$this->_adminPermissions[] = $adminPermissionId;
			}
		}

		return $this->_adminPermissions;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}