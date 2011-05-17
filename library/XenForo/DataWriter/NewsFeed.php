<?php

/**
* Data writer for news feed items.
*
* @package XenForo_NewsFeed
*/
class XenForo_DataWriter_NewsFeed extends XenForo_DataWriter
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
			'xf_news_feed' => array(
				'news_feed_id'
					=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
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
		if (!$newsFeedId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_news_feed' => $this->_getNewsFeedModel()->getNewsFeedById($newsFeedId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'news_feed_id = ' . $this->_db->quote($this->getExisting('news_feed_id'));
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
}