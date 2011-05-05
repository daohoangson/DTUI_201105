<?php

class XenForo_ControllerAdmin_LinkForum extends XenForo_ControllerAdmin_NodeAbstract
{
	/**
	 * Name of the DataWriter that will handle this node type
	 *
	 * @var string
	 */
	protected $_nodeDataWriterName = 'XenForo_DataWriter_LinkForum';

	public function actionIndex()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Node', 'index');
	}

	public function actionAdd()
	{
		return $this->responseReroute(__CLASS__, 'edit');
	}

	public function actionEdit()
	{
		$nodeModel = $this->_getNodeModel();
		$linkForumModel = $this->_getLinkForumModel();

		if ($nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT))
		{
			// if a node ID was specified, we should be editing, so make sure node exists
			$link = $linkForumModel->getLinkForumById($nodeId);
			if (!$link)
			{
				return $this->responseError(new XenForo_Phrase('requested_link_forum_not_found'), 404);
			}
		}
		else
		{
			// add a new link
			$link = array(
				'parent_node_id' => $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT),
				'display_order' => 1,
				'display_in_list' => 1
			);
		}

		$viewParams = array(
			'link' => $link,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($link), $link['parent_node_id'], true
			)
		);

		return $this->responseView('XenForo_ViewAdmin_LinkForum_Edit', 'link_forum_edit', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		$writerData = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'node_type_id' => XenForo_Input::BINARY,
			'parent_node_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT,
			'display_in_list' => XenForo_Input::UINT,
			'description' => XenForo_Input::STRING,
			'link_url' => XenForo_Input::STRING
		));

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
		$linkForumModel = $this->_getLinkForumModel();

		$link = $linkForumModel->getLinkForumById($this->_input->filterSingle('node_id', XenForo_Input::UINT));
		if (!$link)
		{
			return $this->responseError(new XenForo_Phrase('requested_link_forum_not_found'), 404);
		}

		$childNodes = $nodeModel->getChildNodes($link);

		$viewParams = array(
			'link' => $link,
			'childNodes' => $childNodes,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($link), $link['parent_node_id'], true
			)
		);

		return $this->responseView('XenForo_ViewAdmin_LinkForum_Delete', 'link_forum_delete', $viewParams);
	}

	/**
	 * @return XenForo_Model_LinkForum
	 */
	protected function _getLinkForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_LinkForum');
	}

	/**
	 * @return XenForo_DataWriter_Link
	 */
	protected function _getNodeDataWriter()
	{
		return XenForo_DataWriter::create($this->_nodeDataWriterName);
	}
}