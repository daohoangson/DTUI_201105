<?php

/**
* Data writer for alerts.
*
* @package XenForo_Alert
*/
class XenForo_DataWriter_Alert extends XenForo_DataWriter
{
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		$visitor = XenForo_Visitor::getInstance();

		return array(
			'xf_user_alert' => array(
				'alert_id'
					=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'alerted_user_id'
					=> array('type' => self::TYPE_UINT, 'required' => true),
				'user_id'
					=> array('type' => self::TYPE_UINT, 'default' => $visitor['user_id']),
				'username'
					=> array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => $visitor['username']),
				'content_type'
					=> array('type' => self::TYPE_STRING, 'maxLength' => 25),
				'content_id'
					=> array('type' => self::TYPE_UINT),
				'action'
					=> array('type' => self::TYPE_STRING, 'maxLength' => 25),
				'event_date'
					=> array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'view_date'
					=> array('type' => self::TYPE_UINT),
				'extra_data'
					=> array('type' => self::TYPE_UNKNOWN,
						'verification' => array('$this', '_verifyExtraData'))
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
		if (!$alertId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_user_alert' => $this->_getAlertModel()->getAlertById($alertId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'alert_id = ' . $this->_db->quote($this->getExisting('alert_id'));
	}

	/**
	 * Verification method for extra data
	 *
	 * @param string $extraData
	 */
	protected function _verifyExtraData($extraData)
	{
		if ($extraData === null)
		{
			$extraData = '';
			return true;
		}

		return XenForo_DataWriter_Helper_Denormalization::verifySerialized($extraData, $this, 'extra_data');
	}

	/**
	 * Update notified user's total number of unread alerts
	 */
	protected function _postSave()
	{
		$this->_db->query('
			UPDATE xf_user SET
			alerts_unread = alerts_unread + 1
			WHERE user_id = ?
		', $this->get('alerted_user_id'));
	}

	/**
	 * Post-delete behaviors.
	 */
	protected function _postDelete()
	{
		if (!$this->get('view_date'))
		{
			$this->_db->query('
				UPDATE xf_user SET
					alerts_unread = IF(alerts_unread > 0, alerts_unread - 1, 0)
				WHERE user_id = ?
			', $this->get('alerted_user_id'));
		}
	}
}