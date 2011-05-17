<?php

/**
* Data writer for style properties.
*
* @package XenForo_StyleProperty
*/
class XenForo_DataWriter_StyleProperty extends XenForo_DataWriter
{
	/**
	 * The format of the value for this property. Must be "scalar"
	 * or "css". Defaults to false, which will trigger an error.
	 *
	 * @var string
	 */
	const OPTION_VALUE_FORMAT = 'valueFormat';

	/**
	 * Array of components in a CSS value. Component names found in keys.
	 *
	 * @var string
	 */
	const OPTION_VALUE_COMPONENTS = 'valueComponents';

	/**
	 * Option to control whether the property cache in the style should be
	 * rebuilt. Defaults to true.
	 *
	 * @var unknown_type
	 */
	const OPTION_REBUILD_CACHE = 'rebuildCache';

	/**
	 * Controls whether the development files are updated. Requires the definition
	 * info as well. Defaults to debug mode value.
	 *
	 * @var string
	 */
	const OPTION_UPDATE_DEVELOPMENT = 'updateDevelopment';

	/**
	 * Constant for the extra data value that holds info about the definition.
	 * Used for saving definitions to the development area.
	 *
	 * @var string
	 */
	const DATA_DEFINITION = 'definition';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_style_property' => array(
				'property_id'            => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'property_definition_id' => array('type' => self::TYPE_UINT,   'required' => true),
				'style_id'               => array('type' => self::TYPE_INT,   'required' => true),
				'property_value'         => array('type' => self::TYPE_UNKNOWN, 'default' => '',
						'verification' => array('$this', '_verifyPropertyValue')
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
		if (!$propertyId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array(
			'xf_style_property' => $this->_getStylePropertyModel()->getStylePropertyById($propertyId)
		);
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'property_id = ' . $this->_db->quote($this->getExisting('property_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_VALUE_FORMAT => false,
			self::OPTION_VALUE_COMPONENTS => array(),
			self::OPTION_REBUILD_CACHE => true,
			self::OPTION_UPDATE_DEVELOPMENT => XenForo_Application::canWriteDevelopmentFiles()
		);
	}

	/**
	 * Verifies/sets the property value based on the type of the
	 * property.
	 *
	 * @param string|array $value
	 *
	 * @return boolean
	 */
	protected function _verifyPropertyValue(&$value)
	{
		switch ($this->getOption(self::OPTION_VALUE_FORMAT))
		{
			case 'scalar':
				$value = strval($value);
				break;

			case 'css':
				if (!is_array($value))
				{
					$value = array();
				}

				// TODO: need to validate against allowed components
				foreach ($value AS $key => &$propertyValue)
				{
					if (is_string($propertyValue))
					{
						$propertyValue = trim($propertyValue);
						if ($propertyValue === '')
						{
							unset($value[$key]);
							continue;
						}

						$propertyValue = str_replace("\r", '', $propertyValue);
					}
					else if (is_array($propertyValue))
					{
						if (count($propertyValue) == 0)
						{
							unset($value[$key]);
							continue;
						}

						if ($key == 'text-decoration')
						{
							asort($propertyValue);
						}
					}
				}

				ksort($value);

				$value = serialize($value);
				break;

			default:
				throw new XenForo_Exception('Value format option not set properly.');
		}

		return true;
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$propertyModel = $this->_getStylePropertyModel();
		$property = $this->getMergedData();

		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$propertyModel->rebuildPropertyCacheInStyleAndChildren($this->get('style_id'));
		}

		if ($this->_canWriteToDevelopmentFile())
		{
			$definition = $this->getExtraData(self::DATA_DEFINITION);
			$propertyModel->writeStylePropertyDevelopmentFile($definition, $property);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$propertyModel = $this->_getStylePropertyModel();

		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$propertyModel->rebuildPropertyCacheInStyleAndChildren($this->get('style_id'));
		}

		if ($this->_canWriteToDevelopmentFile())
		{
			$definition = $this->getExtraData(self::DATA_DEFINITION);
			$propertyModel->deleteStylePropertyDevelopmentFileIfNeeded($definition, $this->getMergedData());
		}
	}

	/**
	 * Returns true if there is a development file that can be manipulated for this property.
	 *
	 * @return boolean
	 */
	protected function _canWriteToDevelopmentFile()
	{
		if (!$this->getOption(self::OPTION_UPDATE_DEVELOPMENT))
		{
			return false;
		}

		$definition = $this->getExtraData(self::DATA_DEFINITION);
		if (!$definition)
		{
			return false;
		}

		return ($this->get('style_id') < 1 && $definition['definition_style_id'] < 1 && $definition['addon_id'] == 'XenForo');
	}

	/**
	 * @return XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}
}