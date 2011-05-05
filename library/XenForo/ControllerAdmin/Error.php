<?php

class XenForo_ControllerAdmin_Error extends XenForo_ControllerAdmin_Abstract
{
	public function actionErrorNotFound()
	{
		if (XenForo_Application::debugMode())
		{
			$controllerName = $this->_request->getParam('_controllerName');

			if (empty($controllerName))
			{
				return $this->responseError(
					new XenForo_Phrase('controller_for_route_not_found', array
					(
						'routePath' => $this->_request->getParam('_origRoutePath'),
					)), 404
				);
			}
			else
			{
				return $this->responseError(
					new XenForo_Phrase('controller_x_does_not_define_action_y', array
					(
						'controller' => $controllerName,
						'action' => $this->_request->getParam('_action')
					)), 404
				);
			}
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
		}
	}

	public function actionErrorServer()
	{
		$view = $this->responseView(
			'XenForo_ViewAdmin_Error_ServerError',
			'error_server_error',
			array('exception' => $this->_request->getParam('_exception'))
		);
		$view->responseCode = 500;
		return $view;
	}

	public function actionErrorSuperAdmin()
	{
		return $this->responseError(new XenForo_Phrase('you_must_be_super_admin_to_access_this_page'), 403);
	}

	protected function _assertCorrectVersion($action) {}
	protected function _assertInstallLocked($action) {}
	public function assertAdmin() {}
}