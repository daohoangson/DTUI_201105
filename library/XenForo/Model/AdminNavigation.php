<?php

/**
 * Model for admin navigation.
 *
 * @package XenForo_AdminNavigation
 */
class XenForo_Model_AdminNavigation extends XenForo_Model
{
	const FETCH_ADDON = 0x01;

	/**
	 * Gets all admin navigation entries, in parent-display order.
	 *
	 * @param array Fetch options
	 *
	 * @return array [navigation id] => info
	 */
	public function getAdminNavigationEntries(array $fetchOptions = array())
	{
		$joinOptions = $this->prepareAdminNavigationFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT admin_navigation.*
				' . $joinOptions['selectFields'] . '
			FROM xf_admin_navigation AS admin_navigation
				' . $joinOptions['joinTables'] . '
			ORDER BY
				admin_navigation.parent_navigation_id,
				admin_navigation.display_order
		', 'navigation_id');
	}

	/**
	 * Gets all admin navigation entries with the specified parent, in display order.
	 *
	 * @param string $parentId
	 * @param array Fetch options
	 *
	 * @return array [navigation id] => info
	 */
	public function getAdminNavigationEntriesWithParent($parentId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareAdminNavigationFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT admin_navigation.*
				' . $joinOptions['selectFields'] . '
			FROM xf_admin_navigation AS admin_navigation
				' . $joinOptions['joinTables'] . '
			WHERE
				admin_navigation.parent_navigation_id = ?
			ORDER BY
				admin_navigation.display_order
		', 'navigation_id', $parentId);
	}

	/**
	 * Gets all admin navigation entries in the specified add-on.
	 *
	 * @param string $addOnId
	 * @param array Fetch options
	 *
	 * @return array [navigation id] => info
	 */
	public function getAdminNavigationEntriesInAddOn($addOnId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareAdminNavigationFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT admin_navigation.*
				' . $joinOptions['selectFields'] . '
			FROM xf_admin_navigation AS admin_navigation
				' . $joinOptions['joinTables'] . '
			WHERE
				admin_navigation.addon_id = ?
		', 'navigation_id', $addOnId);
	}

	/**
	 * Gets the specified admin navigation entry.
	 *
	 * @param string $navigationId
	 * @param array Fetch options
	 *
	 * @return array|false
	 */
	public function getAdminNavigationEntryById($navigationId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareAdminNavigationFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT admin_navigation.*
				' . $joinOptions['selectFields'] . '
			FROM xf_admin_navigation AS admin_navigation
				' . $joinOptions['joinTables'] . '
			WHERE
				admin_navigation.navigation_id = ?
		', $navigationId);
	}

	/**
	 * Gets the specified admin navigation entries.
	 *
	 * @param array $navigationIds
	 * @param array Fetch options
	 *
	 * @return array [navigation id] => info
	 */
	public function getAdminNavigationEntriesByIds(array $navigationIds, array $fetchOptions = array())
	{
		if (!$navigationIds)
		{
			return array();
		}

		$joinOptions = $this->prepareAdminNavigationFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT admin_navigation.*
				' . $joinOptions['selectFields'] . '
			FROM xf_admin_navigation AS admin_navigation
				' . $joinOptions['joinTables'] . '
			WHERE
				admin_navigation.navigation_id IN (' . $this->_getDb()->quote($navigationIds) . ')
		', 'navigation_id');
	}

	/**
	 * Prepares additional parameters for admin navigation fetch queries.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function prepareAdminNavigationFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_ADDON)
			{
				$selectFields .= ',
					addon.*,addon.title AS addon_title, admin_navigation.addon_id';
				$joinTables .= '
					LEFT JOIN xf_addon AS addon ON
						(addon.addon_id = admin_navigation.addon_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
		);
	}

	/**
	 * Group admin navigation entries by their parent.
	 *
	 * @param array $navigation List of navigation entries to group
	 *
	 * @return array [parent navigation id][navigation id] => info
	 */
	public function groupAdminNavigation(array $navigation)
	{
		$output = array(
			'' => array()
		);
		foreach ($navigation AS $nav)
		{
			$output[$nav['parent_navigation_id']][$nav['navigation_id']] = $nav;
		}

		return $output;
	}

	/**
	 * Get the admin navigation list in the correct display order. This can be processed
	 * linearly with depth markers to visually represent the tree.
	 *
	 * @param array|null $navigation Navigation entries; if null, grabbed automatically
	 * @param string $root Root node to traverse from
	 * @param integer $depth Depth to start at
	 *
	 * @return array [navigation id] => info, with depth key set
	 */
	public function getAdminNavigationInOrder(array $navigation = null, $root = '', $depth = 0)
	{
		if (!is_array($navigation))
		{
			$navigation = $this->groupAdminNavigation($this->getAdminNavigationEntries());
		}

		if (!isset($navigation[$root]))
		{
			return array();
		}

		$output = array();
		foreach ($navigation[$root] AS $nav)
		{
			$nav['depth'] = $depth;
			$output[$nav['navigation_id']] = $nav;

			$output += $this->getAdminNavigationInOrder($navigation, $nav['navigation_id'], $depth + 1);
		}

		return $output;
	}

	/**
	 * Gets the admin navigation as simple options for use in a select. Uses depth markers for tree display.
	 *
	 * @param array|null $navigation Navigation entries; if null, grabbed automatically
	 * @param string $root Root node to traverse from
	 * @param integer $depth Depth to start at
	 *
	 * @return array [] => [value, label, depth]
	 */
	public function getAdminNavigationOptions(array $navigation = null, $root = '', $depth = 0)
	{
		$navList = $this->prepareAdminNavigationEntries(
			$this->getAdminNavigationInOrder($navigation, $root, $depth)
		);

		$options = array();
		foreach ($navList AS $nav)
		{
			$options[] = array(
				'value' => $nav['navigation_id'],
				'label' => $nav['title'],
				'depth' => $nav['depth']
			);
		}

		return $options;
	}

	/**
	 * Gets the admin navigation breadcrumb.
	 *
	 * @param string $breadCrumbId Final piece in the breadcrumb
	 * @param array $navigationList List of all navigation entries
	 *
	 * @return array Breadcrumb pieces (array: [link, title]), from the top ("tab") down to the given breadcrumb ID
	 */
	public function getAdminNavigationBreadCrumb($breadCrumbId, array $navigationList)
	{
		$breadCrumb = array();

		while (isset($navigationList[$breadCrumbId]))
		{
			$navigation = $navigationList[$breadCrumbId];
			$breadCrumbId = $navigation['parent_navigation_id'];

			if (!$navigation['link'])
			{
				continue;
			}

			$navigation = $this->prepareAdminNavigationEntry($navigation);

			$breadCrumb[$navigation['navigation_id']] = array(
				'link' => $navigation['link'],
				'title' => $navigation['title']
			);
			$breadCrumbId = $navigation['parent_navigation_id'];
		}

		return array_reverse($breadCrumb, true);
	}

	/**
	 * Determines if the specified parent navigation ID is a valid parent for the navigation ID.
	 *
	 * @param string $potentialParentId
	 * @param string $navigationId
	 * @param array|null $navigation Navigation entries; if null, grabbed automatically
	 *
	 * @return boolean
	 */
	public function isAdminNavigationEntryValidParent($potentialParentId, $navigationId, array $navigation = null)
	{
		if ($potentialParentId == $navigationId)
		{
			return false;
		}
		else if ($potentialParentId === '')
		{
			return true;
		}

		if (!is_array($navigation))
		{
			$navigation = $this->getAdminNavigationEntries();
		}

		if (!isset($navigation[$potentialParentId]))
		{
			return false;
		}
		else if (!isset($navigation[$navigationId]))
		{
			return true;
		}

		$walkId = $potentialParentId;
		do
		{
			$walkNav = $navigation[$walkId];
			if ($walkNav['navigation_id'] == $navigationId)
			{
				return false;
			}

			$walkId = $walkNav['parent_navigation_id'];
		}
		while (isset($navigation[$walkId]));

		return true;
	}

	/**
	 * Filter out unviewable admin navigation details based on the visitor and application state.
	 *
	 * @param array $navigation List of navigation entries
	 *
	 * @return array Navigation entries filtered
	 */
	public function filterUnviewableAdminNavigation(array $navigation)
	{
		$isDebug = XenForo_Application::debugMode();
		$visitor = XenForo_Visitor::getInstance();

		$childCount = array();

		foreach ($navigation AS $key => $nav)
		{
			if ($this->getModelFromCache('XenForo_Model_AddOn')->isAddOnDisabled($nav))
			{
				// XenForo or "" add-on elements can't be disabled, otherwise ensure add-on is enabled
				unset($navigation[$key]);
			}
			else if ($nav['debug_only'] && !$isDebug)
			{
				unset($navigation[$key]);
			}
			else if ($nav['admin_permission_id'] && !$visitor->hasAdminPermission($nav['admin_permission_id']))
			{
				unset($navigation[$key]);
			}
			else
			{
				if (isset($childCount[$nav['parent_navigation_id']]))
				{
					$childCount[$nav['parent_navigation_id']]++;
				}
				else
				{
					$childCount[$nav['parent_navigation_id']] = 1;
				}
			}
		}

		$traverse = array_keys($navigation);
		while ($traverse)
		{
			$navId = array_shift($traverse);

			if ($navId === '' || !isset($navigation[$navId]))
			{
				continue;
			}

			$nav = $navigation[$navId];
			if (!$nav['hide_no_children'])
			{
				continue;
			}

			if (!isset($childCount[$navId]) || $childCount[$navId] <= 0)
			{
				unset($navigation[$navId]);
				$childCount[$nav['parent_navigation_id']]--;
				$traverse[] = $nav['parent_navigation_id'];
			}
		}

		return $navigation;
	}

	/**
	 * Get the admin navigation for the display of the admin container.
	 *
	 * @param string $breadCrumbId Farthest point down the bread crumb; used to build bread crumb and select tab/section
	 *
	 * @return array Keys: breadCrumb, tabs, sideLinks, sideLinksRoot
	 */
	public function getAdminNavigationForDisplay($breadCrumbId)
	{
		$navigation = $this->getAdminNavigationEntries(array(
			'join' => XenForo_Model_AdminNavigation::FETCH_ADDON
		));
		$navigation = $this->filterUnviewableAdminNavigation($navigation);

		$groupedNavigation = $this->groupAdminNavigation($navigation);

		if (isset($navigation[$breadCrumbId]))
		{
			$breadCrumb = $this->getAdminNavigationBreadCrumb($breadCrumbId, $navigation);

			$keys = array_keys($breadCrumb);
			$selectedTabId = reset($keys);
		}
		else
		{
			$breadCrumb = array();
			$selectedTabId = false;
		}

		$tabs = $this->prepareAdminNavigationEntries($groupedNavigation['']);

		if (!$selectedTabId || !isset($groupedNavigation[$selectedTabId]))
		{
			$selectedTabId = 'setup';
		}
		if (isset($tabs[$selectedTabId]))
		{
			$tabs[$selectedTabId]['selected'] = true;
		}

		if (isset($groupedNavigation[$selectedTabId]))
		{
			$sideLinks = array();

			$traverseList = array($selectedTabId);
			while ($traverseList)
			{
				$traverseId = array_shift($traverseList);
				if (!isset($groupedNavigation[$traverseId]))
				{
					continue;
				}

				foreach ($groupedNavigation[$traverseId] AS $traverse)
				{
					if (isset($groupedNavigation[$traverse['navigation_id']]))
					{
						$traverseList[] = $traverse['navigation_id'];
					}

					$traverse = $this->prepareAdminNavigationEntry($traverse);
					$sideLinks[$traverse['parent_navigation_id']][$traverse['navigation_id']] = $traverse;
				}
			}
		}
		else
		{
			$sideLinks = array();
		}

		return array(
			'breadCrumb' => $breadCrumb,
			'tabs' => $tabs,
			'sideLinks' => $sideLinks,
			'sideLinksRoot' => $selectedTabId
		);
	}

	/**
	 * Prepares an admin navigation entry for display.
	 *
	 * @param array $entry
	 *
	 * @return array
	 */
	public function prepareAdminNavigationEntry(array $entry)
	{
		$entry['title'] = new XenForo_Phrase($this->getAdminNavigationPhraseName($entry['navigation_id']));

		return $entry;
	}

	/**
	 * Prepares a list of admin navigation entries for display.
	 *
	 * @param array $entries
	 *
	 * @return array
	 */
	public function prepareAdminNavigationEntries(array $entries)
	{
		foreach ($entries AS &$entry)
		{
			$entry = $this->prepareAdminNavigationEntry($entry);
		}

		return $entries;
	}

	/**
	 * Gets the name of an admin navigation phrase.
	 *
	 * @param string $navigationId
	 *
	 * @return string
	 */
	public function getAdminNavigationPhraseName($navigationId)
	{
		return 'admin_navigation_' . $navigationId;
	}

	/**
	 * Gets the master title phrase value for the specified admin navigation entry.
	 *
	 * @param string $navigationId
	 *
	 * @return string
	 */
	public function getAdminNavigationMasterTitlePhraseValue($navigationId)
	{
		$phraseName = $this->getAdminNavigationPhraseName($navigationId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the file name for the development output.
	 *
	 * @return string
	 */
	public function getAdminNavigationDevelopmentFileName()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/admin_navigation.xml';
	}

	/**
	 * Gets the development admin navigation XML document.
	 *
	 * @return DOMDocument
	 */
	public function getAdminNavigationDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('admin_navigation');
		$document->appendChild($rootNode);

		$this->appendAdminNavigationAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Appends the add-on admin navigation XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all navigation elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendAdminNavigationAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$navigation = $this->getAdminNavigationEntriesInAddOn($addOnId);
		ksort($navigation);
		$document = $rootNode->ownerDocument;

		foreach ($navigation AS $nav)
		{
			$navNode = $document->createElement('navigation');
			$navNode->setAttribute('navigation_id', $nav['navigation_id']);
			$navNode->setAttribute('parent_navigation_id', $nav['parent_navigation_id']);
			$navNode->setAttribute('display_order', $nav['display_order']);
			$navNode->setAttribute('link', $nav['link']);
			$navNode->setAttribute('admin_permission_id', $nav['admin_permission_id']);
			$navNode->setAttribute('debug_only', $nav['debug_only']);
			$navNode->setAttribute('hide_no_children', $nav['hide_no_children']);

			$rootNode->appendChild($navNode);
		}
	}

	/**
	 * Deletes the admin navigation that belongs to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteAdminNavigationForAddOn($addOnId)
	{
		$db = $this->_getDb();
		$db->delete('xf_admin_navigation', 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports the development admin navigation XML data.
	 *
	 * @param string $fileName File to read the XML from
	 */
	public function importAdminNavigationDevelopmentXml($fileName)
	{
		$document = new SimpleXMLElement($fileName, 0, true);
		$this->importAdminNavigationAddOnXml($document, 'XenForo');
	}

	/**
	 * Imports the add-on admin navigation XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the navigation data
	 * @param string $addOnId Add-on to import for
	 */
	public function importAdminNavigationAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);
		$this->deleteAdminNavigationForAddOn($addOnId);

		$xmlNav = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->navigation);

		$navIds = array();
		foreach ($xmlNav AS $nav)
		{
			$navIds[] = (string)$nav['navigation_id'];
		}

		$existingNavigation = $this->getAdminNavigationEntriesByIds($navIds);

		foreach ($xmlNav AS $nav)
		{
			$navId = (string)$nav['navigation_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminNavigation');
			if (isset($existingNavigation[$navId]))
			{
				$dw->setExistingData($existingNavigation[$navId], true);
			}
			$dw->bulkSet(array(
				'navigation_id' => $navId,
				'parent_navigation_id' => (string)$nav['parent_navigation_id'],
				'display_order' => (string)$nav['display_order'],
				'link' => (string)$nav['link'],
				'admin_permission_id' => (string)$nav['admin_permission_id'],
				'debug_only' => (string)$nav['debug_only'],
				'hide_no_children' => (string)$nav['hide_no_children'],
				'addon_id' => $addOnId
			));
			$dw->save();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}