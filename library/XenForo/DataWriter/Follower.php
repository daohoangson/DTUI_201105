<?php
/**
* Data writer for Followers.
*
* @package XenForo_User
*/
class XenForo_DataWriter_Follower extends XenForo_DataWriter
{
	/**
	 * Option to rebuild the denormalized user_profile.following field for the following user
	 *
	 * @var string
	 */
	const OPTION_POST_WRITE_UPDATE_USER_FOLLOWING = 'updateUserFollowingAfterWrite';

	/**
	 * Returns all xf_user_follow fields
	 *
	 * @see library/XenForo/DataWriter/XenForo_DataWriter#_getFields()
	 */
	protected function _getFields()
	{
		return array('xf_user_follow' => array(
			'user_id'        => array('type' => self::TYPE_UINT, 'required' => true, 'verification' => array('XenForo_DataWriter_Helper_User', 'verifyUserid')),
			'follow_user_id' => array('type' => self::TYPE_UINT, 'required' => true, 'verification' => array('XenForo_DataWriter_Helper_User', 'verifyUserid')),
			'follow_date'    => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time)
		));
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
		else if (isset($data['user_id'], $data['follow_user_id']))
		{
			$userId = $data['user_id'];
			$followUserId = $data['follow_user_id'];
		}
		else if (isset($data[0], $data[1]))
		{
			$userId = $data[0];
			$followUserId = $data[1];
		}
		else
		{
			return false;
		}

		return array('xf_user_follow' => $this->_getUserModel()->getFollowRecord($userId, $followUserId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id')) .
			' AND follow_user_id = ' . $this->_db->quote($this->getExisting('follow_user_id'));
	}

	/**
	* Gets the default set of options for this data writer.
	*
	* @return array
	*/
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_POST_WRITE_UPDATE_USER_FOLLOWING => true
		);
	}

	protected function _preSave()
	{
		if ($this->get('user_id') == $this->get('follow_user_id'))
		{
			$this->error('Users may not follow themselves.', 'follow_user_id');
		}
	}

	/**
	* Post-save handler.
	*/
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_POST_WRITE_UPDATE_USER_FOLLOWING))
		{
			$this->_getUserModel()->updateFollowingDenormalizedValue($this->get('user_id'));
		}
	}

	/**
	 * Post-delete handler
	 */
	protected function _postDelete()
	{
		if ($this->getOption(self::OPTION_POST_WRITE_UPDATE_USER_FOLLOWING))
		{
			$this->_getUserModel()->updateFollowingDenormalizedValue($this->get('user_id'));
		}

		$db = $this->_db;

		$db->delete('xf_user_alert',
			'alerted_user_id = ' . $db->quote($this->get('follow_user_id'))
			. ' AND user_id = ' . $db->quote($this->get('user_id'))
			. ' AND content_type = \'user\' AND action = \'following\''
		);
	}
}