<?php

/**
* Data writer for style property definitions.
*
* @package XenForo_StyleProperty
*/
class XenForo_DataWriter_StylePropertyDefinition extends XenForo_DataWriter
{
	/**
	 * Controls whether the value of the master phrase should be updated when
	 * modifying this definition. Defaults to true.
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
	 * If false, duplicate checking is disabled. An error will occur on dupes. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_CHECK_DUPLICATE = 'checkDuplicate';

	/**
	 * Option to control whether the property cache in the style should be
	 * rebuilt. Defaults to true.
	 *
	 * @var unknown_type
	 */
	const OPTION_REBUILD_CACHE = 'rebuildCache';

	/**
	 * These are banned names for style properties, due to conflicts with other syntax schemes
	 *
	 * @var array Banned property names
	 */
	public static $reservedNames = array(
		// css reserved @ident - see http://www.w3.org/TR/css3-syntax/#lexical
		'import',
		'page',
		'media',
		'font-face',
		'charset',
		'namespace',
		// XenForo reserved @property
		'property',
	);

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_style_property_definition_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_style_property_definition' => array(
				'property_definition_id' => array('type' => self::TYPE_UINT,
					'autoIncrement' => true
				),
				'definition_style_id' => array('type' => self::TYPE_INT,
					'required' => true
				),
				'group_name' => array('type' => self::TYPE_STRING,
					'required' => true,
					'maxLength' => 25,
					'requiredError' => 'please_enter_valid_group_name'
				),
				'property_name' => array('type' => self::TYPE_STRING,
					'required' => true,
					'maxLength' => 100,
					'verification' => array('$this', '_verifyPropertyName'),
					'requiredError' => 'please_enter_valid_property_name'
				),
				'title' => array('type' => self::TYPE_STRING,
					'required' => true,
					'maxLength' => 100,
					'requiredError' => 'please_enter_valid_title'
				),
				'description' => array('type' => self::TYPE_STRING,
					'default' => '',
					'maxLength' => 255
				),
				'property_type' => array('type' => self::TYPE_STRING,
					'required' => true,
					'allowedValues' => array('scalar', 'css')
				),
				'css_components' => array('type' => self::TYPE_UNKNOWN,
					'default' => 'a:0:{}',
					'verification' => array('$this', '_verifyCssComponents')
				),
				'scalar_type' => array('type' => self::TYPE_STRING,
					'default' => '',
					'allowedValues' => array('', 'longstring', 'color', 'number', 'boolean', 'template')
				),
				'scalar_parameters' => array('type' => self::TYPE_STRING,
					'default' => '',
					'maxLength' => 250
				),
				'display_order' => array('type' => self::TYPE_UINT_FORCED,
					'default' => 0
				),
				'addon_id' => array('type' => self::TYPE_STRING,
					'maxLength' => 25,
					'default' => ''
				),
				'sub_group' => array('type' => self::TYPE_STRING,
					'maxLength' => 25,
					'default' => ''
				),
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
		if (!$definitionId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array(
			'xf_style_property_definition' => $this->_getStylePropertyModel()->getStylePropertyDefinitionById($definitionId)
		);
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'property_definition_id = ' . $this->_db->quote($this->getExisting('property_definition_id'));
	}

	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_UPDATE_MASTER_PHRASE => true,
			self::OPTION_UPDATE_DEVELOPMENT => XenForo_Application::canWriteDevelopmentFiles(),
			self::OPTION_CHECK_DUPLICATE => true,
			self::OPTION_REBUILD_CACHE => true
		);
	}

	/**
	 * Verifies that the property name is valid.
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	protected function _verifyPropertyName($name)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $name))
		{
			$this->error(new XenForo_Phrase('please_enter_property_name_using_only_alphanumeric'), 'property_name');
			return false;
		}

		if (in_array(strtolower($name), self::$reservedNames))
		{
			$this->error(new XenForo_Phrase('property_name_reserved', array('name' => $name)), 'property_name');
			return false;
		}

		return true;
	}

	/**
	 * Verifies the list of CSS components.
	 *
	 * @param array|string $components
	 *
	 * @return boolean
	 */
	protected function _verifyCssComponents(&$components)
	{
		if (!is_array($components))
		{
			$components = array();
		}

		$firstValue = reset($components);
		if (!is_bool($firstValue))
		{
			$newComponents = array();
			foreach ($components AS $component)
			{
				$newComponents[$component] = true;
			}
			$components = $newComponents;
		}


		$components = serialize($components);

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isUpdate() && $this->isChanged('definition_style_id'))
		{
			throw new XenForo_Exception('Cannot update the style of existing style property definitions.');
		}

		if ($this->get('property_type') == 'css')
		{
			$components = $this->get('css_components');
			if (is_string($components) && substr($components, 0, 2) == 'a:')
			{
				$components = unserialize($components);
			}
			if (!$components)
			{
				$this->error(new XenForo_Phrase('css_style_property_must_have_at_least_one_css_component'), 'css_components');
			}
		}

		if ($this->isChanged('property_name') && $this->getOption(self::OPTION_CHECK_DUPLICATE))
		{
			$newName = $this->get('property_name');
			$definitions = $this->_getStylePropertyModel()->getEffectiveStylePropertiesInStyle($this->get('definition_style_id'));
			foreach ($definitions AS $definition)
			{
				if ($definition['property_name'] == $newName)
				{
					$this->error(new XenForo_Phrase('style_property_definitions_must_be_unique_per_style'), 'property_name');
					break;
				}
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$propertyModel = $this->_getStylePropertyModel();
		$definition = $this->getMergedData();
		$existingDefinition = reset($this->_existingData);

		if ($this->isUpdate() && $this->isChanged('property_type'))
		{
			// these are all going to be invalid if the type is different
			$definitionId = $this->get('property_definition_id');

			$this->_db->delete('xf_style_property',
				'property_definition_id = ' . $this->_db->quote($definitionId)
			);
		}

		if ($this->getOption(self::OPTION_UPDATE_MASTER_PHRASE))
		{
			$titlePhraseName = $this->_getStylePropertyModel()->getStylePropertyTitlePhraseName($definition);
			$descriptionPhraseName = $this->_getStylePropertyModel()->getStylePropertyDescriptionPhraseName($definition);

			if ($this->get('definition_style_id') < 1 && $this->get('addon_id'))
			{
				$this->_insertOrUpdateMasterPhrase($titlePhraseName, $this->get('title'), $this->get('addon_id'));
				$this->_insertOrUpdateMasterPhrase($descriptionPhraseName, $this->get('description'), $this->get('addon_id'));
			}
			else if ($this->isUpdate() && $this->getExisting('definition_style_id') < 1 && $this->getExisting('addon_id'))
			{
				$this->_deleteMasterPhrase($titlePhraseName);
				$this->_deleteMasterPhrase($descriptionPhraseName);
			}

			if ($this->isUpdate() && $this->isChanged('property_name')
				&& $this->getExisting('definition_style_id') < 1 && $this->getExisting('addon_id')
			)
			{
				$oldTitlePhraseName = $this->_getStylePropertyModel()->getStylePropertyTitlePhraseName($existingDefinition);
				$this->_deleteMasterPhrase($oldTitlePhraseName);

				$oldDescriptionPhraseName = $this->_getStylePropertyModel()->getStylePropertyDescriptionPhraseName($existingDefinition);
				$this->_deleteMasterPhrase($oldDescriptionPhraseName);
			}
		}

		if ($this->isUpdate() && $this->isChanged('property_name'))
		{
			if ($this->_canWriteToDevelopmentFile())
			{
				$propertyModel->moveStylePropertyDevelopmentFile(
					$existingDefinition, $definition
				);
			}

			if ($this->getOption(self::OPTION_REBUILD_CACHE))
			{
				$propertyModel->rebuildPropertyCacheInStyleAndChildren($this->get('definition_style_id'));
			}
		}

		if ($this->isUpdate() && $this->_canWriteToDevelopmentFile())
		{
			$propertyModel->updateStylePropertyDevelopmentFile($definition);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$propertyModel = $this->_getStylePropertyModel();
		$definitionId = $this->get('property_definition_id');
		$definition = $this->getMergedData();

		$this->_db->delete('xf_style_property',
			'property_definition_id = ' . $this->_db->quote($definitionId)
		);

		if ($this->_canWriteToDevelopmentFile())
		{
			$propertyModel->deleteStylePropertyDevelopmentFile(
				$this->get('property_name'), 0
			);
			$propertyModel->deleteStylePropertyDevelopmentFile(
				$this->get('property_name'), -1
			);
		}

		if ($this->getOption(self::OPTION_UPDATE_MASTER_PHRASE) && $this->get('definition_style_id') < 1 && $this->get('addon_id'))
		{
			$titlePhraseName = $this->_getStylePropertyModel()->getStylePropertyTitlePhraseName($definition);
			$this->_deleteMasterPhrase($titlePhraseName);

			$descPhraseName = $this->_getStylePropertyModel()->getStylePropertyDescriptionPhraseName($definition);
			$this->_deleteMasterPhrase($descPhraseName);
		}

		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$propertyModel->rebuildPropertyCacheInStyleAndChildren($this->get('definition_style_id'));
		}
	}

	/**
	 * Returns true if there is a development file that can be manipulated for this property definition.
	 *
	 * @return boolean
	 */
	protected function _canWriteToDevelopmentFile()
	{
		if (!$this->getOption(self::OPTION_UPDATE_DEVELOPMENT))
		{
			return false;
		}

		return ($this->get('definition_style_id') < 1 && $this->get('addon_id') == 'XenForo');
	}

	/**
	 * @return XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}
}