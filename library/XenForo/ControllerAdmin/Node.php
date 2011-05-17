<?php

class XenForo_ControllerAdmin_Node extends XenForo_ControllerAdmin_NodeAbstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('node');
	}

	/**
	 * Name of the DataWriter that will handle this node type
	 *
	 * @var string
	 */
	protected $_nodeDataWriterName = 'XenForo_DataWriter_Node';

	public function actionIndex()
	{
		$nodeModel = $this->_getNodeModel();

		$nodes = $nodeModel->prepareNodesForAdmin($nodeModel->getAllNodes());

		$moderatorsGrouped = array();
		$moderators = $this->_getModeratorModel()->getContentModerators(
			array('content' => 'node')
		);
		foreach ($moderators AS $moderator)
		{
			$moderatorsGrouped[$moderator['content_id']][] = $moderator;
		}
		foreach ($nodes AS &$node)
		{
			if (isset($moderatorsGrouped[$node['node_id']]))
			{
				$node['moderators'] = $moderatorsGrouped[$node['node_id']];
			}
			else
			{
				$node['moderators'] = array();
			}

			$node['moderatorCount'] = count($node['moderators']);
		}

		$viewParams = array(
			'nodes' => $nodes
		);

		return $this->responseView('XenForo_ViewAdmin_Node_List', 'node_list', $viewParams);
	}

	/**
	 * Prompt the user to choose the node type they would like to add
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAdd()
	{
		$nodeModel = $this->_getNodeModel();

		$parentNode = array();

		if ($parentNodeId = $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT))
		{
			if (!$parentNode = $nodeModel->getNodeById($parentNodeId))
			{
				$parentNodeId = 0;
			}
		}

		$viewParams = array(
			'parentNode' => $parentNode,
			'parent_node_id' => $parentNodeId,
			'nodeTypeOptions' => $nodeModel->getNodeTypeOptionsArray($nodeModel->getAllNodeTypes()),
		);

		return $this->responseView('XenForo_ViewAdmin_Nodes_Add', 'node_add', $viewParams);
	}

	/**
	 * If one tries to edit a node, reroute to the controller appropriate to its type
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::INT);

		if ($nodeId && $nodeType = $this->_getNodeModel()->getNodeTypeByNodeId($nodeId))
		{
			return $this->responseReroute($nodeType['controller_admin_class'], 'edit');
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('requested_node_not_found'), 404);
		}
	}

	/**
	 * Accept a form input from actionAdd and either reroute to the appropriate handler, or fail and exit
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInsert()
	{
		$nodeTypeId = $this->_input->filterSingle('node_type_id', XenForo_Input::STRING);

		if ($nodeTypeId && $nodeType = $this->_getNodeModel()->getNodeTypeById($nodeTypeId))
		{
			return $this->responseReroute($nodeType['controller_admin_class'], 'edit');
		}
		else
		{
			return $this->responseReroute(__CLASS__, 'add');
		}
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

	/**
	 * @return XenForo_Model_Moderator
	 */
	protected function _getModeratorModel()
	{
		return $this->getModelFromCache('XenForo_Model_Moderator');
	}

	/**
	 * @return XenForo_DataWriter_Node
	 */
	protected function _getNodeDataWriter()
	{
		return XenForo_DataWriter::create($this->_nodeDataWriterName);
	}
}