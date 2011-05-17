<?php

/**
 * Model for styles
 *
 * @package XenForo_Styles
 */
class XenForo_Model_Style extends XenForo_Model
{
	/**
	 * Gets style information by ID. Information about the master style
	 * can be fetched using an ID of 0 if $fetchMaster is true.
	 *
	 * @param integer $id Style ID
	 * @param boolean $fetchMaster If true, passing an ID of 0 will fetch info about the master style.
	 *
	 * @return array|false
	 */
	public function getStyleById($id, $fetchMaster = false)
	{
		if (strval($id) === '0')
		{
			if ($fetchMaster)
			{
				return array(
					'style_id' => 0,
					'title' => new XenForo_Phrase('master_style'),
					'parent_id' => 0
				);
			}
			else
			{
				return false;
			}
		}

		$localCacheKey = 'style_' . $id;
		if (($data = $this->_getLocalCacheData($localCacheKey)) === false)
		{
			$data = $this->_getDb()->fetchRow('
				SELECT *
				FROM xf_style
				WHERE style_id = ?
			', $id);

			$this->setLocalCacheData($localCacheKey, $data);
		}

		return $data;
	}

	/**
	 * Gets information about all styles, not including the master style.
	 *
	 * @return array Format: [style id] => (array) style info
	 */
	public function getAllStyles()
	{
		if (($styles = $this->_getLocalCacheData('allStyles')) === false)
		{
			$styles = array();
			$stylesDb = $this->_getDb()->fetchAll('
				SELECT *
				FROM xf_style
				ORDER BY title
			');

			foreach ($stylesDb AS $style)
			{
				$styles[$style['style_id']] = $style;
			}

			$this->setLocalCacheData('allStyles', $styles);
		}

		return $styles;
	}

	/**
	 * Generates the style tree association array based on the list of styles
	 * (see {@link getAllStyles()}).
	 *
	 * @param array $styleList List of styles
	 *
	 * @return array Format: [parent style id][] => child style id
	 */
	public function getStyleTreeAssociations(array $styleList)
	{
		$parents = array();
		foreach ($styleList AS $style)
		{
			$parents[$style['parent_id']][] = $style['style_id'];
		}

		return $parents;
	}

	/**
	 * Gets a list of child style IDs that are direct children of the specified style.
	 *
	 * @param integer $styleId
	 *
	 * @return array Array of style IDs
	 */
	public function getDirectChildStyleIds($styleId)
	{
		$styles = $this->getAllStyles();
		$styleTree = $this->getStyleTreeAssociations($styles);

		if (isset($styleTree[$styleId]))
		{
			return $styleTree[$styleId];
		}
		else
		{
			return array();
		}
	}

	/**
	 * Gets all children of a style ID, no matter how many levels below.
	 *
	 * @param integer $styleId
	 *
	 * @return array Array of style IDs
	 */
	public function getAllChildStyleIds($styleId)
	{
		$styles = $this->getAllStyles();
		$styleTree = $this->getStyleTreeAssociations($styles);

		if (isset($styleTree[$styleId]))
		{
			return $this->getAllChildStyleIdsFromTree($styleId, $styleTree);
		}
		else
		{
			return array();
		}
	}

	/**
	 * Internal handler to get call child style IDs.
	 *
	 * @param integer $parentId Parent style ID
	 * @param array $styleTree Tree of styles ([parent id][] => style id)
	 *
	 * @return array
	 */
	public function getAllChildStyleIdsFromTree($parentId, array $styleTree)
	{
		if (!isset($styleTree[$parentId]))
		{
			return array();
		}

		$children = array();
		foreach ($styleTree[$parentId] AS $childId)
		{
			$children[] = $childId;
			$children = array_merge($children, $this->getAllChildStyleIdsFromTree($childId, $styleTree));
		}

		return $children;
	}

	/**
	 * Gets a list of styles in the form of a flattened tree. The return
	 * is an array containing all styles and their related info. Each style
	 * additionally includes a "depth" element that repesents the depth from
	 * the (implicit) master. Children of the master have a depth 0, unless
	 * $baseDepth is overridden.
	 *
	 * @param integer $baseDepth Starting depth value.
	 *
	 * @return array Format: [style id] => (array) style info, including depth
	 */
	public function getAllStylesAsFlattenedTree($baseDepth = 0)
	{
		$styles = $this->getAllStyles();
		$tree = $this->getStyleTreeAssociations($styles);

		return $this->_buildFlattenedStyleTree($styles, $tree, 0, $baseDepth);
	}

	/**
	 * Returns an array of all styles, suitable for use in ACP template syntax as options source.
	 *
	 * @param integer Selected style ID
	 * @param array $styleTree
	 *
	 * @return array
	 */
	public function getStylesForOptionsTag($selectedId = null, $styleTree = null)
	{
		if ($styleTree === null)
		{
			$styleTree = $this->getAllStylesAsFlattenedTree();
		}

		$styles = array();
		foreach ($styleTree AS $id => $style)
		{
			$styles[$id] = array(
				'value' => $id,
				'label' => $style['title'],
				'selected' => ($selectedId == $id),
				'depth' => $style['depth']
			);
		}
		return $styles;
	}

	/**
	 * Builds the flattened tree recursively, incrementing the depth each time.
	 *
	 * @param array $styleList List of styles and their information
	 * @param array $tree Tree structure of styles ([parent id][] => style id)
	 * @param integer $root Where to start in the tree
	 * @param integer $depth Current/starting depth
	 *
	 * @return array List of styles with additional depth key
	 */
	protected function _buildFlattenedStyleTree(array $styleList, array $tree, $root = 0, $depth = 0)
	{
		if (!isset($tree[$root]) || !is_array($tree[$root]))
		{
			return array();
		}

		$output = array();

		foreach ($tree[$root] AS $styleId)
		{
			$output[$styleId] = $styleList[$styleId];
			$output[$styleId]['depth'] = $depth;

			$output += $this->_buildFlattenedStyleTree($styleList, $tree, $styleId, $depth + 1);
		}

		return $output;
	}

	/**
	 * Gets the base parent list for a style. This list starts with the *parent* of the given style ID, then
	 * works up the tree, eventually ending with 0.
	 *
	 * @param integer $styleId
	 *
	 * @return array List of parent style IDs (including 0)
	 */
	public function getStyleBaseParentList($styleId)
	{
		$styles = $this->getAllStyles();

		$parents = array();
		while (isset($styles[$styleId]) && $style = $styles[$styleId])
		{
			$parents[] = $style['parent_id'];
			$styleId = $style['parent_id'];
		}

		return $parents;
	}

	/**
	 * Recursively rebuilds the parent list in part of the style tree.
	 *
	 * @param integer $styleId First style to start with. All child will be rebuild.
	 */
	public function rebuildStyleParentListRecursive($styleId)
	{
		$styles = $this->getAllStyles();

		if (isset($styles[$styleId]))
		{
			$styleTree = $this->getStyleTreeAssociations($styles);

			$baseParentList = $this->getStyleBaseParentList($styleId);
			$this->_rebuildStyleParentListRecursive($styleId, $baseParentList, $styles, $styleTree);
		}
	}

	/**
	 * Internal function to rebuild the style parent list recursively.
	 *
	 * @param integer $styleId Base style Id
	 * @param array $baseParentList Base parent list for the style. Should not include this style ID in it.
	 * @param array $styles List of styles
	 * @param array $styleTree Style tree
	 */
	protected function _rebuildStyleParentListRecursive($styleId, array $baseParentList, array $styles, array $styleTree)
	{
		if (isset($styles[$styleId]))
		{
			$parentList = $baseParentList;
			array_unshift($parentList, $styleId);

			$db = $this->_getDb();
			$db->update(
				'xf_style',
				array('parent_list' => implode(',', $parentList)),
				'style_id = ' . $db->quote($styleId)
			);

			if (isset($styleTree[$styleId]))
			{
				foreach ($styleTree[$styleId] AS $childStyleId)
				{
					$this->_rebuildStyleParentListRecursive($childStyleId, $parentList, $styles, $styleTree);
				}
			}
		}
	}

	/**
	 * Updates the last modified date of all styles.
	 *
	 * @param int|null $time
	 */
	public function updateAllStylesLastModifiedDate($time = null)
	{
		if ($time === null)
		{
			$time = XenForo_Application::$time;
		}

		$this->_getDb()->update('xf_style', array(
			'last_modified_date' => $time
		));

		$this->rebuildStyleCache();
	}

	/**
	 * Helper to determine whether the master style should be shown in lists.
	 *
	 * @return boolean
	 */
	public function showMasterStyle()
	{
		return XenForo_Application::debugMode();
	}

	/**
	 * Returns the total number of templates in the master style
	 *
	 * @return integer
	 */
	public function countMasterTemplates()
	{
		return $this->_getDb()->fetchOne('SELECT COUNT(*) FROM xf_template WHERE style_id = 0');
	}

	/**
	 * Counts the number of customized templates in each non-master style
	 *
	 * @param array $styles Array of styles
	 *
	 * @return array The $styles array including a templateCount key
	 */
	public function countCustomTemplatesPerStyle(array $styles = array())
	{
		$totals = $this->_getDb()->fetchPairs('
			SELECT style_id, COUNT(template_id) AS templateCount
			FROM xf_template
			WHERE style_id <> 0
			GROUP BY style_id
		');

		foreach ($totals AS $styleId => $templateCount)
		{
			if (isset($styles[$styleId]))
			{
				$styles[$styleId]['templateCount'] = $templateCount;
			}
		}

		return $styles;
	}

	/**
	 * Rebuilds the style cache that is put in the data registry.
	 *
	 * @return array
	 */
	public function rebuildStyleCache()
	{
		$this->resetLocalCacheData('allStyles');
		$styles = $this->getAllStyles();
		$this->_getDataRegistryModel()->set('styles', $styles);

		return $styles;
	}

	/**
	 * Gets the XML representation of a style, including customized templates and properties.
	 *
	 * @param array $style
	 *
	 * @return DOMDocument
	 */
	public function getStyleXml(array $style)
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('style');
		$rootNode->setAttribute('title', $style['title']);
		$rootNode->setAttribute('description', $style['description']);
		$rootNode->setAttribute('user_selectable', $style['user_selectable']);
		$document->appendChild($rootNode);

		$propertiesNode = $document->createElement('properties');
		$rootNode->appendChild($propertiesNode);
		$this->getModelFromCache('XenForo_Model_StyleProperty')->appendStylePropertyXml($propertiesNode, $style['style_id']);

		$templatesNode = $document->createElement('templates');
		$rootNode->appendChild($templatesNode);
		$this->getModelFromCache('XenForo_Model_Template')->appendTemplatesStyleXml($templatesNode, $style['style_id']);

		return $document;
	}

	/**
	 * Imports a style XML file.
	 *
	 * @param SimpleXMLElement $document
	 * @param integer $parentStyleId If not overwriting, the ID of the parent style
	 * @param integer $overwriteStyleId If non-0, parent style is ignored
	 *
	 * @return array List of cache rebuilders to run
	 */
	public function importStyleXml(SimpleXMLElement $document, $parentStyleId = 0, $overwriteStyleId = 0)
	{
		if ($document->getName() != 'style')
		{
			throw new XenForo_Exception(new XenForo_Phrase('provided_file_is_not_valid_style_xml'), true);
		}

		$title = (string)$document['title'];
		if ($title === '')
		{
			throw new XenForo_Exception(new XenForo_Phrase('provided_file_is_not_valid_style_xml'), true);
		}

		$description = (string)$document['description'];

		/* @var $templateModel XenForo_Model_Template */
		$templateModel = $this->getModelFromCache('XenForo_Model_Template');

		/* @var $propertyModel XenForo_Model_StyleProperty */
		$propertyModel = $this->getModelFromCache('XenForo_Model_StyleProperty');

		if ($document['user_selectable'] === null)
		{
			$userSelectable = 1;
		}
		else
		{
			$userSelectable = (integer)$document['user_selectable'];
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		if ($overwriteStyleId)
		{
			$templateModel->deleteTemplatesInStyle($overwriteStyleId);
			$targetStyleId = $overwriteStyleId;
		}
		else
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Style');
			$dw->bulkSet(array(
				'title' => $title,
				'description' => $description,
				'parent_id' => $parentStyleId,
				'user_selectable' => $userSelectable
			));
			$dw->set('title', $title);
			$dw->set('description', $description);
			$dw->set('parent_id', $parentStyleId);
			$dw->save();

			$targetStyleId = $dw->get('style_id');
		}

		$templateModel->importTemplatesStyleXml($document->templates, $targetStyleId);
		$propertyModel->importStylePropertyXml($document->properties, $targetStyleId);

		XenForo_Db::commit($db);

		return array('Template');
	}
}