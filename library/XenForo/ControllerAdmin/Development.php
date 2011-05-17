<?php

class XenForo_ControllerAdmin_Development extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	public function actionIndex()
	{
		$addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');

		$viewParams = array(
			'addOns' => $addOnModel->getAllAddOns(),
			'canAccessDevelopment' => $addOnModel->canAccessAddOnDevelopmentAreas()
		);

		return $this->responseView('XenForo_ViewAdmin_Development_Splash', 'development_splash', $viewParams);
	}
}