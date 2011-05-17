<?php

/**
 * Controller for accessing categories.
 *
 * @package XenForo_Nodes
 */
class XenForo_ControllerPublic_Category extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Displays the contents of a category.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$categoryId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		if (!$categoryId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('index')
			);
		}

		$category = $this->_getCategoryOrError($categoryId);

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('categories', $category)
		);

		$nodeModel = $this->_getNodeModel();

		$viewParams = array(
			'nodeList' => $nodeModel->getNodeDataForListDisplay($category, 2),
			'category' => $category,
			'nodeBreadCrumbs' => $nodeModel->getNodeBreadCrumbs($category, false),
		);

		return $this->responseView('XenForo_ViewPublic_Category_View', 'category_view', $viewParams);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_category');
	}

	/**
	 * Gets the specified category or throws an error.
	 *
	 * @param integer $categoryId
	 *
	 * @return array
	 */
	protected function _getCategoryOrError($categoryId)
	{
		$visitor = XenForo_Visitor::getInstance();
		$fetchOptions = array('permissionCombinationId' => $visitor['permission_combination_id']);

		$category = $this->_getNodeModel()->getNodeById($categoryId, $fetchOptions);
		if (!$category || $category['node_type_id'] != 'Category')
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_category_not_found'), 404));
		}

		if (isset($category['node_permission_cache']))
		{
			$visitor->setNodePermissions($categoryId, $category['node_permission_cache']);
			unset($category['node_permission_cache']);
		}

		if (!$this->_getCategoryModel()->canViewCategory($category, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($category['effective_style_id'])
		{
			$this->setViewStateChange('styleId', $category['effective_style_id']);
		}

		return $category;
	}

	/**
	 * @return XenForo_Model_Category
	 */
	protected function _getCategoryModel()
	{
		return $this->getModelFromCache('XenForo_Model_Category');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}