<?php

/**
 * Node handler for link forum-type nodes.
 *
 * @package XenForo_Page
 */
class XenForo_NodeHandler_LinkForum extends XenForo_NodeHandler_Abstract
{
	/**
	 * @var XenForo_Model_LinkForum
	 */
	protected $_linkForumModel = null;

	/**
	 * Determines if the specified node is viewable with the given permissions.
	 *
	 * @param array $node
	 * @param array $nodePermissions
	 *
	 * @return boolean
	 */
	public function isNodeViewable(array $node, array $nodePermissions)
	{
		return $this->_getLinkForumModel()->canViewLinkForum($node, $null, $nodePermissions);
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

		return $view->createTemplateObject('node_link_level_' . $templateLevel, array(
			'level' => $level,
			'link' => $node,
			'renderedChildren' => $renderedChildren
		));
	}


	/**
	 * @return XenForo_Model_LinkForum
	 */
	protected function _getLinkForumModel()
	{
		if ($this->_linkForumModel === null)
		{
			$this->_linkForumModel = XenForo_Model::create('XenForo_Model_LinkForum');
		}

		return $this->_linkForumModel;
	}
}