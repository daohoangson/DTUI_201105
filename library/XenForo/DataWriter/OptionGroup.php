<?php

/**
* Data writer for option groups.
*
* @package XenForo_Options
*/
class XenForo_DataWriter_OptionGroup extends XenForo_DataWriter
{
	/**
	 * Constants for extra data that holds the value for the phrases
	 * that are the title and description of this group.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';
	const DATA_DESCRIPTION = 'phraseDescription';

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
	protected $_existingDataErrorPhrase = 'requested_option_group_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_option_group' => array(
				'group_id'      => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'verification' => array('$this', '_verifyGroupId'), 'requiredError' => 'please_enter_valid_option_group_id'
				),
				'display_order' => array('type' => self::TYPE_UINT,   'default' => 1),
				'debug_only'    => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'addon_id'      => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => '')
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'group_id'))
		{
			return false;
		}

		return array('xf_option_group' => $this->_getOptionModel()->getOptionGroupById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'group_id = ' . $this->_db->quote($this->getExisting('group_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true
		);
	}

	/**
	 * Verifies that the group ID contains valid characters and does not already exist.
	 *
	 * @param $groupId
	 *
	 * @return boolean
	 */
	protected function _verifyGroupId($groupId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $groupId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'group_id');
			return false;
		}

		if ($groupId !== $this->getExisting('group_id'))
		{
			if ($this->_getOptionModel()->getOptionGroupById($groupId))
			{
				$this->error(new XenForo_Phrase('option_group_ids_must_be_unique'), 'group_id');
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

		if ($this->isUpdate() && $this->isChanged('group_id'))
		{
			$db->update('xf_option_group_relation',
				array('group_id' => $this->get('group_id')),
				'group_id = ' . $db->quote($this->getExisting('group_id'))
			);

			$this->_renameMasterPhrase(
				$this->_getTitlePhraseName($this->getExisting('group_id')),
				$this->_getTitlePhraseName($this->get('group_id'))
			);

			$this->_renameMasterPhrase(
				$this->_getDescriptionPhraseName($this->getExisting('group_id')),
				$this->_getDescriptionPhraseName($this->get('group_id'))
			);
		}

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($this->get('group_id')), $titlePhrase, $this->get('addon_id')
			);
		}

		$descriptionPhrase = $this->getExtraData(self::DATA_DESCRIPTION);
		if ($descriptionPhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getDescriptionPhraseName($this->get('group_id')), $descriptionPhrase, $this->get('addon_id')
			);
		}

		$this->_rebuildOptionCache();
	}

	/**
	 * Post-delete handling
	 */
	protected function _postDelete()
	{
		$this->_deleteMasterPhrase($this->_getTitlePhraseName($this->get('group_id')));
		$this->_deleteMasterPhrase($this->_getDescriptionPhraseName($this->get('group_id')));

		$this->_getOptionModel()->deleteOptionsInGroup($this->get('group_id'));
		$this->_rebuildOptionCache();
	}

	/**
	 * Rebuilds the option cache if rebuild option is enabled.
	 */
	protected function _rebuildOptionCache()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getOptionModel()->rebuildOptionCache();
		}
	}

	/**
	 * Gets the name of the title phrase for this group.
	 *
	 * @param string $groupId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($groupId)
	{
		return $this->_getOptionModel()->getOptionGroupTitlePhraseName($groupId);
	}

	/**
	 * Gets the name of the description phrase for this group.
	 *
	 * @param string $groupId
	 *
	 * @return string
	 */
	protected function _getDescriptionPhraseName($groupId)
	{
		return $this->_getOptionModel()->getOptionGroupDescriptionPhraseName($groupId);
	}

	/**
	 * Load option model from cache.
	 *
	 * @return XenForo_Model_Option
	 */
	protected function _getOptionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Option');
	}
}