<?php
/**
* Data writer for Feeds.
*
* @package XenForo_Feed
*/
class XenForo_DataWriter_Feed extends XenForo_DataWriter
{
	/**
	 * Returns all xf_feed fields
	 *
	 * @see XenForo_DataWriter::_getFields()
	 */
	protected function _getFields()
	{
		return array('xf_feed' => array(
			'feed_id'
				=> array('type' => self::TYPE_UINT,   'autoIncrement' => true),
			'title'
				=> array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 250),
			'url'
				=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 2083,
					'verification' => array('XenForo_DataWriter_Helper_Uri', 'verifyUri')),
			'frequency'
				=> array('type' => self::TYPE_UINT,   'required' => true, 'allowedValues' => $this->_getFeedModel()->getFrequencyValues()),
			'node_id'
				=> array('type' => self::TYPE_UINT,   'required' => true, 'verification' => array('$this', '_verifyForum')),
			'user_id'
				=> array('type' => self::TYPE_UINT,   'default' => 0),
			'title_template'
				=> array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 250),
			'message_template'
				=> array('type' => self::TYPE_STRING, 'required' => true),
			'discussion_visible'
				=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			'discussion_open'
				=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			'discussion_sticky'
				=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
			'last_fetch'
				=> array('type' => self::TYPE_UINT,    'default' => 0),
			'active'
				=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
		));
	}

	/**
	 * @see XenForo_DataWriter::_getExistingData()
	 */
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_feed' => $this->_getFeedModel()->getFeedById($id));
	}

	/**
	 * @see XenForo_DataWriter::_getUpdateCondition()
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'feed_id = ' . $this->_db->quote($this->getExisting('feed_id'));
	}

	protected function _verifyForum($nodeId)
	{
		$forum = $this->getModelFromCache('XenForo_Model_Forum')->getForumById($nodeId);
		if (!$forum)
		{
			$this->error(new XenForo_Phrase('please_select_valid_forum'), 'node_id');
			return false;
		}

		return true;
	}

	/**
	 * Fill in the title field if it's not been set
	 *
	 * @see XenForo_DataWriter::_preSave()
	 */
	protected function _preSave()
	{
		if ($this->get('url')
			&& (!$this->get('title')
				|| ($this->isChanged('url') && !$this->isChanged('title'))
			)
		)
		{
			$feed = $this->_getFeedModel()->getFeedData($this->get('url'));
			if ($feed)
			{
				$this->set('title', $feed['title']);
			}
			else
			{
				$this->set('title', $this->get('url'));
			}
		}
	}

	protected function _postDelete()
	{
		$this->_db->delete('xf_feed_log', 'feed_id = ' . $this->_db->quote($this->get('feed_id')));
	}

	/**
	 * @return XenForo_Model_Feed
	 */
	protected function _getFeedModel()
	{
		return $this->getModelFromCache('XenForo_Model_Feed');
	}
}