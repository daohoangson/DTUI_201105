<?php

/**
 * Class to help display a node list/tree.
 *
 * @package XenForo_Nodes
 */
class XenForo_ViewPublic_Helper_Node
{
	/**
	 * Private constructor. Use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Renders the node tree using the specified nodes.
	 *
	 * @param XenForo_View $view View object calling this
	 * @param integer $parentNodeId Parent node ID, all children will be traversed recursively
	 * @param array $nodesGrouped Nodes, grouped by their parent node
	 * @param array $nodeHandlers List of handlers
	 * @param array $nodePerissions List of node permissions
	 * @param integer $level The effective level of the node, based on how it's being displayed.
	 *
	 * @return array Rendered results for each direct child that has been rendered
	 */
	public static function renderNodeTree(XenForo_View $view, $parentNodeId, array $nodesGrouped,
		array $nodePermissions, array $nodeHandlers, $level = 1
	)
	{
		$renderedNodes = array();

		if (!empty($nodesGrouped[$parentNodeId]))
		{
			$nextLevel = $level + 1;

			foreach ($nodesGrouped[$parentNodeId] AS $key => $node)
			{
				$renderedChildren = self::renderNodeTree(
					$view, $node['node_id'], $nodesGrouped, $nodePermissions, $nodeHandlers, $nextLevel
				);

				$permissions = (isset($nodePermissions[$node['node_id']]) ? $nodePermissions[$node['node_id']] : array());
				$handler = $nodeHandlers[$node['node_type_id']];

				$renderedNodes[$key] = $handler->renderNodeForTree(
					$view, $node, $permissions, $renderedChildren, $level
				);
			}
		}

		return $renderedNodes;
	}

	/**
	 * Helper to render a node tree using the array returned by {@link XenForo_Node::getNodeDataForListDisplay()}.
	 *
	 * @param XenForo_View $view View object calling this
	 * @param array $nodeList Node data from {@link XenForo_Node::getNodeDataForListDisplay()}
	 * @param integer $level The effective level of the node, based on how it's being displayed.
	 *
	 * @return array Rendered results for each direct child that has been rendered
	 */
	public static function renderNodeTreeFromDisplayArray(XenForo_View $view, array $nodeList, $level = 1)
	{
		if ($nodeList)
		{
			return self::renderNodeTree(
				$view, $nodeList['parentNodeId'],
				$nodeList['nodesGrouped'], $nodeList['nodePermissions'], $nodeList['nodeHandlers'],
				$level
			);
		}
		else
		{
			return array();
		}
	}
}