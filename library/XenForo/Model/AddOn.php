<?php

/**
 * Model for add-ons.
 *
 * @package XenForo_AddOns
 */
class XenForo_Model_AddOn extends XenForo_Model
{
	/**
	 * Gets the specified add-on if it exists.
	 *
	 * @param string $addOnId
	 *
	 * @return array|false
	 */
	public function getAddOnById($addOnId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_addon
			WHERE addon_id = ?
		', $addOnId);
	}

	/**
	 * Gets the version ID/string for the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array|false
	 */
	public function getAddOnVersion($addOnId)
	{
		if ($addOnId === '')
		{
			return false;
		}
		else if ($addOnId === 'XenForo')
		{
			return array(
				'version_id' => XenForo_Application::$versionId,
				'version_string' => XenForo_Application::$version
			);
		}
		else
		{
			return $this->_getDb()->fetchRow('
				SELECT version_id, version_string
				FROM xf_addon
				WHERE addon_id = ?
			', $addOnId);
		}
	}

	/**
	 * Gets all add-ons in title order.
	 *
	 * @return array Format: [addon id] => info
	 */
	public function getAllAddOns()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_addon
			ORDER BY title
		', 'addon_id');
	}

	/**
	 * Get all add-ons for use in an options list. Includes a "XenForo" option
	 * before all custom add-ons.
	 *
	 * @param boolean $includeCustomOption If true, includes an option for "custom" (non-add-on associated)
	 * @param boolean $includeForoOption If true, includes an option for "XenForo"
	 *
	 * @return array Format: [addon id] => title
	 */
	public function getAddOnOptionsList($includeCustomOption = true, $includeXenForoOption = true)
	{
		$options = array();
		if ($includeCustomOption)
		{
			$options[''] = '';
		}

		if ($includeXenForoOption)
		{
			$options['XenForo'] = 'XenForo';
		}

		$addOns = $this->getAllAddOns();
		foreach ($addOns AS $addOn)
		{
			$options[$addOn['addon_id']] = $addOn['title'];
		}

		return $options;
	}

	/**
	 * Conditionally gets the list of add-ons. Used for situations where
	 * the add-ons options can only be edited if in debug mode.
	 *
	 * @param boolean $includeCustomOption If true, includes an option for "custom" (non-add-on associated)
	 * @param boolean $includeForoOption If true, includes an option for "XenForo"
	 *
	 * @return array Format: [addon id] => title
	 */
	public function getAddOnOptionsListIfAvailable($includeCustomOption = true, $includeXenForoOption = true)
	{
		if (!XenForo_Application::debugMode())
		{
			return array();
		}
		else
		{
			return $this->getAddOnOptionsList($includeCustomOption, $includeXenForoOption);
		}
	}

	/**
	 * Gets the default add-on ID to be used when not set.
	 *
	 * @return string
	 */
	public function getDefaultAddOnId()
	{
		if (XenForo_Application::debugMode())
		{
			return XenForo_Application::get('config')->development->default_addon;
		}
		else
		{
			return '';
		}
	}

	/**
	 * Installs (or upgrades) an add-on using XML from a file.
	 *
	 * If an upgrade add-on is given, the XML add-on ID will be checked against if.
	 * If matching, an upgrade will be performed. Otherwise, installing existing add-ons will
	 * be blocked.
	 *
	 * @param string $fileName Path to file
	 * @param string|false $upgradeAddOnId ID of the add-on to upgrade, if there is one
	 *
	 * @return array List of caches to be built out-of-band with CacheRebuilder
	 */
	public function installAddOnXmlFromFile($fileName, $upgradeAddOnId = false)
	{
		if (!file_exists($fileName) || !is_readable($fileName))
		{
			throw new XenForo_Exception(new XenForo_Phrase('please_enter_valid_file_name_requested_file_not_read'), true);
		}

		try
		{
			$document = new SimpleXMLElement($fileName, 0, true);
		}
		catch (Exception $e)
		{
			throw new XenForo_Exception(
				new XenForo_Phrase('provided_file_was_not_valid_xml_file'), true
			);
		}

		return $this->installAddOnXml($document, $upgradeAddOnId);
	}

	/**
	 * Installs add-on XML from a simple XML document.
	 *
	 * If an upgrade add-on is given, the XML add-on ID will be checked against if.
	 * If matching, an upgrade will be performed. Otherwise, installing existing add-ons will
	 * be blocked.
	 *
	 * @param SimpleXMLElement $xml
	 * @param string $upgradeAddOnId ID of the add-on to upgrade, if there is one
	 *
	 * @return array List of caches to be built out-of-band with CacheRebuilder
	 */
	public function installAddOnXml(SimpleXMLElement $xml, $upgradeAddOnId = false)
	{
		if ($xml->getName() != 'addon')
		{
			throw new XenForo_Exception(new XenForo_Phrase('provided_file_is_not_an_add_on_xml_file'), true);
		}

		$addOnData = array(
			'addon_id' => (string)$xml['addon_id'],
			'title' => (string)$xml['title'],
			'version_string' => (string)$xml['version_string'],
			'version_id' => (int)$xml['version_id'],
			'install_callback_class' => (string)$xml['install_callback_class'],
			'install_callback_method' => (string)$xml['install_callback_method'],
			'uninstall_callback_class' => (string)$xml['uninstall_callback_class'],
			'uninstall_callback_method' => (string)$xml['uninstall_callback_method'],
			'url' => (string)$xml['url'],
		);

		$existingAddOn = $this->verifyAddOnIsInstallable($addOnData, $upgradeAddOnId);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		if ($addOnData['install_callback_class'] && $addOnData['install_callback_method'])
		{
			call_user_func(
				array($addOnData['install_callback_class'], $addOnData['install_callback_method']),
				$existingAddOn,
				$addOnData
			);
		}

		$addOnDw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		if ($existingAddOn)
		{
			$addOnDw->setExistingData($existingAddOn, true);
		}
		$addOnDw->bulkSet($addOnData);
		$addOnDw->save();

		$this->importAddOnExtraDataFromXml($xml, $addOnData['addon_id']);

		XenForo_Db::commit($db);

		return $this->rebuildAddOnCaches();
	}

	/**
	 * Verifies that the add-on given is installable (or upgradeable).
	 *
	 * @param array $addOnData Information about the add-on, from the root XML node
	 * @param string|false $upgradeAddOnId Add-on we're trying to upgrade, if applicable
	 *
	 * @return array If doing an upgrade, returns information about the existing version
	 */
	public function verifyAddOnIsInstallable($addOnData, $upgradeAddOnId = false)
	{
		$addOnId = $addOnData['addon_id'];

		if ($addOnId === '')
		{
			throw new XenForo_Exception(new XenForo_Phrase('add_on_xml_does_not_specify_valid_add_on_id_and_cannot_be_installed'), true);
		}

		$existingAddOn = $this->getAddOnById($addOnId);
		if ($existingAddOn)
		{
			if ($upgradeAddOnId === false)
			{
				throw new XenForo_Exception(new XenForo_Phrase('specified_add_on_is_already_installed'), true);
			}
			else if ($existingAddOn['addon_id'] != $upgradeAddOnId)
			{
				throw new XenForo_Exception(new XenForo_Phrase('specified_add_on_does_not_match_add_on_you_chose_to_upgrade'), true);
			}

			if ($addOnData['version_id'] < $existingAddOn['version_id'])
			{
				throw new XenForo_Exception(new XenForo_Phrase('specified_add_on_is_older_than_install_version'), true);
			}
		}

		if ($upgradeAddOnId !== false && !$existingAddOn)
		{
			throw new XenForo_Exception(new XenForo_Phrase('specified_add_on_does_not_match_add_on_you_chose_to_upgrade'), true);
		}

		return $existingAddOn;
	}

	/**
	 * Imports all the add-on associated XML into the DB and rebuilds the
	 * caches.
	 *
	 * @param SimpleXMLElement $xml Root node that contains all of the "data" nodes below
	 * @param string $addOnId Add-on to import for
	 */
	public function importAddOnExtraDataFromXml(SimpleXMLElement $xml, $addOnId)
	{
		$this->getModelFromCache('XenForo_Model_AdminNavigation')->importAdminNavigationAddOnXml($xml->admin_navigation, $addOnId);

		$this->getModelFromCache('XenForo_Model_Admin')->importAdminPermissionsAddOnXml($xml->admin_permissions, $addOnId);

		$this->getModelFromCache('XenForo_Model_AdminTemplate')->importAdminTemplatesAddOnXml($xml->admin_templates, $addOnId);

		$this->getModelFromCache('XenForo_Model_CodeEvent')->importEventsAddOnXml($xml->code_events, $addOnId);

		$this->getModelFromCache('XenForo_Model_CodeEvent')->importEventListenersAddOnXml($xml->code_event_listeners, $addOnId);

		$this->getModelFromCache('XenForo_Model_Cron')->importCronEntriesAddOnXml($xml->cron, $addOnId);

		$this->getModelFromCache('XenForo_Model_EmailTemplate')->importEmailTemplatesAddOnXml($xml->email_templates, $addOnId, false);

		$this->getModelFromCache('XenForo_Model_Option')->importOptionsAddOnXml($xml->optiongroups, $addOnId);

		$this->getModelFromCache('XenForo_Model_Permission')->importPermissionsAddOnXml($xml->permissions, $addOnId);

		$this->getModelFromCache('XenForo_Model_Phrase')->importPhrasesAddOnXml($xml->phrases, $addOnId);

		$this->getModelFromCache('XenForo_Model_RoutePrefix')->importPrefixesAddOnXml($xml->route_prefixes, $addOnId);

		$this->getModelFromCache('XenForo_Model_StyleProperty')->importStylePropertyXml($xml->style_properties, 0, $addOnId);
		$this->getModelFromCache('XenForo_Model_StyleProperty')->importStylePropertyXml($xml->admin_style_properties, -1, $addOnId);

		$this->getModelFromCache('XenForo_Model_Template')->importTemplatesAddOnXml($xml->templates, $addOnId);
	}

	/**
	 * Gets the XML data for the specified add-on.
	 *
	 * @param array $addOn Add-on info
	 *
	 * @return DOMDocument
	 */
	public function getAddOnXml(array $addOn)
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('addon');
		$rootNode->setAttribute('addon_id', $addOn['addon_id']);
		$rootNode->setAttribute('title', $addOn['title']);
		$rootNode->setAttribute('version_string', $addOn['version_string']);
		$rootNode->setAttribute('version_id', $addOn['version_id']);
		$rootNode->setAttribute('url', $addOn['url']);
		$rootNode->setAttribute('install_callback_class', $addOn['install_callback_class']);
		$rootNode->setAttribute('install_callback_method', $addOn['install_callback_method']);
		$rootNode->setAttribute('uninstall_callback_class', $addOn['uninstall_callback_class']);
		$rootNode->setAttribute('uninstall_callback_method', $addOn['uninstall_callback_method']);
		$document->appendChild($rootNode);

		$addOnId = $addOn['addon_id'];

		$dataNode = $rootNode->appendChild($document->createElement('admin_navigation'));
		$this->getModelFromCache('XenForo_Model_AdminNavigation')->appendAdminNavigationAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('admin_permissions'));
		$this->getModelFromCache('XenForo_Model_Admin')->appendAdminPermissionsAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('admin_style_properties'));
		$this->getModelFromCache('XenForo_Model_StyleProperty')->appendStylePropertyXml($dataNode, -1, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('admin_templates'));
		$this->getModelFromCache('XenForo_Model_AdminTemplate')->appendAdminTemplatesAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('code_events'));
		$this->getModelFromCache('XenForo_Model_CodeEvent')->appendEventsAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('code_event_listeners'));
		$this->getModelFromCache('XenForo_Model_CodeEvent')->appendEventListenersAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('cron'));
		$this->getModelFromCache('XenForo_Model_Cron')->appendCronEntriesAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('email_templates'));
		$this->getModelFromCache('XenForo_Model_EmailTemplate')->appendEmailTemplatesAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('optiongroups'));
		$this->getModelFromCache('XenForo_Model_Option')->appendOptionsAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('permissions'));
		$this->getModelFromCache('XenForo_Model_Permission')->appendPermissionsAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('phrases'));
		$this->getModelFromCache('XenForo_Model_Phrase')->appendPhrasesAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('route_prefixes'));
		$this->getModelFromCache('XenForo_Model_RoutePrefix')->appendPrefixesAddOnXml($dataNode, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('style_properties'));
		$this->getModelFromCache('XenForo_Model_StyleProperty')->appendStylePropertyXml($dataNode, 0, $addOnId);

		$dataNode = $rootNode->appendChild($document->createElement('templates'));
		$this->getModelFromCache('XenForo_Model_Template')->appendTemplatesAddOnXml($dataNode, $addOnId);

		return $document;
	}

	/**
	 * Deletes all master data that is associated with an add-on. Customized data
	 * (eg, templates) will be left.
	 *
	 * @param string $addOnId
	 */
	public function deleteAddOnMasterData($addOnId)
	{
		$this->getModelFromCache('XenForo_Model_AdminNavigation')->deleteAdminNavigationForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_Admin')->deleteAdminPermissionsForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_AdminTemplate')->deleteAdminTemplatesForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_CodeEvent')->deleteEventsForAddOn($addOnId);
		$this->getModelFromCache('XenForo_Model_CodeEvent')->deleteEventListenersForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_Cron')->deleteCronEntriesForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_EmailTemplate')->deleteEmailTemplatesForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_Option')->deleteOptionsForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_Permission')->deletePermissionsForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_Phrase')->deletePhrasesForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_RoutePrefix')->deletePrefixesForAddOn($addOnId);

		$this->getModelFromCache('XenForo_Model_StyleProperty')->deleteStylePropertiesAndDefinitionsInStyle(-1, $addOnId, true);
		$this->getModelFromCache('XenForo_Model_StyleProperty')->deleteStylePropertiesAndDefinitionsInStyle(0, $addOnId, true);

		$this->getModelFromCache('XenForo_Model_Template')->deleteTemplatesForAddOn($addOnId);
	}

	/**
	 * Rebuilds all caches that are touched by add-ons.
	 *
	 * @return array List of caches to rebuild out-of-band
	 */
	public function rebuildAddOnCaches()
	{
		$this->getModelFromCache('XenForo_Model_CodeEvent')->rebuildEventListenerCache();

		$this->getModelFromCache('XenForo_Model_Cron')->updateMinimumNextRunTime();

		$this->getModelFromCache('XenForo_Model_Option')->rebuildOptionCache();

		$this->getModelFromCache('XenForo_Model_RoutePrefix')->rebuildRoutePrefixCache();

		$this->getModelFromCache('XenForo_Model_StyleProperty')->rebuildPropertyCacheForAllStyles();

		return array('Permission', 'Phrase', 'Template', 'AdminTemplate', 'EmailTemplate');
	}

	/**
	 * Rebuilds any caches that need to change after an add-on is enabled/disabled.
	 * This is a limited sub-set of all the caches that need to be rebuild when an
	 * add-on is disabled. (This makes it easier to switch state.)
	 */
	public function rebuildAddOnCachesAfterActiveSwitch()
	{
		$this->getModelFromCache('XenForo_Model_CodeEvent')->rebuildEventListenerCache();

		$this->getModelFromCache('XenForo_Model_Cron')->updateMinimumNextRunTime();
	}

	/**
	 * Returns true if the application is setup so that add-on development
	 * areas can be accessed. (True when in debug mode.)
	 *
	 * @return boolean
	 */
	public function canAccessAddOnDevelopmentAreas()
	{
		return XenForo_Application::debugMode();
	}

	/**
	 * Returns true if the record has the xf_addon table joined, and active = 0
	 *
	 * @param array $record
	 *
	 * @return boolean
	 */
	public function isAddOnDisabled(array $record)
	{
		return (array_key_exists('install_callback_class', $record) && !in_array($record['addon_id'], array('XenForo', '')) && empty($record['active']));
	}
}