<?php

/**
 * Node handler for page-type nodes.
 *
 * @package XenForo_Page
 */
class XenForo_NodeHandler_Page extends XenForo_NodeHandler_Abstract
{
	/**
	 * Page model object.
	 *
	 * @var XenForo_Model_Page
	 */
	protected $_pageModel = null;

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
		return $this->_getPageModel()->canViewPage($node, $null, $nodePermissions);
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

		return $view->createTemplateObject('node_page_level_' . $templateLevel, array(
			'level' => $level,
			'page' => $node,
			'renderedChildren' => $renderedChildren
		));
	}

	/**
	 * Do type-specific node preparations.
	 *
	 * @param array $node Unprepared data
	 *
	 * @return array Prepared data
	 */
	/*public function prepareNode(array $node)
	{
		return $this->_getPageModel()->preparePage($node);
	}*/

	/**
	 * @return XenForo_Model_Page
	 */
	protected function _getPageModel()
	{
		if ($this->_pageModel === null)
		{
			$this->_pageModel = XenForo_Model::create('XenForo_Model_Page');
		}

		return $this->_pageModel;
	}
}