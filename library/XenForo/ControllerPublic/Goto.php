<?php

/**
 * Controller that handles jumping to various content types. It is expected
 * that each action will redirect to the canonical URL for the specified content.
 */
class XenForo_ControllerPublic_Goto extends XenForo_ControllerPublic_Abstract
{
	public function actionPost()
	{
		$this->_request->setParam('post_id', $this->_input->filterSingle('id' , XenForo_Input::UINT));
		return $this->responseReroute('XenForo_ControllerPublic_Post', 'index');
	}

	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}
}