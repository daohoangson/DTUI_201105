<?php

class XenForo_ControllerAdmin_Forum extends XenForo_ControllerAdmin_NodeAbstract
{
	/**
	 * Name of the DataWriter that will handle this node type
	 *
	 * @var string
	 */
	protected $_nodeDataWriterName = 'XenForo_DataWriter_Forum';

	public function actionIndex()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Node', 'index');
	}

	public function actionAdd()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Forum', 'edit');
	}

	public function actionEdit()
	{
		$forumModel = $this->_getForumModel();
		$nodeModel = $this->_getNodeModel();

		if ($nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT))
		{
			// if a node ID was specified, we should be editing, so make sure a forum exists
			$forum = $forumModel->getForumById($nodeId);
			if (!$forum)
			{
				return $this->responseError(new XenForo_Phrase('requested_forum_not_found'), 404);
			}
		}
		else
		{
			// add a new forum
			$forum = array(
				'parent_node_id' => $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT),
				'display_order' => 1,
				'display_in_list' => 1,
				'allow_posting' => 1
			);
		}

		$viewParams = array(
			'forum' => $forum,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($forum), $forum['parent_node_id'], true
			),
			'styles' => $this->_getStyleModel()->getAllStylesAsFlattenedTree(),
		);

		return $this->responseView('XenForo_ViewAdmin_Forum_Edit', 'forum_edit', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_Forum', 'deleteConfirm');
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		$writerData = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'node_name' => XenForo_Input::STRING,
			'node_type_id' => XenForo_Input::STRING,
			'parent_node_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT,
			'display_in_list' => XenForo_Input::UINT,
			'description' => XenForo_Input::STRING,
			'style_id' => XenForo_Input::UINT,
			'moderate_messages' => XenForo_Input::UINT,
			'allow_posting' => XenForo_Input::UINT
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
		$forumModel = $this->_getForumModel();
		$nodeModel = $this->_getNodeModel();

		$forum = $forumModel->getForumById($this->_input->filterSingle('node_id', XenForo_Input::UINT));
		if (!$forum)
		{
			return $this->responseError(new XenForo_Phrase('requested_forum_not_found'), 404);
		}

		$childNodes = $nodeModel->getChildNodes($forum);

		$viewParams = array(
			'forum' => $forum,
			'childNodes' => $childNodes,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($forum), $forum['parent_node_id'], true
			)
		);

		return $this->responseView('XenForo_ViewAdmin_Forum_Delete', 'forum_delete', $viewParams);
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}

	/**
	 * @return XenForo_DataWriter_Forum
	 */
	protected function _getNodeDataWriter()
	{
		return XenForo_DataWriter::create($this->_nodeDataWriterName);
	}
}