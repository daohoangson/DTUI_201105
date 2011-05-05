<?php

abstract class XenForo_ControllerAdmin_NodeAbstract extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('node');
	}

	/**
	 * @return XenForo_DataWriter_Node
	 */
	abstract protected function _getNodeDataWriter();

	/**
	 * Validate a single field
	 *
	 * @return XenForo_ControllerResponse_View|XenForo_ControllerResponse_Error
	 */
	public function actionValidateField()
	{
		$this->_assertPostOnly();

		return $this->_validateField($this->_nodeDataWriterName, array(
			'existingDataKey' => $this->_input->filterSingle('node_id', XenForo_Input::UINT)
		));
	}

	/**
	 * This method should be sufficiently generic to handle deletion of any extended node type
	 *
	 * @return XenForo_ControllerResponse_Reroute
	 */
	public function actionDelete()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::INT);

		if ($this->isConfirmedPost())
		{
			$writer = $this->_getNodeDataWriter();
			$writer->setExistingData($nodeId);

			if ($this->_input->filterSingle('move_child_nodes', XenForo_Input::BINARY))
			{
				$parentNodeId = $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT);

				if ($parentNodeId)
				{
					$parentNode = $this->_getNodeModel()->getNodeById($parentNodeId);

					if (!$parentNode)
					{
						return $this->responseError(new XenForo_Phrase('specified_destination_node_does_not_exist'));
					}
				}
				else
				{
					// no destination node id, so set it to 0 (root node)
					$parentNodeId = 0;
				}

				$writer->setOption(XenForo_DataWriter_Node::OPTION_CHILD_NODE_DESTINATION_PARENT_ID, $parentNodeId);
			}

			$writer->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('nodes')
			);
		}
		else
		{
			if ($nodeId && $nodeType = $this->_getNodeModel()->getNodeTypeByNodeId($nodeId))
			{
				return $this->responseReroute($nodeType['controller_admin_class'], 'deleteConfirm');
			}
			else
			{
				return $this->responseError(new XenForo_Phrase('requested_node_not_found'), 404);
			}
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
	 * @return XenForo_Model_Style
	 */
	protected function _getStyleModel()
	{
		return $this->getModelFromCache('XenForo_Model_Style');
	}
}