<?php

/**
* Data writer for email templates.
*
* @package XenForo_EmailTemplate
*/
class XenForo_DataWriter_EmailTemplate extends XenForo_DataWriter
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
	protected $_existingDataErrorPhrase = 'requested_email_template_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_email_template' => array(
				'template_id'      => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'title'            => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'verification' => array('$this', '_verifyTitle'), 'requiredError' => 'please_enter_valid_title'
				),
				'custom'           => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'subject'          => array('type' => self::TYPE_STRING, 'verification' => array('$this', '_verifySubject'), 'noTrim' => true),
				'subject_parsed'   => array('type' => self::TYPE_BINARY),
				'body_text'        => array('type' => self::TYPE_STRING, 'verification' => array('$this', '_verifyBodyText'), 'noTrim' => true),
				'body_text_parsed' => array('type' => self::TYPE_BINARY),
				'body_html'        => array('type' => self::TYPE_STRING, 'verification' => array('$this', '_verifyBodyHtml'), 'noTrim' => true),
				'body_html_parsed' => array('type' => self::TYPE_BINARY),
				'addon_id'         => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => '')
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

		return array('xf_email_template' => $this->_getEmailTemplateModel()->getEmailTemplateById($templateId));
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
			$options[self::OPTION_DEV_OUTPUT_DIR] = $this->_getEmailTemplateModel()->getEmailTemplateDevelopmentDirectory();
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
	protected function _verifyTitle(&$title)
	{
		if (preg_match('/[^a-zA-Z0-9_\.]/', $title))
		{
			$this->error(new XenForo_Phrase('please_enter_title_using_only_alphanumeric_dot'), 'title');
			return false;
		}

		return true;
	}

	/**
	* Verification callback for an email template subject.
	*
	* @param string Uncompiled subject
	*
	* @return boolean
	*/
	protected function _verifySubject($subject)
	{
		try
		{
			$compiler = new XenForo_Template_Compiler_Email($subject);
			$parsed = $compiler->lexAndParse();

			if ($this->getOption(self::OPTION_TEST_COMPILE))
			{
				$compiler->setFollowExternal(false);
				$compiler->compileParsed($parsed, $this->get('title'), 0, 0);
			}
		}
		catch (XenForo_Template_Compiler_Exception $e)
		{
			$this->error($e->getMessage(), 'subject');
			return false;
		}

		$this->set('subject_parsed', serialize($parsed));
		return true;
	}

	/**
	* Verification callback for an email template plain text body.
	*
	* @param string Uncompiled body
	*
	* @return boolean
	*/
	protected function _verifyBodyText($body)
	{
		try
		{
			$compiler = new XenForo_Template_Compiler_Email($body);
			$parsed = $compiler->lexAndParse();

			if ($this->getOption(self::OPTION_TEST_COMPILE))
			{
				$compiler->setFollowExternal(false);
				$compiler->compileParsed($parsed, $this->get('title'), 0, 0);
			}
		}
		catch (XenForo_Template_Compiler_Exception $e)
		{
			$this->error($e->getMessage(), 'body_text');
			return false;
		}

		$this->set('body_text_parsed', serialize($parsed));
		return true;
	}

	/**
	* Verification callback for an email template plain text body.
	*
	* @param string Uncompiled body
	*
	* @return boolean
	*/
	protected function _verifyBodyHtml($body)
	{
		try
		{
			$compiler = new XenForo_Template_Compiler_Email($body);
			$parsed = $compiler->lexAndParse();

			if ($this->getOption(self::OPTION_TEST_COMPILE))
			{
				$compiler->setFollowExternal(false);
				$compiler->compileParsed($parsed, $this->get('title'), 0, 0);
			}
		}
		catch (XenForo_Template_Compiler_Exception $e)
		{
			$this->error($e->getMessage(), 'body_html');
			return false;
		}

		$this->set('body_html_parsed', serialize($parsed));
		return true;
	}

	/**
	 * Pre-save handler.
	 */
	protected function _preSave()
	{
		if ($this->isChanged('title') || $this->isChanged('custom'))
		{
			$existingTemplate = $this->_getEmailTemplateModel()->getEmailTemplateByTitleAndType(
				$this->get('title'), $this->get('custom')
			);
			if ($existingTemplate)
			{
				$this->error(new XenForo_Phrase('template_titles_must_be_unique'), 'title');
			}
		}
	}

	/**
	* Post-save handler.
	*/
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_FULL_COMPILE))
		{
			XenForo_Template_Compiler_Email::removeTemplateFromCache($this->get('title'));
			XenForo_Template_Compiler_Email::removeTemplateFromCache($this->getExisting('title'));

			$this->_recompileTemplate();
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
		$this->_getEmailTemplateModel()->compileAndInsertNamedEmailTemplate($this->get('title'));
	}

	/**
	 * Helper to get the developer data output directory only if it is enabled
	 * and applicable to this situation.
	 *
	 * @return string
	 */
	protected function _getDevOutputDir()
	{
		if ($this->get('addon_id') == 'XenForo' && $this->get('custom') == 0)
		{
			return $this->getOption(self::OPTION_DEV_OUTPUT_DIR);
		}
		else
		{
			return '';
		}
	}

	/**
	* Writes the development files output to the specified directory. This will write
	* each template into an individual file for easier tracking in source control.
	*
	* @param string Path to directory to write to
	*/
	protected function _writeDevFileOutput($dir)
	{
		if (!is_dir($dir) || !is_writable($dir))
		{
			throw new XenForo_Exception("Email template development directory $dir is not writable");
		}

		$filePrefix = $dir . '/' . $this->get('title');
		$files = array(
			$filePrefix . '.subject.txt' => $this->get('subject'),
			$filePrefix . '.text.txt' => $this->get('body_text'),
			$filePrefix . '.html.txt' => $this->get('body_html'),
		);

		foreach ($files AS $filePath => $data)
		{
			$fp = fopen($filePath, 'w');
			if ($fp)
			{
				fwrite($fp, $data);
				fclose($fp);
			}
		}

		if ($this->isUpdate() && $this->isChanged('title'))
		{
			$this->_deleteExistingDevOutput($dir);
		}
	}

	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		if ($this->get('custom'))
		{
			$this->_recompileTemplate();
		}
		else
		{
			$titleQuoted = $this->_db->quote($this->get('title'));

			$this->_db->delete('xf_email_template', 'title = ' . $titleQuoted);
			$this->_db->delete('xf_email_template_compiled', 'title = ' . $titleQuoted);
			$this->_db->delete('xf_email_template_phrase', 'title = ' . $titleQuoted);
		}

		if ($devDir = $this->_getDevOutputDir())
		{
			$this->_deleteExistingDevOutput($devDir);
		}
	}

	/**
	 * Deletes the corresponding files when a template is deleted from the database
	 *
	 * @param string Path to email templates directory
	 */
	protected function _deleteExistingDevOutput($dir)
	{
		$filePrefix = $dir . '/' . $this->getExisting('title');
		$files = array(
			$filePrefix . '.subject.txt',
			$filePrefix . '.text.txt',
			$filePrefix . '.html.txt',
		);

		// ensure all files are writable before unlinking any
		foreach ($files AS $file)
		{
			if (file_exists($file) && !is_writable($file))
			{
				throw new XenForo_Exception("Email template development file $dir is not writable");
			}
		}

		foreach ($files AS $file)
		{
			if (file_exists($file) && is_writable($file))
			{
				unlink($file);
			}
		}
	}

	/**
	 * @return XenForo_Model_EmailTemplate
	 */
	protected function _getEmailTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_EmailTemplate');
	}
}