<?php

/**
 * Model for thread watch records.
 *
 * @package XenForo_Thread
 */
class XenForo_Model_ThreadWatch extends XenForo_Model
{
	/**
	 * Gets a user's thread watch record for the specified thread ID.
	 *
	 * @param integer $userId
	 * @param integer $threadId
	 *
	 * @return array|false
	 */
	public function getUserThreadWatchByThreadId($userId, $threadId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_thread_watch
			WHERE user_id = ?
				AND thread_id = ?
		', array($userId, $threadId));
	}

	/**
	 * Get the thread watch records for a user, across many thread IDs.
	 *
	 * @param integer $userId
	 * @param array $threadIds
	 *
	 * @return array Format: [thread_id] => thread watch info
	 */
	public function getUserThreadWatchByThreadIds($userId, array $threadIds)
	{
		if (!$threadIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_thread_watch
			WHERE user_id = ?
				AND thread_id IN (' . $this->_getDb()->quote($threadIds) . ')
		', 'thread_id', $userId);
	}

	/**
	 * Get a list of all users watching a thread. Includes permissions for the forum the thread is in.
	 *
	 * @param integer $threadId
	 * @param integer $nodeId Forum the thread is in.
	 *
	 * @return array Format: [user_id] => info
	 */
	public function getUsersWatchingThread($threadId, $nodeId)
	{
		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

		return $this->fetchAllKeyed('
			SELECT user.*,
				user_option.*,
				thread_watch.email_subscribe,
				permission.cache_value AS node_permission_cache,
				GREATEST(COALESCE(thread_read.thread_read_date, 0), COALESCE(forum_read.forum_read_date, 0), ' . $autoReadDate . ') AS thread_read_date
			FROM xf_thread_watch AS thread_watch
			INNER JOIN xf_user AS user ON
				(user.user_id = thread_watch.user_id AND user.user_state = \'valid\' AND user.is_banned = 0)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			LEFT JOIN xf_permission_cache_content AS permission
				ON (permission.permission_combination_id = user.permission_combination_id
					AND permission.content_type = \'node\'
					AND permission.content_id = ?)
			LEFT JOIN xf_thread_read AS thread_read
				ON (thread_read.thread_id = thread_watch.thread_id AND thread_read.user_id = user.user_id)
			LEFT JOIN xf_forum_read AS forum_read
				ON (forum_read.node_id = ? AND forum_read.user_id = user.user_id)
			WHERE thread_watch.thread_id = ?
		', 'user_id', array($nodeId, $nodeId, $threadId));
	}

	/**
	 * Send a notification to the users watching the thread.
	 *
	 * @param array $reply The reply that has been added
	 * @param array|null $thread Info about the thread the reply is in; fetched if null
	 * @param array List of user ids to NOT alert (but still send email)
	 */
	public function sendNotificationToWatchUsersOnReply(array $reply, array $thread = null, array $noAlerts = array())
	{
		if ($reply['message_state'] != 'visible')
		{
			return;
		}

		$threadModel = $this->_getThreadModel();

		if (!$thread)
		{
			$thread = $threadModel->getThreadById($reply['thread_id'], array(
				'join' => XenForo_Model_Thread::FETCH_FORUM
			));
		}
		if (!$thread || $thread['discussion_state'] != 'visible')
		{
			return;
		}

		$latestPosts = $this->getModelFromCache('XenForo_Model_Post')->getNewestPostsInThreadAfterDate(
			$thread['thread_id'], 0,
			array('limit' => 2)
		);
		if (!$latestPosts)
		{
			return;
		}

		// the reply is likely the last post, so get the one before that and only
		// alert again if read since; note these posts are in newest first order,
		// so end() is last
		$previousPost = end($latestPosts);

		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

		$users = $this->getUsersWatchingThread($thread['thread_id'], $thread['node_id']);
		foreach ($users AS $user)
		{
			if ($user['user_id'] == $reply['user_id'])
			{
				continue;
			}

			if ($previousPost['post_date'] < $autoReadDate)
			{
				// always alert
			}
			else if ($previousPost['post_date'] > $user['thread_read_date'])
			{
				// user hasn't read the thread since the last alert, don't send another one
				continue;
			}

			$permissions = XenForo_Permission::unserializePermissions($user['node_permission_cache']);
			if (!$threadModel->canViewThreadAndContainer($thread, $thread, $null, $permissions, $user))
			{
				continue;
			}

			if ($user['email_subscribe'] && $user['email'] && $user['user_state'] == 'valid')
			{
				if (!isset($thread['titleCensored']))
				{
					$thread['titleCensored'] = XenForo_Helper_String::censorString($thread['title']);
				}

				$mail = XenForo_Mail::create('watched_thread_reply', array(
					'reply' => $reply,
					'thread' => $thread,
					'forum' => $thread,
					'receiver' => $user
				), $user['language_id']);
				$mail->enableAllLanguagePreCache();
				$mail->queue($user['email'], $user['username']);
			}

			if (!in_array($user['user_id'], $noAlerts))
			{
				$alertType = ($reply['attach_count'] ? 'insert_attachment' : 'insert');

				if (XenForo_Model_Alert::userReceivesAlert($user, 'post', $alertType))
				{
					XenForo_Model_Alert::alert(
						$user['user_id'],
						$reply['user_id'],
						$reply['username'],
						'post',
						$reply['post_id'],
						$alertType
					);
				}
			}
		}
	}

	/**
	 * Get the threads watched by a specific user.
	 *
	 * @param integer $userId
	 * @param boolean $newOnly If true, only gets unread threads.
	 * @param array $fetchOptions Thread fetch options (uses all valid for XenForo_Model_Thread).
	 *
	 * @return array Format: [thread_id] => info
	 */
	public function getThreadsWatchedByUser($userId, $newOnly, array $fetchOptions = array())
	{
		$fetchOptions['readUserId'] = $userId;
		$fetchOptions['includeForumReadDate'] = true;

		$joinOptions = $this->_getThreadModel()->prepareThreadFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		if ($newOnly)
		{
			$cutoff = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);
			$newOnlyClause = '
				AND thread.last_post_date > ' . $cutoff . '
				AND thread.last_post_date > COALESCE(thread_read.thread_read_date, 0)
				AND thread.last_post_date > COALESCE(forum_read.forum_read_date, 0)
			';
		}
		else
		{
			$newOnlyClause = '';
		}

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT thread.*,
					thread_watch.email_subscribe
					' . $joinOptions['selectFields'] . '
				FROM xf_thread_watch AS thread_watch
				INNER JOIN xf_thread AS thread ON
					(thread.thread_id = thread_watch.thread_id)
				' . $joinOptions['joinTables'] . '
				WHERE thread_watch.user_id = ?
					AND thread.discussion_state = \'visible\'
					' . $newOnlyClause . '
				ORDER BY thread.last_post_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'thread_id', $userId);
	}

	/**
	 * Gets the total number of threads a user is watching.
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function countThreadsWatchedByUser($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_thread_watch
			WHERE user_id = ?
		', $userId);
	}

	/**
	 * Take a list of threads (with the forum and permission info included in the thread)
	 * and filters them to those that are viewable.
	 *
	 * @param array $threads List of threads, with forum info and permissions included
	 * @param array|null $viewingUser
	 *
	 * @return array
	 */
	public function getViewableThreadsFromList(array $threads, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$threadModel = $this->_getThreadModel();

		foreach ($threads AS $key => $thread)
		{
			if (isset($thread['permissions']))
			{
				$permissions = $thread['permissions'];
			}
			else
			{
				$permissions = XenForo_Permission::unserializePermissions($thread['node_permission_cache']);
			}

			if (!$threadModel->canViewThreadAndContainer($thread, $thread, $null, $permissions, $viewingUser))
			{
				unset($threads[$key]);
			}
		}

		return $threads;
	}

	/**
	 * Sets the thread watch state as requested. An empty state will delete any watch record.
	 *
	 * @param integer $userId
	 * @param integer $threadId
	 * @param string $state Values: watch_email, watch_no_email, (empty string)
	 *
	 * @return boolean
	 */
	public function setThreadWatchState($userId, $threadId, $state)
	{
		if (!$userId)
		{
			return false;
		}

		$threadWatch = $this->getUserThreadWatchByThreadId($userId, $threadId);

		switch ($state)
		{
			case 'watch_email':
			case 'watch_no_email':
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
				if ($threadWatch)
				{
					$dw->setExistingData($threadWatch, true);
				}
				else
				{
					$dw->set('user_id', $userId);
					$dw->set('thread_id', $threadId);
				}
				$dw->set('email_subscribe', ($state == 'watch_email' ? 1 : 0));
				$dw->save();
				return true;

			case '':
				if ($threadWatch)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
					$dw->setExistingData($threadWatch, true);
					$dw->delete();
				}
				return true;

			default:
				return false;
		}
	}

	/**
	 * Sets the thread watch state based on the user's default. This will never unwatch a thread.
	 *
	 * @param integer $userId
	 * @param integer $threadId
	 * @param string $state Values: watch_email, watch_no_email, (empty string)
	 *
	 * @return boolean
	 */
	public function setThreadWatchStateWithUserDefault($userId, $threadId, $state)
	{
		if (!$userId)
		{
			return false;
		}

		$threadWatch = $this->getUserThreadWatchByThreadId($userId, $threadId);
		if ($threadWatch)
		{
			return true;
		}

		switch ($state)
		{
			case 'watch_email':
			case 'watch_no_email':
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
				$dw->set('user_id', $userId);
				$dw->set('thread_id', $threadId);
				$dw->set('email_subscribe', ($state == 'watch_email' ? 1 : 0));
				$dw->save();
				return true;

			default:
				return false;
		}
	}

	/**
	 * Sets the thread watch state for the visitor from an array of input. Keys in input:
	 * 	* watch_thread_state: if true, uses watch_thread and watch_thread_email to set state as requested
	 *  * watch_thread: if true, watches thread
	 *  * watch_thread_email: if true (and watch_thread is true), watches thread with email; otherwise, watches thread without email
	 *
	 * @param integer $threadId
	 * @param array $input
	 *
	 * @return boolean
	 */
	public function setVisitorThreadWatchStateFromInput($threadId, array $input)
	{
		$visitor = XenForo_Visitor::getInstance();

		if (!$visitor['user_id'])
		{
			return false;
		}

		if ($input['watch_thread_state'])
		{
			if ($input['watch_thread'])
			{
				$watchState = ($input['watch_thread_email'] ? 'watch_email' : 'watch_no_email');
			}
			else
			{
				$watchState = '';
			}

			return $this->setThreadWatchState($visitor['user_id'], $threadId, $watchState);
		}
		else
		{
			return $this->setThreadWatchStateWithUserDefault($visitor['user_id'], $threadId, $visitor['default_watch_state']);
		}
	}

	/**
	 * Gets the thread watch state for the specified thread for the visiting user.
	 *
	 * @param integer|false $threadId Thread ID, or false if unknown
	 * @param boolean $useDefaultIfNotWatching If true, uses visitor default if thread isn't watched
	 *
	 * @return string Values: watch_email, watch_no_email, (empty string)
	 */
	public function getThreadWatchStateForVisitor($threadId = false, $useDefaultIfNotWatching = true)
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor['user_id'])
		{
			return '';
		}

		if ($threadId)
		{
			$threadWatch = $this->getUserThreadWatchByThreadId($visitor['user_id'], $threadId);
		}
		else
		{
			$threadWatch = false;
		}

		if ($threadWatch)
		{
			return ($threadWatch['email_subscribe'] ? 'watch_email' : 'watch_no_email');
		}
		else if ($useDefaultIfNotWatching)
		{
			return $visitor['default_watch_state'];
		}
		else
		{
			return '';
		}
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}
}