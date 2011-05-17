<?php

/**
* Data writer for admins.
*
* @package XenForo_Admin
*/
class XenForo_DataWriter_Admin extends XenForo_DataWriter
{
	/**
	 * Option that controls whether the visitor's admin record can be deleted.
	 * This defaults to false.
	 *
	 * @var string
	 */
	const OPTION_ALLOW_DELETE_SELF = 'allowDeleteSelf';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_admin_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_admin' => array(
				'user_id'              => array('type' => self::TYPE_UINT, 'required' => true),
				'extra_user_group_ids' => array('type' => self::TYPE_UNKNOWN, 'default' => '',
						'verification' => array('$this', '_verifyExtraUserGroupIds')
				),
				'last_login'           => array('type' => self::TYPE_UINT, 'default' => 0),
				'permission_cache'     => array('type' => self::TYPE_BINARY, 'default' => '')
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

		return array('xf_admin' => $this->_getAdminModel()->getAdminById($id));
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
	 * Gets the default options.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_ALLOW_DELETE_SELF => false
		);
	}

	/**
	 * Verifies the extra user group IDs.
	 *
	 * @param array|string $userGroupIds Array or comma-delimited list
	 *
	 * @return boolean
	 */
	protected function _verifyExtraUserGroupIds(&$userGroupIds)
	{
		if (!is_array($userGroupIds))
		{
			$userGroupIds = preg_split('#,\s*#', $userGroupIds);
		}

		$userGroupIds = array_map('intval', $userGroupIds);
		$userGroupIds = array_unique($userGroupIds);
		sort($userGroupIds, SORT_NUMERIC);
		$userGroupIds = implode(',', $userGroupIds);

		return true;
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isInsert())
		{
			$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$userDw->setExistingData($this->get('user_id'));
			$userDw->set('is_admin', 1);
			$userDw->save();
		}

		if ($this->isChanged('extra_user_group_ids'))
		{
			$this->getModelFromCache('XenForo_Model_User')->addUserGroupChange(
				$this->get('user_id'), 'admin', $this->get('extra_user_group_ids')
			);
		}
	}

	/**
	 * Pre-delete handling.
	 */
	protected function _preDelete()
	{
		$admins = $this->_getAdminModel()->getAllAdmins();
		if (count($admins) < 2)
		{
			$this->error(new XenForo_Phrase('last_administrator_cannot_be_deleted'));
		}

		if (!$this->getOption(self::OPTION_ALLOW_DELETE_SELF))
		{
			if ($this->get('user_id') == XenForo_Visitor::getUserId())
			{
				$this->error(new XenForo_Phrase('you_cannot_delete_your_own_administrator_record'));
			}
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$userDw->setExistingData($this->get('user_id'));
		$userDw->set('is_admin', 0);
		$userDw->save();

		$this->getModelFromCache('XenForo_Model_User')->removeUserGroupChange(
			$this->get('user_id'), 'admin'
		);
	}

	/**
	 * @return XenForo_Model_Admin
	 */
	protected function _getAdminModel()
	{
		return $this->getModelFromCache('XenForo_Model_Admin');
	}
}