<?php

/**
* Data writer for permission interface groups.
*
* @package XenForo_Permissions
*/
class XenForo_DataWriter_PermissionInterfaceGroup extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this link.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_permission_interface_group_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_permission_interface_group' => array(
				'interface_group_id' => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'required' => true,
						'verification' => array('$this', '_verifyInterfaceGroupId'), 'requiredError' => 'please_enter_valid_permission_interface_group_id'
				),
				'display_order'      => array('type' => self::TYPE_UINT,   'default' => 1),
				'addon_id'           => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => '')
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'interface_group_id'))
		{
			return false;
		}

		return array('xf_permission_interface_group' => $this->_getPermissionModel()->getPermissionInterfaceGroupById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'interface_group_id = ' . $this->_db->quote($this->getExisting('interface_group_id'));
	}

	/**
	 * Verifies that the interface group ID is valid.
	 *
	 * @param string $groupId
	 *
	 * @return boolean
	 */
	protected function _verifyInterfaceGroupId($groupId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $groupId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'interface_group_id');
			return false;
		}

		if ($this->isInsert() || $groupId != $this->getExisting('interface_group_id'))
		{
			$newGroup = $this->_getPermissionModel()->getPermissionInterfaceGroupById($groupId);
			if ($newGroup)
			{
				$this->error(new XenForo_Phrase('permission_interface_group_ids_must_be_unique'), 'interface_group_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('interface_group_id'))
		{
			$this->_renameTitlePhrase();

			$this->_db->update('xf_permission',
				array('interface_group_id' => $this->get('interface_group_id')),
				'interface_group_id = ' . $this->_db->quote($this->getExisting('interface_group_id'))
			);
		}

		$this->_updateTitlePhrase();
	}

	/**
	 * Renames the permission title phrase.
	 */
	protected function _renameTitlePhrase()
	{
		$this->_renameMasterPhrase(
			$this->_getTitlePhraseName($this->getExisting('interface_group_id')),
			$this->_getTitlePhraseName($this->get('interface_group_id'))
		);
	}

	/**
	 * Updates the value of the title phrase, if necessary.
	 */
	protected function _updateTitlePhrase()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($this->get('interface_group_id')), $titlePhrase, $this->get('addon_id')
			);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_deleteMasterPhrase(
			$this->_getTitlePhraseName($this->get('interface_group_id'))
		);
	}

	/**
	 * Gets the name of the title phrase for this permission group.
	 *
	 * @param string $interfaceGroupId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($interfaceGroupId)
	{
		return $this->_getPermissionModel()->getPermissionInterfaceGroupTitlePhraseName($interfaceGroupId);
	}

	/**
	 * Gets the permission model object.
	 *
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}
}