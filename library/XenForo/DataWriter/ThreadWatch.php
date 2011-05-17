<?php

/**
* Data writer for thread watch records
*
* @package XenForo_Thread
*/
class XenForo_DataWriter_ThreadWatch extends XenForo_DataWriter
{
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_thread_watch' => array(
				'user_id'          => array('type' => self::TYPE_UINT,    'required' => true),
				'thread_id'        => array('type' => self::TYPE_UINT,    'required' => true),
				'email_subscribe'  => array('type' => self::TYPE_BOOLEAN, 'default' => 0)
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!is_array($data))
		{
			return false;
		}
		else if (isset($data['user_id'], $data['thread_id']))
		{
			$userId = $data['user_id'];
			$threadId = $data['thread_id'];
		}
		else if (isset($data[0], $data[1]))
		{
			$userId = $data[0];
			$threadId = $data[1];
		}
		else
		{
			return false;
		}

		return array('xf_thread_watch' => $this->_getThreadWatchModel()->getUserThreadWatchByThreadId($userId, $threadId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id'))
			. ' AND thread_id = ' . $this->_db->quote($this->getExisting('thread_id'));
	}

	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}
}