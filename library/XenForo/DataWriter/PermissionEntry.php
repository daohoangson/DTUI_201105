<?php

/**
* Data writer for permission entries.
*
* @package XenForo_Permissions
*/
class XenForo_DataWriter_PermissionEntry extends XenForo_DataWriter
{
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_permission_entry' => array(
				'permission_entry_id'  => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'user_group_id'        => array('type' => self::TYPE_UINT, 'default' => 0),
				'user_id'              => array('type' => self::TYPE_UINT, 'default' => 0),
				'permission_group_id'  => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true),
				'permission_id'        => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true),
				'permission_value'     => array('type' => self::TYPE_STRING, 'required' => true,
						'allowedValues' => array('unset', 'allow', 'deny', 'use_int')
				),
				'permission_value_int' => array('type' => self::TYPE_INT)
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

		return array('xf_permission_entry' => $this->_getPermissionModel()->getPermissionEntryById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'permission_entry_id = ' . $this->_db->quote($this->getExisting('permission_entry_id'));
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isChanged('permission_value') && $this->get('permission_value') != 'use_int')
		{
			$this->set('permission_value_int', 0);
		}

		if ($this->get('user_id') && $this->get('user_group_id'))
		{
			// would only happen with buggy code or modified form
			$this->error('Please select either one user or one group for this permission, not both.');
		}
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