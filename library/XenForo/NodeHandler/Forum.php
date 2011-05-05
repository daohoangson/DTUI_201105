<?php

/**
 * Node handler for forum-type nodes.
 *
 * @package XenForo_Forum
 */
class XenForo_NodeHandler_Forum extends XenForo_NodeHandler_Abstract
{
	/**
	 * Forum model object.
	 *
	 * @var XenForo_Model_Forum
	 */
	protected $_forumModel = null;

	/**
	 * Determines if the specified node is viewable with the given permissions.
	 *
	 * @param array $node Node info
	 * @param array $permissions Permissions for this node
	 *
	 * @return boolean
	 */
	public function isNodeViewable(array $node, array $permissions)
	{
		return $this->_getForumModel()->canViewForum($node, $null, $permissions);
	}

	/**
	 * Renders the specified node for display in a node tree.
	 *
	 * @param XenForo_View $view View object doing the rendering
	 * @param array $node Information about this node
	 * @param array $permissions Permissions for this node
	 * @param array $renderedChildren List of rendered children, [node id] => rendered output
	 * @param integer $level The level this node should be rendered at, relative to how it's to be displayed.
	 *
	 * @return string|XenForo_Template_Abstract
	 */
	public function renderNodeForTree(XenForo_View $view, array $node, array $permissions,
		array $renderedChildren, $level
	)
	{
		$templateLevel = ($level <= 2 ? $level : 'n');

		return $view->createTemplateObject('node_forum_level_' . $templateLevel, array(
			'level' => $level,
			'forum' => $node,
			'renderedChildren' => $renderedChildren
		));
	}

	/**
	 * Gets the extra, node-type-specified data for the list of nodes.
	 *
	 * @param array $nodeIds
	 *
	 * @return array Format: [node id] => info
	 */
	public function getExtraDataForNodes(array $nodeIds)
	{
		$userId = XenForo_Visitor::getUserId(); // TODO: ideally this should be passed in
		$forumFetchOptions = array('readUserId' => $userId);

		return $this->_getForumModel()->getExtraForumDataForNodes($nodeIds, $forumFetchOptions);
	}

	/**
	 * Do type-specific node preparations.
	 *
	 * @param array $node Unprepared data
	 *
	 * @return array Prepared data
	 */
	public function prepareNode(array $node)
	{
		return $this->_getForumModel()->prepareForum($node);
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		if ($this->_forumModel === null)
		{
			$this->_forumModel = XenForo_Model::create('XenForo_Model_Forum');
		}

		return $this->_forumModel;
	}
}