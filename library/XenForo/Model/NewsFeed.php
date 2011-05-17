<?php

/**
 * Model class for manipulating the news feed.
 *
 * @author kier
 */
class XenForo_Model_NewsFeed extends XenForo_Model
{
	/**
	 * Array to store news feed handler classes
	 *
	 * @var array
	 */
	protected $_handlerCache = array();

	/**
	 * Fetches a single news feed item using its ID
	 *
	 * @param integer $newsFeedId
	 *
	 * @return array
	 */
	public function getNewsFeedItemById($newsFeedId)
	{
		return $this->_getDb()->fetchAll('

			SELECT *
			FROM xf_news_feed
			WERE news_feed_id = ?

		', $newsFeedId);
	}

	/**
	 * Returns news feed data for the specified user.
	 * By default, returns the most recent items unless a 'fetchOlderThanId' is specified.
	 *
	 * @param array $user
	 * @param integer If specified, switches the mode of the function to return results *older* than the specified news feed id
	 * @param array|null $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions) or null for visitor
	 *
	 * @return array
	 */
	public function getNewsFeedForUser(array $user, $fetchOlderThanId = 0, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($fetchOlderThanId)
		{
			$newsFeed = $this->getNewsFeedItemsForUser($user,
				array('news_feed_id' => array('<', $fetchOlderThanId))
			);
		}
		else
		{
			$cacheNewsFeed = $this->getNewsFeedCache($user['user_id']);
			$newestItemId = $this->getNewestNewsFeedIdFromArray($cacheNewsFeed);

			$newsFeed = $this->getNewsFeedItemsForUser($user,
				array('news_feed_id' => array('>', $newestItemId))
			);
		}

		$newsFeed = $this->fillOutNewsFeedItems($newsFeed, $viewingUser);

		if (!$fetchOlderThanId)
		{
			$updateCache = (count($newsFeed) > 0);

			$newsFeed = $this->_mergeLatestNewsFeedItemsWithCache($newsFeed, $cacheNewsFeed);
			if ($updateCache AND $user['user_id'] == $viewingUser['user_id'])
			{
				$this->_saveCache($user['user_id'], $newsFeed);
			}
		}

		$this->_cacheHandlersForNewsFeed($newsFeed);

		return array(
			'newsFeed' => $newsFeed,
			'newsFeedHandlers' => $this->_handlerCache,
			'oldestItemId' => $this->getOldestNewsFeedIdFromArray($newsFeed),
			'feedEnds' => (sizeof($newsFeed) == 0) // permissions make this hard to calculate
		);
	}

	/**
	 * Gets a news feed with the specified conditions.
	 *
	 * @param array $conditions
	 * @param integer $fetchOlderThanId If > 0, only fetches items with a lower ID than this
	 * @param array|null $viewingUser
	 *
	 * @return array
	 */
	public function getNewsFeed(array $conditions = array(), $fetchOlderThanId = 0, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($fetchOlderThanId)
		{
			$conditions['news_feed_id'] = array('<', $fetchOlderThanId);
		}

		$newsFeed = $this->getNewsFeedItems($conditions, $viewingUser);

		$newsFeed = $this->fillOutNewsFeedItems($newsFeed, $viewingUser);
		$this->_cacheHandlersForNewsFeed($newsFeed);

		return array(
			'newsFeed' => $newsFeed,
			'newsFeedHandlers' => $this->_handlerCache,
			'oldestItemId' => $this->getOldestNewsFeedIdFromArray($newsFeed),
			'feedEnds' => (sizeof($newsFeed) == 0) // permissions make this hard to calculate
		);
	}

	/**
	 * Fills out a collection of news feed items, to include the necessary content and prepares
	 * them for view. Also filters out unviewable items.
	 *
	 * @param array $newsFeed
	 * @param array $viewingUser
	 *
	 * @return array
	 */
	public function fillOutNewsFeedItems(array $newsFeed, array $viewingUser)
	{
		if ($newsFeed)
		{
			$newsFeed = $this->_getContentForNewsFeedItems($newsFeed, $viewingUser);
			$newsFeed = $this->_getViewableNewsFeedItems($newsFeed, $viewingUser);
			$newsFeed = $this->_prepareNewsFeedItems($newsFeed, $viewingUser);
		}

		return $newsFeed;
	}

	/**
	 * Caches an instance of every news feed handler required by the data provided
	 *
	 * @param array $newsFeed
	 */
	protected function _cacheHandlersForNewsFeed(array $newsFeed)
	{
		foreach ($newsFeed AS $item)
		{
			$this->_getNewsFeedHandlerFromCache($item['news_feed_handler_class']);
		}
	}

	/**
	 * Gets news feed items for a particular viewing user. This will get news feed items
	 * for all user he/she follows.
	 *
	 * @param array $viewingUser
	 * @param array $conditions
	 * @param integer|null $maxItems
	 *
	 * @return array
	 */
	public function getNewsFeedItemsForUser(array $viewingUser, array $conditions = array(), $maxItems = null)
	{
		$followingIds = $this->_getDb()->fetchCol('
			SELECT follow_user_id
			FROM xf_user_follow
			WHERE xf_user_follow.user_id = ?
		', $viewingUser['user_id']);

		if (!$followingIds)
		{
			return array();
		}

		$conditions['user_id'] = $followingIds;

		return $this->getNewsFeedItems($conditions, $viewingUser, $maxItems);
	}

	/**
	 * Gets news feed items matching the given conditions.
	 *
	 * @param array $conditions
	 * @param array $viewingUser
	 * @param integer|null $maxItems
	 *
	 * @return array
	 */
	public function getNewsFeedItems(array $conditions = array(), array $viewingUser, $maxItems = null)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (isset($conditions['news_feed_id']) && is_array($conditions['news_feed_id']))
		{
			list($operator, $newsFeedId) = $conditions['news_feed_id'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "news_feed.news_feed_id $operator " . $db->quote($newsFeedId);
		}

		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'news_feed.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'news_feed.user_id = ' . $db->quote($conditions['user_id']);
			}
			$forceIndex = '';
		}
		else
		{
			$forceIndex = 'FORCE INDEX (event_date)';
		}

		$whereClause = $this->getConditionsForClause($sqlConditions);

		if ($maxItems === null)
		{
			$maxItems = XenForo_Application::get('options')->newsFeedMaxItems;
		}

		$viewingUserIdQuoted = $db->quote($viewingUser['user_id']);
		$isRegistered = ($viewingUser['user_id'] > 0 ? 1 : 0);
		$bypassPrivacy = $this->getModelFromCache('XenForo_Model_User')->canBypassUserPrivacy($errorPhraseKey, $viewingUser);

		// TODO: restore user_id = 0 announcements functionality down the line
		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT
					user.*,
					user_profile.*,
					user_privacy.*,
					news_feed.*,
					content_type_field.field_value AS news_feed_handler_class
				FROM xf_news_feed AS news_feed ' . $forceIndex . '
				INNER JOIN xf_content_type_field AS content_type_field ON
					(content_type_field.content_type = news_feed.content_type
					AND content_type_field.field_name = \'news_feed_handler_class\')
				INNER JOIN xf_user AS user ON
					(user.user_id = news_feed.user_id)
				INNER JOIN xf_user_profile AS user_profile ON
					(user_profile.user_id = user.user_id)
				LEFT JOIN xf_user_follow AS user_follow ON
					(user_follow.user_id = user.user_id
					AND user_follow.follow_user_id = ' . $viewingUserIdQuoted . ')
				INNER JOIN xf_user_privacy AS user_privacy ON
					(user_privacy.user_id = user.user_id
						' . ($bypassPrivacy ? '' : '
							AND (user.user_id = ' . $viewingUserIdQuoted . '
								OR (
									user_privacy.allow_receive_news_feed <> \'none\'
									AND IF(user_privacy.allow_receive_news_feed = \'members\', ' . $isRegistered . ', 1)
									AND IF(user_privacy.allow_receive_news_feed = \'followed\', user_follow.user_id IS NOT NULL, 1)
								)
							)
						') . '
					)
				WHERE ' . $whereClause . '
				ORDER BY news_feed.event_date DESC
			', $maxItems
		), 'news_feed_id');
	}

	/**
	 * Gets the ID of the newest feed item in an array of feed items
	 *
	 * @param array News feed array
	 *
	 * @return integer
	 */
	public function getNewestNewsFeedIdFromArray(array $newsFeed)
	{
		if (empty($newsFeed))
		{
			return 0;
		}

		return max(array_keys($newsFeed));
	}

	/**
	 * Gets the ID of the oldest feed item in an array of news feed items
	 *
	 * @param array News feed array
	 *
	 * @return integer
	 */
	public function getOldestNewsFeedIdFromArray(array $newsFeed)
	{
		if (empty($newsFeed))
		{
			return 0;
		}

		return min(array_keys($newsFeed));
	}

	/**
	 * Fetches content data for news feed items
	 *
	 * @param array $data Raw news feed data
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _getContentForNewsFeedItems(array $data, array $viewingUser)
	{
		// group all content ids of each content type...
		$fetchQueue = array();
		foreach ($data AS $id => $item)
		{
			$fetchQueue[$item['news_feed_handler_class']][$item['news_feed_id']] = $item['content_id'];
		}

		// fetch data for all items of each content type in one go...
		$fetchData = array();
		foreach ($fetchQueue AS $handlerClass => $contentIds)
		{
			$fetchData[$handlerClass] = $this->_getNewsFeedHandlerFromCache($handlerClass)->getContentByIds($contentIds, $this, $viewingUser);
		}

		// attach resulting content to each feed item...
		foreach ($data AS $id => $item)
		{
			if (!isset($fetchData[$item['news_feed_handler_class']][$item['content_id']]))
			{
				// For whatever reason, there was no related content found for this news feed item,
				// therefore remove it from this user's news feed
				unset($data[$id]);
				continue;
			}

			$data[$id]['content'] = $fetchData[$item['news_feed_handler_class']][$item['content_id']];
		}

		return $data;
	}

	/**
	 * Filters out unviewable news feed items and returns only those the user can view.
	 *
	 * @param array $items
	 * @param array $viewingUser
	 *
	 * @return array Filtered items
	 */
	protected function _getViewableNewsFeedItems(array $items, array $viewingUser)
	{
		foreach ($items AS $key => $item)
		{
			$handler = $this->_getNewsFeedHandlerFromCache($item['news_feed_handler_class']);
			if (!$handler->canViewNewsFeedItem($item, $item['content'], $viewingUser))
			{
				unset($items[$key]);
			}
		}

		return $items;
	}

	/**
	 * Takes all new feed items and appends old items from the cache until the array
	 * contains options->newsFeedMaxItems items
	 *
	 * @param array $newsFeed
	 * @param array $cachedItems
	 */
	protected function _mergeLatestNewsFeedItemsWithCache(array $newsFeed, array $cachedItems)
	{
		$i = sizeof($newsFeed);
		$maxFeedItems = XenForo_Application::get('options')->newsFeedMaxItems;

		while ($i++ < $maxFeedItems && list($id, $item) = each($cachedItems))
		{
			$newsFeed[$item['news_feed_id']] = $item;
		}

		return $newsFeed;
	}

	/**
	 * Runs prepareNewsFeedItem on an array of items
	 *
	 * @param array $items
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _prepareNewsFeedItems(array $items, array $viewingUser)
	{
		foreach ($items AS $id => $item)
		{
			$items[$id] = $this->_prepareNewsFeedItem($item, $item['news_feed_handler_class'], $viewingUser);
		}

		return $items;
	}

	/**
	 * Wraps around the prepareX functions in the handler class for each content type.
	 * Also does basic setup such as fetching user avatars.
	 *
	 * @param array $newsFeedItem
	 * @param string $handlerClassName
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return mixed
	 */
	protected function _prepareNewsFeedItem(array $item, $handlerClassName, array $viewingUser)
	{
		$item['user'] = array(
			'user_id' => $item['user_id'],
			'username' => $item['username'],
		);

		return $this->_getNewsFeedHandlerFromCache($handlerClassName)->prepareNewsFeedItem($item, $viewingUser);
	}

	/**
	 * Saves a user's news feed into their cache
	 *
	 * @param integer $userId
	 * @param array $newsFeed
	 *
	 * @return integer news_feed_id of latest news feed item in the cache
	 */
	protected function _saveCache($userId, array $newsFeed)
	{
		$latestNewsFeedId = $this->getNewestNewsFeedIdFromArray($newsFeed);

		if (XenForo_Application::get('options')->newsFeedCache)
		{
			$this->_getDb()->query('

				INSERT INTO xf_user_news_feed_cache
					(user_id, news_feed_cache, news_feed_cache_date)
				VALUES
					(?, ?, ?)
				ON DUPLICATE KEY UPDATE
					news_feed_cache = VALUES(news_feed_cache),
					news_feed_cache_date = VALUES(news_feed_cache_date)

			', array($userId, serialize($newsFeed), XenForo_Application::$time
			));
		}

		return $latestNewsFeedId;
	}

	/**
	 * Fetches a user's cached news feed
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getNewsFeedCache($userId)
	{
		$options = XenForo_Application::get('options');

		if ($options->newsFeedCache)
		{
			$newsFeed = $this->_getDb()->fetchOne('

				SELECT news_feed_cache
				FROM xf_user_news_feed_cache
				WHERE user_id = ?

			', $userId);

			if ($newsFeed = unserialize($newsFeed))
			{
				if (sizeof($newsFeed) > $options->newsFeedMaxItems)
				{
					return array_slice($newsFeed, 0, $options->newsFeedMaxItems, true);
				}

				return $newsFeed;
			}
		}

		return array();
	}

	/**
	 * Clears a user's cache, forcing a clean fetch on the next news feed load
	 *
	 * @param integer $userId
	 */
	public function deleteNewsFeedCache($userId)
	{
		$db = $this->_getDb();

		$db->delete('xf_user_news_feed_cache', 'user_id = ' . $db->quote($userId));
	}

	/**
	 * Publish a news feed item
	 *
	 * @param integer $userId
	 * @param string $username
	 * @param string $contentType
	 * @param integer $contentId
	 * @param string $action
	 * @param array $extraData
	 */
	public function publish($userId, $username, $contentType, $contentId, $action, array $extraData = null)
	{
		if (!$userId)
		{
			return;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_NewsFeed');

		$dw->set('user_id', $userId);
		$dw->set('username', $username);
		$dw->set('content_type', $contentType);
		$dw->set('content_id', $contentId);
		$dw->set('action', $action);
		$dw->set('extra_data', $extraData);

		$dw->save();
	}

	/**
	 * Permanently delete an item or items from the news feed
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param integer $userId (optional)
	 * @param string $action (optional)
	 */
	public function delete($contentType, $contentId, $userId = null, $action = null)
	{
		$db = $this->_getDb();

		$deleteCondition = 'content_type = ' . $db->quote($contentType) . ' AND content_id = ' . $db->quote($contentId);

		if (isset($userId))
		{
			$deleteCondition .= ' AND user_id = ' . $db->quote($userId);

			if (isset($action))
			{
				$deleteCondition .= ' AND action = ' . $db->quote($action);
			}
		}

		return $db->delete('xf_news_feed', $deleteCondition);
	}

	/**
	 * Deletes old news feed items. This does not trigger a cache rebuild, so a user could keep old records around
	 * until they're pushed off.
	 *
	 * @param integer|null $dateCut Uses default setting if null
	 */
	public function deleteOldNewsFeedItems($dateCut = null)
	{
		if ($dateCut === null)
		{
			$expiryTime = 7 * 86400; // TODO: hard coded to 7 days
			$dateCut = XenForo_Application::$time - $expiryTime;
		}

		$db = $this->_getDb();
		$db->delete('xf_news_feed', 'event_date < '. $db->quote($dateCut));
	}

	/**
	 * Fetches an instance of the specified news feed handler class
	 *
	 * @param string $class
	 *
	 * @return XenForo_NewsFeedHandler_Abstract
	 */
	protected function _getNewsFeedHandlerFromCache($class)
	{
		if (!isset($this->_handlerCache[$class]))
		{
			$this->_handlerCache[$class] = XenForo_NewsFeedHandler_Abstract::create($class);
		}

		return $this->_handlerCache[$class];
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
}