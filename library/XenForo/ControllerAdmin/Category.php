<?php

class XenForo_ControllerAdmin_Category extends XenForo_ControllerAdmin_NodeAbstract
{
	/**
	 * Name of the DataWriter that will handle this node type
	 *
	 * @var string
	 */
	protected $_nodeDataWriterName = 'XenForo_DataWriter_Category';

	public function actionIndex()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Node', 'index');
	}

	public function actionAdd()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Category', 'edit');
	}

	public function actionEdit()
	{
		$nodeModel = $this->_getNodeModel();

		if ($nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT))
		{
			// if a node ID was specified, we should be editing, so make sure a category exists
			$category = $nodeModel->getNodeById($nodeId);
			if (!$category)
			{
				return $this->responseError(new XenForo_Phrase('requested_category_not_found'), 404);
			}
		}
		else
		{
			// add a new category
			$category = array(
				'parent_node_id' => $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT),
				'display_order' => 1,
				'display_in_list' => 1
			);
		}

		$viewParams = array(
			'category' => $category,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($category), $category['parent_node_id'], true
			),
			'styles' => $this->_getStyleModel()->getAllStylesAsFlattenedTree(),
		);

		return $this->responseView('XenForo_ViewAdmin_Category_Edit', 'category_edit', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_Category', 'deleteConfirm');
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		$writerData = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'node_type_id' => XenForo_Input::BINARY,
			'parent_node_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT,
			'display_in_list' => XenForo_Input::UINT,
			'description' => XenForo_Input::STRING,
			'style_id' => XenForo_Input::UINT,
		));
		if (!$this->_input->filterSingle('style_override', XenForo_Input::UINT))
		{
			$writerData['style_id'] = 0;
		}

		$writer = $this->_getNodeDataWriter();

		if ($nodeId)
		{
			$writer->setExistingData($nodeId);
		}

		$writer->bulkSet($writerData);
		$writer->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('nodes') . $this->getLastHash($writer->get('node_id'))
		);
	}

	public function actionDeleteConfirm()
	{
		$nodeModel = $this->_getNodeModel();

		$category = $nodeModel->getNodeById($this->_input->filterSingle('node_id', XenForo_Input::UINT));
		if (!$category)
		{
			return $this->responseError(new XenForo_Phrase('requested_category_not_found'), 404);
		}

		$childNodes = $nodeModel->getChildNodes($category);

		$viewParams = array(
			'category' => $category,
			'childNodes' => $childNodes,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($category), $category['parent_node_id'], true
			)
		);

		return $this->responseView('XenForo_ViewAdmin_Category_Delete', 'category_delete', $viewParams);
	}

	/**
	 * @return XenForo_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenForo_Model_Category');
	}

	/**
	 * @return XenForo_DataWriter_Category
	 */
	protected function _getNodeDataWriter()
	{
		return XenForo_DataWriter::create($this->_nodeDataWriterName);
	}
}