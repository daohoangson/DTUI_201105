<?php

class XenForo_Model_DeletionLog extends XenForo_Model
{
	/**
	 * Gets the deletion log record for a specified piece of content.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return array|false
	 */
	public function getDeletionLog($contentType, $contentId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_deletion_log
			WHERE content_type = ?
				AND content_id = ?
		', array($contentType, $contentId));
	}

	/**
	 * Logs the deletion of a particular piece of content.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param string $reason
	 * @param array|mixed $deleteUser Array of info about user doing deleting, else the visitor
	 * @param integer|null $time Time of deletion, else now
	 */
	public function logDeletion($contentType, $contentId, $reason = '', $deleteUser = null, $time = null)
	{
		if (!$deleteUser)
		{
			$deleteUser = XenForo_Visitor::getInstance()->toArray();
		}
		if (!$time)
		{
			$time = XenForo_Application::$time;
		}

		$this->_getDb()->query('
			INSERT IGNORE INTO xf_deletion_log
				(content_type, content_id, delete_date, delete_user_id, delete_username, delete_reason)
			VALUES
				(?, ?, ?, ?, ?, ?)
		', array($contentType, $contentId, $time, $deleteUser['user_id'], $deleteUser['username'], $reason));
	}

	/**
	 * Removes the deletion log of a particular piece of content (such as on undeletion).
	 * May remove from multiple pieces of content at once
	 *
	 * @param string $contentType
	 * @param array|int $contentIds An array of content IDs or a single one
	 */
	public function removeDeletionLog($contentType, $contentIds)
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

		$db->delete('xf_deletion_log',
			'content_type = ' . $db->quote($contentType) . ' AND content_id IN (' . $db->quote($contentIds) . ')'
		);
	}
}