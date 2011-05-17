<?php

/**
 * Controller for accessing link forums.
 *
 * @package XenForo_Nodes
 */
class XenForo_ControllerPublic_LinkForum extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Displays the contents of a link forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$linkId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		if (!$linkId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('index')
			);
		}

		$link = $this->_getLinkOrError($linkId);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			$link['link_url']
		);
	}

	/**
	 * Gets the specified category or throws an error.
	 *
	 * @param integer $linkId
	 *
	 * @return array
	 */
	protected function _getLinkOrError($linkId)
	{
		$visitor = XenForo_Visitor::getInstance();
		$fetchOptions = array('permissionCombinationId' => $visitor['permission_combination_id']);

		$link = $this->_getLinkForumModel()->getLinkForumById($linkId, $fetchOptions);
		if (!$link || $link['node_type_id'] != 'LinkForum')
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_link_forum_not_found'), 404));
		}

		if (isset($link['node_permission_cache']))
		{
			$visitor->setNodePermissions($linkId, $link['node_permission_cache']);
			unset($link['node_permission_cache']);
		}

		if (!$this->_getLinkForumModel()->canViewLinkForum($link, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		return $link;
	}

	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * @return XenForo_Model_LinkForum
	 */
	protected function _getLinkForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_LinkForum');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}