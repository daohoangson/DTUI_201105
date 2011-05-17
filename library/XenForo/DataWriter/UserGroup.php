<?php

/**
* Data writer for user groups.
*
* @package XenForo_UserGroups
*/
class XenForo_DataWriter_UserGroup extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_user_group_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_user_group' => array(
				'user_group_id'          => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'title'                  => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'required' => true,
						'requiredError' => 'please_enter_valid_title'
				),
				'display_style_priority' => array('type' => self::TYPE_UINT,   'default' => 0),
				'username_css'           => array('type' => self::TYPE_STRING, 'default' => ''),
				'user_title'             => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 100),
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
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_user_group' => $this->_getUserGroupModel()->getUserGroupById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'user_group_id = ' . $this->_db->quote($this->getExisting('user_group_id'));
	}

	protected function _postSave()
	{
		$userGroupId = $this->get('user_group_id');
		$userGroupModel = $this->_getUserGroupModel();

		if ($this->isUpdate() && $this->isChanged('display_style_priority'))
		{
			$userGroupModel->recalculateUserGroupDisplayStylePriority(
				$userGroupId, $this->getExisting('display_style_priority'), $this->get('display_style_priority')
			);
		}

		if ($this->isChanged('username_css') || $this->isChanged('user_title'))
		{
			$userGroupModel->rebuildDisplayStyleCache();
		}
	}

	protected function _preDelete()
	{
		switch ($this->get('user_group_id'))
		{
			case XenForo_Model_User::$defaultGuestGroupId:
			case XenForo_Model_User::$defaultRegisteredGroupId:
			case XenForo_Model_User::$defaultAdminGroupId:
			case XenForo_Model_User::$defaultModeratorGroupId:
				$this->error(new XenForo_Phrase('you_may_not_delete_important_default_user_groups'));
		}
	}

	protected function _postDelete()
	{
		$userGroupId = $this->get('user_group_id');
		$userGroupModel = $this->_getUserGroupModel();

		$this->_db->delete('xf_permission_entry', 'user_group_id = ' . $this->_db->quote($userGroupId));
		$this->_db->delete('xf_permission_entry_content', 'user_group_id = ' . $this->_db->quote($userGroupId));

		$userGroupModel->deletePermissionCombinationsForUserGroup($userGroupId);

		$userGroupModel->removeUserGroupFromUsers($userGroupId, XenForo_Model_User::$defaultRegisteredGroupId);

		$userGroupModel->recalculateUserGroupDisplayStylePriority(
			$userGroupId, $this->get('display_style_priority'), -1
		);
		$userGroupModel->rebuildDisplayStyleCache();
	}

	/**
	 * Gets the user group model object.
	 *
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}
}