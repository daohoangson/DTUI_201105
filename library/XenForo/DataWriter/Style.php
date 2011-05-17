<?php

/**
* Data writer for styles.
*
* @package XenForo_Style
*/
class XenForo_DataWriter_Style extends XenForo_DataWriter
{
	const DATA_REBUILD_CACHES = 'rebuildCaches';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_style_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_style' => array(
				'style_id'    => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'parent_id'   => array('type' => self::TYPE_UINT, 'default' => 0, 'verification' => array('$this', '_verifyParentId')),
				'parent_list' => array('type' => self::TYPE_BINARY, 'default' => '', 'maxLength' => 100),
				'title'       => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_title'
				),
				'description' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 100),
				'properties'  => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'last_modified_date' => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'user_selectable'    => array('type' => self::TYPE_BOOLEAN, 'default' => 1, 'verification' => array('$this', '_verifyUserSelectable')),
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
		if (!$styleId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_style' => $this->_getStyleModel()->getStyleById($styleId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'style_id = ' . $this->_db->quote($this->getExisting('style_id'));
	}

	/**
	 * Verifies that the parent ID has been set correctly by ensuring
	 * an invalid tree is not created (can't parent to self or child).
	 *
	 * @param integer $parentId
	 *
	 * @return boolean
	 */
	protected function _verifyParentId($parentId)
	{
		$styleId = $this->get('style_id');

		if ($styleId && $parentId)
		{
			$parentStyle = $this->_getStyleModel()->getStyleById($parentId);
			$parentList = explode(',', $parentStyle['parent_list']);

			if (in_array($styleId, $parentList))
			{
				$this->error(new XenForo_Phrase('please_select_valid_parent_style'), 'parent_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifies that the style being modified can be set to unselectable by users -
	 * The default style may not be set unselectable.
	 *
	 * @param boolean $userSelectable
	 *
	 * @return boolean
	 */
	protected function _verifyUserSelectable($userSelectable)
	{
		if (!$userSelectable)
		{
			$defaultStyleId = XenForo_Application::get('options')->defaultStyleId;

			if ($this->get('style_id') == $defaultStyleId)
			{
				$this->error(new XenForo_Phrase('it_is_not_possible_to_prevent_users_selecting_the_default_style'), 'user_selectable');
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
		if ($this->isChanged('properties') && !$this->isChanged('last_modified_date'))
		{
			$this->set('last_modified_date', XenForo_Application::$time);
		}
	}

	/**
	 * Internal post-save handler
	 */
	protected function _postSave()
	{
		$styleId = $this->get('style_id');
		$rebuildCache = true;

		if ($this->isChanged('parent_id'))
		{
			// moved in tree or a new insert - rebuild style parent list and templates
			$styleModel = $this->_getStyleModel();

			$styleModel->rebuildStyleParentListRecursive($styleId);

			$templateModel = $this->_getTemplateModel();

			$templateModel->insertTemplateMapForStyles(
				$templateModel->buildTemplateMapForStyleTree($styleId)
			);

			$this->setExtraData(self::DATA_REBUILD_CACHES, array('Template'));

			$this->_getStylePropertyModel()->rebuildPropertyCacheInStyleAndChildren($styleId);

			$rebuildCache = false; // the property rebuild will trigger this, or the template rebuild will
		}

		if ($rebuildCache)
		{
			$this->_rebuildCache();
		}
	}

	/**
	 * Rebuilds the style cache.
	 */
	protected function _rebuildCache()
	{
		$this->_getStyleModel()->rebuildStyleCache();
	}

	/**
	 * Internal pre-delete handler.
	 */
	protected function _preDelete()
	{
		$styleModel = $this->_getStyleModel();
		$styles = $styleModel->getAllStyles();

		if (sizeof($styles) <= 1)
		{
			$this->error(new XenForo_Phrase('it_is_not_possible_to_remove_last_style'));
		}

		if ($this->get('style_id') == XenForo_Application::get('options')->defaultStyleId)
		{
			$this->error(new XenForo_Phrase('it_is_not_possible_to_remove_default_style'));
		}
	}

	/**
	 * Internal post-delete handler.
	 */
	protected function _postDelete()
	{
		$db = $this->_db;
		$styleModel = $this->_getStyleModel();
		$templateModel = $this->_getTemplateModel();

		$styleId = $this->get('style_id');
		$styleIdQuoted = $db->quote($styleId);

		$directChildren = $styleModel->getDirectChildStyleIds($styleId);
		if ($directChildren)
		{
			// re-parent child styles
			$db->update('xf_style',
				array('parent_id' => $this->get('parent_id')),
				'parent_id = ' . $db->quote($styleId)
			);

			$styleModel->resetLocalCacheData('allStyles');
			foreach ($directChildren AS $childStyleId)
			{
				$styleModel->rebuildStyleParentListRecursive($childStyleId);
			}
		}

		$db->delete('xf_template_map', 'style_id = ' . $styleIdQuoted);
		$db->delete('xf_template_compiled', 'style_id = ' . $styleIdQuoted);

		$templates = $templateModel->getAllTemplatesInStyle($styleId);
		foreach ($templates AS $template)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($template, true);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->delete();
		}

		foreach ($directChildren AS $childStyleId)
		{
			$templateModel->insertTemplateMapForStyles(
				$templateModel->buildTemplateMapForStyleTree($childStyleId)
			);
		}

		$db->update('xf_node', array(
			'style_id' => 0,
			'effective_style_id' => 0
		), "style_id = $styleIdQuoted OR effective_style_id = $styleIdQuoted");

		$db->update('xf_user', array('style_id' => 0), "style_id = $styleIdQuoted");

		$this->setExtraData(self::DATA_REBUILD_CACHES, array('Template'));

		$this->_rebuildCache();
	}

	/**
	 * Gets the style model.
	 *
	 * @return XenForo_Model_Style
	 */
	protected function _getStyleModel()
	{
		return $this->getModelFromCache('XenForo_Model_Style');
	}

	/**
	 * Gets the tempate model.
	 *
	 * @return XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}

	/**
	 * Gets the style property model.
	 *
	 * @return XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}
}