<?php

/**
 * Model for email template related data.
 *
 * @package XenForo_EmailTemplate
 */
class XenForo_Model_EmailTemplate extends XenForo_Model
{
	/**
	 * Gets the titles and IDs of the effective email templates that
	 * will be used. Customized templates will sit "on top" of master templates.
	 *
	 * Note that templates must exist in the master to be returned by this!
	 *
	 * @return array Format: [title] => info, not including subject or body text
	 */
	public function getAllEffectiveEmailTemplateTitles()
	{
		return $this->fetchAllKeyed('
			SELECT
				COALESCE(custom.template_id, master.template_id) AS template_id,
				master.title,
				COALESCE(custom.custom, master.custom) AS custom,
				master.addon_id,
				addon.title AS addonTitle
			FROM xf_email_template AS master
			LEFT JOIN xf_email_template AS custom ON
				(custom.title = master.title AND custom.custom = 1)
			LEFT JOIN xf_addon AS addon ON
				(addon.addon_id = master.addon_id)
			WHERE master.custom = 0
			ORDER BY master.title
		', 'title');
	}

	/**
	 * Gets the titles and IDs of all master email templates.
	 *
	 * @return array Format: [title] => info, not including subject or body text
	 */
	public function getAllMasterEmailTemplateTitles()
	{
		return $this->fetchAllKeyed('
			SELECT
				email_template.template_id, email_template.title, email_template.custom,
				addon.addon_id, addon.title AS addonTitle
			FROM xf_email_template AS email_template
			LEFT JOIN xf_addon AS addon ON
				(addon.addon_id = email_template.addon_id)
			WHERE custom = 0
			ORDER BY title
		', 'title');
	}

	/**
	 * Get the titles and IDs of all master email templates that belong to the
	 * specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [title] => info, not including subject or body text
	 */
	public function getMasterEmailTemplateTitlesByAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT template_id, title, custom, addon_id
			FROM xf_email_template
			WHERE addon_id = ?
				AND custom = 0
			ORDER BY title
		', 'title', $addOnId);
	}

	/**
	 * Get the master email templates that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [title] => info
	 */
	public function getMasterEmailTemplatesByAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_email_template
			WHERE addon_id = ?
				AND custom = 0
			ORDER BY title
		', 'title', $addOnId);
	}

	/**
	 * Gets the master email templates by a collection of titles.
	 *
	 * @param array $titles
	 *
	 * @return array Format: [title] => info
	 */
	public function getMasterEmailTemplatesByTitles(array $titles)
	{
		if (!$titles)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT template_id, title, custom, addon_id
			FROM xf_email_template
			WHERE title IN (' . $this->_getDb()->quote($titles) . ')
				AND custom = 0
			ORDER BY title
		', 'title');
	}

	/**
	 * Gets all effective email templates.
	 *
	 * @return array Format: [title] => info
	 */
	public function getAllEffectiveEmailTemplates()
	{
		// this relies on the ordering and later items overwriting
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_email_template
			ORDER BY custom, title
		', 'title');
	}

	/**
	 * Get the effective version of any templates that include the given phrase.
	 *
	 * @param string $phraseTitle

	 * @return array Format: [title] => info
	 */
	public function getEffectiveEmailTemplatesThatIncludePhrase($phraseTitle)
	{
		// TODO: this query will likely cause issues with mysqli not returning full data (or use lots of memory)
		return $this->fetchAllKeyed('
			SELECT
				COALESCE(custom.template_id, master.template_id) AS template_id,
				COALESCE(custom.title, master.title) AS title,
				COALESCE(custom.custom, master.custom) AS custom,
				COALESCE(custom.subject, master.subject) AS subject,
				COALESCE(custom.subject_parsed, master.subject_parsed) AS subject_parsed,
				COALESCE(custom.body_text, master.body_text) AS body_text,
				COALESCE(custom.body_text_parsed, master.body_text_parsed) AS body_text_parsed,
				COALESCE(custom.body_html, master.body_html) AS body_html,
				COALESCE(custom.body_html_parsed, master.body_html_parsed) AS body_html_parsed,
				COALESCE(custom.addon_id, master.addon_id) AS addon_id
			FROM xf_email_template_phrase AS template_phrase
			INNER JOIN xf_email_template AS master ON
				(master.title = template_phrase.title AND master.custom = 0)
			LEFT JOIN xf_email_template AS custom ON
				(custom.title = master.title AND custom.custom = 1)
			WHERE template_phrase.phrase_title = ?
			ORDER BY master.title
		', 'title', $phraseTitle);
	}

	/**
	 * Gets the email template with the given ID.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getEmailTemplateById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_email_template
			WHERE template_id = ?
		', $id);
	}

	/**
	 * Gets the effective email template for a given title. A customized
	 * version of a template will sit on top of the master version.
	 *
	 * @param string $title
	 *
	 * @return array|false
	 */
	public function getEffectiveEmailTemplateByTitle($title)
	{
		$db = $this->_getDb();

		return $db->fetchRow($db->limit(
			'
				SELECT *
				FROM xf_email_template
				WHERE title = ?
				ORDER BY custom DESC
			', 1
		), $title);
	}

	/**
	 * Gets the named template, based on its title and type (custom: 1/0).
	 *
	 * @param string $title
	 * @param integer $custom Custom, 1/0
	 *
	 * @return array|false
	 */
	public function getEmailTemplateByTitleAndType($title, $custom)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_email_template
			WHERE title = ?
				AND custom = ?
		', array($title, $custom));
	}

	/**
	 * Compiles the named email template and inserts the compiled output.
	 *
	 * @param string $title
	 */
	public function compileAndInsertNamedEmailTemplate($title)
	{
		$template = $this->getEffectiveEmailTemplateByTitle($title);
		if (!$template)
		{
			return;
		}

		$this->compileAndInsertEmailTemplate($template);
	}

	/**
	 * Compiles all email templates.
	 */
	public function compileAllEmailTemplates()
	{
		$this->_getDb()->query('TRUNCATE TABLE xf_email_template_compiled');

		$templates = $this->getAllEffectiveEmailTemplates();
		foreach ($templates AS $template)
		{
			$this->compileAndInsertEmailTemplate($template);
		}
	}

	/**
	 * Compiles all email templates that include the given phrase.
	 *
	 * @param string $phraseTitle
	 */
	public function compileEmailTemplatesThatIncludePhrase($phraseTitle)
	{
		$templates = $this->getEffectiveEmailTemplatesThatIncludePhrase($phraseTitle);
		foreach ($templates AS $template)
		{
			$this->compileAndInsertEmailTemplate($template);
		}
	}

	/**
	 * Compiles the given email template data and inserts the compiled output. This
	 * template is assumed to be the effective value for a given title.
	 *
	 * @param array $template Template info
	 */
	public function compileAndInsertEmailTemplate(array $template)
	{
		$languages = $this->getModelFromCache('XenForo_Model_Language')->getAllLanguages();
		$languages[0] = array('language_id' => 0);

		$db = $this->_getDb();
		$compiler = new XenForo_Template_Compiler_Email('');

		$templateId = $template['template_id'];
		$title = $template['title'];
		$subjectParsed = unserialize($template['subject_parsed']);
		$bodyTextParsed = unserialize($template['body_text_parsed']);
		$bodyHtmlParsed = unserialize($template['body_html_parsed']);

		$phrases = array();
		$checkPhrases = true;

		foreach ($languages AS $language)
		{
			$compiler->setOutputVar('__subject');
			$compiledTemplate = $compiler->compileParsedPlainText($subjectParsed, $title, 0, $language['language_id']);
			if ($checkPhrases)
			{
				$phrases = array_merge($phrases, $compiler->getIncludedPhrases());
			}

			$compiler->setOutputVar('__bodyText');
			$compiledTemplate .= $compiler->compileParsedPlainText($bodyTextParsed, $title, 0, $language['language_id']);
			if ($checkPhrases)
			{
				$phrases = array_merge($phrases, $compiler->getIncludedPhrases());
			}

			$compiler->setOutputVar('__bodyHtml');
			$compiledTemplate .= $compiler->compileParsed($bodyHtmlParsed, $title, 0, $language['language_id']);
			if ($checkPhrases)
			{
				$phrases = array_merge($phrases, $compiler->getIncludedPhrases());
			}

			$db->query('
				INSERT INTO xf_email_template_compiled
					(language_id, title, template_compiled)
				VALUES
					(?, ?, ?)
				ON DUPLICATE KEY UPDATE template_compiled = VALUES(template_compiled)
			', array($language['language_id'], $title, $compiledTemplate));

			$checkPhrases = false;
		}

		$phrases = array_unique($phrases);

		$db->delete('xf_email_template_phrase', 'title = ' . $db->quote($title));
		foreach ($phrases AS $includedPhrase)
		{
			$db->insert('xf_email_template_phrase', array(
				'title' => $title,
				'phrase_title' => $includedPhrase
			));
		}
	}

	/**
	 * Returns the path to the email template development directory, if it has been configured and exists
	 *
	 * @return string Path to email template directory
	 */
	public function getEmailTemplateDevelopmentDirectory()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/email_templates';
	}

	/**
	 * Deletes the email templates that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteEmailTemplatesForAddOn($addOnId)
	{
		$templates = $this->getMasterEmailTemplateTitlesByAddOn($addOnId);
		$titles = array_keys($templates);

		if ($titles)
		{
			$db = $this->_getDb();
			$quotedTitles = $db->quote($titles);

			$db->delete('xf_email_template', "title IN ($quotedTitles) AND custom = 0");
			$db->delete('xf_email_template_compiled', "title IN ($quotedTitles)");
			$db->delete('xf_email_template_phrase', "title IN ($quotedTitles)");
		}

		XenForo_Template_Compiler_Email::resetTemplateCache();
	}

	/**
	 * Imports all email templates from the email templates directory into the database
	 */
	public function importEmailTemplatesFromDevelopment()
	{
		$db = $this->_getDb();

		$templateDir = $this->getEmailTemplateDevelopmentDirectory();
		if (!$templateDir && !is_dir($templateDir))
		{
			throw new XenForo_Exception("Email template development directory not enabled or doesn't exist");
		}

		$files = glob("$templateDir/*.txt");

		XenForo_Db::beginTransaction($db);
		$this->deleteEmailTemplatesForAddOn('XenForo');

		$titles = array();
		foreach ($files AS $templateFile)
		{
			if (!is_readable($templateFile))
			{
				throw new XenForo_Exception("Template file '$templateFile' not readable");
			}


			$filename = basename($templateFile);
			if (preg_match('/^(.+)\.(subject|text|html)\.txt$/', $filename, $match))
			{
				$titles[$match[1]][$match[2]] = $templateFile;
			}
		}

		$existingTemplates = $this->getMasterEmailTemplatesByTitles(array_keys($titles));

		foreach ($titles AS $title => $parts)
		{
			$data = array(
				'title' => $title,
				'custom' => 0,
				'subject' => file_get_contents($parts['subject']),
				'body_text' => file_get_contents($parts['text']),
				'body_html' => file_get_contents($parts['html']),
				'addon_id' => 'XenForo',
			);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_EmailTemplate');
			if (isset($existingTemplates[$title]))
			{
				$dw->setExistingData($existingTemplates[$title], true);
			}
			$dw->setOption(XenForo_DataWriter_EmailTemplate::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_EmailTemplate::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_EmailTemplate::OPTION_TEST_COMPILE, false);
			$dw->bulkSet($data);
			$dw->save();
		}

		$this->compileAllEmailTemplates();

		XenForo_Db::commit($db);
	}

	/**
	 * Imports the add-on email templates XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the data
	 * @param string $addOnId Add-on to import for
	 * @param boolean $fullCompile True to recompile all templates after importing
	 */
	public function importEmailTemplatesAddOnXml(SimpleXMLElement $xml, $addOnId, $fullCompile = true)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);
		$this->deleteEmailTemplatesForAddOn($addOnId);

		$templates = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->template);

		$titles = array();
		foreach ($templates AS $template)
		{
			$titles[] = (string)$template['title'];
		}

		$existingTemplates = $this->getMasterEmailTemplatesByTitles($titles);

		foreach ($templates AS $template)
		{
			$title = (string)$template['title'];

			$data = array(
				'title' => $title,
				'custom' => 0,
				'subject' => (string)$template->subject,
				'body_text' => (string)$template->body_text,
				'body_html' => (string)$template->body_html,
				'addon_id' => $addOnId,
			);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_EmailTemplate');
			if (isset($existingTemplates[$title]))
			{
				$dw->setExistingData($existingTemplates[$title], true);
			}
			$dw->setOption(XenForo_DataWriter_EmailTemplate::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_EmailTemplate::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_EmailTemplate::OPTION_TEST_COMPILE, false);
			$dw->bulkSet($data);
			$dw->save();
		}

		if ($fullCompile)
		{
			$this->compileAllEmailTemplates();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Appends the add-on email template XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendEmailTemplatesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		$templates = $this->getMasterEmailTemplatesByAddOn($addOnId);
		foreach ($templates AS $template)
		{
			$templateNode = $document->createElement('template');
			$templateNode->setAttribute('title', $template['title']);

			$subjectNode = $document->createElement('subject');
			$subjectNode->appendChild($document->createCDATASection($template['subject']));
			$templateNode->appendChild($subjectNode);

			$bodyTextNode = $document->createElement('body_text');
			$bodyTextNode->appendChild($document->createCDATASection($template['body_text']));
			$templateNode->appendChild($bodyTextNode);

			$bodyHtmlNode = $document->createElement('body_html');
			$bodyHtmlNode->appendChild($document->createCDATASection($template['body_html']));
			$templateNode->appendChild($bodyHtmlNode);

			$rootNode->appendChild($templateNode);
		}
	}

	/**
	 * Gets the email templates development XML.
	 *
	 * @return DOMDocument
	 */
	public function getEmailTemplatesDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('email_templates');
		$document->appendChild($rootNode);

		$this->appendEmailTemplatesAddOnXml($rootNode, 'XenForo');

		return $document;
	}
}