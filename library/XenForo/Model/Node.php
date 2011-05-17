<?php

class XenForo_Model_Node extends XenForo_Model
{
	/**
	 * Checks that the provided array is a node
	 * by checking that it contains node_id and parent_node_id keys
	 *
	 * @param array $node
	 *
	 * @return boolean
	 */
	protected static function _isNode($node)
	{
		return (
			!empty($node) &&
			is_array($node) &&
			array_key_exists('node_id', $node) &&
			array_key_exists('parent_node_id', $node)
		);
	}

	/**
	 * Checks that the provided array is a node array
	 * by checking that the first element is a node
	 *
	 * @param mixed $nodes
	 *
	 * @return boolean
	 */
	protected static function _isNodesArray($nodes)
	{
		if (is_array($nodes))
		{
			if (count($nodes) == 0)
			{
				return true;
			}
			else
			{
				return (self::_isNode(reset($nodes)));
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 * Checks that the provided array is a node hierarchy
	 * by checking that the first child of the first element has a node_id key
	 *
	 * @param mixed $nodeHierarchy
	 *
	 * @return boolean
	 */
	protected static function _isNodeHierarchy($nodeHierarchy)
	{
		if (is_array($nodeHierarchy))
		{
			if (count($nodeHierarchy) == 0)
			{
				return true;
			}

			$firstChild = reset($nodeHierarchy);
			if (is_array($firstChild))
			{
				return (self::_isNodesArray(reset($firstChild)));
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets a single node record specified by its ID
	 *
	 * @param integer node_id
	 *
	 * @return array Node
	 */
	public function getNodeById($nodeId, array $fetchOptions = array())
	{
		$fetchOptions = array_merge(
			array(
				'permissionCombinationId' => 0
			), $fetchOptions
		);

		$permissionCombinationId = intval($fetchOptions['permissionCombinationId']);

		$data = $this->_getDb()->fetchRow('
			SELECT node.*
				' . ($permissionCombinationId ? ', permission.cache_value AS node_permission_cache' : '') . '
			FROM xf_node AS node
			' . ($permissionCombinationId ? '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $permissionCombinationId . '
						AND permission.content_type = \'node\'
						AND permission.content_id = node.node_id)
				' : '') . '
			WHERE node.node_id = ?
		', $nodeId);

		return $data;
	}

	/**
	 * Gets a node of the specified type that has a given name.
	 *
	 * @param string $nodeName
	 * @param string $nodeTypeId
	 * @param array $fetchOptions
	 *
	 * @return array|false
	 */
	public function getNodeByName($nodeName, $nodeTypeId, array $fetchOptions = array())
	{
		$fetchOptions = array_merge(
			array(
				'permissionCombinationId' => 0
			), $fetchOptions
		);

		$permissionCombinationId = intval($fetchOptions['permissionCombinationId']);

		$data = $this->_getDb()->fetchRow('
			SELECT node.*
				' . ($permissionCombinationId ? ', permission.cache_value AS node_permission_cache' : '') . '
			FROM xf_node AS node
			' . ($permissionCombinationId ? '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $permissionCombinationId . '
						AND permission.content_type = \'node\'
						AND permission.content_id = node.node_id)
				' : '') . '
			WHERE node.node_name = ?
				AND node.node_type_id = ?
		', array($nodeName, $nodeTypeId));

		return $data;
	}

	/**
	 * Get an array of node_ids from the specified array of node_names
	 *
	 * @param array Node names
	 *
	 * @return array [node_name] => node_id
	 */
	public function getNodeIdsFromNames(array $nodeNames)
	{
		return $this->_getDb()->fetchPairs('
			SELECT node_name, node_id
			FROM xf_node
			WHERE node_name IN (' . $this->_getDb()->quote($nodeNames) . ')
		');
	}

	/**
	 * Gets all nodes from the database
	 *
	 * @param boolean $ignoreNestedSetOrdering If true, ignore nested set infor for ordering and use display_order instead
	 * @param boolean $listView If true, only includes nodes viewable in list
	 *
	 * @return array
	 */
	public function getAllNodes($ignoreNestedSetOrdering = false, $listView = false)
	{
		if ($ignoreNestedSetOrdering)
		{
			return $this->fetchAllKeyed('
				SELECT *
				FROM xf_node
				' . ($listView ? 'WHERE display_in_list = 1' : '') . '
				ORDER BY parent_node_id, display_order ASC
			', 'node_id');
		}
		else
		{
			return $this->fetchAllKeyed('
				SELECT *
				FROM xf_node
				' . ($listView ? 'WHERE display_in_list = 1' : '') . '
				ORDER BY lft ASC
			', 'node_id');
		}
	}

	/**
	 * Gets an array representing the node hierarchy that can be traversed recursively
	 * Format: item[parent_id][node_id] = node
	 *
	 * @param array|null Node list from getAllNodes()
	 *
	 * @return array Node hierarchy
	 */
	public function getNodeHierarchy($nodes = null)
	{
		if (!$this->_isNodesArray($nodes))
		{
			$nodes = $this->getAllNodes(true);
		}

		$nodeHierarchy = array();

		foreach ($nodes AS $node)
		{
			$nodeHierarchy[$node['parent_node_id']][$node['node_id']] = $node;
		}

		return $nodeHierarchy;
	}

	/**
	 * Gets an array of all nodes that are not decendents of the specified node
	 *
	 * @param array	Node
	 *
	 * @return array Nodes
	 */
	public function getPossibleParentNodes($node = null)
	{
		$rootNode = array($this->getRootNode());

		if (!$this->_isNode($node))
		{
			// we are going to return ALL nodes, as the specified node does not exist
			$nodes = $this->getAllNodes();
		}
		else
		{
			// return only nodes that are not decendents of the specified node
			$nodes = $this->fetchAllKeyed('
				SELECT *
				FROM xf_node
				WHERE lft < ? OR rgt > ?
				ORDER BY lft ASC
			', 'node_id', array($node['lft'], $node['rgt']));
		}

		return $rootNode + $nodes;
	}

	/**
	 * Get all ancestors for the given node, from the root of the tree
	 * down the the node's direct parent.
	 *
	 * @param array $node
	 *
	 * @return array List of ancestor nodes, from root down; format: [node id] => info
	 */
	public function getNodeAncestors(array $node)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_node
			WHERE lft < ? AND rgt > ?
			ORDER BY lft ASC
		', 'node_id', array($node['lft'], $node['rgt']));
	}

	/**
	 * Gets the bread crumb nodes for the specified node.
	 *
	 * @param array $node
	 * @param boolean $includeSelf If true, includes itself as the last entry
	 *
	 * @return array List of nodes that form bread crumbs, root down; [node id] => node info
	 */
	public function getNodeBreadCrumbs(array $node, $includeSelf = true)
	{
		$nodes = $this->getNodeAncestors($node);
		if ($includeSelf)
		{
			$nodes[$node['node_id']] = $node;
		}

		$nodeTypes = $this->getAllNodeTypes();

		$breadCrumbs = array();

		foreach ($nodes AS $nodeId => $node)
		{
			$breadCrumbs[$nodeId] = array(
				'href' => XenForo_Link::buildPublicLink('full:' . $nodeTypes[$node['node_type_id']]['public_route_prefix'], $node),
				'value' => $node['title'],
				'node_id' => $node['node_id']
			);
		}

		return $breadCrumbs;
	}

	/**
	 * Gets all nodes that are siblings of this node
	 *
	 * @param array $node
	 * @param boolean $includeSelf
	 * @param boolean $listView If true, only includes nodes viewable in list
	 *
	 * @return array
	 */
	public function getSiblingNodes(array $node, $includeSelf = true, $listView = false)
	{
		if (!$this->_isNode($node))
		{
			return false;
		}

		$nodes = $this->fetchAllKeyed('
			SELECT *
			FROM xf_node
			WHERE parent_node_id = ?
				' . ($listView ? 'AND display_in_list = 1' : '') . '
			ORDER BY lft
		', 'node_id', $node['parent_node_id']);

		if (!$includeSelf)
		{
			unset($nodes[$node['node_id']]);
		}

		return $nodes;
	}

	/**
	 * Gets an array of all nodes that are decendents of the specified node
	 *
	 * @param array $node
	 * @param boolean $listView If true, only nodes that are visible in the list view are included
	 *
	 * @return mixed
	 */
	public function getChildNodes($node, $listView = false)
	{
		if (!$this->_isNode($node))
		{
			return false;
		}

		if (!$this->hasChildNodes($node))
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_node
			WHERE lft > ? AND rgt < ?
				' . ($listView ? ' AND display_in_list = 1' : '') . '
			ORDER BY lft ASC
		', 'node_id', array($node['lft'], $node['rgt']));
	}

	/**
	 * Gets an array of all nodes that are decendents of the specified node
	 * up to $depth levels of nesting
	 *
	 * @param array $node
	 * @param integer $depth
	 * @param boolean $listView If true, only nodes that are visible in the list view are included
	 *
	 * @return mixed
	 */
	public function getChildNodesToDepth($node, $depth, $listView = false)
	{
		if (!$this->_isNode($node) || $depth < 1)
		{
			return false;
		}
		else if ($depth == 1)
		{
			// use parent id to get the results
			return $this->fetchAllKeyed('
				SELECT *
				FROM xf_node
				WHERE parent_node_id = ?
					' . ($listView ? ' AND display_in_list = 1' : '') . '
				ORDER BY lft ASC
			', 'node_id', $node['node_id']);
		}
		else
		{
			// use left/right/depth to get children
			return $this->fetchAllKeyed('
				SELECT *
				FROM xf_node
				WHERE lft > ? AND rgt < ? AND depth <= ?
					' . ($listView ? ' AND display_in_list = 1' : '') . '
				ORDER BY lft ASC
			', 'node_id', array($node['lft'], $node['rgt'], $node['depth'] + $depth));
		}
	}

	/**
	 * Gets all child nodes for each node ID. The child nodes are not
	 * grouped.
	 *
	 * @param array $nodeIds
	 *
	 * @return array Child nodes, [node id] => node
	 */
	public function getChildNodesForNodeIds(array $nodeIds)
	{
		$nodes = $this->getAllNodes();

		$ranges = array();
		foreach ($nodeIds AS $nodeId)
		{
			if (isset($nodes[$nodeId]))
			{
				$node = $nodes[$nodeId];
				$ranges[] = array($node['lft'], $node['rgt']);
			}
		}

		$childNodes = array();
		foreach ($nodes AS $node)
		{
			foreach ($ranges AS $range)
			{
				if ($node['lft'] > $range[0] && $node['lft'] < $range[1])
				{
					$childNodes[$node['node_id']] = $node;
					break;
				}
			}
		}

		return $childNodes;
	}

	/**
	 * Gets all nodes up to a specified depth from
	 * the root of the tree.
	 *
	 * @param integer $depth
	 *
	 * @return array Format: [node id] => node info
	 */
	public function getNodesToDepthFromRoot($depth)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_node
			WHERE depth < ?
			ORDER BY lft ASC
		', 'node_id', $depth);
	}

	/**
	 * Groups a list of nodes by their parent node ID. This allows
	 * for easier recursive traversal.
	 *
	 * @param array $nodes Format: [node id] => info
	 *
	 * @return array Format: [parent node id][node id] => info
	 */
	public function groupNodesByParent(array $nodes)
	{
		$output = array();

		foreach ($nodes AS $nodeId => $node)
		{
			$output[$node['parent_node_id']][$nodeId] = $node;
		}

		return $output;
	}

	/**
	 * Gets a unique list of node type IDs from a list of
	 * ungrouped nodes.
	 *
	 * @param array $nodes
	 *
	 * @return array List of node type IDs
	 */
	public function getUniqueNodeTypeIdsFromNodeList(array $nodes)
	{
		$output = array();

		foreach ($nodes AS $node)
		{
			$output[$node['node_type_id']] = true;
		}

		return array_keys($output);
	}

	/**
	 * Gets a node handler object for each node type in the list.
	 *
	 * @param array $nodeTypeIds List of node type IDs
	 *
	 * @return array Format: [node type id] => node handler object
	 */
	public function getNodeHandlersForNodeTypes(array $nodeTypeIds)
	{
		$nodeTypes = $this->getAllNodeTypes();

		$output = array();
		foreach ($nodeTypeIds AS $nodeTypeId)
		{
			$class = isset($nodeTypes[$nodeTypeId]) ? $nodeTypes[$nodeTypeId]['handler_class'] : '';
			$output[$nodeTypeId] = new $class();
		}

		return $output;
	}

	/**
	 * Gets the viewable nodes from a list of nodes.
	 *
	 * @param array $nodes Format: [node id] => info
	 * @param array $nodeHandlers List of node handlers
	 * @param array $nodePermissions Node permissions, [node id] => permissions
	 *
	 * @return array List of nodes, [node id] => info
	 */
	public function getViewableNodesFromNodeList(array $nodes, array $nodeHandlers, array $nodePermissions)
	{
		$viewable = array();
		foreach ($nodes AS $nodeId => $node)
		{
			$handler = $nodeHandlers[$node['node_type_id']];
			$permissions = (isset($nodePermissions[$node['node_id']]) ? $nodePermissions[$node['node_id']] : array());

			if ($handler->isNodeViewable($node, $permissions))
			{
				$viewable[$nodeId] = $node;
			}
		}

		return $viewable;
	}

	/**
	 * Merges extra, node-specific data into a list of nodes.
	 *
	 * @param array $nodes List of nodes, [node id] => info
	 * @param array $nodeHandlers List of node handlers
	 *
	 * @return array Node list with extra data merged in
	 */
	public function mergeExtraNodeDataIntoNodeList(array $nodes, array $nodeHandlers)
	{
		$groupedTypes = array();
		foreach ($nodes AS $nodeId => $node)
		{
			$groupedTypes[$node['node_type_id']][] = $nodeId;
		}

		foreach ($groupedTypes AS $nodeTypeId => $nodeIds)
		{
			$extraData = $nodeHandlers[$nodeTypeId]->getExtraDataForNodes($nodeIds);
			if ($extraData)
			{
				foreach ($extraData AS $nodeId => $data)
				{
					if (isset($nodes[$nodeId]))
					{
						$nodes[$nodeId] = array_merge($nodes[$nodeId], $data);
					}
				}
			}
		}

		return $nodes;
	}

	/**
	 * Prepare nodes using the type-specific handlers.
	 *
	 * @param array $nodes Unprepared data
	 * @param array $nodeHandlers List of node handlers
	 *
	 * @return array Prepared data
	 */
	public function prepareNodesWithHandlers(array $nodes, array $nodeHandlers)
	{
		foreach ($nodes AS &$node)
		{
			$node = $nodeHandlers[$node['node_type_id']]->prepareNode($node);
		}

		return $nodes;
	}

	/**
	 * Pushes applicable child node data up the tree. This is used
	 * for things like last post data.
	 *
	 * @param integer $parentNodeId ID to start traversing at; all children will be handled
	 * @param array $groupedNodes Nodes grouped by parent, [parent node id][node id] => info
	 * @param array $nodeHandlers List of node handlers
	 * @param array $nodePermissions List of node permissions, [node id] => permissions
	 *
	 * @return array Grouped node list with data pushed up as necessary
	 */
	public function pushNodeDataUpTree($parentNodeId, array $groupedNodes, array $nodeHandlers, array $nodePermissions)
	{
		$this->mergePushableNodeData($parentNodeId, $groupedNodes, $nodeHandlers, $nodePermissions);
		return $groupedNodes;
	}

	/**
	 * Merges pushable node data into a grouped node list and
	 * returns all the pushed data from this level.
	 *
	 * @param integer $parentNodeId Parent node, all children traversed
	 * @param array $groupedNodes List of grouped nodes. This will be modified by ref!
	 * @param array $nodeHandlers List of node handlers
	 * @param array $nodePermissions List of node permissions
	 *
	 * @return array Pushed data for the nodes traversed at this level; [node id] => pushed data
	 */
	public function mergePushableNodeData($parentNodeId, array &$groupedNodes, array $nodeHandlers, array $nodePermissions)
	{
		if (empty($groupedNodes[$parentNodeId]))
		{
			return array();
		}

		$pushToParent = array();

		foreach ($groupedNodes[$parentNodeId] AS &$node)
		{
			$childPushableData = $this->mergePushableNodeData(
				$node['node_id'], $groupedNodes, $nodeHandlers, $nodePermissions
			);

			$permissions = (isset($nodePermissions[$node['node_id']]) ? $nodePermissions[$node['node_id']] : array());
			$pushableData = $nodeHandlers[$node['node_type_id']]->getPushableDataForNode(
				$node, $childPushableData, $permissions
			);

			if ($pushableData)
			{
				$node = array_merge($node, $pushableData);
				$pushToParent[$node['node_id']] = $pushableData;
			}
		}

		return $pushToParent;
	}

	/**
	 * Filters a set of grouped node data to only include nodes up to
	 * a certain depth from the specified root node.
	 *
	 * @param integer $parentNodeId Root of the sub-tree or the whole tree (0)
	 * @param array $groupedNodes Nodes, grouped by parent: [parent node id][node id] => info
	 * @param integer $depth Depth to filter to; should be at least 1
	 *
	 * @return array Filtered, grouped nodes
	 */
	public function filterGroupedNodesToDepth($parentNodeId, array $groupedNodes, $depth)
	{
		if (empty($groupedNodes[$parentNodeId]) || $depth < 1)
		{
			return array();
		}

		$okParentNodes = array($parentNodeId);

		$currentDepth = 1;
		$checkNodes = array($parentNodeId);

		while ($currentDepth < $depth)
		{
			$newCheckNodes = array();
			foreach ($checkNodes AS $checkNodeId)
			{
				if (!empty($groupedNodes[$checkNodeId]))
				{
					$newCheckNodes = array_merge(
						$newCheckNodes, array_keys($groupedNodes[$checkNodeId])
					);
				}
			}

			$okParentNodes = array_merge($okParentNodes, $newCheckNodes);
			$checkNodes = $newCheckNodes;
			$currentDepth++;
		}

		$newGroupedNodes = array();
		foreach ($okParentNodes AS $parentNodeId)
		{
			if (isset($groupedNodes[$parentNodeId]))
			{
				$newGroupedNodes[$parentNodeId] = $groupedNodes[$parentNodeId];
			}
		}

		return $newGroupedNodes;
	}

	/**
	 * Gets all the node data required for a node list display
	 * (eg, a forum list) from a given point. Returns 3 pieces of data:
	 * 	* nodesGrouped - nodes, grouped by parent, with all data integrated
	 *  * nodeHandlers - list of node handlers by node type
	 *  * nodePermissions - the node permissions passed on
	 *
	 * @param array|false $parentNode Root node of the tree to display from; false for the entire tree
	 * @param integer $displayDepth Number of levels of nodes to display below the root, 0 for all
	 * @param array|null $nodePermissions List of node permissions, [node id] => permissions; if null, get's current visitor's permissions
	 *
	 * @return array Empty, or with keys: nodesGrouped, parentNodeId, nodeHandlers, nodePermissions
	 */
	public function getNodeDataForListDisplay($parentNode, $displayDepth, array $nodePermissions = null)
	{
		if (is_array($parentNode))
		{
			$nodes = $this->getChildNodes($parentNode, true);
			$parentNodeId = $parentNode['node_id'];
		}
		else if ($parentNode === false)
		{
			$nodes = $this->getAllNodes(false, true);
			$parentNodeId = 0;
		}
		else
		{
			throw new XenForo_Exception('Unexpected parent node parameter passed to getNodeDataForListDisplay');
		}

		if (!$nodes)
		{
			return array();
		}

		if (!is_array($nodePermissions))
		{
			$nodePermissions = $this->getNodePermissionsForPermissionCombination();
		}

		$nodeHandlers = $this->getNodeHandlersForNodeTypes(
			$this->getUniqueNodeTypeIdsFromNodeList($nodes)
		);

		$nodes = $this->getViewableNodesFromNodeList($nodes, $nodeHandlers, $nodePermissions);
		$nodes = $this->mergeExtraNodeDataIntoNodeList($nodes, $nodeHandlers);
		$nodes = $this->prepareNodesWithHandlers($nodes, $nodeHandlers);

		$groupedNodes = $this->groupNodesByParent($nodes);
		$groupedNodes = $this->pushNodeDataUpTree($parentNodeId, $groupedNodes, $nodeHandlers, $nodePermissions);

		if ($displayDepth)
		{
			$groupedNodes = $this->filterGroupedNodesToDepth($parentNodeId, $groupedNodes, $displayDepth);
		}

		return array(
			'nodesGrouped' => $groupedNodes,
			'parentNodeId' => $parentNodeId,
			'nodeHandlers' => $nodeHandlers,
			'nodePermissions' => $nodePermissions
		);
	}

	/**
	 * Gets item counts across all nodes (or all nodes in a sub-tree). This does not
	 * respect permissions.
	 *
	 * @param array|false $parentNode
	 *
	 * @return array Keys: discussions, messages
	 */
	public function getNodeTotalItemCounts(array $parentNode = null)
	{
		if (is_array($parentNode))
		{
			$nodes = $this->getChildNodes($parentNode);
			$parentNodeId = $parentNode['node_id'];
		}
		else
		{
			$nodes = $this->getAllNodes();
			$parentNodeId = 0;
		}

		$output = array(
			'discussions' => 0,
			'messages' => 0
		);

		$nodeHandlers = $this->getNodeHandlersForNodeTypes(
			$this->getUniqueNodeTypeIdsFromNodeList($nodes)
		);

		$nodes = $this->mergeExtraNodeDataIntoNodeList($nodes, $nodeHandlers);
		foreach ($nodes AS $node)
		{
			$nodeOutput = $nodeHandlers[$node['node_type_id']]->getNodeItemCounts($node);
			if ($nodeOutput)
			{
				$output['discussions'] += $nodeOutput['discussions'];
				$output['messages'] += $nodeOutput['messages'];
			}
		}

		return $output;
	}

	/**
	 * Get a list of all nodes that are viewable.
	 *
	 * @param array|null $nodePermissions List of node permissions, [node id] => permissions; if null, get's current visitor's permissions
	 * @param boolean Get nodes in list mode (respect display_in_list option for each node)
	 *
	 * @return array List of viewable nodes: [node id] => info, ordered by lft
	 */
	public function getViewableNodeList(array $nodePermissions = null, $listView = false)
	{
		$nodes = $this->getAllNodes(false, $listView);
		if (!$nodes)
		{
			return array();
		}

		if (!is_array($nodePermissions))
		{
			$nodePermissions = $this->getNodePermissionsForPermissionCombination();
		}

		$nodeHandlers = $this->getNodeHandlersForNodeTypes(
			$this->getUniqueNodeTypeIdsFromNodeList($nodes)
		);

		return $this->getViewableNodesFromNodeList($nodes, $nodeHandlers, $nodePermissions);
	}

	/**
	 * Gets all the node permissions for a given permission combination ID.
	 *
	 * @param integer|null $permissionCombinationId If null, uses current visitor permissions
	 *
	 * @return array
	 */
	public function getNodePermissionsForPermissionCombination($permissionCombinationId = null)
	{
		if ($permissionCombinationId === null)
		{
			$visitor = XenForo_Visitor::getInstance();
			$permissionCombinationId = $visitor['permission_combination_id'];
		}

		$cacheKey = 'nodePermissions' . $permissionCombinationId;
		$data = $this->_getLocalCacheData($cacheKey);
		if ($data === false)
		{
			// TODO: this is a very cacheable query (but we need to expire the cache if data changes)
			$permissionsData = $this->_getDb()->fetchOne('
				SELECT cache_value
				FROM xf_permission_cache_content_type
				WHERE permission_combination_id = ?
					AND content_type = \'node\'
			', $permissionCombinationId);

			$data = XenForo_Permission::unserializePermissions($permissionsData);
			$this->setLocalCacheData($cacheKey, $data);
		}

		return $data;
	}

	/**
	 * Prepares the raw data of a node into human-readable information for use in
	 * the admin area.
	 *
	 * @param array Raw node data
	 *
	 * @return array Prepared node
	 */
	public function prepareNodeForAdmin(array $node)
	{
		// get human-readable node type names
		$node['node_type'] = $this->getNodeTypeNameById($node['node_type_id']);

		return $node;
	}

	/**
	 * Calls prepareNodeForAdmin() on each member of the input array
	 *
	 * @param array Raw nodes
	 *
	 * @return array Prepared nodes
	 */
	public function prepareNodesForAdmin(array $nodes)
	{
		foreach ($nodes AS $nodeId => $node)
		{
			$nodes[$nodeId] = $this->prepareNodeForAdmin($node);
		}

		return $nodes;
	}

	/**
	 * Fetches a representation of the root node to be merged into an array of other nodes
	 *
	 * @return array
	 */
	public function getRootNode()
	{
		return array(
			'node_id' => 0,
			'title' => new XenForo_Phrase('root_node_meta'),
			'node_type_id' => null,
			'parent_node_id' => null,
			'display_order' => 0,
			'lft' => 0,
			'rgt' => 0,
			'depth' => 0
		);
	}

	/**
	 * Fetch all information for the specified node type
	 *
	 * @param string node_type_id
	 *
	 * @return array
	 */
	public function getNodeTypeById($nodeTypeId)
	{
		$nodeTypes = $this->getAllNodeTypes();
		if (isset($nodeTypes[$nodeTypeId]))
		{
			return $nodeTypes[$nodeTypeId];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Fetches all information for the node type attached to a particular node
	 *
	 * @param integer $nodeId
	 *
	 * @return arrayf
	 */
	public function getNodeTypeByNodeId($nodeId)
	{
		return $this->_getDb()->fetchRow('
			SELECT node_type.*
			FROM xf_node_type AS node_type
			INNER JOIN xf_node AS node ON
				(node_type.node_type_id = node.node_type_id)
			WHERE node.node_id = ?
		', $nodeId);
	}

	/**
	 * Gets an array of all node types from the DB
	 *
	 * @return array Format: [node type id] => type info
	 */
	public function getAllNodeTypes()
	{
		// note: this uses the global cache as this can come from the data registry
		if (XenForo_Application::isRegistered('nodeTypes'))
		{
			return XenForo_Application::get('nodeTypes');
		}

		$nodeTypes = $this->getAllNodeTypesFromDb();
		XenForo_Application::set('nodeTypes', $nodeTypes);

		return $nodeTypes;
	}

	/**
	 * Gets an array of all node types from the DB
	 *
	 * @return array Format: [node type id] => type info
	 */
	public function getAllNodeTypesFromDb()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_node_type
		', 'node_type_id');
	}

	/**
	 * Rebuilds the cache of node types.
	 *
	 * @return array Node type cache [node type id] => type info
	 */
	public function rebuildNodeTypeCache()
	{
		$nodeTypes = $this->getAllNodeTypesFromDb();
		$this->_getDataRegistryModel()->set('nodeTypes', $nodeTypes);

		return $nodeTypes;
	}

	/**
	 * Gets all node types, grouped by their permission group.
	 *
	 * @return array Format: [permission group id][node type id] => node type info
	 */
	public function getNodeTypesGroupedByPermissionGroup()
	{
		$groups = array();
		foreach ($this->getAllNodeTypes() AS $nodeTypeId => $nodeType)
		{
			if ($nodeType['permission_group_id'])
			{
				$groups[$nodeType['permission_group_id']][$nodeTypeId] = $nodeType;
			}
		}

		return $groups;
	}

	/**
	 * Gets the human-readable name of the node type specified by its id
	 *
	 * @param string $nodeTypeId
	 *
	 * @return string
	 */
	public function getNodeTypeNameById($nodeTypeId)
	{
		return new XenForo_Phrase('node_type_' . $nodeTypeId);
	}

	/**
	 * If the specified node has a lft-rgt difference of more than 1, it must have child nodes.
	 *
	 * @param array Node must include lft and rgt keys
	 *
	 * @return boolean
	 */
	public function hasChildNodes(array $node)
	{
		if (array_key_exists('lft', $node) && array_key_exists('rgt', $node))
		{
			return ($node['rgt'] > ($node['lft'] + 1));
		}
		else
		{
			throw new XenForo_Exception('The array provided to ' . __CLASS__. '::hasChildNodes() did not contain the necessary keys.');
		}
	}

	/**
	 * Moves all child nodes from one parent to another
	 *
	 * @param mixed Source node: Either node array or node id
	 * @param mixed Destination node: Either node array or node id
	 * @param boolean Rebuild caches afterwards
	 *
	 * @return null
	 */
	public function moveChildNodes($fromNode, $toNode, $rebuildCaches = true)
	{
		if (!$this->_isNode($fromNode))
		{
			$fromNode = $this->getNodeById($fromNode);
		}

		if (!is_int($toNode) && $this->_isNode($toNode))
		{
			$toNode = $this->getNodeById($toNode);
			$toNode = $toNode['node_id'];
		}

		$nodeTypes = $this->getAllNodeTypes();

		if ($childNodes = $this->getChildNodesToDepth($fromNode, 1))
		{
			foreach ($childNodes AS $childNodeId => $childNode)
			{
				$dataWriterClass = $nodeTypes[$childNode['node_type_id']]['datawriter_class'];

				$writer = XenForo_DataWriter::create($dataWriterClass);
				$writer->setExistingData($childNodeId);
				$writer->setOption(XenForo_DataWriter_Node::OPTION_POST_WRITE_UPDATE_CHILD_NODES, false);
				$writer->setOption(XenForo_DataWriter_Node::OPTION_REBUILD_CACHE, false);
				$writer->set('parent_node_id', $toNode);
				$writer->save();
			}
		}

		if ($rebuildCaches)
		{
			$this->updateNestedSetInfo();
			$this->getModelFromCache('XenForo_Model_Permission')->rebuildPermissionCache();
		}
	}

	/**
	 * Deletes all child nodes of the specified parent node
	 *
	 * @param mixed Parent node: Either node array or node id
	 * @param boolean Rebuild caches afterwards
	 *
	 * @return null
	 */
	public function deleteChildNodes($parentNode, $rebuildCaches = true)
	{
		if (!$this->_isNode($parentNode))
		{
			$parentNode = $this->getNodeById($parentNode);
		}

		$nodeTypes = $this->getAllNodeTypes();

		if ($childNodes = $this->getChildNodes($parentNode))
		{
			foreach ($childNodes AS $childNodeId => $childNode)
			{
				$dataWriterClass = $nodeTypes[$childNode['node_type_id']]['datawriter_class'];

				$writer = XenForo_DataWriter::create($dataWriterClass);
				$writer->setExistingData($childNodeId);
				$writer->setOption(XenForo_DataWriter_Node::OPTION_POST_WRITE_UPDATE_CHILD_NODES, false);
				$writer->setOption(XenForo_DataWriter_Node::OPTION_REBUILD_CACHE, false);
				$writer->delete();
			}
		}

		if ($rebuildCaches)
		{
			$this->updateNestedSetInfo();
			$this->getModelFromCache('XenForo_Model_Permission')->rebuildPermissionCache();
		}
	}

	/**
	 * Builds lft, rgt and depth values for all nodes, based on the parent_node_id and display_order information in the database.
	 * Also rebuilds the effective style ID.
	 *
	 * @param array|null $nodeHierarchy - will be fetched automatically when NULL is provided
	 * @param integer $parentNodeId
	 * @param integer $depth
	 * @param integer $lft The entry left value; note that this will be changed and returned as the rgt value
	 * @param integer $effectiveStyleId The effective style ID from the parent
	 *
	 * @return array [node_id] => array(lft => int, rgt => int)...
	 */
	public function getNewNestedSetInfo($nodeHierarchy = null, $parentNodeId = 0, $depth = 0, &$lft = 1, $effectiveStyleId = 0)
	{
		$nodes = array();

		if ($depth == 0 && !$this->_isNodeHierarchy($nodeHierarchy))
		{
			$nodeHierarchy = $this->getNodeHierarchy($nodeHierarchy);
		}

		if (empty($nodeHierarchy[$parentNodeId]))
		{
			return array();
		}

		foreach ($nodeHierarchy[$parentNodeId] AS $i => $node)
		{
			$nodes[$node['node_id']] = $node;
			$nodes[$node['node_id']]['lft'] = $lft++;
			$nodes[$node['node_id']]['depth'] = $depth;

			$newStyleId = ($node['style_id'] ? $node['style_id'] : $effectiveStyleId);
			$nodes[$node['node_id']]['effective_style_id'] = $newStyleId;

			$nodes += $this->getNewNestedSetInfo($nodeHierarchy, $node['node_id'], $depth + 1, $lft, $newStyleId);

			$nodes[$node['node_id']]['rgt'] = $lft++;
		}

		return $nodes;
	}

	/**
	 * Rebuilds and saves nested set info (lft, rgt, depth) for all nodes based on parent id and display order
	 *
	 * @return array All nodes
	 */
	public function updateNestedSetInfo()
	{
		//TODO: This should probably have a much cleverer system than forcing a complete rebuild of the nested set info...
		$nodes = $this->getAllNodes(true);
		$nestedSetInfo = $this->getNewNestedSetInfo($nodes);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($nestedSetInfo AS $nodeId => $node)
		{
			/* @var $writer XenForo_DataWriter_Node */
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Node');

			// we want to set nested set info, so don't prevent it
			$writer->setOption(XenForo_DataWriter_Node::OPTION_ALLOW_NESTED_SET_WRITE, true);

			// prevent any child updates from occuring - we're handling it here
			$writer->setOption(XenForo_DataWriter_Node::OPTION_POST_WRITE_UPDATE_CHILD_NODES, false);

			// we already have the data, don't go and query it again
			$writer->setExistingData($nodes[$nodeId], true);

			$writer->set('lft', $node['lft']);
			$writer->set('rgt', $node['rgt']);
			$writer->set('depth', $node['depth']);
			$writer->set('effective_style_id', $node['effective_style_id']);

			// fingers crossed...
			$writer->save();
		}

		XenForo_Db::commit($db);

		return $nodes;
	}

	/**
	 * Fetches an array suitable as source for admin template 'options' tag from nodes array
	 *
	 * @param array Array of nodes, including node_id, title, parent_node_id and depth keys
	 * @param integer NodeId of selected node
	 * @param mixed Add root as the first option, and increment all depths by 1 to show indenting.
	 * 	If 'true', the root node will be entitled '(root node)', alternatively, specify a string to use
	 *  as the option text.
	 *
	 * @return array
	 */
	public function getNodeOptionsArray(array $nodes, $selectedNodeId = 0, $includeRoot = false)
	{
		$options = array();

		if ($includeRoot !== false)
		{
			$root = $this->getRootNode();

			$options[0] = array(
				'value' => 0,
				'label' => (is_string($includeRoot) === true ? $includeRoot : $root['title']),
				'selected' => (strval($selectedNodeId) === '0'),
				'depth' => 0,
			);
		}

		foreach ($nodes AS $nodeId => $node)
		{
			$node['depth'] += (($includeRoot && $nodeId) ? 1 : 0);

			$options[$nodeId] = array(
				'value' => $nodeId,
				'label' => $node['title'],
				'selected' => ($nodeId == $selectedNodeId),
				'depth' => $node['depth'],
				'node_type_id' => $node['node_type_id']
			);
		}

		return $options;
	}

	/**
	 * Returns a list of node type names for use in <xen:option> tags
	 *
	 * @param array Array of nodes including node_type_id key
	 *
	 * @return array
	 */
	public function getNodeTypeOptionsArray(array $nodes)
	{
		$nodeTypes = array();

		foreach ($nodes AS $nodeType)
		{
			$nodeTypes[$nodeType['node_type_id']] = $this->getNodeTypeNameById($nodeType['node_type_id']);
		}

		return $nodeTypes;
	}

	/**
	 * Filters a tree of nodes to show only the specified node types and their children.
	 * Children of an excluded node type will be removed, even if they are an included type.
	 *
	 * @param array Flattened, ordered node tree in order (by lft). Must include depth and node_type_id. Keyed by ID.
	 * @param $nodeTypes [Category, Forum, ... ] or [Category => 1, Forum => 1,...]
	 *
	 * @return array
	 */
	public function filterNodeTypesInTree(array $nodes, array $nodeTypes)
	{
		foreach ($nodes AS $index => $node)
		{
			if (!isset($nodeTypes[$node['node_type_id']]) && !in_array($node['node_type_id'], $nodeTypes))
			{
				unset($nodes[$index]);
				continue;
			}
		}

		return $this->filterOrphanNodes($nodes);
	}

	/**
	 * Filters orphaned nodes out of a list. Note the caveats:
	 * 	* The list must be in lft order
	 * 	* The nodes must be keyed by their ID
	 *
	 * @param array $nodes
	 *
	 * @return array
	 */
	public function filterOrphanNodes(array $nodes)
	{
		foreach ($nodes AS $nodeId => $node)
		{
			if ($node['parent_node_id'] > 0 && !isset($nodes[$node['parent_node_id']]))
			{
				unset($nodes[$nodeId]);
			}
		}

		return $nodes;
	}
}