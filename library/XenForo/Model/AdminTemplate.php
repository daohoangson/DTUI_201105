<?php

/**
 * Model for admin templates.
 *
 * @package XenForo_AdminTemplates
 */
class XenForo_Model_AdminTemplate extends XenForo_Model
{
	/**
	 * Returns all admin template titles in alphabetical order
	 *
	 * @return array Format: template_id => title
	 */
	public function getAllAdminTemplateTitles()
	{
		return $this->_getDb()->fetchAll('
			SELECT template.template_id, template.title,
				addon.addon_id, addon.title AS addonTitle
			FROM xf_admin_template AS template
				LEFT JOIN xf_addon AS addon ON (addon.addon_id = template.addon_id)
			ORDER BY template.title ASC
		');
	}

	/**
	 * Returns all admin templates in alphabetical title order
	 *
	 * @return array Format: [] => (array) template
	 */
	public function getAllAdminTemplates()
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_admin_template
			ORDER BY title ASC
		');
	}

	/**
	 * Returns all admin templates that include the specified admin template
	 *
	 * @param integer $templateId
	 *
	 * @return array Format: [] => (array) template
	 */
	public function getDependentAdminTemplates($templateId)
	{
		return $this->_getDb()->fetchAll('
			SELECT admin_template.*
			FROM xf_admin_template AS admin_template
			INNER JOIN xf_admin_template_include AS include ON
				(admin_template.template_id = include.source_id)
			WHERE include.target_id = ?
		', $templateId);
	}

	/**
	 * Returns the admin template specified by template_id
	 *
	 * @param integer Template id
	 * @return array Template
	 */
	public function getAdminTemplateById($template_id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_admin_template
			WHERE template_id = ?
		', $template_id);
	}

	/**
	 * Returns the admin template specified by its title
	 *
	 * @param string Template title
	 *
	 * @return array Template
	 */
	public function getAdminTemplateByTitle($title)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_admin_template
			WHERE title = ?
		', $title);
	}

	/**
	 * Returns all the admin templates specified by the array of titles provided
	 *
	 * @param array $titles
	 *
	 * @return array Templates
	 */
	public function getAdminTemplatesByTitles(array $titles)
	{
		if (empty($titles))
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_admin_template
			WHERE title IN(' . $this->_getDb()->quote($titles) . ')
			ORDER BY title ASC
		', 'title');
	}

	/**
	 * Returns all the admin templates that belong to the specified add-on
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [title] => info
	 */
	public function getAdminTemplatesByAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_admin_template
			WHERE addon_id = ?
			ORDER BY title ASC
		', 'title', $addOnId);
	}

	/**
	 * Gets information on all admin templates that include the named phrase.
	 *
	 * @param string $phraseTitle
	 *
	 * @return array Format: [] => admin template info
	 */
	public function getAdminTemplatesThatIncludePhrase($phraseTitle)
	{
		return $this->_getDb()->fetchAll('
			SELECT admin_template.*
			FROM xf_admin_template_phrase AS template_phrase
			INNER JOIN xf_admin_template AS admin_template ON
				(admin_template.template_id = template_phrase.template_id)
			WHERE template_phrase.phrase_title = ?
		', $phraseTitle);
	}

	/**
	 * Gets all template ID and title pairs that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [tempalte id] => title
	 */
	public function getAdminTemplateTitlesByAddOn($addOnId)
	{
		return $this->_getDb()->fetchPairs('
			SELECT template_id, title
			FROM xf_admin_template
			WHERE addon_id = ?
		', $addOnId);
	}

	/**
	 * Returns the path to the admin template development directory, if it has been configured and exists
	 *
	 * @return string Path to admin template directory
	 */
	public function getAdminTemplateDevelopmentDirectory()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/admin_templates';
	}

	/**
	 * Checks that the admin templates directory has been configured and exists
	 *
	 * @return boolean
	 */
	public function canImportAdminTemplatesFromDevelopment()
	{
		$dir = $this->getAdminTemplateDevelopmentDirectory();
		return ($dir && is_dir($dir));
	}

	/**
	 * Deletes the admin templates that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteAdminTemplatesForAddOn($addOnId)
	{
		$templateTitles = $this->getAdminTemplateTitlesByAddOn($addOnId);
		$templateIds = array_keys($templateTitles);

		if ($templateTitles)
		{
			$db = $this->_getDb();
			$quotedIds = $db->quote($templateIds);

			$db->delete('xf_admin_template', "template_id IN ($quotedIds)");
			$db->delete('xf_admin_template_compiled', 'title IN (' . $db->quote($templateTitles) . ')');
			$db->delete('xf_admin_template_include', "source_id IN ($quotedIds)");
			$db->delete('xf_admin_template_phrase', "template_id IN ($quotedIds)");
		}

		XenForo_Template_Compiler_Admin::resetTemplateCache();
	}

	/**
	 * Imports all admin templates from the admin templates directory into the database
	 */
	public function importAdminTemplatesFromDevelopment()
	{
		$db = $this->_getDb();

		$templateDir = $this->getAdminTemplateDevelopmentDirectory();
		if (!$templateDir && !is_dir($templateDir))
		{
			throw new XenForo_Exception("Admin template development directory not enabled or doesn't exist");
		}

		$files = glob("$templateDir/*.html");
		if (!$files)
		{
			throw new XenForo_Exception("Admin template development directory does not have any templates");
		}

		XenForo_Db::beginTransaction($db);
		$this->deleteAdminTemplatesForAddOn('XenForo');

		$titles = array();
		foreach ($files AS $templateFile)
		{
			$filename = basename($templateFile);
			if (preg_match('/^(.+)\.html$/', $filename, $match))
			{
				$titles[] = $match[1];
			}
		}

		$existingTemplates = $this->getAdminTemplatesByTitles($titles);

		foreach ($files AS $templateFile)
		{
			if (!is_readable($templateFile))
			{
				throw new XenForo_Exception("Template file '$templateFile' not readable");
			}

			$filename = basename($templateFile);
			if (preg_match('/^(.+)\.html$/', $filename, $match))
			{
				$templateName = $match[1];
				$data = file_get_contents($templateFile);

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
				if (isset($existingTemplates[$templateName]))
				{
					$dw->setExistingData($existingTemplates[$templateName], true);
				}
				$dw->setOption(XenForo_DataWriter_AdminTemplate::OPTION_DEV_OUTPUT_DIR, '');
				$dw->setOption(XenForo_DataWriter_AdminTemplate::OPTION_FULL_COMPILE, false);
				$dw->setOption(XenForo_DataWriter_AdminTemplate::OPTION_TEST_COMPILE, false);
				$dw->set('title', $templateName);
				$dw->set('template', $data);
				$dw->set('addon_id', 'XenForo');

				try
				{
					$dw->save();
				}
				catch (Exception $e)
				{
					throw new XenForo_Exception("Template '$templateName' not imported." . "\n" . $e->getMessage() . "\n" . $e->getTraceAsString());
				}
			}
		}

		$this->compileAllParsedAdminTemplates();

		XenForo_Db::commit($db);
	}

	/**
	 * Imports the add-on admin templates XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the data
	 * @param string $addOnId Add-on to import for
	 * @param integer $maxExecution Maximum run time in seconds
	 * @param integer $offset Number of elements to skip
	 *
	 * @return boolean|integer True on completion; false if the XML isn't correct; integer otherwise with new offset value
	 */
	public function importAdminTemplatesAddOnXml(SimpleXMLElement $xml, $addOnId, $maxExecution = 0, $offset = 0)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$startTime = microtime(true);

		if ($offset == 0)
		{
			$this->deleteAdminTemplatesForAddOn($addOnId);
		}

		$templates = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->template);

		$titles = array();
		$current = 0;
		foreach ($templates AS $template)
		{
			$current++;
			if ($current <= $offset)
			{
				continue;
			}
			$titles[] = (string)$template['title'];
		}

		$existingTemplates = $this->getAdminTemplatesByTitles($titles);

		$current = 0;
		$restartOffset = false;
		foreach ($templates AS $template)
		{
			$current++;
			if ($current <= $offset)
			{
				continue;
			}

			$templateName = (string)$template['title'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			if (isset($existingTemplates[$templateName]))
			{
				$dw->setExistingData($existingTemplates[$templateName], true);
			}
			$dw->setOption(XenForo_DataWriter_AdminTemplate::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_AdminTemplate::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_AdminTemplate::OPTION_TEST_COMPILE, false);
			$dw->set('title', $templateName);
			$dw->set('template', (string)$template);
			$dw->set('addon_id', $addOnId);
			$dw->save();

			if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
			{
				$restartOffset = $current;
				break;
			}
		}

		XenForo_Db::commit($db);

		return ($restartOffset ? $restartOffset : true);
	}

	/**
	 * Appends the add-on admin template XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendAdminTemplatesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		$templates = $this->getAdminTemplatesByAddOn($addOnId);
		foreach ($templates AS $template)
		{
			$templateNode = $document->createElement('template');
			$templateNode->setAttribute('title', $template['title']);
			$templateNode->appendChild($document->createCDATASection($template['template']));

			$rootNode->appendChild($templateNode);
		}
	}

	/**
	 * Gets the admin templates development XML.
	 *
	 * @return DOMDocument
	 */
	public function getAdminTemplatesDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('admin_templates');
		$document->appendChild($rootNode);

		$this->appendAdminTemplatesAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Updates the last modified date of the admin style.
	 */
	public function updateAdminStyleLastModifiedDate()
	{
		$this->_getDataRegistryModel()->set('adminStyleModifiedDate', XenForo_Application::$time);
	}

	/**
	 * Compiles all admin templates
	 */
	public function compileAllParsedAdminTemplates()
	{
		$this->_getDb()->query('TRUNCATE TABLE xf_admin_template_compiled');

		$templates = $this->getAllAdminTemplates();
		$this->compileParsedAdminTemplates($templates);

		$this->updateAdminStyleLastModifiedDate();
	}

	/**
	 * Compiles the specified, pre-parsed admin templates.
	 *
	 * @param array $templates
	 */
	public function compileParsedAdminTemplates(array $templates)
	{
		if (!$templates)
		{
			return;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($templates AS $template)
		{
			$this->compileParsedAdminTemplate(
				$template['template_id'], unserialize($template['template_parsed']), $template['title']
			);
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Compiles any admin templates that include the specified phrase title.
	 *
	 * @param string $phraseTitle
	 */
	public function compileAdminTemplatesThatIncludePhrase($phraseTitle)
	{
		$templates = $this->getAdminTemplatesThatIncludePhrase($phraseTitle);
		$this->compileParsedAdminTemplates($templates);
	}

	/**
	 * Compiles a single admin template
	 *
	 * @param array Template
	 */
	public function compileParsedAdminTemplate($templateId, array $parsedTemplate, $title)
	{
		$isCss = (substr($title, -4) == '.css');

		$languages = $this->getModelFromCache('XenForo_Model_Language')->getAllLanguages();
		$db = $this->_getDb();

		$compiler = new XenForo_Template_Compiler_Admin('');

		if ($isCss)
		{
			$compiledTemplate = $compiler->compileParsed($parsedTemplate, $title, 0, 0);
			$db->query('
				INSERT INTO xf_admin_template_compiled
					(language_id, title, template_compiled)
				VALUES
					(?, ?, ?)
				ON DUPLICATE KEY UPDATE template_compiled = VALUES(template_compiled)
			', array(0, $title, $compiledTemplate));
		}
		else
		{
			foreach ($languages AS $language)
			{
				$compiledTemplate = $compiler->compileParsed($parsedTemplate, $title, 0, $language['language_id']);
				$db->query('
					INSERT INTO xf_admin_template_compiled
						(language_id, title, template_compiled)
					VALUES
						(?, ?, ?)
					ON DUPLICATE KEY UPDATE template_compiled = VALUES(template_compiled)
				', array($language['language_id'], $title, $compiledTemplate));
			}
		}

		$db->delete('xf_admin_template_include', 'source_id = ' . $db->quote($templateId));
		foreach ($compiler->getIncludedTemplates() AS $includedId)
		{
			$db->insert('xf_admin_template_include', array(
				'source_id' => $templateId,
				'target_id' => $includedId
			));

			//TODO: this system doesn't handle includes for templates that don't exist yet
		}

		$db->delete('xf_admin_template_phrase', 'template_id = ' . $db->quote($templateId));
		foreach ($compiler->getIncludedPhrases() AS $includedPhrase)
		{
			$db->insert('xf_admin_template_phrase', array(
				'template_id' => $templateId,
				'phrase_title' => $includedPhrase
			));
		}
	}
}