<?php

/**
* Data writer for style property groups.
*
* @package XenForo_StyleProperty
*/
class XenForo_DataWriter_StylePropertyGroup extends XenForo_DataWriter
{
	/**
	 * Controls whether the value of the master phrase should be updated when
	 * modifying this group. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_UPDATE_MASTER_PHRASE = 'updateMasterPhrase';

	/**
	 * Controls whether the development files are updated. Defaults to debug mode value.
	 *
	 * @var string
	 */
	const OPTION_UPDATE_DEVELOPMENT = 'updateDevelopment';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_style_property_group_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_style_property_group' => array(
				'property_group_id' => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'group_style_id'    => array('type' => self::TYPE_INT,   'required' => true),
				'group_name'        => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25,
						'verification' => array('$this', '_verifyGroupName'),
						'requiredError' => 'please_enter_valid_group_name'
				),
				'description'       => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 255),
				'title'             => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 100,
						'requiredError' => 'please_enter_valid_title'
				),
				'display_order'     => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'addon_id'          => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 25)
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
		if (!$groupId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array(
			'xf_style_property_group' => $this->_getStylePropertyModel()->getStylePropertyGroupById($groupId)
		);
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'property_group_id = ' . $this->_db->quote($this->getExisting('property_group_id'));
	}

	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_UPDATE_MASTER_PHRASE => true,
			self::OPTION_UPDATE_DEVELOPMENT => XenForo_Application::canWriteDevelopmentFiles()
		);
	}

	/**
	 * Verifies that the group name is valid.
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	protected function _verifyGroupName($name)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $name))
		{
			$this->error(new XenForo_Phrase('please_enter_group_name_using_only_alphanumeric'), 'group_name');
			return false;
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isUpdate() && $this->isChanged('group_style_id'))
		{
			throw new XenForo_Exception('Cannot update the style of existing style property groups.');
		}

		if ($this->isChanged('group_name'))
		{
			$groups = $this->_getStylePropertyModel()->getEffectiveStylePropertyGroupsInStyle($this->get('group_style_id'));
			if (isset($groups[$this->get('group_name')]))
			{
				$this->error(new XenForo_Phrase('style_property_groups_must_be_unique_per_style'), 'group_name');
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$propertyModel = $this->_getStylePropertyModel();
		$group = $this->getMergedData();
		$existingGroup = reset($this->_existingData);

		if ($this->getOption(self::OPTION_UPDATE_MASTER_PHRASE))
		{
			$titlePhraseName = $this->_getStylePropertyModel()->getStylePropertyGroupTitlePhraseName($group);
			$descPhraseName = $this->_getStylePropertyModel()->getStylePropertyGroupDescriptionPhraseName($group);

			if ($this->get('group_style_id') < 1 && $this->get('addon_id'))
			{
				$this->_insertOrUpdateMasterPhrase($titlePhraseName, $this->get('title'), $this->get('addon_id'));
				$this->_insertOrUpdateMasterPhrase($descPhraseName, $this->get('description'), $this->get('addon_id'));
			}
			else if ($this->isUpdate() && $this->getExisting('group_style_id') < 1 && $this->getExisting('addon_id'))
			{
				$this->_deleteMasterPhrase($titlePhraseName);
				$this->_deleteMasterPhrase($descPhraseName);
			}

			if ($this->isUpdate() && $this->isChanged('group_name')
				&& $this->getExisting('group_style_id') < 1 && $this->getExisting('addon_id')
			)
			{
				$oldTitlePhraseName = $this->_getStylePropertyModel()->getStylePropertyGroupTitlePhraseName($existingGroup);
				$this->_deleteMasterPhrase($oldTitlePhraseName);

				$oldDescPhraseName = $this->_getStylePropertyModel()->getStylePropertyGroupDescriptionPhraseName($existingGroup);
				$this->_deleteMasterPhrase($oldDescPhraseName);

				$this->_getStylePropertyModel()->moveStylePropertiesBetweenGroups($this->getExisting('group_name'), $this->getNew('group_name'));
			}
		}

		if ($this->isUpdate()
			&& $this->isChanged('group_name')
			&& ($this->getExisting('group_style_id') < 1 && $this->getExisting('addon_id') == 'XenForo')
		)
		{
			$this->deleteGroupDevelopmentFile($existingGroup);
		}

		if ($this->_canWriteToDevelopmentFile())
		{
			$this->writeGroupDevelopmentFile($group);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$propertyModel = $this->_getStylePropertyModel();
		$group = $this->getMergedData();

		if ($this->_canWriteToDevelopmentFile())
		{
			$this->deleteGroupDevelopmentFile($group);
		}

		if ($this->getOption(self::OPTION_UPDATE_MASTER_PHRASE) && $this->get('group_style_id') < 1 && $this->get('addon_id'))
		{
			$titlePhraseName = $this->_getStylePropertyModel()->getStylePropertyGroupTitlePhraseName($group);
			$this->_deleteMasterPhrase($titlePhraseName);

			$descPhraseName = $this->_getStylePropertyModel()->getStylePropertyGroupDescriptionPhraseName($group);
			$this->_deleteMasterPhrase($descPhraseName);
		}

		foreach ($propertyModel->getStylePropertyDefinitionsByGroup($group['group_name'], $group['group_style_id']) AS $def)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_StylePropertyDefinition', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($def, true);
			$dw->setOption(XenForo_DataWriter_StylePropertyDefinition::OPTION_REBUILD_CACHE, false);
			$dw->delete();
		}

		$propertyModel->rebuildPropertyCacheInStyleAndChildren($group['group_style_id']);
	}

	/**
	 * Gets the full path to a specific style property group development file.
	 * Ensures directory is writable.
	 *
	 * @param string $groupName
	 * @param integer $styleId
	 *
	 * @return string
	 */
	public function getGroupDevelopmentFileName($groupName, $styleId)
	{
		$dir = $this->_getStylePropertyModel()->getStylePropertyDevelopmentDirectory($styleId);
		if (!$dir)
		{
			throw new XenForo_Exception('Tried to write non-master/admin style property group value to development directory, or debug mode is not enabled');
		}
		if (!is_dir($dir) || !is_writable($dir))
		{
			throw new XenForo_Exception("Style property development directory $dir is not writable");
		}

		return ($dir . '/group.' . $groupName . '.xml');
	}

	public function deleteGroupDevelopmentFile(array $group)
	{
		$fileName = $this->getGroupDevelopmentFileName($group['group_name'], $group['group_style_id']);
		if (file_exists($fileName))
		{
			unlink($fileName);
		}
	}

	public function writeGroupDevelopmentFile(array $group)
	{
		$fileName = $this->getGroupDevelopmentFileName($group['group_name'], $group['group_style_id']);

		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$node = $document->createElement('group');
		$document->appendChild($node);

		$node->setAttribute('display_order', $group['display_order']);
		XenForo_Helper_DevelopmentXml::createDomElements($node, array(
			'title' => $group['title'],
			'description' => $group['description']
		));

		$document->save($fileName);
	}

	/**
	 * Returns true if there is a development file that can be manipulated for this property group.
	 *
	 * @return boolean
	 */
	protected function _canWriteToDevelopmentFile()
	{
		if (!$this->getOption(self::OPTION_UPDATE_DEVELOPMENT))
		{
			return false;
		}

		return ($this->get('group_style_id') < 1 && $this->get('addon_id') == 'XenForo');
	}

	/**
	 * @return XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}
}