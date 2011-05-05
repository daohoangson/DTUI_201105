<?php

/**
* Data writer for permissions.
*
* @package XenForo_Permissions
*/
class XenForo_DataWriter_Permission extends XenForo_DataWriter
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
	 * Option that represents whether the permission this permission depends on
	 * should be verified. This should be set to false when doing bulk imports,
	 * as the required permission may not exist. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_DEPENDENT_CHECK = 'dependentCheck';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_permission_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_permission' => array(
				'permission_id'        => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true,
						'verification' => array('$this', '_verifyPermissionId'), 'requiredError' => 'please_enter_valid_permission_id'
				),
				'permission_group_id'  => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true),
				'permission_type'      => array('type' => self::TYPE_STRING, 'required' => true,
						'allowedValues' => array('flag', 'integer')
				),
				'interface_group_id'   => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => ''),
				'depend_permission_id' => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
				'display_order'        => array('type' => self::TYPE_UINT,   'default' => 1),
				'default_value'        => array('type' => self::TYPE_STRING, 'default' => 'unset',
						'allowedValues' => array('unset', 'allow', 'deny')
				),
				'default_value_int'    => array('type' => self::TYPE_INT,    'default' => 0),
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
		if (!is_array($data))
		{
			return false;
		}
		else if (isset($data['permission_group_id'], $data['permission_id']))
		{
			$groupId = $data['permission_group_id'];
			$permissionId = $data['permission_id'];
		}
		else if (isset($data[0], $data[1]))
		{
			$groupId = $data[0];
			$permissionId = $data[1];
		}
		else
		{
			return false;
		}

		return array('xf_permission' => $this->_getPermissionModel()->getPermissionByGroupAndId($groupId, $permissionId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'permission_group_id = ' . $this->_db->quote($this->getExisting('permission_group_id'))
			. ' AND permission_id = ' . $this->_db->quote($this->getExisting('permission_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true,
			self::OPTION_DEPENDENT_CHECK => true
		);
	}

	/**
	 * Verifies that the permission ID is valid.
	 *
	 * @param string $permissionId
	 *
	 * @return boolean
	 */
	protected function _verifyPermissionId($permissionId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $permissionId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'permission_id');
			return false;
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isChanged('permission_id') || $this->isChanged('permission_group_id'))
		{
			$newPermission = $this->_getPermissionModel()->getPermissionByGroupAndId(
				$this->get('permission_group_id'), $this->get('permission_id')
			);
			if ($newPermission)
			{
				$this->error(new XenForo_Phrase('permission_ids_must_be_unique_within_groups'), 'permission_id');
			}
		}

		if ($this->get('depend_permission_id') && $this->getOption(self::OPTION_DEPENDENT_CHECK))
		{
			if ($this->isChanged('permission_group_id') || $this->isChanged('depend_permission_id'))
			{
				$dependPermission = $this->_getPermissionModel()->getPermissionByGroupAndId(
					$this->get('permission_group_id'), $this->get('depend_permission_id')
				);
				if (!$dependPermission)
				{
					$this->error(new XenForo_Phrase('please_enter_valid_dependent_permission_id'), 'depend_permission_id');
				}
			}
		}

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
		if ($this->isChanged('permission_id') || $this->isChanged('permission_group_id'))
		{
			if ($this->isUpdate())
			{
				$this->_renameTitlePhrase();
				$this->_renamePermissionEntries();
			}

			$this->_rebuildPermissionCache();
		}

		$this->_updateTitlePhrase();
	}

	/**
	 * Renames the permission title phrase.
	 */
	protected function _renameTitlePhrase()
	{
		$this->_renameMasterPhrase(
			$this->_getTitlePhraseName($this->getExisting('permission_group_id'), $this->getExisting('permission_id')),
			$this->_getTitlePhraseName($this->get('permission_group_id'), $this->get('permission_id'))
		);
	}

	/**
	 * Renames the permission entries that are associated with this permission definition.
	 */
	protected function _renamePermissionEntries()
	{
		$updateFields = array(
			'permission_group_id' => $this->get('permission_group_id'),
			'permission_id' => $this->get('permission_id'),
		);

		$condition = 'permission_group_id = ' . $this->_db->quote($this->getExisting('permission_group_id'))
			. ' AND permission_id = ' . $this->_db->quote($this->getExisting('permission_id'));

		$this->_db->update('xf_permission_entry', $updateFields, $condition);
		$this->_db->update('xf_permission_entry_content', $updateFields, $condition);
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
				$this->_getTitlePhraseName($this->get('permission_group_id'), $this->get('permission_id')),
				$titlePhrase, $this->get('addon_id')
			);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_deleteMasterPhrase(
			$this->_getTitlePhraseName($this->get('permission_group_id'), $this->get('permission_id'))
		);
		$this->_rebuildPermissionCache();
	}

	/**
	 * Rebuilds the permission cache if the option is enabled.
	 */
	protected function _rebuildPermissionCache()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getPermissionModel()->rebuildPermissionCache();
		}
	}

	/**
	 * Gets the name of the title phrase for this permission.
	 *
	 * @param string $permissionGroupId
	 * @param string $permissionId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($permissionGroupId, $permissionId)
	{
		return $this->_getPermissionModel()->getPermissionTitlePhraseName($permissionGroupId, $permissionId);
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