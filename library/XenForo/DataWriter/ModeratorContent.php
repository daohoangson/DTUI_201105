<?php

/**
* Data writer for moderators (content records)
*
* @package XenForo_Moderator
*/
class XenForo_DataWriter_ModeratorContent extends XenForo_DataWriter
{
	/**
	 * Option that controls whether we should check if we're deleting the
	 * last content moderator record for this user and potentially remove
	 * the general moderator record. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_CHECK_GENERAL_MOD_ON_DELETE = 'checkGeneralModOnDelete';

	/**
	 * Extra data for the set of general moderator permissions that apply to this user.
	 * Data is an array.
	 *
	 * @var string
	 */
	const DATA_GENERAL_PERMISSIONS = 'generalPermissions';

	/**
	 * Extra data for the set of extra user groups this user should be put in.
	 * Array.
	 *
	 * @var string
	 */
	const DATA_EXTRA_GROUP_IDS = 'extraGroupIds';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_moderator_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_moderator_content' => array(
				'moderator_id'          => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'content_type'          => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25),
				'content_id'            => array('type' => self::TYPE_UINT, 'required' => true),
				'user_id'               => array('type' => self::TYPE_UINT,    'required' => true),
				'moderator_permissions' => array('type' => self::TYPE_SERIALIZED, 'required' => true),
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

		return array('xf_moderator_content' => $this->_getModeratorModel()->getContentModeratorById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'moderator_id = ' . $this->_db->quote($this->getExisting('moderator_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_CHECK_GENERAL_MOD_ON_DELETE => true
		);
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$generalPermissions = $this->getExtraData(self::DATA_GENERAL_PERMISSIONS);
		$extraGroupIds = $this->getExtraData(self::DATA_EXTRA_GROUP_IDS);

		if ($this->isInsert() || $generalPermissions !== null || $extraGroupIds !== null)
		{
			$generalModerator = $this->_getModeratorModel()->getGeneralModeratorByUserId($this->get('user_id'));
			if (!$generalModerator)
			{
				$globalDw = XenForo_DataWriter::create('XenForo_DataWriter_Moderator');
				$globalDw->bulkSet(array(
					'user_id' => $this->get('user_id'),
					'is_super_moderator' => 0,
				));

				if ($generalPermissions !== null)
				{
					$globalDw->setGeneralPermissions($generalPermissions);
				}
				if ($extraGroupIds !== null)
				{
					$globalDw->set('extra_user_group_ids', $extraGroupIds);
				}

				$globalDw->save();
			}
			else if ($generalPermissions !== null || $extraGroupIds !== null)
			{
				$globalDw = XenForo_DataWriter::create('XenForo_DataWriter_Moderator');
				$globalDw->setExistingData($generalModerator, true);
				$globalDw->setGeneralPermissions($generalPermissions);
				if ($extraGroupIds !== null)
				{
					$globalDw->set('extra_user_group_ids', $extraGroupIds);
				}
				$globalDw->save();
			}
		}

		if ($this->isChanged('moderator_permissions'))
		{
			$this->_updatePermissions($this->get('moderator_permissions'), $this->getExisting('moderator_permissions'));
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_updatePermissions(array(), $this->getExisting('moderator_permissions'));

		if ($this->getOption(self::OPTION_CHECK_GENERAL_MOD_ON_DELETE)
			&& !$this->_getModeratorModel()->getContentModeratorsByUserId($this->get('user_id'))
		)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Moderator', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($this->get('user_id')))
			{
				if (!$dw->get('is_super_moderator'))
				{
					$dw->delete();
				}
			}
		}
	}

	/**
	 * Helper to update content permissions.
	 *
	 * @param array|string $newPermissions
	 * @param array|string $existingPermissions
	 */
	protected function _updatePermissions($newPermissions, $existingPermissions)
	{
		$finalPermissions = $this->_getModeratorModel()->getModeratorPermissionsForUpdate(
			$newPermissions, $existingPermissions, 'content_allow'
		);

		$this->_getPermissionModel()->updateContentPermissionsForUserCollection(
			$finalPermissions, $this->get('content_type'), $this->get('content_id'), 0, $this->get('user_id')
		);
	}

	/**
	 * @return XenForo_Model_Moderator
	 */
	protected function _getModeratorModel()
	{
		return $this->getModelFromCache('XenForo_Model_Moderator');
	}

	/**
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}
}