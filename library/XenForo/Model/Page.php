<?php

class XenForo_Model_Page extends XenForo_Model
{
	const PAGE_TEMPLATE_PREFIX = '_page_node.';

	/**
	 * Constructs the title of the template that corresponds to a page.
	 *
	 * @param array $page
	 *
	 * @return string
	 */
	public function getTemplateTitle(array $page)
	{
		if (!isset($page['node_id']) || $page['node_id'] === '')
		{
			throw new XenForo_Exception('Input page array does not contain node_id');
		}

		return self::PAGE_TEMPLATE_PREFIX . $page['node_id'];
	}

	/**
	 * Fetches a single page record, as specified by the node ID
	 *
	 * @param integer $nodeId
	 * @param array $fetchOptions
	 *
	 * @return array|false
	 */
	public function getPageById($nodeId, array $fetchOptions = array())
	{
		$joinOptions = $this->preparePageJoinOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT
				node.*,
				page.*
				' . $joinOptions['selectFields'] . '
			FROM xf_page AS page
			INNER JOIN xf_node AS node ON
				(node.node_id = page.node_id)
			' . $joinOptions['joinTables'] . '
			WHERE node.node_id = ?
		', $nodeId);
	}

	/**
	 * Fetches a single page record, as specified by the node name
	 *
	 * @param string $nodeName
	 * @param array $fetchOptions
	 *
	 * @return array|false
	 */
	public function getPageByName($nodeName, array $fetchOptions = array())
	{
		$joinOptions = $this->preparePageJoinOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT
				node.*,
				page.*
				' . $joinOptions['selectFields'] . '
			FROM xf_page AS page
			INNER JOIN xf_node AS node ON
				(node.node_id = page.node_id)
			' . $joinOptions['joinTables'] . '
			WHERE node.node_name = ?
		', $nodeName);
	}

	/**
	 * Fetches all pages specified by the node names given
	 *
	 * @param array $nodeNames
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getPagesByNames(array $nodeNames, $fetchOptions = array())
	{
		$joinOptions = $this->preparePageJoinOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT
				node.*,
				page.*
				' . $joinOptions['selectFields'] . '
			FROM xf_page AS page
			INNER JOIN xf_node AS node ON
				(node.node_id = page.node_id)
			' . $joinOptions['joinTables'] . '
			WHERE node.node_name IN (' . $this->_getDb()->quote($nodeNames) . ')
		', 'node_name');
	}

	/**
	 * Prepares page join options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function preparePageJoinOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		$db = $this->_getDb();

		if (!empty($fetchOptions['permissionCombinationId']))
		{
			$selectFields .= ',
				permission.cache_value AS node_permission_cache';
			$joinTables .= '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $db->quote($fetchOptions['permissionCombinationId']) . '
						AND permission.content_type = \'node\'
						AND permission.content_id = page.node_id)';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Determines if the specified page can be viewed with the given permissions.
	 *
	 * @param array $category Info about the category posting in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions List of permissions for this page; if not provided, use visitor's permissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewPage(array $page, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($page['node_id'], $viewingUser, $nodePermissions);

		return XenForo_Permission::hasContentPermission($nodePermissions, 'view');
	}

	/**
	 * Logs a visit by a user to a page
	 *
	 * @param array $page
	 * @param array $user
	 * @param integer $time
	 */
	public function logVisit(array $page, array $user, $time)
	{
		// TODO: bulk view updating like attachments/threads
		$this->_getDb()->query('
			UPDATE xf_page SET
			view_count = view_count + 1
			WHERE node_id = ?
		', $page['node_id']);
	}

	/**
	 * Fetches all (visible) sibling nodes of the specified page
	 *
	 * @param array $page
	 * @param boolean Include self in results
	 *
	 * @return array Node list, plus routePrefix and href (public link) keys for each node
	 */
	public function getSiblingNodes(array $page, $includeSelf = true)
	{
		$nodes = $this->_getNodeModel()->getSiblingNodes($page, $includeSelf, true);

		return $this->_prepareRelatedNodes($nodes);
	}

	/**
	 * Fetches all (visible) child nodes of the specified page
	 *
	 * @param array $page
	 *
	 * @return array
	 */
	public function getChildNodes(array $page)
	{
		$nodes = $this->_getNodeModel()->getChildNodesToDepth($page, 1, true);

		return $this->_prepareRelatedNodes($nodes);
	}

	/**
	 * Takes a list of nodes and filters them so that only viewable nodes are listed,
	 * then adds parameters (routePrefix, href) to each node to allow links to be build
	 *
	 * @param array $nodes
	 *
	 * @return array
	 */
	protected function _prepareRelatedNodes(array $nodes)
	{
		$nodeModel = $this->_getNodeModel();

		$nodeTypes = $nodeModel->getAllNodeTypes();
		$nodeTypeIds = $nodeModel->getUniqueNodeTypeIdsFromNodeList($nodes);
		$nodeHandlers = $nodeModel->getNodeHandlersForNodeTypes($nodeTypeIds);
		$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination();

		$nodes = $nodeModel->getViewableNodesFromNodeList($nodes, $nodeHandlers, $nodePermissions);

		foreach ($nodes AS $nodeId => $node)
		{
			$routePrefix = $nodeTypes[$node['node_type_id']]['public_route_prefix'];

			$nodes[$nodeId]['routePrefix'] = $routePrefix;
			$nodes[$nodeId]['href'] = XenForo_Link::buildPublicLink($routePrefix, $node);
		}

		return $nodes;
	}

	/**
	 * Saves a page, wrapping the whole operation up in a transaction
	 *
	 * @param array $page
	 * @param string $template
	 * @param integer $nodeId
	 * @param integer $templateId
	 *
	 * @return integer $nodeId
	 */
	public function savePage(array $page, $template, $nodeId = 0, $templateId = 0)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		// save page
		$pageWriter = XenForo_DataWriter::create('XenForo_DataWriter_Page');

		if ($nodeId)
		{
			$pageWriter->setExistingData($nodeId);
		}

		$pageWriter->bulkSet($page);
		$pageWriter->save();

		$page = $pageWriter->getMergedData();

		// save template
		$templateWriter = XenForo_DataWriter::create('XenForo_DataWriter_Template');

		if ($templateId)
		{
			$templateWriter->setExistingData($templateId);
		}

		$templateWriter->set('title', $this->getTemplateTitle($page));
		$templateWriter->set('template', $template);
		$templateWriter->set('style_id', 0);
		$templateWriter->set('addon_id', '');

		$templateWriter->save();

		XenForo_Db::commit($db);

		return $pageWriter->get('node_id');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}