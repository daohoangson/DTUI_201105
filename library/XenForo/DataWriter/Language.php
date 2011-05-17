<?php

/**
* Data writer for languages.
*
* @package XenForo_Language
*/
class XenForo_DataWriter_Language extends XenForo_DataWriter
{
	const DATA_REBUILD_CACHES = 'rebildCaches';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_language_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_language' => array(
				'language_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'parent_id'   => array('type' => self::TYPE_UINT, 'default' => 0, 'verification' => array('$this', '_verifyParentId')),
				'parent_list' => array('type' => self::TYPE_BINARY, 'default' => '0', 'maxLength' => 100),
				'title'       => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_title'
				),
				'date_format' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 30,
						'requiredError' => 'please_enter_valid_date_format'
				),
				'time_format' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 15,
						'requiredError' => 'please_enter_valid_time_format'
				),
				'decimal_point'       => array('type' => self::TYPE_STRING, 'default' => '.', 'maxLength' => 1, 'noTrim' => true),
				'thousands_separator' => array('type' => self::TYPE_STRING, 'default' => ',', 'maxLength' => 1, 'noTrim' => true),
				'phrase_cache' => array('type' => self::TYPE_BINARY, 'default' => ''),
				'language_code' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 25)
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
		if (!$languageId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_language' => $this->_getLanguageModel()->getLanguageById($languageId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'language_id = ' . $this->_db->quote($this->getExisting('language_id'));
	}

	/**
	 * Verifies that the parent ID has been set correctly by ensuring
	 * an invalid tree is not created (can't parent to self or child).
	 *
	 * @param $parentId
	 *
	 * @return boolean
	 */
	protected function _verifyParentId($parentId)
	{
		$languageId = $this->get('language_id');

		if ($languageId && $parentId)
		{
			$languageModel = $this->_getLanguageModel();

			$parentLanguage = $languageModel->getLanguageById($parentId);
			$parentList = explode(',', $parentLanguage['parent_list']);

			if (in_array($languageId, $parentList))
			{
				$this->error(new XenForo_Phrase('please_select_valid_parent_language'), 'parent_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Internal post-save handler
	 */
	protected function _postSave()
	{
		$languageId = $this->get('language_id');

		$languageModel = $this->_getLanguageModel();

		if ($this->isChanged('parent_id'))
		{
			$languageModel->rebuildLanguageParentListRecursive($languageId);

			$this->setExtraData(self::DATA_REBUILD_CACHES, array('Phrase', 'Template', 'AdminTemplate', 'EmailTemplate'));
		}

		$languageModel->rebuildLanguageCaches();
	}

	/**
	 * Internal pre-delete handler.
	 */
	protected function _preDelete()
	{
		$languageModel = $this->_getLanguageModel();
		$languages = $languageModel->getAllLanguages();

		if (sizeof($languages) <= 1)
		{
			$this->error(new XenForo_Phrase('it_is_not_possible_to_delete_last_language'));
		}

		if ($this->get('language_id') == XenForo_Application::get('options')->defaultLanguageId)
		{
			$this->error(new XenForo_Phrase('it_is_not_possible_to_remove_default_language'));
		}
	}

	/**
	 * Internal post-delete handler.
	 */
	protected function _postDelete()
	{
		$languageId = $this->get('language_id');
		$db = $this->_db;

		$languageModel = $this->_getLanguageModel();
		$phraseModel = $this->_getPhraseModel();

		$directChildren = $languageModel->getDirectChildLanguageIds($languageId);
		if ($directChildren)
		{
			// re-parent child styles
			$db->update('xf_language',
				array('parent_id' => $this->get('parent_id')),
				'parent_id = ' . $db->quote($languageId)
			);

			$languageModel->resetLocalCacheData('allLanguages');
			foreach ($directChildren AS $childLanguageId)
			{
				$languageModel->rebuildLanguageParentListRecursive($childLanguageId);
			}
		}

		$db->delete('xf_phrase', 'language_id = ' . $db->quote($languageId));
		$db->delete('xf_phrase_map', 'language_id = ' . $db->quote($languageId));
		$db->delete('xf_phrase_compiled', 'language_id = ' . $db->quote($languageId));
		$db->delete('xf_template_compiled', 'language_id = ' . $db->quote($languageId));
		$db->delete('xf_admin_template_compiled', 'language_id = ' . $db->quote($languageId));
		$db->delete('xf_email_template_compiled', 'language_id = ' . $db->quote($languageId));

		$this->_getLanguageModel()->rebuildLanguageCaches();

		$this->setExtraData(self::DATA_REBUILD_CACHES, array('Phrase', 'Template', 'AdminTemplate', 'EmailTemplate'));
	}

	/**
	 * Gets the language model.
	 *
	 * @return XenForo_Model_Language
	 */
	protected function _getLanguageModel()
	{
		return $this->getModelFromCache('XenForo_Model_Language');
	}
}