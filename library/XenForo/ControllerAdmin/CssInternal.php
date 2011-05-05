<?php

/**
 * Controller for displaying the CSS that runs the admin control panel.
 *
 * @package XenForo_CssInternal
 */
class XenForo_ControllerAdmin_CssInternal extends XenForo_ControllerAdmin_Abstract
{
	/**
	 * Displays the selected CSS.
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionCss()
	{
		$cssTemplates = explode(',', $this->_input->filterSingle('css', XenForo_Input::STRING));

		$templates = array();
		foreach ($cssTemplates AS $cssName)
		{
			$cssName = trim($cssName);
			if (!$cssName)
			{
				continue;
			}

			$templates[] = $cssName . '.css';
		}

		$viewParams = array(
			'css' => array_unique($templates)
		);

		return $this->responseView('XenForo_ViewAdmin_CssInternal', '', $viewParams);
	}

	protected function _assertCorrectVersion($action) {}
	protected function _assertInstallLocked($action) {}
	public function assertAdmin() {}
}