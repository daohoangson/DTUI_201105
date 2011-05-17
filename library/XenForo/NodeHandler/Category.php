<?php

/**
 * Node handler for category-type nodes.
 *
 * @package XenForo_Category
 */
class XenForo_NodeHandler_Category extends XenForo_NodeHandler_Abstract
{
	/**
	 * Category model object.
	 *
	 * @var XenForo_Model_Category
	 */
	protected $_categoryModel = null;

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
		return $this->_getCategoryModel()->canViewCategory($node, $null, $permissions);
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

		return $view->createTemplateObject('node_category_level_' . $templateLevel, array(
			'level' => $level,
			'category' => $node,
			'renderedChildren' => $renderedChildren
		));
	}

	/**
	 * @return XenForo_Model_Category
	 */
	protected function _getCategoryModel()
	{
		if ($this->_categoryModel === null)
		{
			$this->_categoryModel = XenForo_Model::create('XenForo_Model_Category');
		}

		return $this->_categoryModel;
	}
}