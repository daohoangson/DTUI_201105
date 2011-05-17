<?php

/**
* Data writer for phrases.
*
* @package XenForo_Phrase
*/
class XenForo_DataWriter_Phrase extends XenForo_DataWriter
{
	/**
	 * Option that takes the path to the development template output directory.
	 * If not specified, output will not be written. Default determined based
	 * on config settings.
	 *
	 * @var string
	 */
	const OPTION_DEV_OUTPUT_DIR = 'devOutputDir';

	/**
	 * Option that controls whether language-related caches will be rebuild.
	 * Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_REBUILD_LANGUAGE_CACHE = 'rebuildLanguageCache';

	/**
	 * Option that controls whether templates that use this phrase should be recompiled.
	 * This can be a slow process if updating a lot of phrases. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_FULL_RECOMPILE = 'fullRecompile';

	/**
	 * Option that controls if phrase map is rebuild when phrase is changed. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_REBUILD_PHRASE_MAP = 'rebuildPhraseMap';

	/**
	 * If false, duplicate checking is disabled. An error will occur on dupes. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_CHECK_DUPLICATE = 'checkDuplicate';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_phrase_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_phrase' => array(
				'phrase_id'    => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'language_id'  => array('type' => self::TYPE_UINT,   'required' => true),
				'title'        => array('type' => self::TYPE_BINARY, 'required' => true, 'maxLength' => 75,
					'verification' => array('$this', '_verifyTitle'),
					'requiredError' => 'please_enter_valid_title'
				),
				'phrase_text'  => array('type' => self::TYPE_STRING, 'default' => '', 'noTrim' => true),
				'global_cache' => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'addon_id'     => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
				'version_id'   => array('type' => self::TYPE_UINT, 'default' => 0),
				'version_string' => array('type' => self::TYPE_STRING,  'maxLength' => 30, 'default' => '')
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
		if (!$phrase_id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_phrase' => $this->_getPhraseModel()->getPhraseById($phrase_id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'phrase_id = ' . $this->_db->quote($this->getExisting('phrase_id'));
	}

	/**
	* Gets the default set of options for this data writer.
	* If in debug mode and we have a development directory config, we set the
	* dev output directory automatically.
	*
	* @return array
	*/
	protected function _getDefaultOptions()
	{
		$options = array(
			self::OPTION_DEV_OUTPUT_DIR => '',
			self::OPTION_REBUILD_LANGUAGE_CACHE => true,
			self::OPTION_FULL_RECOMPILE => true,
			self::OPTION_REBUILD_PHRASE_MAP => true,
			self::OPTION_CHECK_DUPLICATE => true
		);

		if (XenForo_Application::debugMode())
		{
			$options[self::OPTION_DEV_OUTPUT_DIR] = $this->_getPhraseModel()->getPhraseDevelopmentDirectory();
		}

		return $options;
	}

	/**
	 * Verifies that the phrase title ID is valid.
	 *
	 * @param string $title
	 *
	 * @return boolean
	 */
	protected function _verifyTitle($title)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $title))
		{
			$this->error(new XenForo_Phrase('please_enter_title_using_only_alphanumeric'), 'title');
			return false;
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->getOption(self::OPTION_CHECK_DUPLICATE))
		{
			if ($this->isChanged('title') || $this->isChanged('language_id'))
			{
				$existing = $this->_getPhraseModel()->getPhraseInLanguageByTitle($this->get('title'), $this->get('language_id'));
				if ($existing)
				{
					$this->error(new XenForo_Phrase('phrase_titles_must_be_unique_in_language'), 'title');
				}
			}
		}
	}

	/**
	* Post-save handler.
	*/
	protected function _postSave()
	{
		$phraseModel = $this->_getPhraseModel();

		if ($this->getOption(self::OPTION_REBUILD_PHRASE_MAP))
		{
			if ($this->isChanged('title'))
			{
				$phraseModel->buildPhraseMap($this->get('title'));
				if ($existingTitle = $this->getExisting('title'))
				{
					$phraseModel->buildPhraseMap($existingTitle);
				}
			}
			else if ($this->isChanged('language_id'))
			{
				$phraseModel->buildPhraseMap($this->get('title'));
			}
		}

		if ($this->getOption(self::OPTION_FULL_RECOMPILE))
		{
			$this->_recompilePhrase();
			$this->_recompileTemplatesIncludingPhrase();
		}

		$this->_rebuildLanguageCaches();

		if ($devDir = $this->_getDevOutputDir())
		{
			$this->_writeDevFileOutput($devDir);
		}
	}

	/**
	 * Rebuilds the language caches, if the option is enabled.
	 */
	protected function _rebuildLanguageCaches()
	{
		if ($this->getOption(self::OPTION_REBUILD_LANGUAGE_CACHE))
		{
			$this->_getLanguageModel()->rebuildLanguageCaches();
		}
	}

	/**
	 * Helper to get the developer data output directory only if it is enabled
	 * and applicable to this situation.
	 *
	 * @return string
	 */
	protected function _getDevOutputDir()
	{
		if ($this->get('language_id') == 0 && $this->get('addon_id') == 'XenForo')
		{
			return $this->getOption(self::OPTION_DEV_OUTPUT_DIR);
		}
		else
		{
			return '';
		}
	}

	/**
	* Writes the development file output to the specified directory. This will write
	* each template into an individual file for easier tracking in source control.
	*
	* @param string Path to directory to write to
	*/
	protected function _writeDevFileOutput($dir)
	{
		$title = $this->get('title');
		$newFile = $dir . '/' . $title . '.txt';

		if (!is_dir($dir) || !is_writable($dir))
		{
			throw new XenForo_Exception("Phrase development directory $dir is not writable");
		}

		$fp = fopen($newFile, 'w');
		fwrite($fp, $this->get('phrase_text'));
		fclose($fp);

		$this->_writeMetaDataDevFileOutput($dir, $title, $this->getMergedData());

		if ($this->isUpdate() && $this->isChanged('title'))
		{
			$this->_deleteExistingDevFile($dir);
		}
	}

	protected function _writeMetaDataDevFileOutput($dir, $title, $data)
	{
		$metaDataFile = $dir . '/_metadata.xml';
		XenForo_Helper_DevelopmentXml::writeMetaDataOutput(
			$metaDataFile, $title, $data, array('global_cache', 'version_id', 'version_string')
		);
	}

	protected function _recompilePhrase()
	{
		$this->_getPhraseModel()->compileNamedPhraseInLanguageTree($this->get('title'), $this->get('language_id'));
	}

	/**
	 * Recompiles all templates (admin and public) that include this phrase.
	 */
	protected function _recompileTemplatesIncludingPhrase()
	{
		XenForo_Template_Compiler::resetPhraseCache();

		$templateModel = $this->_getTemplateModel();
		$adminTemplateModel = $this->_getAdminTemplateModel();
		$emailTemplateModel = $this->_getEmailTemplateModel();

		$title = $this->get('title');

		$templateModel->compileTemplatesThatIncludePhrase($title);
		$adminTemplateModel->compileAdminTemplatesThatIncludePhrase($title);
		$emailTemplateModel->compileEmailTemplatesThatIncludePhrase($title);

		if ($this->isChanged('title') && $this->isUpdate())
		{
			$existingTitle = $this->getExisting('title');

			$templateModel->compileTemplatesThatIncludePhrase($existingTitle);
			$adminTemplateModel->compileAdminTemplatesThatIncludePhrase($existingTitle);
			$emailTemplateModel->compileEmailTemplatesThatIncludePhrase($existingTitle);
		}
	}

	protected function _buildPhraseMap()
	{
		$phraseModel = $this->_getPhraseModel();
		$phraseModel->buildPhraseMap($this->get('phrase_id'), $this->get('language_id'), $this->get('title'));
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		$dataChanged = $this->_deleteMappedData();
		if ($dataChanged && $this->getOption(self::OPTION_FULL_RECOMPILE))
		{
			$this->_recompilePhrase();
		}

		if ($this->getOption(self::OPTION_FULL_RECOMPILE))
		{
			$this->_recompileTemplatesIncludingPhrase();
		}

		$this->_rebuildLanguageCaches();

		if ($devDir = $this->_getDevOutputDir())
		{
			$this->_deleteExistingDevFile($devDir);
		}
	}

	protected function _deleteMappedData()
	{
		$phraseModel = $this->_getPhraseModel();

		$mappedPhrases = $phraseModel->getMappedPhrasesByPhraseId($this->get('phrase_id'));
		if ($mappedPhrases)
		{
			$myPhraseMapId = 0;
			$phraseMapIds = array();
			$languageIds = array();
			foreach ($mappedPhrases AS $mappedPhrase)
			{

				if ($mappedPhrase['language_id'] == $this->get('language_id'))
				{
					$myPhraseMapId = $mappedPhrase['phrase_map_id'];
				}

				$phraseMapIds[] = $mappedPhrase['phrase_map_id'];
				$languageIds[] = $mappedPhrase['language_id'];
			}

			$phraseMapIdsQuoted = $this->_db->quote($phraseMapIds);

			$parentMappedPhrase = $phraseModel->getParentMappedPhraseByTitle($this->get('title'), $this->get('language_id'));

			if ($parentMappedPhrase)
			{
				// point everything pointing at this phrase to the parent
				$this->_db->update('xf_phrase_map',
					array('phrase_id' => $parentMappedPhrase['phrase_id']),
					'phrase_map_id IN (' . $phraseMapIdsQuoted . ')'
				);
				return true;
			}
			else
			{
				// no parent, remove phrase - this should primarily happen when deleting a master or custom phrase
				$this->_db->delete('xf_phrase_map', 'phrase_map_id IN (' . $phraseMapIdsQuoted . ')');
				$this->_db->delete('xf_phrase_compiled',
					'language_id IN (' . $this->_db->quote($languageIds) . ') AND title = ' . $this->_db->quote($this->get('title'))
				);
			}
		}

		return false;
	}

	/**
	 * Deletes the corresponding file when a template is deleted from the database
	 *
	 * @param string Path to admin templates directory
	 */
	protected function _deleteExistingDevFile($dir)
	{
		$existingTitle = $this->getExisting('title');
		$fileName = $dir . '/' . $existingTitle . '.txt';

		if (file_exists($fileName))
		{
			if (!is_writable($fileName))
			{
				throw new XenForo_Exception("Phrase development file $dir is not writable");
			}
			unlink($fileName);

			$this->_writeMetaDataDevFileOutput($dir, $existingTitle, false);
		}
	}

	/**
	 * Gets the language model object.
	 *
	 * @return XenForo_Model_Language
	 */
	protected function _getLanguageModel()
	{
		return $this->getModelFromCache('XenForo_Model_Language');
	}

	/**
	 * Gets the template model.
	 *
	 * @return XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}

	/**
	 * Gets the admin template model.
	 *
	 * @return XenForo_Model_AdminTemplate
	 */
	protected function _getAdminTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminTemplate');
	}

	/**
	 * @return XenForo_Model_EmailTemplate
	 */
	protected function _getEmailTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_EmailTemplate');
	}
}