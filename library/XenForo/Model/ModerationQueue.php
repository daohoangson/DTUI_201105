<?php

/**
 * Model for the moderation queue.
 *
 * @package XenForo_Moderation
 */
class XenForo_Model_ModerationQueue extends XenForo_Model
{
	/**
	 * Gets all moderation queue entries, oldest first.
	 *
	 * @return array [] => info
	 */
	public function getModerationQueueEntries()
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_moderation_queue
			ORDER BY content_date
		');
	}

	/**
	 * Counts all moderation queue entries.
	 *
	 * @return integer
	 */
	public function countModerationQueueEntries()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_moderation_queue
		');
	}

	/**
	 * Inserts a new entry into the moderation queue.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param integer $contentDate
	 */
	public function insertIntoModerationQueue($contentType, $contentId, $contentDate)
	{
		$this->_getDb()->query('
			INSERT IGNORE INTO xf_moderation_queue
				(content_type, content_id, content_date)
			VALUES
				(?, ?, ?)
		', array($contentType, $contentId, $contentDate));

		$this->rebuildModerationQueueCountCache();
	}

	/**
	 * Deletes one or more entries from the moderation queue.
	 *
	 * @param string $contentType
	 * @param array|int $contentIds A single ID or an array
	 */
	public function deleteFromModerationQueue($contentType, $contentIds)
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
		$db->delete('xf_moderation_queue',
			'content_type = ' . $db->quote($contentType) . ' AND content_id IN (' . $db->quote($contentIds) . ')'
		);

		$this->rebuildModerationQueueCountCache();
	}

	/**
	 * Rebuilds the moderation queue count cache.
	 *
	 * @return array Cache, [total, lastModifiedDate]
	 */
	public function rebuildModerationQueueCountCache()
	{
		$cache = array(
			'total' => $this->countModerationQueueEntries(),
			'lastModifiedDate' => XenForo_Application::$time
		);

		$this->_getDataRegistryModel()->set('moderationCounts', $cache);

		return $cache;
	}

	/**
	 * Gets an accurate moderation queue count for the specified user. Takes into account permissions.
	 *
	 * @param array|null $viewingUser
	 *
	 * @return integer
	 */
	public function getModerationQueueCountForUser(array $viewingUser = null)
	{
		$entries = $this->getVisibleModerationQueueEntriesForUser($this->getModerationQueueEntries(), $viewingUser);
		return count($entries);
	}

	/**
	 * Filters out the moderation queue entries a user cannot see from a list.
	 *
	 * @param array $queue List of queue entries
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return array Visible entries; [] => info
	 */
	public function getVisibleModerationQueueEntriesForUser(array $queue, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		if (!$viewingUser['user_id'])
		{
			return array();
		}

		$grouped = array();
		foreach ($queue AS $entry)
		{
			$grouped[$entry['content_type']][$entry['content_id']] = $entry['content_id'];
		}

		if (!$grouped)
		{
			return array();
		}

		$handlers = $this->getModerationQueueHandlers();

		foreach ($grouped AS $contentType => &$typeQueue)
		{
			if (!empty($handlers[$contentType]))
			{
				$handler = $handlers[$contentType];
				$typeQueue = $handler->getVisibleModerationQueueEntriesForUser($typeQueue, $viewingUser);
			}
			else
			{
				unset($grouped[$contentType]);
			}
		}

		$output = array();
		foreach ($queue AS $entry)
		{
			if (isset($grouped[$entry['content_type']][$entry['content_id']]))
			{
				$entry['content'] = $grouped[$entry['content_type']][$entry['content_id']];
				$output[] = $entry;
			}
		}

		return $output;
	}

	/**
	 * Saves changes to the moderation queue. Takes into account the specified user's permissions.
	 *
	 * @param array $changes Format: [type][id] => [action (approve/delete), message, title]
	 * @param array|null $viewingUser
	 */
	public function saveModerationQueueChanges(array $changes, array $viewingUser = null)
	{
		$entries = $this->getVisibleModerationQueueEntriesForUser($this->getModerationQueueEntries(), $viewingUser);
		$handlers = $this->getModerationQueueHandlers();

		foreach ($entries AS $entry)
		{
			if (!isset($changes[$entry['content_type']][$entry['content_id']]))
			{
				continue;
			}

			$change = $changes[$entry['content_type']][$entry['content_id']];
			if (!is_array($change) || empty($change['action']))
			{
				continue;
			}

			$handler = $handlers[$entry['content_type']];

			$message = (isset($change['message']) ? $change['message'] : '');
			$title = (isset($change['title']) ? $change['title'] : '');

			if ($change['action'] == 'approve')
			{
				$handler->approveModerationQueueEntry($entry['content_id'], $message, $title);
			}
			else if ($change['action'] == 'delete')
			{
				$handler->deleteModerationQueueEntry($entry['content_id']);
			}
		}
	}

	/**
	 * Gets all moderation queue handler classes.
	 *
	 * @return array [content type] => XenForo_ModerationQueueHandler_Abstract
	 */
	public function getModerationQueueHandlers()
	{
		$classes = $this->getContentTypesWithField('moderation_queue_handler_class');
		$handlers = array();
		foreach ($classes AS $contentType => $class)
		{
			$handlers[$contentType] = new $class();
		}

		return $handlers;
	}
}