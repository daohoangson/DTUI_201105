<?php

/**
* Data writer for add-ons.
*
* @package XenForo_AddOns
*/
class XenForo_DataWriter_AddOn extends XenForo_DataWriter
{
	const DATA_REBUILD_CACHES = 'rebuildCaches';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_addon_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_addon' => array(
				'addon_id'                  => array('type' => self::TYPE_STRING,  'maxLength' => 25, 'required' => true,
						'verification' => array('$this', '_verifyAddOnId'), 'requiredError' => 'please_enter_valid_addon_id'),
				'title'                     => array('type' => self::TYPE_STRING,  'maxLength' => 75, 'required' => true,
						'requiredError' => 'please_enter_valid_title'
				),
				'version_string'            => array('type' => self::TYPE_STRING,  'maxLength' => 30, 'default' => ''),
				'version_id'                => array('type' => self::TYPE_UINT,    'default' => 0),
				'install_callback_class'    => array('type' => self::TYPE_STRING,  'maxLength' => 75, 'default' => ''),
				'install_callback_method'   => array('type' => self::TYPE_STRING,  'maxLength' => 50, 'default' => ''),
				'uninstall_callback_class'  => array('type' => self::TYPE_STRING,  'maxLength' => 75, 'default' => ''),
				'uninstall_callback_method' => array('type' => self::TYPE_STRING,  'maxLength' => 50, 'default' => ''),
				'url'                       => array('type' => self::TYPE_STRING,  'maxLength' => 100, 'default' => ''),
				'active'                    => array('type' => self::TYPE_BOOLEAN, 'default' => 1)
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'addon_id'))
		{
			return false;
		}

		return array('xf_addon' => $this->_getAddOnModel()->getAddOnById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'addon_id = ' . $this->_db->quote($this->getExisting('addon_id'));
	}

	/**
	 * Verifies that the add-on ID is valid.
	 *
	 * @param string $addOnId
	 *
	 * @return boolean
	 */
	protected function _verifyAddOnId($addOnId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $addOnId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'addon_id');
			return false;
		}

		if ($this->isInsert() || $addOnId != $this->getExisting('addon_id'))
		{
			$existing = $this->_getAddOnModel()->getAddOnById($addOnId);
			if ($existing)
			{
				$this->error(new XenForo_Phrase('add_on_ids_must_be_unique'), 'addon_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('addon_id'))
		{
			$db = $this->_db;
			$updateClause = 'addon_id = ' . $db->quote($this->getExisting('addon_id'));
			$updateValue = array('addon_id' => $this->get('addon_id'));

			$db->update('xf_admin_navigation', $updateValue, $updateClause);
			$db->update('xf_admin_permission', $updateValue, $updateClause);
			$db->update('xf_admin_template', $updateValue, $updateClause);
			$db->update('xf_code_event', $updateValue, $updateClause);
			$db->update('xf_code_event_listener', $updateValue, $updateClause);
			$db->update('xf_content_type', $updateValue, $updateClause);
			$db->update('xf_cron_entry', $updateValue, $updateClause);
			$db->update('xf_email_template', $updateValue, $updateClause);
			$db->update('xf_option', $updateValue, $updateClause);
			$db->update('xf_option_group', $updateValue, $updateClause);
			$db->update('xf_permission', $updateValue, $updateClause);
			$db->update('xf_permission_group', $updateValue, $updateClause);
			$db->update('xf_permission_interface_group', $updateValue, $updateClause);
			$db->update('xf_phrase', $updateValue, $updateClause);
			$db->update('xf_route_prefix', $updateValue, $updateClause);
			$db->update('xf_style_property_definition', $updateValue, $updateClause);
			$db->update('xf_style_property_group', $updateValue, $updateClause);
			$db->update('xf_template', $updateValue, $updateClause);
		}

		if ($this->isUpdate() && $this->isChanged('active'))
		{
			$this->_getAddOnModel()->rebuildAddOnCachesAfterActiveSwitch();
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		if ($this->get('uninstall_callback_class') && $this->get('uninstall_callback_method'))
		{
			call_user_func(
				array($this->get('uninstall_callback_class'), $this->get('uninstall_callback_method')),
				$this->getMergedData()
			);
		}

		$addOnModel = $this->_getAddOnModel();
		$addOnModel->deleteAddOnMasterData($this->get('addon_id'));

		$this->setExtraData(self::DATA_REBUILD_CACHES, $addOnModel->rebuildAddOnCaches());
	}

	/**
	 * Gets the add-on model object.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}