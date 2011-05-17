<?php

/**
 * Model for forums
 *
 * @package XenForo_Forum
 */
class XenForo_Model_Forum extends XenForo_Model
{
	/**
	 * Fetches the combined node-forum record for the specified node id
	 *
	 * @param integer $id Node ID
	 * @param array $fetchOptions Options that affect what is fetched
	 *
	 * @return array
	 */
	public function getForumById($id, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareForumJoinOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT node.*, forum.*
				' . $joinOptions['selectFields'] . '
			FROM xf_forum AS forum
			INNER JOIN xf_node AS node ON (node.node_id = forum.node_id)
			' . $joinOptions['joinTables'] . '
			WHERE node.node_id = ?
		', $id);
	}

	/**
	 * Fetches the combined node-forum record for the specified node name
	 *
	 * @param string $name Node name
	 * @param array $fetchOptions Options that affect what is fetched
	 *
	 * @return array
	 */
	public function getForumByNodeName($name, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareForumJoinOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT node.*, forum.*
				' . $joinOptions['selectFields'] . '
			FROM xf_forum AS forum
			INNER JOIN xf_node AS node ON (node.node_id = forum.node_id)
			' . $joinOptions['joinTables'] . '
			WHERE node.node_name = ?
				AND node.node_type_id = \'Forum\'
		', $name);
	}

	/**
	 * Fetches the combined node-forum records for the specified forum/node IDs.
	 *
	 * @param array $forumIds
	 * @param array $fetchOptions Options that affect what is fetched
	 *
	 * @return array Format: [node id] => info
	 */
	public function getForumsByIds(array $forumIds, array $fetchOptions = array())
	{
		if (!$forumIds)
		{
			return array();
		}

		$joinOptions = $this->prepareForumJoinOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT node.*, forum.*
				' . $joinOptions['selectFields'] . '
			FROM xf_forum AS forum
			INNER JOIN xf_node AS node ON (node.node_id = forum.node_id)
			' . $joinOptions['joinTables'] . '
			WHERE node.node_id IN (' . $this->_getDb()->quote($forumIds) . ')
		', 'node_id');
	}

	/**
	 * Gets all forums matching the specified criteria (no criteria implemented yet).
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getForums(array $conditions = array(), array $fetchOptions = array())
	{
		$joinOptions = $this->prepareForumJoinOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT node.*, forum.*
					' . $joinOptions['selectFields'] . '
				FROM xf_forum AS forum
				INNER JOIN xf_node AS node ON (node.node_id = forum.node_id)
				' . $joinOptions['joinTables'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'node_id');
	}

	/**
	 * Gets the extra data that applies to the specified forum nodes.
	 *
	 * @param array $nodeIds
	 * @param array $fetchOptions Options that affect what is fetched
	 *
	 * @return array Format: [node id] => extra info
	 */
	public function getExtraForumDataForNodes(array $nodeIds, array $fetchOptions = array())
	{
		if (!$nodeIds)
		{
			return array();
		}

		$joinOptions = $this->prepareForumJoinOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT forum.*
				' . $joinOptions['selectFields'] . '
			FROM xf_forum AS forum
			INNER JOIN xf_node AS node ON (node.node_id = forum.node_id)
			' . $joinOptions['joinTables'] . '
			WHERE forum.node_id IN (' . $this->_getDb()->quote($nodeIds) . ')
		', 'node_id');
	}

	/**
	 * Checks the 'join' key of the incoming array for the presence of the FETCH_x bitfields in this class
	 * and returns SQL snippets to join the specified tables if required
	 *
	 * @param array $fetchOptions Array containing a 'join' integer key build from this class's FETCH_x bitfields and other keys
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys. Example: selectFields = ', user.*, foo.title'; joinTables = ' INNER JOIN foo ON (foo.id = other.id) '
	 */
	public function prepareForumJoinOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		$db = $this->_getDb();

		if (!empty($fetchOptions['permissionCombinationId']))
		{
			$selectFields .= ',
				permission.cache_value AS node_permission_cache';
			$joinTables .= '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $db->quote($fetchOptions['permissionCombinationId']) . '
						AND permission.content_type = \'node\'
						AND permission.content_id = forum.node_id)';
		}

		if (isset($fetchOptions['readUserId']))
		{
			if (!empty($fetchOptions['readUserId']))
			{
				$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

				$selectFields .= ",
					IF(forum_read.forum_read_date > $autoReadDate, forum_read.forum_read_date, $autoReadDate) AS forum_read_date";
				$joinTables .= '
					LEFT JOIN xf_forum_read AS forum_read ON
						(forum_read.node_id = forum.node_id
						AND forum_read.user_id = ' . $db->quote($fetchOptions['readUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					NULL AS forum_read_date';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Prepares a forum for display.
	 *
	 * @param array $forum Unprepared forum
	 *
	 * @return array Prepared forum
	 */
	public function prepareForum(array $forum)
	{
		$forum['hasNew'] = (isset($forum['forum_read_date']) && $forum['forum_read_date'] < $forum['last_post_date']);

		return $forum;
	}

	/**
	 * Prepares a collection of forums for display.
	 *
	 * @param array $forums Unprepared forums
	 *
	 * @return array Prepared forums
	 */
	public function prepareForums(array $forums)
	{
		foreach ($forums AS &$forum)
		{
			$forum = $this->prepareForum($forum);
		}

		return $forums;
	}

	/**
	 * Gets the permissions in use for a specific forum from the details of
	 * the forum, or from a permissions override list if provided.
	 *
	 * When looking within the forum, looks for "nodePermissions" or "node_permission_cache" keys.
	 *
	 * @param array $forum Forum info
	 * @param array $permissionsList Optional permissions to override; format: [forum id] => permissions
	 *
	 * @return array Permissions for forum
	 */
	public function getPermissionsForForum(array $forum, array $permissionsList = array())
	{
		if (isset($permissionsList[$forum['node_id']]))
		{
			return $permissionsList[$forum['node_id']];
		}
		else if (isset($forum['nodePermissions']))
		{
			return $forum['nodePermissions'];
		}
		else if (isset($forum['node_permission_cache']))
		{
			return XenForo_Permission::unserializePermissions($forum['node_permission_cache']);
		}
		else
		{
			return array();
		}
	}

	/**
	 * Determines if the specified forum can be viewed with the given permissions.
	 *
	 * @param array $forum Info about the forum posting in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewForum(array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($forum['node_id'], $viewingUser, $nodePermissions);

		return XenForo_Permission::hasContentPermission($nodePermissions, 'view');
	}

	/**
	 * Determines if a new thread can be posted in the specified forum,
	 * with the given permissions. If no permissions are specified, permissions
	 * are retrieved from the currently visiting user. This does not check viewing permissions.
	 *
	 * @param array $forum Info about the forum posting in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canPostThreadInForum(array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($forum['node_id'], $viewingUser, $nodePermissions);

		if (empty($forum['allow_posting']))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_forum_does_not_allow_posting';
			return false;
		}

		return XenForo_Permission::hasContentPermission($nodePermissions, 'postThread');
	}

	/**
	 * Determines if a new attachment can be posted in the specified forum,
	 * with the given permissions. If no permissions are specified, permissions
	 * are retrieved from the currently visiting user. This does not check viewing permissions.
	 *
	 * @param array $forum Info about the forum posting in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canUploadAndManageAttachment(array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($forum['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return XenForo_Permission::hasContentPermission($nodePermissions, 'uploadAttachment');
	}

	/**
	 * Determines if a thread can be locked or unlocked in the specified forum
	 * with the given permissions.
	 *
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canLockUnlockThreadInForum(array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($forum['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return XenForo_Permission::hasContentPermission($nodePermissions, 'lockUnlockThread');
	}

	/**
	 * Determines if a thread can be stuck or unstuck in the specified forum
	 * with the given permissions.
	 *
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStickUnstickThreadInForum(array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($forum['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return XenForo_Permission::hasContentPermission($nodePermissions, 'stickUnstickThread');
	}

	/**
	 * Gets the set of attachment params required to allow uploading.
	 *
	 * @param array $forum
	 * @param array $contentData Information about the content, for URL building
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return array|false
	 */
	public function getAttachmentParams(array $forum, array $contentData, array $nodePermissions = null, array $viewingUser = null)
	{
		if ($this->canUploadAndManageAttachment($forum, $null, $nodePermissions, $viewingUser))
		{
			return array(
				'hash' => md5(uniqid('', true)),
				'content_type' => 'post',
				'content_data' => $contentData
			);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets the count of unread threads in the given forum. This only applies to registered
	 * users. If no user ID is given, false is returned.
	 *
	 * @param integer $forumId
	 * @param integer $userId
	 * @param integer $forumReadDate Time when the whole forum is read from
	 *
	 * @return integer|false
	 */
	public function getUnreadThreadCountInForum($forumId, $userId, $forumReadDate = 0)
	{
		if (!$userId)
		{
			return false;
		}

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_thread AS thread
			LEFT JOIN xf_thread_read AS thread_read ON
				(thread_read.thread_id = thread.thread_id AND thread_read.user_id = ?)
			WHERE thread.node_id = ?
				AND thread.last_post_date > ?
				AND (thread_read.thread_id IS NULL OR thread.last_post_date > thread_read.thread_read_date)
				AND thread.discussion_state = \'visible\'
				AND thread.discussion_type <> \'redirect\'
		', array($userId, $forumId, $forumReadDate));
	}

	/**
	 * Marks the specified forum as read up to a specific time. Forum must have the
	 * forum_read_date key.
	 *
	 * @param array $forum Forum info
	 * @param integer $readDate Timestamp to mark as read until
	 * @param integer $userId User marking forum read
	 *
	 * @return boolean True if marked as read
	 */
	public function markForumRead(array $forum, $readDate, $userId)
	{
		if (!$userId)
		{
			return false;
		}

		if (!array_key_exists('forum_read_date', $forum))
		{
			$forum['forum_read_date'] = $this->getUserForumReadDate($userId, $forum['node_id']);
		}

		if ($readDate <= $forum['forum_read_date'])
		{
			return false;
		}

		$this->_getDb()->query('
			INSERT INTO xf_forum_read
				(user_id, node_id, forum_read_date)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE forum_read_date = VALUES(forum_read_date)
		', array($userId, $forum['node_id'], $readDate));

		return true;
	}

	/**
	 * Marks a forum and all sub-forums read. This can be used without a base forum
	 * to mark all forums as read.
	 *
	 * @param array|null $baseForum Info about base forum to mark read; may be null
	 * @param integer $readDate Date to set as read date
	 * @param array|null $viewingUser
	 *
	 * @return array A list of node IDs that were marked as read
	 */
	public function markForumTreeRead(array $baseForum = null, $readDate, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return array();
		}

		// TODO: technically, this should mark all nodes as read; need to refactor down the line
		$forums = $this->getForums(array(), array(
			'readUserId' => $viewingUser['user_id'],
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		$forumIds = array();
		foreach ($forums AS $markForum)
		{
			if ($baseForum && (
				$markForum['lft'] < $baseForum['lft'] || $markForum['rgt'] > $baseForum['rgt'])
			)
			{
				continue;
			}

			if ($this->canViewForum($markForum, $null,
				XenForo_Permission::unserializePermissions($markForum['node_permission_cache'])
			))
			{
				if ($this->markForumRead($markForum, $readDate, $viewingUser['user_id']))
				{
					$forumIds[] = $markForum['node_id'];
				}
			}
		}

		return $forumIds;
	}

	/**
	 * Determine if the forum should be marked as read and do so if needed.
	 *
	 * @param array $forum
	 * @param integer $userId
	 *
	 * @return boolean
	 */
	public function markForumReadIfNeeded(array $forum, $userId)
	{
		if (!$userId)
		{
			return false;
		}

		if (!array_key_exists('forum_read_date', $forum))
		{
			$forum['forum_read_date'] = $this->getUserForumReadDate($userId, $forum['node_id']);
		}

		$unreadThreadCount = $this->getUnreadThreadCountInForum(
			$forum['node_id'], $userId, $forum['forum_read_date']
		);

		if (!$unreadThreadCount)
		{
			return $this->markForumRead($forum, XenForo_Application::$time, $userId);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get the time when a user has marked the given forum as read.
	 *
	 * @param integer $userId
	 * @param integer $forumId
	 *
	 * @return integer|null Null if guest; timestamp otherwise
	 */
	public function getUserForumReadDate($userId, $forumId)
	{
		if (!$userId)
		{
			return null;
		}

		$readDate = $this->_getDb()->fetchOne('
			SELECT forum_read_date
			FROM xf_forum_read
			WHERE user_id = ?
				AND node_id = ?
		', array($userId, $forumId));

		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);
		return max($readDate, $autoReadDate);
	}

	/**
	 * Gets the forum counters for the specified forum.
	 *
	 * @param integer $forumId
	 *
	 * @return array Keys: discussion_count, message_count
	 */
	public function getForumCounters($forumId)
	{
		return $this->_getDb()->fetchRow('
			SELECT
				COUNT(*) AS discussion_count,
				COUNT(*) + SUM(reply_count) AS message_count
			FROM xf_thread
			WHERE node_id = ?
				AND discussion_state = \'visible\'
				AND discussion_type <> \'redirect\'
		', $forumId);
	}
}