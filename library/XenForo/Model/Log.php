<?php

class XenForo_Model_Log extends XenForo_Model
{
	public function getServerErrorLogById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT user.*, error_log.*
			FROM xf_error_log AS error_log
			LEFT JOIN xf_user AS user ON (user.user_id = error_log.user_id)
			WHERE error_log.error_id = ?
		', $id);
	}

	public function getServerErrorLogs(array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT *
				FROM xf_error_log
				ORDER BY exception_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'error_id');
	}

	public function countServerErrors()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_error_log
		');
	}

	public function deleteServerErrorLog($id)
	{
		$db = $this->_getDb();
		$db->delete('xf_error_log', 'error_id = ' . $db->quote($id));
	}
}