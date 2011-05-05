<?php

/**
 * Options model.
 *
 * @package XenForo_Options
 */
class XenForo_Model_Option extends XenForo_Model
{
	const FETCH_ADDON = 0x01;

	/**
	 * Get the list of option groups.
	 *
	 * @param array $fetchOptions, including 'includeDebug' to include debug-only option groups. If unset, uses debug option from config.
	 *
	 * @return array Format: [] => group info
	 */
	public function getOptionGroupList(array $fetchOptions = array())
	{
		if (!isset($fetchOptions['includeDebug']))
		{
			$fetchOptions['includeDebug'] = XenForo_Application::debugMode();
		}

		$joinOptions = $this->prepareOptionGroupFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT option_group.*
				' . $joinOptions['selectFields'] . '
			FROM xf_option_group AS option_group
				' . $joinOptions['joinTables'] . '
			WHERE 1=1
				' . (!$fetchOptions['includeDebug'] ? 'AND option_group.debug_only = 0' : '') . '
			ORDER BY
				option_group.display_order
		', 'group_id');
	}

	/**
	 * Gets an option group based on group ID.
	 *
	 * @param string $groupId
	 * @param array $fetchOptions
	 *
	 * @return array|false Option group info
	 */
	public function getOptionGroupById($groupId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareOptionGroupFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT option_group.*
				' . $joinOptions['selectFields'] . '
			FROM xf_option_group AS option_group
				' . $joinOptions['joinTables'] . '
			WHERE
				option_group.group_id = ?
		', $groupId);
	}

	/**
	 * Gets the named groups.
	 *
	 * @param array $groupIds
	 * @param array $fetchOptions
	 *
	 * @return array Format: [group id] => info
	 */
	public function getOptionGroupsByIds(array $groupIds, array $fetchOptions = array())
	{
		if (!$groupIds)
		{
			return array();
		}

		$joinOptions = $this->prepareOptionGroupFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT option_group.*
				' . $joinOptions['selectFields'] . '
			FROM xf_option_group AS option_group
				' . $joinOptions['joinTables'] . '
			WHERE
				option_group.group_id IN (' . $this->_getDb()->quote($groupIds) . ')
		', 'group_id');
	}

	/**
	 * Get all option groups that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [group id] => info
	 */
	public function getOptionGroupsByAddOn($addOnId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareOptionGroupFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT option_group.*
				' . $joinOptions['selectFields'] . '
			FROM xf_option_group AS option_group
				' . $joinOptions['joinTables'] . '
			WHERE
				option_group.addon_id = ?
			ORDER BY
				group_id
		', 'group_id', $addOnId);
	}

	/**
	 * Prepares additional parameters for option group fetch queries.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function prepareOptionGroupFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_ADDON)
			{
				$selectFields .= ',
					addon.*, addon.title AS addon_title, option_group.addon_id';
				$joinTables .= '
					LEFT JOIN xf_addon AS addon ON
						(addon.addon_id = option_group.addon_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
		);
	}

	/**
	 * Prepares a collection of option groups for display.
	 *
	 * @param array $groups
	 * @param boolean If false, remove option groups belonging to disabled add-ons.
	 *
	 * @return array
	 */
	public function prepareOptionGroups(array $groups, $includeDisabledAddons = true)
	{
		$addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');

		foreach ($groups AS $id => &$group)
		{
			if (!$includeDisabledAddons && $addOnModel->isAddOnDisabled($group))
			{
				unset($groups[$id]);
			}
			else
			{
				$group = $this->prepareOptionGroup($group);
			}
		}

		return $groups;
	}

	/**
	 * Prepares an option group for further processing or display.
	 *
	 * @param array $group Unprepared group
	 *
	 * @return array Prepared group
	 */
	public function prepareOptionGroup(array $group)
	{
		$group['title'] = new XenForo_Phrase($this->getOptionGroupTitlePhraseName($group['group_id']));
		$group['description'] = new XenForo_Phrase($this->getOptionGroupDescriptionPhraseName($group['group_id']));

		return $group;
	}

	/**
	 * Gets all options in the specified group.
	 *
	 * @param string $groupId
	 * @param array $fetchOptions
	 *
	 * @return array Format: [] => option info30
	 */
	public function getOptionsInGroup($groupId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareOptionFetchOptions($fetchOptions);

		// "option" is a reserved word in MySQL
		return $this->fetchAllKeyed('
			SELECT xf_option.*,
				relation.group_id, relation.display_order
				' . $joinOptions['selectFields'] . '
			FROM xf_option_group_relation AS relation
			INNER JOIN xf_option ON
				(relation.option_id = xf_option.option_id)
				' . $joinOptions['joinTables'] . '
			WHERE relation.group_id = ?
			ORDER BY relation.display_order
		', 'option_id', $groupId);
	}

	/**
	 * Gets the named option by its ID.
	 *
	 * @param string $optionId
	 * @param array $fetchOptions
	 *
	 * @return array|false Option info
	 */
	public function getOptionById($optionId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareOptionFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT xf_option.*
				' . $joinOptions['selectFields'] . '
			FROM xf_option
				' . $joinOptions['joinTables'] . '
			WHERE
				xf_option.option_id = ?
		', $optionId);
	}

	/**
	 * Get multiple options by their IDs.
	 *
	 * @param array $optionIds List of option IDs
	 *
	 * @return array Format: [option id] => option info
	 */
	public function getOptionsByIds(array $optionIds, array $fetchOptions = array())
	{
		if (empty($optionIds))
		{
			return array();
		}

		$db = $this->_getDb();

		$joinOptions = $this->prepareOptionFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT xf_option.*
				' . $joinOptions['selectFields'] . '
			FROM xf_option
				' . $joinOptions['joinTables'] . '
			WHERE
				option_id IN (' . $this->_getDb()->quote($optionIds) . ')
		', 'option_id');
	}

	/**
	 * Gets all options across all groups.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Format: [option_id] => option info
	 */
	public function getAllOptions(array $fetchOptions = array())
	{
		$joinOptions = $this->prepareOptionFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT xf_option.*
				' . $joinOptions['selectFields'] . '
			FROM xf_option
				' . $joinOptions['joinTables'] . '
			ORDER BY
				xf_option.option_id
		', 'option_id');
	}

	/**
	 * Get all option that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 * @param array $fetchOptions
	 *
	 * @return array Format: [option group id] => info
	 */
	public function getOptionsByAddOn($addOnId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareOptionFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT xf_option.*
				' . $joinOptions['selectFields'] . '
			FROM xf_option
				' . $joinOptions['joinTables'] . '
			WHERE
				xf_option.addon_id = ?
			ORDER BY
				xf_option.option_id
		', 'option_id', $addOnId);
	}

	/**
	 * Prepares additional parameters for option fetch queries.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function prepareOptionFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_ADDON)
			{
				$selectFields .= ',
					addon.*, addon.title AS addon_title, xf_option.addon_id';
				$joinTables .= '
					LEFT JOIN xf_addon AS addon ON
						(addon.addon_id = xf_option.addon_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
		);
	}

	/**
	 * Parse named edit format parameters. Parameters use format "name => value"
	 * with one parameter per line.
	 *
	 * @param string $params Unparsed params
	 *
	 * @return array Format: [name] => value/label
	 */
	protected function _parseNamedFormatParams($params)
	{
		$pairs = array();

		preg_match_all('/
			^\s*
			(?P<name>([^=\r\n])+?)
			\s*=\s*
			(?P<value>.*?)
			\s*$
		/mix', trim($params), $matches, PREG_SET_ORDER);

		foreach ($matches AS $match)
		{
			$pairs[$match['name']] = $match['value'];
		}

		return $pairs;
	}

	/**
	 * Prepare an option's format params.
	 *
	 * @param string $editFormat Edit format (textbox, spinbox, callback, etc)
	 * @param string $formatParamsString Unparsed format params
	 *
	 * @return array
	 */
	public function prepareOptionFormatParams($editFormat, $formatParamsString)
	{
		$formatParams = array();

		switch ($editFormat)
		{
			case 'textbox':
			case 'spinbox':
			case 'radio':
			case 'select':
			case 'checkbox':
				$formatParams = $this->_parseNamedFormatParams($formatParamsString);
				break;


			case 'callback':
				$callback = explode('::', $formatParamsString);
				if (count($callback) == 2)
				{
					$formatParams = array('class' => $callback[0], 'method' => $callback[1]);
				}
				break;

			case 'template':
				$formatParams = array('template' => $formatParamsString);
				break;
		}

		return $formatParams;
	}

	/**
	 * Prepares an option into a more useful format for processing or display.
	 *
	 * @param array $option
	 *
	 * @return array Prepared option
	 */
	public function prepareOption(array $option)
	{
		$option['formatParams'] = $this->prepareOptionFormatParams($option['edit_format'], $option['edit_format_params']);
		$option['title'] = new XenForo_Phrase($this->getOptionTitlePhraseName($option['option_id']));
		$option['explain'] = new XenForo_Phrase($this->getOptionExplainPhraseName($option['option_id']));
		if ($option['data_type'] == 'array')
		{
			$option['option_value'] = @unserialize($option['option_value']);
			if (!is_array($option['option_value']))
			{
				$option['option_value'] = array();
			}
		}

		return $option;
	}

	/**
	 * Prepares a collection of options for processing or display.
	 *
	 * @param array $options Collection of options
	 * @param boolean If false, remove options belonging to disabled add-ons.
	 *
	 * @return array Collection of prepared options
	 */
	public function prepareOptions(array $options, $includeDisabledAddOns = true)
	{
		$addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');

		foreach ($options AS $id => &$option)
		{
			if (!$includeDisabledAddOns && $addOnModel->isAddOnDisabled($option))
			{
				unset($options[$id]);
			}
			else
			{
				$option = $this->prepareOption($option);
			}
		}

		return $options;
	}

	/**
	 * Updates the value of a collection of options.
	 *
	 * @param array $options Format: [option id] => new option value
	 */
	public function updateOptions(array $options)
	{
		$dbOptions = $this->getOptionsByIds(array_keys($options));

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($dbOptions AS $dbOption)
		{
			$newValue = $options[$dbOption['option_id']];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
			$dw->setExistingData($dbOption, true);
			$dw->setOption(XenForo_DataWriter_Option::OPTION_REBUILD_CACHE, false);
			$dw->set('option_value', $newValue);
			$dw->save();
		}

		$this->rebuildOptionCache();
		$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		$this->getModelFromCache('XenForo_Model_AdminTemplate')->updateAdminStyleLastModifiedDate();

		XenForo_Db::commit($db);
	}

	/**
	 * Gets all the groups an option is related to (belongs to).
	 *
	 * @param string $optionId
	 *
	 * @return array Format: [group id] => display order
	 */
	public function getOptionRelationsByOptionId($optionId)
	{
		return $this->_getDb()->fetchPairs('
			SELECT group_id, display_order
			FROM xf_option_group_relation
			WHERE option_id = ?
		', $optionId);
	}

	/**
	 * Gets all option IDs that belong to a particular group.
	 *
	 * @param string $groupId
	 *
	 * @return array Array of option IDs
	 */
	public function getGroupRelatedOptionIdsByGroupId($groupId)
	{
		return $this->_getDb()->fetchCol('
			SELECT option_id
			FROM xf_option_group_relation
			WHERE group_id = ?
			ORDER BY display_order
		', $groupId);
	}

	/**
	 * Get option-group relationships for the specified options, grouped by option.
	 *
	 * @param array $optionIds List of option IDs
	 *
	 * @return array Format: [option id][] = relation info
	 */
	public function getOptionRelationsGroupedByOption(array $optionIds)
	{
		if (!$optionIds)
		{
			return array();
		}

		$db = $this->_getDb();

		$relations = array();
		$relationsDb = $db->query('
			SELECT *
			FROM xf_option_group_relation
			WHERE option_id IN (' . $db->quote($optionIds) . ')
			ORDER BY option_id
		');
		while ($relation = $relationsDb->fetch())
		{
			$relations[$relation['option_id']][$relation['group_id']] = $relation;
		}

		return $relations;
	}

	/**
	 * Delete the options in the specified group. If an option is in multiple
	 * groups, it will not deleted until the last reference is removed.
	 *
	 * @param string $groupId
	 *
	 * @return array List of option IDs that were deleted
	 */
	public function deleteOptionsInGroup($groupId)
	{
		$db = $this->_getDb();

		$options = $this->getGroupRelatedOptionIdsByGroupId($groupId);
		if ($options)
		{
			$multiGroupOptions = $db->fetchCol('
				SELECT DISTINCT option_id
				FROM xf_option_group_relation
				WHERE option_id IN (' . $db->quote($options) . ')
					AND group_id <> ?
			', $groupId);

			$phraseModel = $this->_getPhraseModel();

			$singleGroupOptions = array_diff($options, $multiGroupOptions);
			if ($singleGroupOptions)
			{
				$db->delete('xf_option', 'option_id IN (' . $db->quote($singleGroupOptions) . ')');
				foreach ($singleGroupOptions AS $optionId)
				{
					$phraseModel->deleteMasterPhrase($this->getOptionTitlePhraseName($optionId));
				}
			}

			$db->delete('xf_option_group_relation',
				'option_id IN (' . $db->quote($options) . ') AND group_id = ' . $db->quote($groupId)
			);
		}

		return $options;
	}

	/**
	 * Builds an array of all options, in the format used by the cache and
	 * the {@link XenForo_Options} class.
	 *
	 * @return array
	 */
	public function buildOptionArray()
	{
		$options = $this->getAllOptions();
		$optionArray = array();

		foreach ($options AS $option)
		{
			if ($option['data_type'] == 'array')
			{
				$optionArray[$option['option_id']] = @unserialize($option['option_value']);
				if (!is_array($optionArray[$option['option_id']]))
				{
					$optionArray[$option['option_id']] = array();
				}
			}
			else
			{
				$optionArray[$option['option_id']] = $option['option_value'];
			}
		}

		return $optionArray;
	}

	/**
	 * Rebuilds the option cache.
	 *
	 * @return array Rebuild options array
	 */
	public function rebuildOptionCache()
	{
		$optionCache = $this->buildOptionArray();
		$this->_getDataRegistryModel()->set('options', $optionCache);

		return $optionCache;
	}

	/**
	 * Determines whether the browsing user can edit option and group definitions.
	 *
	 * @return boolean
	 */
	public function canEditOptionAndGroupDefinitions()
	{
		return XenForo_Application::debugMode();
	}

	/**
	 * Returns an array that represents the default option group. Used when creating
	 * a new option group.
	 *
	 * @return array
	 */
	public function getDefaultOptionGroup()
	{
		return array(
			'group_id' => '',
			'display_order' => 1
		);
	}

	/**
	 * Returns an array that represents the default option . Used when creating
	 * a new option.
	 *
	 * @return array
	 */
	public function getDefaultOption()
	{
		return array(
			'option_id' => '',
			'default_value' => '',
			'edit_format' => 'textbox',
			'edit_format_params' => '',
			'data_type' => 'string',
			'can_backup' => 1,
			'validation_class' => '',
			'validation_method' => ''
		);
	}

	/**
	 * Gets the option group's title phrase name.
	 *
	 * @param string $groupId
	 *
	 * @return string
	 */
	public function getOptionGroupTitlePhraseName($groupId)
	{
		return 'option_group_' . $groupId;
	}

	/**
	 * Gets the option group's description phrase name.
	 *
	 * @param string $groupId
	 *
	 * @return string
	 */
	public function getOptionGroupDescriptionPhraseName($groupId)
	{
		return 'option_group_' . $groupId . '_description';
	}

	/**
	 * Gets a option group's master title phrase text.
	 *
	 * @param string $groupId
	 *
	 * @return string
	 */
	public function getOptionGroupMasterTitlePhraseValue($groupId)
	{
		$phraseName = $this->getOptionGroupTitlePhraseName($groupId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets a option group's master description phrase text.
	 *
	 * @param string $groupId
	 *
	 * @return string
	 */
	public function getOptionGroupMasterDescriptionPhraseValue($groupId)
	{
		$phraseName = $this->getOptionGroupDescriptionPhraseName($groupId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the option's title phrase name.
	 *
	 * @param string $optionId
	 *
	 * @return string
	 */
	public function getOptionTitlePhraseName($optionId)
	{
		return 'option_' . $optionId;
	}

	/**
	 * Gets the option's explain phrase name.
	 *
	 * @param string $optionId
	 *
	 * @return string
	 */
	public function getOptionExplainPhraseName($optionId)
	{
		return 'option_' . $optionId . '_explain';
	}

	/**
	 * Gets a option's master title phrase text.
	 *
	 * @param string $optionId
	 *
	 * @return string
	 */
	public function getOptionMasterTitlePhraseValue($optionId)
	{
		$phraseName = $this->getOptionTitlePhraseName($optionId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets a option's master explain phrase text.
	 *
	 * @param string $optionId
	 *
	 * @return string
	 */
	public function getOptionMasterExplainPhraseValue($optionId)
	{
		$phraseName = $this->getOptionExplainPhraseName($optionId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the file name for the development output.
	 *
	 * @return string
	 */
	public function getOptionsDevelopmentFileName()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/options.xml';
	}

	/**
	 * Determines if the option development file is writable. If the file
	 * does not exist, it checks whether the parent directory is writable.
	 *
	 * @param $fileName
	 *
	 * @return boolean
	 */
	public function canWriteOptionsDevelopmentFile($fileName)
	{
		return file_exists($fileName) ? is_writable($fileName) : is_writable(dirname($fileName));
	}

	/**
	 * Gets the development options XML document.
	 *
	 * @return DOMDocument
	 */
	public function getOptionsDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('optiongroups');
		$document->appendChild($rootNode);

		$this->appendOptionsAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Appends the add-on options XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all navigation elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendOptionsAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$groups = $this->getOptionGroupsByAddOn($addOnId);
		$options = $this->getOptionsByAddOn($addOnId);
		$relations = $this->getOptionRelationsGroupedByOption(array_keys($options));

		$document = $rootNode->ownerDocument;

		foreach ($groups AS $group)
		{
			$groupNode = $document->createElement('group');
			$groupNode->setAttribute('group_id', $group['group_id']);
			$groupNode->setAttribute('display_order', $group['display_order']);
			$groupNode->setAttribute('debug_only', $group['debug_only']);

			$rootNode->appendChild($groupNode);
		}

		foreach ($options AS $option)
		{
			$optionNode = $document->createElement('option');
			$optionNode->setAttribute('option_id', $option['option_id']);
			$optionNode->setAttribute('edit_format', $option['edit_format']);
			$optionNode->setAttribute('data_type', $option['data_type']);
			$optionNode->setAttribute('can_backup', $option['can_backup']);
			if ($option['validation_class'])
			{
				$optionNode->setAttribute('validation_class', $option['validation_class']);
				$optionNode->setAttribute('validation_method', $option['validation_method']);
			}

			XenForo_Helper_DevelopmentXml::createDomElements($optionNode, array(
				'default_value' => str_replace("\r\n", "\n", $option['default_value']),
				'edit_format_params' => str_replace("\r\n", "\n", $option['edit_format_params']),
				'sub_options' => str_replace("\r\n", "\n", $option['sub_options'])
			));

			if (isset($relations[$option['option_id']]))
			{
				foreach ($relations[$option['option_id']] AS $relation)
				{
					$relationNode = $document->createElement('relation');
					$relationNode->setAttribute('group_id', $relation['group_id']);
					$relationNode->setAttribute('display_order', $relation['display_order']);
					$optionNode->appendChild($relationNode);
				}
			}

			$rootNode->appendChild($optionNode);
		}

		return $document;
	}

	/**
	 * Deletes the options that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteOptionsForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$db->query('
			DELETE FROM xf_option_group_relation
			WHERE option_id IN (
				SELECT option_id
				FROM xf_option
				WHERE addon_id = ?
			)
		', $addOnId);
		$db->delete('xf_option', 'addon_id = ' . $db->quote($addOnId));
		$db->delete('xf_option_group', 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports the options development XML data.
	 *
	 * @param string $fileName File to read the XML from
	 */
	public function importOptionsDevelopmentXml($fileName)
	{
		$document = new SimpleXMLElement($fileName, 0, true);
		$this->importOptionsAddOnXml($document, 'XenForo');
	}

	/**
	 * Imports the add-on admin navigation XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the navigation data
	 * @param string $addOnId Add-on to import for
	 */
	public function importOptionsAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		$options = $this->getAllOptions();

		XenForo_Db::beginTransaction($db);
		$this->deleteOptionsForAddOn($addOnId);

		$xmlGroups = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->group);
		$xmlOptions = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->option);

		$groupIds = array();
		foreach ($xmlGroups AS $group)
		{
			$groupIds[] = (string)$group['group_id'];
		}

		$optionIds = array();
		foreach ($xmlOptions AS $option)
		{
			$optionIds[] = (string)$option['option_id'];
		}

		$existingGroups = $this->getOptionGroupsByIds($groupIds);
		$existingOptions = $this->getOptionsByIds($optionIds);

		foreach ($xmlGroups AS $group)
		{
			$groupId = (string)$group['group_id'];

			$groupDw = XenForo_DataWriter::create('XenForo_DataWriter_OptionGroup');
			if (isset($existingGroups[$groupId]))
			{
				$groupDw->setExistingData($existingGroups[$groupId], true);
			}
			$groupDw->setOption(XenForo_DataWriter_Option::OPTION_REBUILD_CACHE, false);
			$groupDw->bulkSet(array(
				'group_id' => $groupId,
				'display_order' => (string)$group['display_order'],
				'debug_only' => (string)$group['debug_only'],
				'addon_id' => $addOnId
			));
			$groupDw->save();
		}

		foreach ($xmlOptions AS $option)
		{
			$optionId = (string)$option['option_id'];

			$optionDw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
			if (isset($existingOptions[$optionId]))
			{
				$optionDw->setExistingData($existingOptions[$optionId], true);
			}
			$optionDw->setOption(XenForo_DataWriter_Option::OPTION_REBUILD_CACHE, false);

			$optionDw->bulkSet(array(
				'option_id' => $optionId,
				'edit_format' => (string)$option['edit_format'],
				'data_type' => (string)$option['data_type'],
				'can_backup' => (string)$option['can_backup'],
				'addon_id' => $addOnId
			));
			if ((string)$option['validation_class'])
			{
				$optionDw->set('validation_class', (string)$option['validation_class']);
				$optionDw->set('validation_method', (string)$option['validation_method']);
			}

			$optionDw->set('default_value', (string)$option->default_value);
			$optionDw->set('edit_format_params', (string)$option->edit_format_params);
			$optionDw->set('sub_options', (string)$option->sub_options);

			$relations = array();
			foreach ($option->relation AS $relation)
			{
				$relations[(string)$relation['group_id']] = (string)$relation['display_order'];
			}

			$optionDw->setRelations($relations);

			if (isset($options[$optionDw->get('option_id')]))
			{
				$optionDw->setOption(XenForo_DataWriter_Option::OPTION_VALIDATE_VALUE, false);
				$optionDw->set('option_value', $options[$optionDw->get('option_id')]['option_value']);
			}

			$optionDw->save();
		}

		$this->rebuildOptionCache();

		XenForo_Db::commit($db);
	}

	/**
	 * Gets the phrase model object.
	 *
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}