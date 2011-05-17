<?php

class XenForo_Install_Controller_Index extends XenForo_Install_Controller_Abstract
{
	public function actionIndex()
	{
		if ($this->_getInstallModel()->isInstalled())
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				'index.php?upgrade/'
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				'index.php?install/'
			);
		}
	}

	public function actionErrorNotFound()
	{
		$response = $this->responseView(
			'XenForo_Install_View_ErrorNotFound',
			'error_not_found',
			array(
				'_controllerName' => $this->_request->getParam('_controllerName'),
				'_action' => $this->_request->getParam('_action')
			)
		);

		$response->responseCode = 404;
		return $response;
	}

	public function actionErrorServer()
	{
		$response = $this->responseView(
			'XenForo_Install_View_ErrorServer',
			'error_server_error',
			array(
				'exception' => $this->_request->getParam('_exception')
			)
		);

		$response->responseCode = 500;
		return $response;
	}

	protected function _setupSession($action) {}
	protected function _handlePost($action) {}

	/**
	 * @return XenForo_Install_Model_Install
	 */
	protected function _getInstallModel()
	{
		return $this->getModelFromCache('XenForo_Install_Model_Install');
	}
}