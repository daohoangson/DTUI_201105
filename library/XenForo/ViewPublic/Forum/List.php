<?php

/**
 * View handling for displaying a list of all forums (nodes).
 *
 * @package XenForo_Nodes
 */
class XenForo_ViewPublic_Forum_List extends XenForo_ViewPublic_Base
{
	/**
	 * Renders the HTML page.
	 *
	 * @return mixed
	 */
	public function renderHtml()
	{
		$this->_params['renderedNodes'] = XenForo_ViewPublic_Helper_Node::renderNodeTreeFromDisplayArray(
			$this, $this->_params['nodeList']
		);
	}
}