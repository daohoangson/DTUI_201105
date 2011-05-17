<?php

/**
* Data writer for banned users.
*
* @package XenForo_Banning
*/
class XenForo_DataWriter_UserBan extends XenForo_DataWriter
{
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_user_ban' => array(
				'user_id'           => array('type' => self::TYPE_UINT, 'required' => true),
				'ban_user_id'       => array('type' => self::TYPE_UINT, 'required' => true),
				'ban_date'          => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'end_date'          => array('type' => self::TYPE_UINT, 'required' => true),
				'user_reason'       => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 255)
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'user_id'))
		{
			return false;
		}

		return array('xf_user_ban' => $this->_getBanningModel()->getBannedUserById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id'));
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isChanged('user_id'))
		{
			$userBan = $this->_getBanningModel()->getBannedUserById($this->get('user_id'));
			if ($userBan)
			{
				$this->error(new XenForo_Phrase('this_user_is_already_banned'), 'user_id');
			}
			else
			{
				$user = $this->getModelFromCache('XenForo_Model_User')->getUserById($this->get('user_id'));
				if (!$user || $user['is_moderator'] || $user['is_admin'])
				{
					$this->error(new XenForo_Phrase('this_user_is_an_admin_or_moderator_choose_another'), 'user_id');
				}
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('user_id'))
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$dw->setExistingData($this->getExisting('user_id'));
			$dw->set('is_banned', 0);
			$dw->save();
		}

		if ($this->isChanged('user_id'))
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$dw->setExistingData($this->get('user_id'));
			$dw->set('is_banned', 1);
			$dw->save();
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setExistingData($this->get('user_id'));
		$dw->set('is_banned', 0);
		$dw->save();
	}

	/**
	 * @return XenForo_Model_Banning
	 */
	protected function _getBanningModel()
	{
		return $this->getModelFromCache('XenForo_Model_Banning');
	}
}