<?php

/**
 * Moderator handler for nodes (and all node types).
 *
 * @package XenForo_Moderator
 */
class XenForo_ModeratorHandler_Node extends XenForo_ModeratorHandler_Abstract
{
	/**
	 * Gets moderator permission interface group IDs.
	 * @see XenForo_ModeratorHandler_Abstract::getModeratorInterfaceGroupIds()
	 */
	public function getModeratorInterfaceGroupIds()
	{
		$nodeTypes = $this->_getNodeModel()->getAllNodeTypes();

		$ids = array();
		foreach ($nodeTypes AS $nodeType)
		{
			if ($nodeType['moderator_interface_group_id'])
			{
				$ids[] = $nodeType['moderator_interface_group_id'];
			}
		}

		return $ids;
	}

	/**
	 * Gets the option for the moderator add "choice" page.
	 * @see XenForo_ModeratorHandler_Abstract::getAddModeratorOption()
	 */
	public function getAddModeratorOption(XenForo_View $view, $selectedContentId, $contentType)
	{
		$nodeModel = $this->_getNodeModel();
		$nodes = array('0' => array('value' => 0, 'label' => '')) + $nodeModel->getNodeOptionsArray($nodeModel->getAllNodes());

		return array(
			'value' => $contentType,
			'label' => new XenForo_Phrase('forum_moderator') . ':',
			'disabled' => array(
				XenForo_Template_Helper_Admin::select("type_id[$contentType]", $selectedContentId, $nodes)
			)
		);
	}

	/**
	 * Gets the titles of multiple pieces of content.
	 * @see XenForo_ModeratorHandler_Abstract::getContentTitles()
	 */
	public function getContentTitles(array $ids)
	{
		$nodes = $this->_getNodeModel()->getAllNodes();
		$titles = array();
		foreach ($ids AS $key => $id)
		{
			if (isset($nodes[$id]))
			{
				$node = $nodes[$id];
				$titles[$key] = new XenForo_Phrase('node_type_' . $node['node_type_id']) . " - $node[title]";
			}
		}

		return $titles;
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return XenForo_Model::create('XenForo_Model_Node');
	}
}