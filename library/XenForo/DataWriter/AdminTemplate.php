<?php

/**
* Data writer for admin templates.
*
* @package XenForo_AdminTemplates
*/
class XenForo_DataWriter_AdminTemplate extends XenForo_DataWriter
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
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_admin_template_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_admin_template' => array(
				'template_id'       => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'title'             => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'verification' => array('$this', '_verifyPrepareTitle'), 'requiredError' => 'please_enter_valid_title'
				),
				'template'          => array('type' => self::TYPE_STRING, 'verification' => array('$this', '_verifyPrepareTemplate'), 'noTrim' => true),
				'template_parsed'   => array('type' => self::TYPE_BINARY),
				'addon_id'          => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => '')
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
		if (!$templateId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_admin_template' => $this->getModelFromCache('XenForo_Model_AdminTemplate')->getAdminTemplateById($templateId));
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
			self::OPTION_TEST_COMPILE => true
		);

		$config = XenForo_Application::get('config');
		if ($config->debug)
		{
			$options[self::OPTION_DEV_OUTPUT_DIR] = $this->getModelFromCache('XenForo_Model_AdminTemplate')->getAdminTemplateDevelopmentDirectory();
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
	protected function _verifyPrepareTitle(&$title)
	{
		if (preg_match('/[^a-zA-Z0-9_\.]/', $title))
		{
			$this->error(new XenForo_Phrase('please_enter_title_using_only_alphanumeric_dot'), 'title');
			return false;
		}

		if ($this->isInsert() || $title != $this->getExisting('title'))
		{
			$titleConflict = $this->_getAdminTemplateModel()->getAdminTemplateByTitle($title);
			if ($titleConflict)
			{
				$this->error(new XenForo_Phrase('template_titles_must_be_unique'), 'title');
				return false;
			}
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
			$compiler = new XenForo_Template_Compiler_Admin($template);
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
	 * Pre-save handler.
	 */
	protected function _preSave()
	{
		if ($this->isInsert() && !$this->isChanged('template') && !$this->getError('template'))
		{
			$this->error(new XenForo_Phrase('template_value_has_not_been_set_properly'), 'template');
		}
	}

	/**
	* Post-save handler.
	*/
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('title'))
		{
			$this->_db->delete('xf_admin_template_compiled', 'title = ' . $this->_db->quote($this->getExisting('title')));
			$this->_db->delete('xf_admin_template_include', 'source_id = ' . $this->_db->quote($this->get('template_id')));
			$this->_db->delete('xf_admin_template_phrase', 'template_id = ' . $this->_db->quote($this->get('template_id')));
		}

		if ($this->getOption(self::OPTION_FULL_COMPILE))
		{
			XenForo_Template_Compiler_Admin::removeTemplateFromCache($this->get('title'));
			XenForo_Template_Compiler_Admin::removeTemplateFromCache($this->getExisting('title'));

			$this->_recompileTemplate();
			$this->_recompileDependentTemplates();

			$this->_getAdminTemplateModel()->updateAdminStyleLastModifiedDate();
		}

		if ($devDir = $this->_getDevOutputDir())
		{
			$this->_writeDevFileOutput($devDir);
		}
	}

	/**
	 * Recompiles this template.
	 */
	protected function _recompileTemplate()
	{
		$model = $this->_getAdminTemplateModel();
		$model->compileParsedAdminTemplate(
			$this->get('template_id'), unserialize($this->get('template_parsed')), $this->get('title')
		);
	}

	/**
	* Recompiles any dependent templates (templates that include this template) to
	* reflect changes to this template.
	*/
	protected function _recompileDependentTemplates()
	{
		$model = $this->_getAdminTemplateModel();
		$templates = $model->getDependentAdminTemplates($this->get('template_id'));

		foreach ($templates AS $template)
		{
			$model->compileParsedAdminTemplate(
				$template['template_id'], unserialize($template['template_parsed']), $template['title']
			);
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
		if ($this->get('addon_id') == 'XenForo')
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
		$newFile = $dir . '/' . $this->get('title') . '.html';

		if (!is_dir($dir) || !is_writable($dir))
		{
			throw new XenForo_Exception("Admin template development directory $dir is not writable");
		}

		$fp = fopen($newFile, 'w');
		fwrite($fp, $this->get('template'));
		fclose($fp);

		if ($this->isUpdate() && $this->isChanged('title'))
		{
			$this->_deleteExistingDevFile($dir);
		}
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		$this->_db->delete('xf_admin_template_compiled', 'title = ' . $this->_db->quote($this->get('title')));
		$this->_db->delete('xf_admin_template_include', 'source_id = ' . $this->_db->quote($this->get('template_id')));
		$this->_db->delete('xf_admin_template_phrase', 'template_id = ' . $this->_db->quote($this->get('template_id')));

		if ($this->getOption(self::OPTION_FULL_COMPILE))
		{
			$this->_recompileDependentTemplates();

			$this->_getAdminTemplateModel()->updateAdminStyleLastModifiedDate();
		}

		if ($devDir = $this->_getDevOutputDir())
		{
			$this->_deleteExistingDevFile($devDir);
		}
	}

	/**
	 * Deletes the corresponding file when a template is deleted from the database
	 *
	 * @param string Path to admin templates directory
	 */
	protected function _deleteExistingDevFile($dir)
	{
		$templateFile = $dir . '/' . $this->getExisting('title') . '.html';

		if (file_exists($templateFile))
		{
			if (!is_writable($templateFile))
			{
				throw new XenForo_Exception("Admin template development file $dir is not writable");
			}
			unlink($templateFile);
		}
	}

	/**
	 * Gets the admin template model object.
	 *
	 * @return XenForo_Model_AdminTemplate
	 */
	protected function _getAdminTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminTemplate');
	}
}