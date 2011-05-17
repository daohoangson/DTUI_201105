<?php

/**
 * Model for route prefixes.
 *
 * @package XenForo_RoutePrefixes
 */
class XenForo_Model_RoutePrefix extends XenForo_Model
{
	/**
	 * Retrieves all the prefixes for the specified route type.
	 *
	 * @param string $type Type of route (public or admin)
	 *
	 * @return array Format: [original prefix] => info
	 */
	public function getPrefixesByRouteType($type)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_route_prefix
			WHERE route_type = ?
			ORDER BY original_prefix
		', 'original_prefix', $type);
	}

	/**
	 * Gets the prefix data for the specified route type that will be used in the
	 * route cache.
	 *
	 * @param string $type
	 *
	 * @return array Format: [original prefix] => [prefix, route_class, build_link]
	 */
	public function getPrefixesForRouteCache($type)
	{
		$info = $this->getPrefixesByRouteType($type);

		$output = array();
		foreach ($info AS $prefixInfo)
		{
			$output[$prefixInfo['original_prefix']] = array(
				'route_class' => $prefixInfo['route_class'],
				'build_link' => $prefixInfo['build_link']
			);
		}

		return $output;
	}

	/**
	 * Gets all prefixes, grouped by the route type they belong to and
	 * keyed by their original prefix values.
	 *
	 * @return array Format: [route type][original prefix] => info
	 */
	public function getAllPrefixesGroupedByRouteType()
	{
		$prefixes = array();
		foreach ($this->_getRoutePrefixTypes() AS $routeType)
		{
			$prefixes[$routeType] = $this->getPrefixesByRouteType($routeType);
		}

		return $prefixes;
	}

	/**
	 * Gets the named prefix in the specified route type by the original prefix value.
	 *
	 * @param string $prefix Original route prefix value
	 * @param string $routeType Route type (public or admin)
	 *
	 * @return array|false
	 */
	public function getPrefixByOriginal($prefix, $routeType)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_route_prefix
			WHERE original_prefix = ?
				AND route_type = ?
		', array($prefix, $routeType));
	}

	/**
	 * Gets the default route prefix array. Useful when adding a prefix.
	 *
	 * @return array
	 */
	public function getDefaultRoutePrefix()
	{
		return array(
			'route_type' => '',
			'route_class' => '',
			'original_prefix' => '',
			'build_link' => 'data_only',
			'addon_id' => null // must fail isset()
		);
	}

	/**
	 * Gets all prefixes that belong to the specified add-on, grouped by the
	 * route type.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [route type][original prefix] => info
	 */
	public function getPrefixesByAddOnGroupedByRouteType($addOnId)
	{
		$output = array();

		$prefixResult = $this->_getDb()->query('
			SELECT *
			FROM xf_route_prefix
			WHERE addon_id = ?
			ORDER BY original_prefix
		', $addOnId);
		while ($prefix = $prefixResult->fetch())
		{
			$output[$prefix['route_type']][$prefix['original_prefix']] = $prefix;
		}

		return $output;
	}

	/**
	 * Gets the file name for the development output.
	 *
	 * @return string
	 */
	public function getPrefixDevelopmentFileName()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/route_prefixes.xml';
	}

	/**
	 * Determines if the prefix development file is writable. If the file
	 * does not exist, it checks whether the parent directory is writable.
	 *
	 * @param $fileName
	 *
	 * @return boolean
	 */
	public function canWritePrefixDevelopmentFile($fileName)
	{
		if (file_exists($fileName))
		{
			return is_writable($fileName);
		}
		else
		{
			return is_writable(dirname($fileName));
		}
	}

	/**
	 * Gets the DOM document that represents the prefix development file.
	 * This must be turned into XML (or HTML) by the caller.
	 *
	 * @return DOMDocument
	 */
	public function getPrefixesDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('route_prefixes');
		$document->appendChild($rootNode);

		$this->appendPrefixesAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Appends the add-on route prefix XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all prefix elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendPrefixesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$prefixes = $this->getPrefixesByAddOnGroupedByRouteType($addOnId);

		$document = $rootNode->ownerDocument;

		foreach ($this->_getRoutePrefixTypes() AS $type)
		{
			if (empty($prefixes[$type]))
			{
				continue;
			}

			$typeNode = $document->createElement('route_type');
			$typeNode->setAttribute('type', $type);
			$rootNode->appendChild($typeNode);

			foreach ($prefixes[$type] AS $prefix)
			{
				$prefixNode = $document->createElement('prefix');
				$prefixNode->setAttribute('original_prefix', $prefix['original_prefix']);
				$prefixNode->setAttribute('class', $prefix['route_class']);
				$prefixNode->setAttribute('build_link', $prefix['build_link']);

				$typeNode->appendChild($prefixNode);
			}
		}
	}

	/**
	 * Deletes the route prefixes that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deletePrefixesForAddOn($addOnId)
	{
		$db = $this->_getDb();
		$db->delete('xf_route_prefix', 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports prefixes from the development XML format. This will overwrite all prefixes.
	 *
	 * @param string $fileName
	 */
	public function importPrefixesDevelopmentXml($fileName)
	{
		$document = new SimpleXMLElement($fileName, 0, true);
		$this->importPrefixesAddOnXml($document, 'XenForo');
	}

	/**
	 * Imports the add-on route prefixes XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the prefix data
	 * @param string $addOnId Add-on to import for
	 */
	public function importPrefixesAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		$currentPrefixes = $this->getAllPrefixesGroupedByRouteType();

		XenForo_Db::beginTransaction($db);
		$this->deletePrefixesForAddOn($addOnId);

		$routeTypes = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->route_type);
		foreach ($routeTypes AS $typeXml)
		{
			$type = (string)$typeXml['type'];
			if (!$type)
			{
				continue;
			}

			$conflictPrefixes = $this->getPrefixesByRouteType($type);

			$types = XenForo_Helper_DevelopmentXml::fixPhpBug50670($typeXml->prefix);
			foreach ($types AS $prefix)
			{
				$originalPrefixValue = (string)$prefix['original_prefix'];

				$prefixInfo = array(
					'route_type' => $type,
					'route_class' => (string)$prefix['class'],
					'original_prefix' => $originalPrefixValue,
					'build_link' => (string)$prefix['build_link'],
					'addon_id' => $addOnId
				);

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_RoutePrefix');
				if (isset($conflictPrefixes[$originalPrefixValue]))
				{
					$dw->setExistingData($conflictPrefixes[$originalPrefixValue], true);
				}
				$dw->setOption(XenForo_DataWriter_RoutePrefix::OPTION_REBUILD_CACHE, false);
				$dw->bulkSet($prefixInfo);
				$dw->save();
			}
		}

		$this->rebuildRoutePrefixCache();

		XenForo_Db::commit($db);
	}

	/**
	 * Gets all valid route prefix types.
	 *
	 * @return array
	 */
	protected function _getRoutePrefixTypes()
	{
		return array('admin', 'public');
	}

	/**
	 * Rebuilds all route prefix cache(s).
	 */
	public function rebuildRoutePrefixCache()
	{
		foreach ($this->_getRoutePrefixTypes() AS $routeType)
		{
			$this->rebuildRoutePrefixTypeCache($routeType);
		}
	}

	/**
	 * Rebuilds the route prefix cache for the specified type.
	 *
	 * @param string $type
	 *
	 * @return array Cache data
	 */
	public function rebuildRoutePrefixTypeCache($type)
	{
		$prefixes = $this->getPrefixesForRouteCache($type);
		$this->_getDataRegistryModel()->set('routes' . ucfirst($type), $prefixes);

		return $prefixes;
	}
}