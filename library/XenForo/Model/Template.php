<?php

/**
 * Model for templates
 *
 * @package XenForo_Templates
 */
class XenForo_Model_Template extends XenForo_Model
{
	/**
	 * Returns all templates customized in a style in alphabetical title order
	 *
	 * @param integer $styleId Style ID
	 * @param boolean $basicData If true, gets basic data only
	 *
	 * @return array Format: [] => (array) template
	 */
	public function getAllTemplatesInStyle($styleId, $basicData = false)
	{
		return $this->_getDb()->fetchAll('
			SELECT ' . ($basicData ? 'template_id, title, style_id, addon_id' : '*') . '
			FROM xf_template
			WHERE style_id = ?
			ORDER BY title
		', $styleId);
	}

	/**
	 * Get the effective template list for a style. "Effective" means a merged/flattened
	 * system where every valid template has a record.
	 *
	 * This only returns data appropriate for a list view (map id, template id, title).
	 * Template_state is also calculated based on whether this template has been customized.
	 * State options: default, custom, inherited.
	 *
	 * @param integer $styleId
	 *
	 * @return array Format: [] => (array) template list info
	 */
	public function getEffectiveTemplateListForStyle($styleId, array $conditions = array(), $fetchOptions = array())
	{
		$whereClause = $this->prepareTemplateConditions($conditions, $fetchOptions);

		return $this->_getDb()->fetchAll('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.template_id,
				template.title,
				addon.addon_id, addon.title AS addonTitle,
				IF(template.style_id = 0, \'default\', IF(template.style_id = template_map.style_id, \'custom\', \'inherited\')) AS template_state,
				IF(template.style_id = template_map.style_id, 1, 0) AS canDelete
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template_map.template_id = template.template_id)
			LEFT JOIN xf_addon AS addon ON
				(addon.addon_id = template.addon_id)
			WHERE template_map.style_id = ?
				AND ' . $whereClause . '
			ORDER BY template_map.title
		', $styleId);
	}

	/**
	 * Prepares conditions for searching templates. Often, this search will
	 * be done on an effective template set (using the map). Some conditions
	 * may require this.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return string SQL conditions
	 */
	public function prepareTemplateConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'template.title LIKE ' . XenForo_Db::quoteLike($conditions['title'][0], $conditions['title'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'template.title LIKE ' . XenForo_Db::quoteLike($conditions['title'], 'lr', $db);
			}
		}

		if (!empty($conditions['template']))
		{
			if (is_array($conditions['template']))
			{
				$sqlConditions[] = 'template.template LIKE ' . XenForo_Db::quoteLike($conditions['template'][0], $conditions['phrase_text'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'template.template LIKE ' . XenForo_Db::quoteLike($conditions['template'], 'lr', $db);
			}
		}

		if (!empty($conditions['template_state']))
		{
			$stateIf = 'IF(template.style_id = 0, \'default\', IF(template.style_id = template_map.style_id, \'custom\', \'inherited\'))';
			if (is_array($conditions['template_state']))
			{
				$sqlConditions[] = $stateIf . ' IN (' . $db->quote($conditions['template_state']) . ')';
			}
			else
			{
				$sqlConditions[] = $stateIf . ' = ' . $db->quote($conditions['template_state']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Gets all effective templates in a style. "Effective" means a merged/flattened system
	 * where every valid template has a record.
	 *
	 * @param integer $styleId
	 *
	 * @return array Format: [] => (array) effective template info
	 */
	public function getAllEffectiveTemplatesInStyle($styleId)
	{
		return $this->_getDb()->fetchAll('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template_map.template_id = template.template_id)
			WHERE template_map.style_id = ?
			ORDER BY template_map.title
		', $styleId);
	}

	/**
	 * Gets style ID/template ID pairs for all styles where the named template
	 * is modified.
	 *
	 * @param string $templateTitle
	 *
	 * @return array Format: [style_id] => template_id
	 */
	public function getTemplateIdInStylesByTitle($templateTitle)
	{
		return $this->_getDb()->fetchPairs('
			SELECT style_id, template_id
			FROM xf_template
			WHERE title = ?
		', $templateTitle);
	}

	/**
	 * Gets the effective template in a style by its title. This includes all
	 * template information and the map ID.
	 *
	 * @param string $title
	 * @param integer $styleId
	 *
	 * @return array|false Effective template info.
	 */
	public function getEffectiveTemplateByTitle($title, $styleId)
	{
		return $this->_getDb()->fetchRow('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.title = ? AND template_map.style_id = ?
		', array($title, $styleId));
	}

	/**
	 * Gets effective templates in a style by their titles
	 *
	 * @param array $titles
	 * @param integer $styleId
	 *
	 * @return array|false Effective template info
	 */
	public function getEffectiveTemplatesByTitles(array $titles, $styleId)
	{
		if (empty($titles))
		{
			return array();
		}

		return $this->_getDb()->fetchAll('
			SELECT template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.title IN(' . $this->_getDb()->quote($titles) . ') AND template_map.style_id = ?
		', array($styleId));
	}

	/**
	 * Gets the effective template based on a known map idea. Returns all template
	 * information and the map ID.
	 *
	 * @param integer $templateMapId
	 *
	 * @return array|false Effective template info.
	 */
	public function getEffectiveTemplateByMapId($templateMapId)
	{
		return $this->_getDb()->fetchRow('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.template_map_id = ?
		', $templateMapId);
	}

	/**
	 * Gets multiple effective templates based on 1 or more map IDs. Returns all template
	 * information and the map ID.
	 *
	 * @param integery|array $templateMapIds Either one map ID as a scalar or any array of map IDs
	 *
	 * @return array Format: [] => (array) effective template info
	 */
	public function getEffectiveTemplatesByMapIds($templateMapIds)
	{
		if (!is_array($templateMapIds))
		{
			$templateMapIds = array($templateMapIds);
		}

		if (!$templateMapIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchAll('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.template_map_id IN (' . $db->quote($templateMapIds) . ')
		');
	}

	/**
	 * Returns the template specified by template_id
	 *
	 * @param integer $templateId Template ID
	 *
	 * @return array|false Template
	 */
	public function getTemplateById($templateId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_template
			WHERE template_id = ?
		', $templateId);
	}

	/**
	 * Fetches a template from a particular style based on its title.
	 * Note that if a version of the requested template does not exist
	 * in the specified style, nothing will be returned.
	 *
	 * @param string Title
	 * @param integer Style ID (defaults to master style)
	 *
	 * @return array
	 */
	public function getTemplateInStyleByTitle($title, $styleId = 0)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_template
			WHERE title = ?
				AND style_id = ?
		', array($title, $styleId));
	}

	/**
	 * Fetches templates from a particular style based on their titles.
	 * Note that if a version of the requested template does not exist
	 * in the specified style, nothing will be returned for it.
	 *
	 * @param array $titles List of titles
	 * @param integer $styleId Style ID (defaults to master style)
	 *
	 * @return array Format: [title] => info
	 */
	public function getTemplatesInStyleByTitles(array $titles, $styleId = 0)
	{
		if (!$titles)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_template
			WHERE title IN (' . $this->_getDb()->quote($titles) . ')
				AND style_id = ?
		', 'title', $styleId);
	}

	/**
	 * Gets all templates that are outdated (master version edited more recently).
	 * Does not include contents of template.
	 *
	 * @return array [template id] => template info, including master_version_string
	 */
	public function getOutdatedTemplates()
	{
		return $this->fetchAllKeyed('
			SELECT template.template_id, template.title, template.style_id,
				template.addon_id, template.version_id, template.version_string,
				master.version_string AS master_version_string
			FROM xf_template AS template
			INNER JOIN xf_template AS master ON (master.title = template.title AND master.style_id = 0)
			INNER JOIN xf_style AS style ON (style.style_id = template.style_id)
			WHERE template.style_id > 0
				AND master.version_id > template.version_id
		', 'template_id');
	}

	/**
	 * Returns all the templates that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [title] => info
	 */
	public function getMasterTemplatesInAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_template
			WHERE addon_id = ?
				AND style_id = 0
			ORDER BY title ASC
		', 'title', $addOnId);
	}

	/**
	 * Gets the template map IDs of any templates that include the source
	 * map IDs. For example, this would pass in the map ID of _header
	 * and get the map ID of the PAGE_CONTAINER.
	 *
	 * @param integer|array $mapIds One map ID as a scalar or an array of many.
	 *
	 * @return array Array of map IDs
	 */
	public function getIncludingTemplateMapIds($mapIds)
	{
		if (!is_array($mapIds))
		{
			$mapIds = array($mapIds);
		}

		if (!$mapIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchCol('
			SELECT source_map_id
			FROM xf_template_include
			WHERE target_map_id IN (' . $db->quote($mapIds) . ')
		');
	}

	/**
	 * Gets the template map information for all templates that are mapped
	 * to the specified template ID.
	 *
	 * @param integer $templateId
	 *
	 * @return array Format: [] => (array) template map info
	 */
	public function getMappedTemplatesByTemplateId($templateId)
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_template_map
			WHERE template_id = ?
		', $templateId);
	}

	/**
	 * Gets mapped template information from the parent style of the named
	 * template. If the named style is 0 (or invalid), returns false.
	 *
	 * @param string $title
	 * @param integer $styleId
	 *
	 * @return array|false
	 */
	public function getParentMappedTemplateByTitle($title, $styleId)
	{
		if ($styleId == 0)
		{
			return false;
		}

		return $this->_getDb()->fetchRow('
			SELECT parent_template_map.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_style AS style ON
				(template_map.style_id = style.style_id)
			INNER JOIN xf_template_map AS parent_template_map ON
				(parent_template_map.style_id = style.parent_id AND parent_template_map.title = template_map.title)
			WHERE template_map.title = ? AND template_map.style_id = ?
		', array($title, $styleId));
	}

	/**
	 * Gets the list of all template map IDs that include the named phrase.
	 *
	 * @param string $phraseTitle
	 *
	 * @return array List of template map IDs
	 */
	public function getTemplateMapIdsThatIncludePhrase($phraseTitle)
	{
		return $this->_getDb()->fetchCol('
			SELECT template_map_id
			FROM xf_template_phrase
			WHERE phrase_title = ?
		', $phraseTitle);
	}

	/**
	 * Returns the path to the template development directory, if it has been configured and exists
	 *
	 * @return string Path to templates directory
	 */
	public function getTemplateDevelopmentDirectory()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/templates';
	}

	/**
	 * Checks that the templates directory has been configured and exists
	 *
	 * @return boolean
	 */
	public function canImportTemplatesFromDevelopment()
	{
		$dir = $this->getTemplateDevelopmentDirectory();
		return ($dir && is_dir($dir));
	}

	/**
	 * Deletes the templates that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteTemplatesForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$db->query('
			DELETE FROM xf_template_include
			WHERE source_map_id IN (
				SELECT template_map_id
				FROM xf_template AS template
				INNER JOIN xf_template_map AS template_map ON
					(template.template_id = template_map.template_id)
				WHERE template.style_id = 0
					AND template.addon_id = ?
			)
		', $addOnId);
		$db->query('
			DELETE FROM xf_template_phrase
			WHERE template_map_id IN (
				SELECT template_map_id
				FROM xf_template AS template
				INNER JOIN xf_template_map AS template_map ON
					(template.template_id = template_map.template_id)
				WHERE template.style_id = 0
					AND template.addon_id = ?
			)
		', $addOnId);
		$db->query('
			DELETE FROM xf_template_map
			WHERE template_id IN (
				SELECT template_id
				FROM xf_template
				WHERE style_id = 0
					AND addon_id = ?
			)
		', $addOnId);
		$db->query('
			DELETE FROM xf_template_compiled
			WHERE style_id = 0
				AND title IN (
					SELECT title
					FROM xf_template
					WHERE style_id = 0
						AND addon_id = ?
				)
		', $addOnId);
		$db->delete('xf_template', 'style_id = 0 AND addon_id = ' . $db->quote($addOnId));

		XenForo_Template_Compiler::resetTemplateCache();
	}

	public function deleteTemplatesInStyle($styleId)
	{
		$db = $this->_getDb();

		$db->query('
			DELETE FROM xf_template_include
			WHERE source_map_id IN (
				SELECT template_map_id
				FROM xf_template AS template
				INNER JOIN xf_template_map AS template_map ON
					(template.template_id = template_map.template_id)
				WHERE template.style_id = ?
			)
		', $styleId);
		$db->query('
			DELETE FROM xf_template_phrase
			WHERE template_map_id IN (
				SELECT template_map_id
				FROM xf_template AS template
				INNER JOIN xf_template_map AS template_map ON
					(template.template_id = template_map.template_id)
				WHERE template.style_id = ?
			)
		', $styleId);
		$db->query('
			DELETE FROM xf_template_map
			WHERE template_id IN (
				SELECT template_id
				FROM xf_template
				WHERE style_id = ?
			)
		', $styleId);
		$db->query('
			DELETE FROM xf_template_compiled
			WHERE style_id = 0
				AND title IN (
					SELECT title
					FROM xf_template
					WHERE style_id = ?
				)
		', $styleId);
		$db->delete('xf_template', 'style_id = ' . $db->quote($styleId));

		XenForo_Template_Compiler::resetTemplateCache();
	}

	/**
	 * Imports all templates from the templates directory into the database
	 */
	public function importTemplatesFromDevelopment()
	{
		$db = $this->_getDb();

		$templateDir = $this->getTemplateDevelopmentDirectory();
		if (!$templateDir && !is_dir($templateDir))
		{
			throw new XenForo_Exception("Template development directory not enabled or doesn't exist");
		}

		$files = glob("$templateDir/*.html");
		if (!$files)
		{
			throw new XenForo_Exception("Template development directory does not have any templates");
		}

		$metaData = XenForo_Helper_DevelopmentXml::readMetaDataFile($templateDir . '/_metadata.xml');

		XenForo_Db::beginTransaction($db);
		$this->deleteTemplatesForAddOn('XenForo');

		$titles = array();
		foreach ($files AS $templateFile)
		{
			$filename = basename($templateFile);
			if (preg_match('/^(.+)\.html$/', $filename, $match))
			{
				$titles[] = $match[1];
			}
		}

		$existingTemplates = $this->getTemplatesInStyleByTitles($titles, 0);

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

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
				if (isset($existingTemplates[$templateName]))
				{
					$dw->setExistingData($existingTemplates[$templateName], true);
				}
				$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
				$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
				$dw->bulkSet(array(
					'style_id' => 0,
					'title' => $templateName,
					'template' => $data,
					'addon_id' => 'XenForo'
				));
				if (isset($metaData[$templateName]))
				{
					$dw->bulkSet($metaData[$templateName]);
				}
				$dw->save();
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Imports the add-on templates XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the data
	 * @param string $addOnId Add-on to import for
	 * @param integer $maxExecution Maximum run time in seconds
	 * @param integer $offset Number of elements to skip
	 *
	 * @return boolean|integer True on completion; false if the XML isn't correct; integer otherwise with new offset value
	 */
	public function importTemplatesAddOnXml(SimpleXMLElement $xml, $addOnId, $maxExecution = 0, $offset = 0)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$startTime = microtime(true);

		if ($offset == 0)
		{
			$this->deleteTemplatesForAddOn($addOnId);
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

		$existingTemplates = $this->getTemplatesInStyleByTitles($titles, 0);

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

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			if (isset($existingTemplates[$templateName]))
			{
				$dw->setExistingData($existingTemplates[$templateName], true);
			}
			$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
			$dw->bulkSet(array(
				'style_id' => 0,
				'title' => $templateName,
				'template' => (string)$template,
				'addon_id' => $addOnId,
				'version_id' => (int)$template['version_id'],
				'version_string' => (string)$template['version_string']
			));
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
	 * Imports templates into a given style. Note that this assumes the style is already empty.
	 * It does not check for conflicts.
	 *
	 * @param SimpleXMLElement $xml
	 * @param integer $styleId
	 */
	public function importTemplatesStyleXml(SimpleXMLElement $xml, $styleId)
	{
		$db = $this->_getDb();

		if ($xml->template === null)
		{
			return;
		}

		XenForo_Db::beginTransaction($db);

		foreach ($xml->template AS $template)
		{
			$templateName = (string)$template['title'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
			$dw->bulkSet(array(
				'style_id' => $styleId,
				'title' => (string)$template['title'],
				'template' => (string)$template,
				'addon_id' => (string)$template['addon_id'],
				'version_id' => (int)$template['version_id'],
				'version_string' => (string)$template['version_string']
			));
			$dw->save();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Appends the add-on template XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendTemplatesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		$templates = $this->getMasterTemplatesInAddOn($addOnId);
		foreach ($templates AS $template)
		{
			$templateNode = $document->createElement('template');
			$templateNode->setAttribute('title', $template['title']);
			$templateNode->setAttribute('version_id', $template['version_id']);
			$templateNode->setAttribute('version_string', $template['version_string']);
			$templateNode->appendChild($document->createCDATASection($template['template']));

			$rootNode->appendChild($templateNode);
		}
	}

	/**
	 * Appends the template XML for templates in the specified style.
	 *
	 * @param DOMElement $rootNode
	 * @param integer $styleId
	 */
	public function appendTemplatesStyleXml(DOMElement $rootNode, $styleId)
	{
		$document = $rootNode->ownerDocument;

		$templates = $this->getAllTemplatesInStyle($styleId);
		foreach ($templates AS $template)
		{
			$templateNode = $document->createElement('template');
			$templateNode->setAttribute('title', $template['title']);
			$templateNode->setAttribute('addon_id', $template['addon_id']);
			$templateNode->setAttribute('version_id', $template['version_id']);
			$templateNode->setAttribute('version_string', $template['version_string']);
			$templateNode->appendChild($document->createCDATASection($template['template']));

			$rootNode->appendChild($templateNode);
		}
	}

	/**
	 * Gets the templates development XML.
	 *
	 * @return DOMDocument
	 */
	public function getTemplatesDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('templates');
		$document->appendChild($rootNode);

		$this->appendTemplatesAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Recompiles all templates.
	 *
	 * @param integer $maxExecution The approx maximum length of time this function will run for
	 * @param integer $startStyle The ID of the style to start with
	 * @param integer $startTemplate The number of the template to start with in that style (not ID, just counter)
	 *
	 * @return boolean|array True if completed successfull, otherwise array of where to restart (values start style ID, start template counter)
	 */
	public function compileAllTemplates($maxExecution = 0, $startStyle = 0, $startTemplate = 0)
	{
		$db = $this->_getDb();

		$styles = $this->getModelFromCache('XenForo_Model_Style')->getAllStyles();
		$styleIds = array_merge(array(0), array_keys($styles));
		sort($styleIds);

		$lastStyle = 0;
		$startTime = microtime(true);
		$complete = true;

		XenForo_Db::beginTransaction($db);

		if ($startStyle == 0 && $startTemplate == 0)
		{
			$db->query('DELETE FROM xf_template_compiled');
		}

		foreach ($styleIds AS $styleId)
		{
			if ($styleId < $startStyle)
			{
				continue;
			}

			$lastStyle = $styleId;
			$lastTemplate = 0;

			$templates = $this->getAllTemplatesInStyle($styleId, true);
			foreach ($templates AS $key => $template)
			{
				$lastTemplate++;
				if ($styleId == $startStyle && $lastTemplate < $startTemplate)
				{
					continue;
				}

				$this->compileNamedTemplateInStyleTree($template['title'], $template['style_id']);

				if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
				{
					$complete = false;
					break 2;
				}
			}
		}

		if ($complete)
		{
			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		}

		XenForo_Db::commit($db);

		if ($complete)
		{
			return true;
		}
		else
		{
			return array($lastStyle, $lastTemplate + 1);
		}
	}

	/**
	 * Compiles the named template in the style tree. Any child templates that
	 * use this template will be recompiled as well.
	 *
	 * @param string $title
	 * @param integer $styleId
	 *
	 * @return array A list of template map IDs that were compiled
	 */
	public function compileNamedTemplateInStyleTree($title, $styleId)
	{
		$parsedRecord = $this->getEffectiveTemplateByTitle($title, $styleId);
		if (!$parsedRecord)
		{
			return array();
		}
		return $this->compileTemplateInStyleTree($parsedRecord);
	}

	/**
	 * Compiles the list of template map IDs and any child templates that are using
	 * the same core template.
	 *
	 * @param integer|array $templateMapIds One map ID as a scalar or many as an array
	 *
	 * @return array A list of template map IDs that were compiled
	 */
	public function compileMappedTemplatesInStyleTree($templateMapIds)
	{
		$templates = $this->getEffectiveTemplatesByMapIds($templateMapIds);
		$mapIds = array();

		foreach ($templates AS $template)
		{
			$mapIds = array_merge($mapIds, $this->compileTemplateInStyleTree($template));
		}

		return $mapIds;
	}

	/**
	 * Compiles the specified template data in the style tree. This compiles this template
	 * in any style that is actually using this template.
	 *
	 * @param array $parsedRecord Full template information
	 *
	 * @return array List of template map IDs that were compiled
	 */
	public function compileTemplateInStyleTree(array $parsedRecord)
	{
		$parsedTemplate = unserialize($parsedRecord['template_parsed']);

		$dependentTemplates = array();

		$styles = $this->getMappedTemplatesByTemplateId($parsedRecord['template_id']);
		foreach ($styles AS $compileStyle)
		{
			$this->compileAndInsertParsedTemplate(
				$compileStyle['template_map_id'],
				$parsedTemplate,
				$parsedRecord['title'],
				$compileStyle['style_id']
			);
			$dependentTemplates[] = $compileStyle['template_map_id'];
		}

		return $dependentTemplates;
	}

	/**
	 * Compiles and inserts the specified effective templates.
	 *
	 * @param array $templates Array of effective template info
	 */
	public function compileAndInsertEffectiveTemplates(array $templates)
	{
		foreach ($templates AS $template)
		{
			$this->compileAndInsertParsedTemplate(
				$template['template_map_id'],
				unserialize($template['template_parsed']),
				$template['title'],
				isset($template['map_style_id']) ? $template['map_style_id'] : $template['style_id']
			);
		}
	}

	/**
	 * Recompiles all templates that include the named phrase.
	 *
	 * @param string $phraseTitle
	 *
	 * @return array List of template map IDs that were compiled
	 */
	public function compileTemplatesThatIncludePhrase($phraseTitle)
	{
		$mapIds = $this->getTemplateMapIdsThatIncludePhrase($phraseTitle);
		return $this->compileMappedTemplatesInStyleTree($mapIds);
	}

	/**
	 * Compiles the specified parsed template and updates the compiled table
	 * and included templates list.
	 *
	 * @param integer $templateMapId The map ID of the template being compiled (for includes)
	 * @param string|array $parsedTemplate Parsed form of the template
	 * @param string $title Title of the template
	 * @param integer $compileStyleId Style ID of the template
	 */
	public function compileAndInsertParsedTemplate($templateMapId, $parsedTemplate, $title, $compileStyleId)
	{
		$isCss = (substr($title, -4) == '.css');

		if (!$compileStyleId && !$isCss)
		{
			return; // skip compiling master templates, but compile css we need it
		}

		$compiler = new XenForo_Template_Compiler('');
		$languages = $this->getModelFromCache('XenForo_Model_Language')->getAllLanguages();

		$db = $this->_getDb();

		if ($isCss)
		{
			$compiledTemplate = $compiler->compileParsed($parsedTemplate, $title, $compileStyleId, 0);
			$db->query('
				INSERT INTO xf_template_compiled
					(style_id, language_id, title, template_compiled)
				VALUES
					(?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE template_compiled = VALUES(template_compiled)
			', array($compileStyleId, 0, $title, $compiledTemplate));
		}
		else
		{
			foreach ($languages AS $language)
			{
				$compiledTemplate = $compiler->compileParsed($parsedTemplate, $title, $compileStyleId, $language['language_id']);
				$db->query('
					INSERT INTO xf_template_compiled
						(style_id, language_id, title, template_compiled)
					VALUES
						(?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE template_compiled = VALUES(template_compiled)
				', array($compileStyleId, $language['language_id'], $title, $compiledTemplate));
			}
		}

		$db->delete('xf_template_include', 'source_map_id = ' . $db->quote($templateMapId));
		foreach ($compiler->getIncludedTemplates() AS $includedMapId)
		{
			$db->insert('xf_template_include', array(
				'source_map_id' => $templateMapId,
				'target_map_id' => $includedMapId
			));

			//TODO: this system doesn't handle includes for templates that don't exist yet
		}

		$db->delete('xf_template_phrase', 'template_map_id = ' . $db->quote($templateMapId));
		foreach ($compiler->getIncludedPhrases() AS $includedPhrase)
		{
			$db->insert('xf_template_phrase', array(
				'template_map_id' => $templateMapId,
				'phrase_title' => $includedPhrase
			));
		}
	}

	/**
	 * Determines if the visiting user can modify a template in the specified style.
	 * If debug mode is not enabled, users can't modify templates in the master style.
	 *
	 * @param integer $styleId
	 *
	 * @return boolean
	 */
	public function canModifyTemplateInStyle($styleId)
	{
		return ($styleId != 0 || XenForo_Application::debugMode());
	}

	/**
	 * Builds (and inserts) the template map for a specified template, from
	 * the root of the style tree.
	 *
	 * @param string $title Title of the template being build
	 * @param array $data Injectable data. Supports styleTree and styleTemplateMap.
	 */
	public function buildTemplateMap($title, array $data = array())
	{
		if (!isset($data['styleTree']))
		{
			/* @var $styleModel XenForo_Model_Style */
			$styleModel = $this->getModelFromCache('XenForo_Model_Style');
			$data['styleTree'] = $styleModel->getStyleTreeAssociations($styleModel->getAllStyles());
		}

		if (!isset($data['styleTemplateMap']))
		{
			$data['styleTemplateMap'] = $this->getTemplateIdInStylesByTitle($title);
		}

		$mapUpdates = $this->findTemplateMapUpdates(0, $data['styleTree'], $data['styleTemplateMap']);
		if ($mapUpdates)
		{
			$db = $this->_getDb();
			$toDeleteInStyleIds = array();

			foreach ($mapUpdates AS $styleId => $newTemplateId)
			{
				if ($newTemplateId == 0)
				{
					$toDeleteInStyleIds[] = $styleId;
					continue;
				}

				$db->query('
					INSERT INTO xf_template_map
						(style_id, title, template_id)
					VALUES
						(?, ?, ?)
					ON DUPLICATE KEY UPDATE
						template_id = ?
				', array($styleId, $title, $newTemplateId, $newTemplateId));
			}

			if ($toDeleteInStyleIds)
			{
				$db->delete('xf_template_map',
					'title = ' . $db->quote($title) . ' AND style_id IN (' . $db->quote($toDeleteInStyleIds) . ')'
				);
				$db->delete('xf_template_compiled',
					'title = ' . $db->quote($title) . ' AND style_id IN (' . $db->quote($toDeleteInStyleIds) . ')'
				);
			}
		}
	}

	/**
	 * Finds the necessary template map updates for the specified template within the
	 * sub-tree.
	 *
	 * If {$defaultTemplateId} is non-0, a return entry will be inserted for {$parentId}.
	 *
	 * @param integer $parentId Parent of the style sub-tree to search.
	 * @param array $styleTree Tree of styles
	 * @param array $styleTemplateMap List of styleId => templateId pairs for the places where this template has been customized.
	 * @param integer $defaultTemplateId The default template ID that non-customized template in the sub-tree should get.
	 *
	 * @return array Format: [style id] => [effective template id]
	 */
	public function findTemplateMapUpdates($parentId, array $styleTree, array $styleTemplateMap, $defaultTemplateId = 0)
	{
		$output = array();

		if (isset($styleTemplateMap[$parentId]))
		{
			$defaultTemplateId = $styleTemplateMap[$parentId];
		}

		$output[$parentId] = $defaultTemplateId;

		if (!isset($styleTree[$parentId]))
		{
			return $output;
		}

		foreach ($styleTree[$parentId] AS $styleId)
		{
			$output += $this->findTemplateMapUpdates($styleId, $styleTree, $styleTemplateMap, $defaultTemplateId);
		}

		return $output;
	}

	/**
	 * Inserts the template map records for all elements of various styles.
	 *
	 * @param array $styleMapList Format: [style id][title] => template id
	 * @param bolean $truncate If true, all map data is truncated (quicker that way)
	 */
	public function insertTemplateMapForStyles(array $styleMapList, $truncate = false)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		if ($truncate)
		{
			$db->query('TRUNCATE TABLE xf_template_map');
		}

		foreach ($styleMapList AS $builtStyleId => $map)
		{
			if (!$truncate)
			{
				$db->delete('xf_template_map', 'style_id = ' . $db->quote($builtStyleId));
			}

			foreach ($map AS $title => $templateId)
			{
				$db->insert('xf_template_map', array(
					'style_id' => $builtStyleId,
					'title' => $title,
					'template_id' => $templateId
				));
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Builds the full template map data for an entire style sub-tree.
	 *
	 * @param integer $styleId Starting style. This style and all children will be built.
	 *
	 * @return array Format: [style id][title] => template id
	 */
	public function buildTemplateMapForStyleTree($styleId)
	{
		/* @var $styleModel XenForo_Model_Style */
		$styleModel = $this->getModelFromCache('XenForo_Model_Style');

		$styles = $styleModel->getAllStyles();
		$styleTree = $styleModel->getStyleTreeAssociations($styles);
		$styles[0] = true;

		if ($styleId && !isset($styles[$styleId]))
		{
			return array();
		}

		$map = array();
		if ($styleId)
		{
			$style = $styles[$styleId];

			$templates = $this->getEffectiveTemplateListForStyle($style['parent_id']);
			foreach ($templates AS $template)
			{
				$map[$template['title']] = $template['template_id'];
			}
		}

		return $this->_buildTemplateMapForStyleTree($styleId, $map, $styles, $styleTree);
	}

	/**
	 * Internal handler to build the template map data for a style sub-tree.
	 * Calls itself recursively.
	 *
	 * @param integer $styleId Style to build (builds children automatically)
	 * @param array $map Base template map data. Format: [title] => template id
	 * @param array $styles List of styles
	 * @param array $styleTree Style tree
	 *
	 * @return array Format: [style id][title] => template id
	 */
	protected function _buildTemplateMapForStyleTree($styleId, array $map, array $styles, array $styleTree)
	{
		if (!isset($styles[$styleId]))
		{
			return array();
		}

		$customTemplates = $this->getAllTemplatesInStyle($styleId);
		foreach ($customTemplates AS $template)
		{
			$map[$template['title']] = $template['template_id'];
		}

		$output = array($styleId => $map);

		if (isset($styleTree[$styleId]))
		{
			foreach ($styleTree[$styleId] AS $childStyleId)
			{
				$output += $this->_buildTemplateMapForStyleTree($childStyleId, $map, $styles, $styleTree);
			}
		}

		return $output;
	}

	/**
	 * Replaces <xen:require/include/edithint with <link rel="xenforo_x"
	 * for the purposes of easy WebDAV editing.
	 *
	 * @param string $templateText
	 *
	 * @return string
	 */
	public static function replaceIncludesWithLinkRel($templateText)
	{
		$search = array(
			'#<xen:require\s+css="([^"]+)"\s*/>#siU'
			=>	'<link rel="xenforo_stylesheet" type="text/css" href="\1" />',

			'#<xen:edithint\s+template="([^"]+\.css)"\s*/>#siU'
			=> '<link rel="xenforo_stylesheet_hint" type="text/css" href="\1" />',

			'#<xen:edithint\s+template="([^"]+)"\s*/>#siU'
			=> '<link rel="xenforo_template_hint" type="text/html" href="\1.html" />',

			'#<xen:include\s+template="([^"]+)"(\s*/)?>#siU'
			=> '<link rel="xenforo_template" type="text/html" href="\1.html"\2>',

			'#</xen:include>#siU'
			=> '</link>',
		);

		return preg_replace(array_keys($search), $search, $templateText);
	}

	/**
	 * Replaces <link rel="xenforo_x" with <xen:require/include/edithint
	 * for the purposes of easy WebDAV editing.
	 *
	 * @param string $templateText
	 *
	 * @return string
	 */
	public static function replaceLinkRelWithIncludes($templateText)
	{
		$search = array(
			'#</link>#siU'
			=> '</xen:include>',

			'#<link rel="xenforo_template" type="text/html" href="([^"]+)(\.html)?"(\s*/)?>#siU'
			=> '<xen:include template="\1"\3>',

			'#<link rel="xenforo_template_hint" type="text/html" href="([^"]+)(\.html)?"\s/>#siU'
			=> '<xen:edithint template="\1" />',

			'#<link rel="xenforo_stylesheet_hint" type="text/css" href="([^"]+)"\s*/>#siU'
			=> '<xen:edithint template="\1" />',

			'#<link rel="xenforo_stylesheet" type="text/css" href="([^"]+)"\s*/>#siU'
			=> '<xen:require css="\1" />'
		);

		return preg_replace(array_keys($search), $search, $templateText);
	}

	/* //TODO:...
	const CSS_MIN = 'minified_css_format';
	const CSS_STANDARD = 'standard_css_format';
	const CSS_EXTENDED = 'extended_xenforo_css_format';

	public static function formatCss($css, $mode = self::CSS_EXTENDED)
	{
		return self::_parseCss($css);

		switch ($mode)
		{
			case self::CSS_MIN:
				break;

			case self::CSS_STANDARD:
				break;

			case self::CSS_EXTENDED:
			default:
				break;
		}
	}*/
}