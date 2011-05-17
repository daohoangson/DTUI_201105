<?php

class XenForo_ControllerAdmin_Page extends XenForo_ControllerAdmin_NodeAbstract
{
	/**
	 * Name of the DataWriter that will handle this node type
	 *
	 * @var string
	 */
	protected $_nodeDataWriterName = 'XenForo_DataWriter_Page';

	public function actionIndex()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Node', 'index');
	}

	public function actionAdd()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Page', 'edit');
	}

	public function actionEdit()
	{
		$pageModel = $this->_getPageModel();
		$nodeModel = $this->_getNodeModel();

		if ($nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT))
		{
			// if a node ID was specified, we should be editing, so make sure a page exists
			$page = $pageModel->getPageById($nodeId);
			if (!$page)
			{
				return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
			}

			$template = $this->_getTemplateModel()->getTemplateInStyleByTitle($pageModel->getTemplateTitle($page));
			if (!$template)
			{
				$template = array('template' => '');
			}
		}
		else
		{
			// add a new page
			$page = array(
				'parent_node_id' => $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT),
				'display_order' => 1,
				'display_in_list' => 1
			);

			$template = array('template' => '');
		}

		$viewParams = array(
			'page' => $page,
			'template' => $template,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($page), $page['parent_node_id'], true
			),
			'styles' => $this->_getStyleModel()->getAllStylesAsFlattenedTree(),
		);

		return $this->responseView('XenForo_ViewAdmin_Page_Edit', 'page_edit', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_Page', 'deleteConfirm');
		}

		$pageData = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'node_name' => XenForo_Input::STRING,
			'node_type_id' => XenForo_Input::BINARY,
			'parent_node_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT,
			'display_in_list' => XenForo_Input::UINT,
			'style_id' => XenForo_Input::UINT,
			'log_visits' => XenForo_Input::UINT,
			'list_siblings' => XenForo_Input::UINT,
			'list_children' => XenForo_Input::UINT,
			'callback_class' => XenForo_Input::STRING,
			'callback_method' => XenForo_Input::STRING,
		));

		if (!$this->_input->filterSingle('style_override', XenForo_Input::UINT))
		{
			$pageData['style_id'] = 0;
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		$pageData['modified_date'] = XenForo_Application::$time;

		$nodeId = $this->_getPageModel()->savePage(
			$pageData,
			$this->_input->filterSingle('template', XenForo_Input::STRING),
			$nodeId,
			$this->_input->filterSingle('template_id', XenForo_Input::UINT)
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('nodes') . $this->getLastHash($nodeId)
		);
	}

	public function actionDeleteConfirm()
	{
		$nodeModel = $this->_getNodeModel();

		$page = $nodeModel->getNodeById($this->_input->filterSingle('node_id', XenForo_Input::UINT));
		if (!$page)
		{
			return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
		}

		$childNodes = $nodeModel->getChildNodes($page);

		$viewParams = array(
			'page' => $page,
			'childNodes' => $childNodes,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($page), $page['parent_node_id'], true
			)
		);

		return $this->responseView('XenForo_ViewAdmin_Page_Delete', 'page_delete', $viewParams);
	}

	/**
	 * @return XenForo_Model_Page
	 */
	protected function _getPageModel()
	{
		return $this->getModelFromCache('XenForo_Model_Page');
	}

	/**
	 * @return XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}

	/**
	 * @return XenForo_DataWriter_Page
	 */
	protected function _getNodeDataWriter()
	{
		return XenForo_DataWriter::create($this->_nodeDataWriterName);
	}
}