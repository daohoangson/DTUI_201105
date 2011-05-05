<?php

/**
* Data writer for permission groups.
*
* @package XenForo_Permissions
*/
class XenForo_DataWriter_PermissionGroup extends XenForo_DataWriter
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
	 * Option that represents whether the option cache will be automatically
	 * rebuilt. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_REBUILD_CACHE = 'rebuildCache';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_permission_group_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_permission_group' => array(
				'permission_group_id'  => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true,
						'verification' => array('$this', '_verifyPermissionGroupId'), 'requiredError' => 'please_enter_valid_permission_group_id'
				),
				'addon_id'             => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => '')
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'permission_group_id'))
		{
			return false;
		}

		return array('xf_permission_group' => $this->_getPermissionModel()->getPermissionGroupById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'permission_group_id = ' . $this->_db->quote($this->getExisting('permission_group_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true,
		);
	}

	/**
	 * Verifies that the permission group ID is valid.
	 *
	 * @param string $groupId
	 *
	 * @return boolean
	 */
	protected function _verifyPermissionGroupId($groupId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $groupId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'permission_group_id');
			return false;
		}

		if ($this->isInsert() || $groupId != $this->getExisting('permission_group_id'))
		{
			$newGroup = $this->_getPermissionModel()->getPermissionGroupById($groupId);
			if ($newGroup)
			{
				$this->error(new XenForo_Phrase('permission_group_ids_must_be_unique', array('groupId' => $groupId)), 'permission_group_id');
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
		$db = $this->_db;
		$permissionGroupId = $this->get('permission_group_id');

		if ($this->isChanged('permission_group_id'))
		{
			if ($this->isUpdate())
			{
				$updateFields = array('permission_group_id' => $permissionGroupId);
				$groupCondition = 'permission_group_id = ' . $db->quote($this->getExisting('permission_group_id'));

				$db->update('xf_permission', $updateFields, $groupCondition);
				$db->update('xf_permission_entry', $updateFields, $groupCondition);
				$db->update('xf_permission_entry_content', $updateFields, $groupCondition);
				$db->update('xf_permission_cache_global_group', $updateFields, $groupCondition);

				$this->_renameTitlePhrase();
			}

			if ($this->getOption(self::OPTION_REBUILD_CACHE))
			{
				$this->_getPermissionModel()->rebuildPermissionCache();
			}
		}

		$this->_updateTitlePhrase();
	}

	/**
	 * Renames the permission title phrase.
	 */
	protected function _renameTitlePhrase()
	{
		$this->_renameMasterPhrase(
			$this->_getTitlePhraseName($this->getExisting('permission_group_id')),
			$this->_getTitlePhraseName($this->get('permission_group_id'))
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
				$this->_getTitlePhraseName($this->get('permission_group_id')),
				$titlePhrase, $this->get('addon_id')
			);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$groupId = $this->get('permission_group_id');

		$db = $this->_db;
		$groupCondition = 'permission_group_id = ' . $db->quote($groupId);

		$permissions = $this->_getPermissionModel()->getAllPermissionsGrouped();

		$db->delete('xf_permission', $groupCondition);
		$db->delete('xf_permission_entry', $groupCondition);
		$db->delete('xf_permission_entry_content', $groupCondition);
		$db->delete('xf_permission_cache_global_group', $groupCondition);

		$this->_deleteMasterPhrase(
			$this->_getTitlePhraseName($groupId)
		);

		if (!empty($permissions[$groupId]))
		{
			$permissionIds = array_keys($permissions[$groupId]);
			foreach ($permissionIds AS $permissionId)
			{
				$this->_deleteMasterPhrase(
					$this->_getPermissionModel()->getPermissionTitlePhraseName($groupId, $permissionId)
				);
			}
		}

		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getPermissionModel()->rebuildPermissionCache();
		}
	}

	/**
	 * Gets the name of the title phrase for this permission group.
	 *
	 * @param string $permissionGroupId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($permissionGroupId)
	{
		return $this->_getPermissionModel()->getPermissionGroupTitlePhraseName($permissionGroupId);
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