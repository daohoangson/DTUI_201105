<?php

/**
* Data writer for route prefixes. Note that this works off original prefixes!
*
* @package XenForo_RoutePrefixes
*/
class XenForo_DataWriter_RoutePrefix extends XenForo_DataWriter
{
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
	protected $_existingDataErrorPhrase = 'requested_route_prefix_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_route_prefix' => array(
				'route_type'      => array('type' => self::TYPE_STRING, 'required' => true, 'allowedValues' => array('public', 'admin')),
				'original_prefix' => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true,
						'verification' => array('$this', '_verifyPrefix'), 'requiredError' => 'please_enter_valid_route_prefix'
				),
				'route_class'     => array('type' => self::TYPE_STRING, 'maxLength' => 75, 'required' => true,
						'requiredError' => 'please_enter_valid_route_class'
				),
				'build_link'      => array('type' => self::TYPE_STRING, 'required' => true,
						'allowedValues' => array('all', 'data_only', 'none')
				),
				'addon_id'        => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => '')
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
		else if (isset($data['original_prefix'], $data['route_type']))
		{
			$prefix = $data['original_prefix'];
			$type = $data['route_type'];
		}
		else if (isset($data[0], $data[1]))
		{
			$prefix = $data[0];
			$type = $data[1];
		}
		else
		{
			return false;
		}

		return array('xf_route_prefix' => $this->_getRoutePrefixModel()->getPrefixByOriginal($prefix, $type));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'route_type = ' . $this->_db->quote($this->getExisting('route_type'))
			. ' AND original_prefix = ' . $this->_db->quote($this->getExisting('original_prefix'));
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
	 * Verifies that the prefix is valid
	 *
	 * @param string $prefix
	 * @param XenForo_DataWriter $dw Ignored
	 * @param string $fieldName Name of the field that triggered this function
	 *
	 * @return boolean
	 */
	protected function _verifyPrefix($prefix, $dw, $fieldName)
	{
		if (preg_match('#[\?&=/\. \#\[\]%:;]#', $prefix))
		{
			$this->error(new XenForo_Phrase('please_enter_valid_prefix'), $fieldName);
			return false;
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isChanged('original_prefix') || $this->isChanged('route_type'))
		{
			$existing = $this->_getRoutePrefixModel()->getPrefixByOriginal($this->get('original_prefix'), $this->get('route_type'));
			if ($existing)
			{
				$this->error(new XenForo_Phrase('route_prefixes_must_be_unique', array('prefix' => $this->get('original_prefix'))), 'original_prefix');
				return;
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getRoutePrefixModel()->rebuildRoutePrefixTypeCache($this->get('route_type'));

			if ($this->isUpdate() && $this->isChanged('route_type'))
			{
				$this->_getRoutePrefixModel()->rebuildRoutePrefixTypeCache($this->getExisting('route_type'));
			}
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getRoutePrefixModel()->rebuildRoutePrefixTypeCache($this->get('route_type'));
		}
	}

	/**
	 * Gets the route prefix model object.
	 *
	 * @return XenForo_Model_RoutePrefix
	 */
	protected function _getRoutePrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_RoutePrefix');
	}
}