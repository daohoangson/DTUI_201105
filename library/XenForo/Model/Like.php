<?php

/**
 * Model for liking content.
 *
 * @package XenForo_Like
 */
class XenForo_Model_Like extends XenForo_Model
{
	/**
	 * Gets a liked content record for a user that has liked a piece of content.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param integer $userId
	 *
	 * @return array|false
	 */
	public function getContentLikeByLikeUser($contentType, $contentId, $userId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_liked_content
			WHERE content_type = ?
				AND content_id = ?
				AND like_user_id = ?
		', array($contentType, $contentId, $userId));
	}

	/**
	 * Gets likes based on the content user.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions Fetch options. Supports limit only now.
	 *
	 * @return array Format: [like id] => info
	 */
	public function getLikesForContentUser($userId, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT liked_content.*,
					user.*
				FROM xf_liked_content AS liked_content
				INNER JOIN xf_user AS user ON (user.user_id = liked_content.like_user_id)
				WHERE liked_content.content_user_id = ?
				ORDER BY liked_content.like_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'like_id', $userId);
	}

	/**
	 * Count the number of likes a content user has received.
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function countLikesForContentUser($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_liked_content
			WHERE content_user_id = ?
		', $userId);
	}

	/**
	 * Get all the likes for a particular piece of content.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param array $fetchOptions Fetch options (limit only right now)
	 *
	 * @return array Format: [like id] => info
	 */
	public function getContentLikes($contentType, $contentId, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT liked_content.*,
					user.*,
					user_profile.*,
					user_option.*
				FROM xf_liked_content AS liked_content
				INNER JOIN xf_user AS user ON
					(user.user_id = liked_content.like_user_id)
				INNER JOIN xf_user_profile AS user_profile ON
					(user_profile.user_id = user.user_id)
				INNER JOIN xf_user_option AS user_option ON
					(user_option.user_id = user.user_id)
				WHERE liked_content.content_type = ?
					AND liked_content.content_id = ?
				ORDER BY liked_content.like_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'like_id', array($contentType, $contentId));
	}

	/**
	 * Gets the latest like users on a piece of content. Currently returns the last 5.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return array Format: [] => [user_id] and [username]
	 */
	public function getLatestContentLikeUsers($contentType, $contentId)
	{
		$likes = $this->getContentLikes($contentType, $contentId, array(
			'limit' => 5
		));

		$output = array();
		foreach ($likes AS $like)
		{
			$output[] = array(
				'user_id' => $like['like_user_id'],
				'username' => $like['username']
			);
		}

		return $output;
	}

	/**
	 * Inserts a new like for a piece of content.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param integer $contentUserId User that owns/created the content
	 * @param integer|null $likeUserId User liking content; defaults to visitor
	 * @param integer|null $likeDate Timestamp of liking; defaults to now.
	 *
	 * @return array|false List of latest like users or false
	 */
	public function likeContent($contentType, $contentId, $contentUserId, $likeUserId = null, $likeDate = null)
	{
		$visitor = XenForo_Visitor::getInstance();

		if ($likeUserId === null)
		{
			$likeUserId = $visitor['user_id'];
		}
		if (!$likeUserId)
		{
			return false;
		}

		if ($likeUserId != $visitor['user_id'])
		{
			$user = $this->getModelFromCache('XenForo_Model_User')->getUserById($likeUserId);
			if (!$user)
			{
				return false;
			}
			$likeUsername = $user['username'];
		}
		else
		{
			$likeUsername = $visitor['username'];
		}

		if ($likeDate === null)
		{
			$likeDate = XenForo_Application::$time;
		}

		$likeHandler = $this->getLikeHandler($contentType);
		if (!$likeHandler)
		{
			return false;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $db->query('
			INSERT IGNORE INTO xf_liked_content
				(content_type, content_id, content_user_id, like_user_id, like_date)
			VALUES
				(?, ?, ?, ?, ?)
		', array($contentType, $contentId, $contentUserId, $likeUserId, $likeDate));

		if (!$result->rowCount())
		{
			XenForo_Db::commit($db);
			return false;
		}

		if ($contentUserId)
		{
			$contentUser = $this->getModelFromCache('XenForo_Model_User')->getUserById($contentUserId, array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION
			));

			if ($contentUser)
			{
				$db->query('
					UPDATE xf_user
					SET like_count = like_count + 1
					WHERE user_id = ?
				', $contentUserId);


				if (XenForo_Model_Alert::userReceivesAlert($contentUser, $contentType, 'like'))
				{
					XenForo_Model_Alert::alert(
						$contentUserId,
						$likeUserId,
						$likeUsername,
						$contentType,
						$contentId,
						'like'
					);
				}
			}
		}

		// publish to news feed
		$this->getModelFromCache('XenForo_Model_NewsFeed')->publish(
			$likeUserId,
			$likeUsername,
			$contentType,
			$contentId,
			'like'
		);

		$latestLikeUsers = $this->getLatestContentLikeUsers($contentType, $contentId);
		$likeHandler->incrementLikeCounter($contentId, $latestLikeUsers);

		XenForo_Db::commit($db);

		return $latestLikeUsers;
	}

	/**
	 * Unlikes the specified like record.
	 *
	 * @param array $like
	 *
	 * @return array|false List of latest like users or false
	 */
	public function unlikeContent(array $like)
	{
		$likeHandler = $this->getLikeHandler($like['content_type']);
		if (!$likeHandler)
		{
			return false;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $db->query('
			DELETE FROM xf_liked_content
			WHERE like_id = ?
		', $like['like_id']);

		if (!$result->rowCount())
		{
			XenForo_Db::commit($db);
			return false;
		}

		if ($like['content_user_id'])
		{
			$db->query('
				UPDATE xf_user
				SET like_count = IF(like_count > 1, like_count - 1, 0)
				WHERE user_id = ?
			', $like['content_user_id']);

			$this->_getAlertModel()->deleteAlerts(
				$like['content_type'], $like['content_id'], $like['like_user_id'], 'like'
			);

			$this->_getNewsFeedModel()->delete(
				$like['content_type'], $like['content_id'], $like['like_user_id'], 'like'
			);
		}

		$latestLikeUsers = $this->getLatestContentLikeUsers($like['content_type'], $like['content_id']);
		$likeHandler->incrementLikeCounter($like['content_id'], $latestLikeUsers, -1);

		XenForo_Db::commit($db);

		return $latestLikeUsers;
	}

	/**
	 * Deletes all the likes applied to a piece of content.
	 *
	 * @param string $contentType
	 * @param integer|array $contentIds Single content ID or an array of them
	 * @param boolean Update the likes counter for liked users
	 */
	public function deleteContentLikes($contentType, $contentIds, $updateUserLikeCounter = true)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}
		if (!$contentIds)
		{
			return;
		}

		$db = $this->_getDb();
		$contentIdsQuoted = $db->quote($contentIds);

		if ($updateUserLikeCounter)
		{
			$updates = $db->fetchPairs('
				SELECT content_user_id, COUNT(*)
				FROM xf_liked_content
				WHERE content_type = ?
					AND content_id IN (' . $contentIdsQuoted . ')
				GROUP BY content_user_id
			', $contentType);

			foreach ($updates AS $userId => $decrement)
			{
				$decrementQuoted = $db->quote($decrement);
				$db->query("
					UPDATE xf_user
					SET like_count = IF(like_count > $decrementQuoted, like_count - $decrementQuoted, 0)
					WHERE user_id = ?
				", $userId);
			}
		}

		$db->delete('xf_liked_content',
			'content_type = ' . $db->quote($contentType) . ' AND content_id IN (' . $contentIdsQuoted . ')'
		);
	}

	/**
	 * Gets the like handler for a specific type of content.
	 *
	 * @param string $contentType
	 *
	 * @return false|XenForo_LikeHandler_Abstract
	 */
	public function getLikeHandler($contentType)
	{
		$handlerClass = $this->getContentTypeField($contentType, 'like_handler_class');
		if (!$handlerClass)
		{
			return false;
		}

		return new $handlerClass();
	}

	/**
	 * Gets the like handlers for all content types.
	 *
	 * @return array Array of XenForo_LikeHandler_Abstract objects
	 */
	public function getLikeHandlers()
	{
		$handlerClasses = $this->getContentTypesWithField('like_handler_class');
		$handlers = array();
		foreach ($handlerClasses AS $contentType => $handlerClass)
		{
			$handlers[$contentType] = new $handlerClass();
		}

		return $handlers;
	}

	/**
	 * Adds content specific data to a list of likes. Likes of unviewable content will
	 * be removed.
	 *
	 * @param array $likes
	 * @param array|null $viewingUser
	 *
	 * @return array Viewable likes with content key added (sub keys: url, title)
	 */
	public function addContentDataToLikes(array $likes, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$likesGrouped = array();
		foreach ($likes AS $key => $like)
		{
			$likesGrouped[$like['content_type']][$key] = $like['content_id'];
		}

		$handlers = $this->getLikeHandlers();
		foreach ($likesGrouped AS $contentType => $contentIds)
		{
			if (!isset($handlers[$contentType]))
			{
				foreach ($contentIds AS $key => $contentId)
				{
					unset($likes[$key]);
				}
			}
			else
			{
				$contents = $handlers[$contentType]->getContentData($contentIds, $viewingUser);
				$listTemplateName = $handlers[$contentType]->getListTemplateName();
				foreach ($contentIds AS $key => $contentId)
				{
					if (isset($contents[$contentId]))
					{
						$likes[$key]['content'] = $contents[$contentId];
						$likes[$key]['listTemplateName'] = $listTemplateName;
					}
					else
					{
						unset($likes[$key]);
					}
				}
			}
		}

		return $likes;
	}

	/**
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}

	/**
	 * @return XenForo_Model_NewsFeed
	 */
	protected function _getNewsFeedModel()
	{
		return $this->getModelFromCache('XenForo_Model_NewsFeed');
	}
}