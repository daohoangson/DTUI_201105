<?php

/**
 * Model for thread redirect behaviors.
 *
 * @package XenForo_Thread
 */
class XenForo_Model_ThreadRedirect extends XenForo_Model
{
	/**
	 * Gets thread redirect information by the thread ID.
	 *
	 * @param integer $threadId
	 *
	 * @return array|false
	 */
	public function getThreadRedirectById($threadId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_thread_redirect
			WHERE thread_id = ?
		', $threadId);
	}

	/**
	 * Gets thread redirects that have been expired.
	 *
	 * @param integer $expiredDate Last expiration date
	 *
	 * @return array [thread id] => info
	 */
	public function getExpiredThreadRedirects($expiredDate)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_thread_redirect
			WHERE expiry_date > 0 AND expiry_date < ?
		', 'thread_id', $expiredDate);
	}

	/**
	 * Gets all thread redirects that share a particular key (or key pattern). Usually, this will
	 * be no more than one, but that is not guaranteed.
	 *
	 * @param string $redirectKey
	 * @param boolean $likeMatch If true, finds keys that match a LIKE pattern (% for wildcard, _ for single character)
	 *
	 * @return array [thread id] => info
	 */
	public function getThreadRedirectsByKey($redirectKey, $likeMatch = false)
	{
		if ($likeMatch)
		{
			return $this->fetchAllKeyed('
				SELECT *
				FROM xf_thread_redirect
				WHERE redirect_key LIKE ' . $this->_getDb()->quote($redirectKey) . '
			', 'thread_id');
		}
		else
		{
			return $this->fetchAllKeyed('
				SELECT *
				FROM xf_thread_redirect
				WHERE redirect_key = ?
			', 'thread_id', $redirectKey);
		}
	}

	/**
	 * Creates a thread that redirects to the target URL.
	 *
	 * @param string $targetUrl Target URL. May be relative or absolute.
	 * @param array $newThread Information about a thread. State must be visible or not specified
	 * @param string $redirectKey A unique key for relating this redirect to something else.
	 * 		For example, when redirecting to another thread, this is used to relate the redirect record
	 * 		to the thread it points to. If that thread is moved, we may need to remove the redirect.
	 * @param integer $expiryDate Timestamp for expiry. 0 means never expires
	 *
	 * @return false|integer If successful, the ID of the redirect's thread
	 */
	public function createRedirectThread($targetUrl, array $newThread, $redirectKey = '', $expiryDate = 0)
	{
		unset($newThread['thread_id']);

		if (empty($newThread['discussion_state']))
		{
			$newThread['discussion_state'] = 'visible';
		}
		else if ($newThread['discussion_state'] != 'visible')
		{
			return false;
		}

		$newThread['discussion_type'] = 'redirect';
		$newThread['first_post_id'] = 0; // remove any potential preview

		XenForo_Db::beginTransaction();

		$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
		$threadDw->setOption(XenForo_DataWriter_Discussion::OPTION_REQUIRE_INSERT_FIRST_MESSAGE, false);
		$threadDw->bulkSet($newThread, array('ignoreInvalidFields' => true));
		if (!$threadDw->save())
		{
			XenForo_Db::rollback();
			return false;
		}

		$newThreadId = $threadDw->get('thread_id');

		$this->insertThreadRedirect($newThreadId, $targetUrl, $redirectKey, $expiryDate);

		XenForo_Db::commit();

		return $newThreadId;
	}

	/**
	 * Inserts a raw thread redirect record.
	 *
	 * @param integer $threadId ID of redirect thread
	 * @param string $targetUrl Target URL. May be relative or absolute.
	 * @param string $redirectKey A unique key for relating this redirect to something else.
	 * 		For example, when redirecting to another thread, this is used to relate the redirect record
	 * 		to the thread it points to. If that thread is moved, we may need to remove the redirect.
	 * @param integer $expiryDate Timestamp for expiry. 0 means never expires
	 */
	public function insertThreadRedirect($threadId, $targetUrl, $redirectKey = '', $expiryDate = 0)
	{
		$this->_getDb()->insert('xf_thread_redirect', array(
			'thread_id' => $threadId,
			'target_url' => $targetUrl,
			'redirect_key' => $redirectKey,
			'expiry_date' => $expiryDate
		));
	}

	/**
	 * Deletes the specified thread redirects.
	 *
	 * @param array $threadIds
	 */
	public function deleteThreadRedirects(array $threadIds)
	{
		if (!$threadIds)
		{
			return;
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$db->delete('xf_thread_redirect', 'thread_id IN (' . $db->quote($threadIds) . ')');

		foreach ($threadIds AS $threadId)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($threadId);
			$dw->delete();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Updates the specified thread redirect.
	 *
	 * @param integer $threadId
	 * @param array $update Fields to update
	 */
	public function updateThreadRedirect($threadId, array $update)
	{
		$db = $this->_getDb();
		$db->update('xf_thread_redirect', $update, 'thread_id = ' . $db->quote($threadId));
	}
}