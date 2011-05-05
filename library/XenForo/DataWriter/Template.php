<?php

/**
* Data writer for templates.
*
* @package XenForo_Template
*/
class XenForo_DataWriter_Template extends XenForo_DataWriter
{
	/**
	 * Option that takes the path to the development template output directory.
	 * If not specified, output will not be written. Defaults determined based
	 * on config settings.
	 *
	 * @var string
	 */
	const OPTION_DEV_OUTPUT_DIR = 'devOutputDir';

	/**
	 * Option that controls whether a full compile is performed when the template
	 * is modified. If false, the template is only parsed into segments. Defaults
	 * to true, but should be set to false for bulk imports; compilation should
	 * happen in the second pass.
	 *
	 * @var string
	 */
	const OPTION_FULL_COMPILE = 'fullCompile';

	/**
	 * Option that controls whether a test compile will be performed when setting
	 * the value of a template. If false, the error will only be detected when a
	 * full compile is done.
	 *
	 * Note that this does not prevent the template from being parsed. That will
	 * always happen.
	 *
	 * @var string
	 */
	const OPTION_TEST_COMPILE = 'testCompile';

	/**
	 * Option that controls if template map is rebuild when template is changed. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_REBUILD_TEMPLATE_MAP = 'rebuildTemplateMap';

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
	protected $_existingDataErrorPhrase = 'requested_template_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_template' => array(
				'template_id'       => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'title'             => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'verification' => array('$this', '_verifyPrepareTitle'), 'requiredError' => 'please_enter_valid_title'),
				'style_id'          => array('type' => self::TYPE_UINT,   'required' => true),
				'template'          => array('type' => self::TYPE_STRING, 'verification' => array('$this', '_verifyPrepareTemplate'), 'noTrim' => true),
				'template_parsed'   => array('type' => self::TYPE_BINARY),
				'addon_id'          => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
				'version_id'        => array('type' => self::TYPE_UINT,   'default' => 0),
				'version_string'    => array('type' => self::TYPE_STRING, 'maxLength' => 30, 'default' => '')
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
		if (!$template_id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_template' => $this->_getTemplateModel()->getTemplateById($template_id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'template_id = ' . $this->_db->quote($this->getExisting('template_id'));
	}

	/**
	* Gets the default set of options for this data writer.
	* If in debug mode and we have a development directory config, we set the template
	* dev output directory automatically.
	*
	* @return array
	*/
	protected function _getDefaultOptions()
	{
		$options = array(
			self::OPTION_DEV_OUTPUT_DIR => '',
			self::OPTION_FULL_COMPILE => true,
			self::OPTION_TEST_COMPILE => true,
			self::OPTION_REBUILD_TEMPLATE_MAP => true,
			self::OPTION_CHECK_DUPLICATE => true
		);

		$config = XenForo_Application::get('config');
		if ($config->debug)
		{
			$options[self::OPTION_DEV_OUTPUT_DIR] = $this->_getTemplateModel()->getTemplateDevelopmentDirectory();
		}

		return $options;
	}

	/**
	 * Verifies that the provided template title contains only valid characters
	 *
	 * @param string Title
	 *
	 * @return boolean
	 */
	protected function _verifyPrepareTitle($title)
	{
		if (preg_match('/[^a-zA-Z0-9_\.]/', $title))
		{
			$this->error(new XenForo_Phrase('please_enter_title_using_only_alphanumeric_dot'), 'title');
			return false;
		}

		return true;
	}

	/**
	* Verification callback to prepare a template. This isn't actually a verifier;
	* it just automatically compiles the template.
	*
	* @param string Uncompiled template
	*
	* @return true
	*/
	protected function _verifyPrepareTemplate($template)
	{
		try
		{
			$compiler = new XenForo_Template_Compiler($template);
			$parsed = $compiler->lexAndParse();

			if ($this->getOption(self::OPTION_TEST_COMPILE))
			{
				$compiler->setFollowExternal(false);
				$compiler->compileParsed($parsed, $this->get('title'), 0, 0);
			}
		}
		catch (XenForo_Template_Compiler_Exception $e)
		{
			$this->error($e->getMessage(), 'template');
			return false;
		}

		$this->set('template_parsed', serialize($parsed));
		return true;
	}

	/**
	 * Helper to get the developer data output directory only if it is enabled
	 * and applicable to this situation.
	 *
	 * @return string
	 */
	protected function _getDevOutputDir()
	{
		if ($this->get('style_id') == 0 && $this->get('addon_id') == 'XenForo')
		{
			return $this->getOption(self::OPTION_DEV_OUTPUT_DIR);
		}
		else
		{
			return '';
		}
	}

	/**
	 * Verifies that the specified title is not a duplicate
	 */
	protected function _preSave()
	{
		if ($this->isInsert() && !$this->isChanged('template') && !$this->getError('template'))
		{
			$this->error(new XenForo_Phrase('template_value_has_not_been_set_properly'), 'template');
		}

		if ($this->getOption(self::OPTION_CHECK_DUPLICATE))
		{
			if ($this->isInsert() || $this->get('title') != $this->getExisting('title'))
			{
				$titleConflict = $this->_getTemplateModel()->getTemplateInStyleByTitle($this->getNew('title'), $this->get('style_id'));
				if ($titleConflict)
				{
					$this->error(new XenForo_Phrase('template_titles_must_be_unique'), 'title');
				}
			}
		}
	}

	/**
	* Post-save handler.
	*/
	protected function _postSave()
	{
		$templateModel = $this->_getTemplateModel();

		if ($this->getOption(self::OPTION_REBUILD_TEMPLATE_MAP))
		{
			if ($this->isChanged('title'))
			{
				$templateModel->buildTemplateMap($this->get('title'));
				if ($existingTitle = $this->getExisting('title'))
				{
					if ($this->getOption(self::OPTION_FULL_COMPILE))
					{
						// need to recompile anything including this template
						$mappedTemplates = $templateModel->getMappedTemplatesByTemplateId($this->get('template_id'));
						$mappedTemplateIds = array();
						foreach ($mappedTemplates AS $mappedTemplate)
						{
							$mappedTemplateIds[] = $mappedTemplate['template_map_id'];
						}

						$templateModel->buildTemplateMap($existingTitle);

						$templateModel->compileMappedTemplatesInStyleTree(
							$templateModel->getIncludingTemplateMapIds($mappedTemplateIds)
						);
					}
					else
					{
						$templateModel->buildTemplateMap($existingTitle);
					}
				}
			}
			else if ($this->isChanged('style_id'))
			{
				$templateModel->buildTemplateMap($this->get('title'));
			}
		}

		if ($this->getOption(self::OPTION_FULL_COMPILE))
		{
			XenForo_Template_Compiler::removeTemplateFromCache($this->get('title'));
			XenForo_Template_Compiler::removeTemplateFromCache($this->getExisting('title'));

			$this->_recompileTemplate();

			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		}

		if ($devDir = $this->_getDevOutputDir())
		{
			$this->_writeDevFileOutput($devDir);
		}
	}

	/**
	 * Recompiles the changed template and any templates that include it.
	 */
	protected function _recompileTemplate()
	{
		$templateModel = $this->_getTemplateModel();

		$compiledMapIds = $templateModel->compileNamedTemplateInStyleTree($this->get('title'), $this->get('style_id'));
		$templateModel->compileMappedTemplatesInStyleTree($templateModel->getIncludingTemplateMapIds($compiledMapIds));
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
		$newFile = $dir . '/' . $title . '.html';

		if (!is_dir($dir) || !is_writable($dir))
		{
			throw new XenForo_Exception("Template development directory $dir is not writable");
		}

		$fp = fopen($newFile, 'w');
		fwrite($fp, $this->get('template'));
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
			$metaDataFile, $title, $data, array('version_id', 'version_string')
		);
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		$recompileTemplates = $this->_deleteMappedData();

		if ($recompileTemplates && $this->getOption(self::OPTION_FULL_COMPILE))
		{
			if ($recompileTemplates === true)
			{
				// new template still exists in this position -- recompile it and follow includes
				$this->_recompileTemplate();
			}
			else
			{
				// template no longer exists -- recompile includes
				$this->_getTemplateModel()->compileMappedTemplatesInStyleTree($recompileTemplates);
			}
		}

		if ($this->getOption(self::OPTION_FULL_COMPILE))
		{
			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		}

		if ($devDir = $this->_getDevOutputDir())
		{
			$this->_deleteExistingDevFile($devDir);
		}
	}

	/**
	 * Deletes mapped data (template map entries, includes, compiled info) and determines
	 * what templates (if any need to be recompiled). A deletion can be a "revert" or it
	 * can actually remove a template from the hierarchy.
	 *
	 * @return true|false|array If true, recompile this template and includes; if array, recompile specified map IDs; else, no recompile
	 */
	protected function _deleteMappedData()
	{
		$templateModel = $this->_getTemplateModel();

		$mappedTemplates = $templateModel->getMappedTemplatesByTemplateId($this->get('template_id'));
		if ($mappedTemplates)
		{
			$myTemplateMapId = 0;
			$templateMapIds = array();
			$styleIds = array();
			foreach ($mappedTemplates AS $mappedTemplate)
			{
				if ($mappedTemplate['style_id'] == $this->get('style_id'))
				{
					$myTemplateMapId = $mappedTemplate['template_map_id'];
				}

				$templateMapIds[] = $mappedTemplate['template_map_id'];
				$styleIds[] = $mappedTemplate['style_id'];
			}

			$templateMapIdsQuoted = $this->_db->quote($templateMapIds);

			$parentMappedTemplate = $templateModel->getParentMappedTemplateByTitle($this->get('title'), $this->get('style_id'));
			if ($parentMappedTemplate)
			{
				// point everything pointing at this template to the parent
				$this->_db->update('xf_template_map',
					array('template_id' => $parentMappedTemplate['template_id']),
					'template_map_id IN (' . $templateMapIdsQuoted . ')'
				);

				// template_include and template_compiled will be updated on a recompile
				return true;
			}
			else
			{
				// no parent, remove template - this should primarily happen when deleting a master or custom template
				$this->_db->delete('xf_template_map', 'template_map_id IN (' . $templateMapIdsQuoted . ')');
				$this->_db->delete('xf_template_phrase', 'template_map_id IN (' . $templateMapIdsQuoted . ')');
				$this->_db->delete('xf_template_include', 'source_map_id IN (' . $templateMapIdsQuoted . ')');
				$this->_db->delete('xf_template_compiled',
					'style_id IN (' . $this->_db->quote($styleIds) . ') AND title = ' . $this->_db->quote($this->get('title'))
				);

				if ($myTemplateMapId)
				{
					// need to recompile includes
					return $templateModel->getIncludingTemplateMapIds($myTemplateMapId);
				}
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
		$templateFile = $dir . '/' . $existingTitle . '.html';

		if (file_exists($templateFile))
		{
			if (!is_writable($templateFile))
			{
				throw new XenForo_Exception("Template development file $dir is not writable");
			}
			unlink($templateFile);

			$this->_writeMetaDataDevFileOutput($dir, $existingTitle, false);
		}
	}

	/**
	 * Gets the template model object.
	 *
	 * @return XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}
}