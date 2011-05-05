<?php

/**
* Data writer for admin permissions.
*
* @package XenForo_Admin
*/
class XenForo_DataWriter_AdminPermission extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this item.
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
	protected $_existingDataErrorPhrase = 'requested_permission_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_admin_permission' => array(
				'admin_permission_id' => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true,
						'verification' => array('$this', '_verifyPermissionId'),
						'requiredError' => 'please_enter_valid_permission_id'
				),
				'display_order'      => array('type' => self::TYPE_UINT, 'default' => 0),
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'admin_permission_id'))
		{
			return false;
		}

		return array('xf_admin_permission' => $this->_getAdminModel()->getAdminPermissionById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'admin_permission_id = ' . $this->_db->quote($this->getExisting('admin_permission_id'));
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
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'admin_permission_id');
			return false;
		}

		if ($this->isInsert() || $permissionId != $this->getExisting('admin_permission_id'))
		{
			if ($this->_getAdminModel()->getAdminPermissionById($permissionId))
			{
				$this->error(new XenForo_Phrase('admin_permission_ids_must_be_unique'), 'admin_permission_id');
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
		$adminPermissionId = $this->get('admin_permission_id');

		if ($this->isChanged('admin_permission_id') && $this->isUpdate())
		{
			$this->_renameMasterPhrase(
				$this->_getTitlePhraseName($this->getExisting('admin_permission_id')),
				$this->_getTitlePhraseName($adminPermissionId)
			);

			$db = $this->_db;
			$db->update('xf_admin_permission_entry', array(
				'admin_permission_id' => $adminPermissionId
			),  'admin_permission_id = ' . $db->quote($this->getExisting('admin_permission_id')));

			$this->_rebuildAdminPermissionCache();
		}

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($adminPermissionId),
				$titlePhrase, $this->get('addon_id')
			);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$adminPermissionId = $this->get('admin_permission_id');

		$this->_deleteMasterPhrase(
			$this->_getTitlePhraseName($adminPermissionId)
		);

		$this->_db->delete('xf_admin_permission_entry',
			'admin_permission_id = ' . $this->_db->quote($adminPermissionId)
		);
		$this->_rebuildAdminPermissionCache();
	}

	/**
	 * Gets the name of the title phrase for this permission.
	 *
	 * @param string $permissionId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($permissionId)
	{
		return $this->_getAdminModel()->getAdminPermissionTitlePhraseName($permissionId);
	}

	/**
	 * Rebuilds the admin permission cach for all users.
	 * @return unknown_type
	 */
	protected function _rebuildAdminPermissionCache()
	{
		$this->_getAdminModel()->rebuildUserAdminPermissionCache();
	}

	/**
	 * @return XenForo_Model_Admin
	 */
	protected function _getAdminModel()
	{
		return $this->getModelFromCache('XenForo_Model_Admin');
	}
}