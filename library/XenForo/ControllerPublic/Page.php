<?php

class XenForo_ControllerPublic_Page extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$nodeName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);
		$page = $this->_getPageOrError($nodeName);

		$this->_canonicalizeRequestUrl($page);

		$pageModel = $this->_getPageModel();
		$nodeModel = $this->_getNodeModel();

		if ($page['log_visits'])
		{
			$pageModel->logVisit($page, XenForo_Visitor::getInstance()->toArray(), XenForo_Application::$time);

			$page['view_count']++;
		}

		$nodeBreadCrumbs = $this->_getNodeModel()->getNodeBreadCrumbs($page, false);

		$viewParams = array(
			'page' => $page,
			'templateTitle' => $pageModel->getTemplateTitle($page),
			'nodeBreadCrumbs' => $nodeBreadCrumbs,

			'listSiblingNodes' => $page['list_siblings'],
			'siblingNodes' => $pageModel->getSiblingNodes($page),

			'listChildNodes' => $page['list_children'],
			'childNodes' => $pageModel->getChildNodes($page),

			'parentNode' => (isset($nodeBreadCrumbs[$page['parent_node_id']]) ? $nodeBreadCrumbs[$page['parent_node_id']] : null),
		);

		$response = $this->responseView(
			'XenForo_ViewPublic_Page_View',
			'pagenode_container',
			$viewParams
		);

		if (!empty($page['callback_class']) && !empty($page['callback_method']))
		{
			call_user_func_array(array($page['callback_class'], $page['callback_method']), array($this, &$response));
		}

		return $response;
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		$pageNames = array();
		foreach ($activities AS $activity)
		{
			if (!empty($activity['params']['node_name']))
			{
				$pageNames[$activity['params']['node_name']] = $activity['params']['node_name'];
			}
		}

		$pageData = array();

		if ($pageNames)
		{
			/* @var $pageModel XenForo_Model_Page */
			$pageModel = XenForo_Model::create('XenForo_Model_Page');

			$visitor = XenForo_Visitor::getInstance();
			$permissionCombinationId = $visitor['permission_combination_id'];

			$pages = $pageModel->getPagesByNames($pageNames, array(
				'permissionCombinationId' => $permissionCombinationId
			));
			foreach ($pages AS $page)
			{
				$visitor->setNodePermissions($page['node_id'], $page['node_permission_cache']);
				if ($pageModel->canViewPage($page))
				{
					$pageData[$page['node_name']] = array(
						'title' => $page['title'],
						'url' => XenForo_Link::buildPublicLink('pages', $page)
					);
				}
			}
		}

		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$page = false;
			if (!empty($activity['params']['node_name']))
			{
				$pageName = $activity['params']['node_name'];
				if (isset($pageData[$pageName]))
				{
					$page = $pageData[$pageName];
				}
			}

			if ($page)
			{
				$output[$key] = array(
					new XenForo_Phrase('viewing_page'),
					$page['title'],
					$page['url'],
					false
				);
			}
			else
			{
				$output[$key] = new XenForo_Phrase('viewing_page');
			}
		}

		return $output;
	}

	/**
	 * Returns a controller response with the template name corresponding to the page node_name
	 *
	 * @param XenForo_ControllerPublic_Abstract $controller
	 * @param XenForo_ControllerResponse_Abstract $response
	 */
	public static function getCustomTemplate(XenForo_ControllerPublic_Abstract $controller, XenForo_ControllerResponse_Abstract $response)
	{
		$response->templateName = $controller->_input->filterSingle('node_name', XenForo_Input::STRING);
	}

	/**
	 * Gets the specified page or throws an error.
	 *
	 * @param string $nodeName
	 *
	 * @return array
	 */
	protected function _getPageOrError($nodeName)
	{
		$visitor = XenForo_Visitor::getInstance();
		$fetchOptions = array('permissionCombinationId' => $visitor['permission_combination_id']);

		$page = $this->_getPageModel()->getPageByName($nodeName, $fetchOptions);
		if (!$page || $page['node_type_id'] != 'Page')
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_page_not_found'), 404));
		}

		if (isset($page['node_permission_cache']))
		{
			$visitor->setNodePermissions($page['node_id'], $page['node_permission_cache']);
			unset($page['node_permission_cache']);
		}

		if (!$this->_getPageModel()->canViewPage($page, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($page['effective_style_id'])
		{
			$this->setViewStateChange('styleId', $page['effective_style_id']);
		}

		return $page;
	}

	/**
	 * Force a redirect if we are not in the right place
	 *
	 * @param array $page
	 */
	protected function _canonicalizeRequestUrl(array $page)
	{
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('pages', $page)
		);
	}

	/**
	 * @return XenForo_Model_Page
	 */
	protected function _getPageModel()
	{
		return $this->getModelFromCache('XenForo_Model_Page');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}