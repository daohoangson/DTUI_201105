<?php

class XenForo_ControllerPublic_Editor extends XenForo_ControllerPublic_Abstract
{
	public function actionDialog()
	{
		$styleId = $this->_input->filterSingle('style', XenForo_Input::UINT);
		if ($styleId)
		{
			$this->setViewStateChange('styleId', $styleId);
		}

		$dialog = $this->_input->filterSingle('dialog', XenForo_Input::STRING);
		$viewParams = array();

		if ($dialog == 'media')
		{
			$viewParams['sites'] = $this->_getBbCodeModel()->getAllBbCodeMediaSites();
		}

		$viewParams['jQuerySource'] = XenForo_Dependencies_Public::getJquerySource();
		$viewParams['jQuerySourceLocal'] = XenForo_Dependencies_Public::getJquerySource(true);
		$viewParams['javaScriptSource'] = XenForo_Application::get('options')->javaScriptSource;

		return $this->responseView('XenForo_ViewPublic_Editor_Dialog', 'editor_dialog_' . $dialog, $viewParams);
	}

	public function actionMedia()
	{
		$url = $this->_input->filterSingle('url', XenForo_Input::STRING);

		$matchBbCode = false;
		if ($url)
		{
			$bbCodeModel = $this->_getBbCodeModel();

			$sites = $bbCodeModel->getAllBbCodeMediaSites();
			foreach ($sites AS $siteId => $site)
			{
				$regexes = $bbCodeModel->convertMatchUrlsToRegexes($site['match_urls']);
				foreach ($regexes AS $regex)
				{
					if (preg_match($regex, $url, $matches))
					{
						$matchBbCode = '[media=' . $siteId . ']' . $matches['id'] . '[/media]';
						break;
					}
				}
			}
		}

		$viewParams = array('matchBbCode' => $matchBbCode);
		if (!$matchBbCode)
		{
			$viewParams['noMatch'] = new XenForo_Phrase('specified_url_cannot_be_embedded_as_media');
		}

		return $this->responseView('XenForo_ViewPublic_Editor_Media', '', $viewParams);
	}

	public function actionToBbCode()
	{
		$html = $this->_input->filterSingle('html', XenForo_Input::STRING);

		$options = array('stripLinkPathTraversal' => XenForo_Visitor::isBrowsingWith('firefox'));
		$bbCode = trim(XenForo_Html_Renderer_BbCode::renderFromHtml($html, $options));

		return $this->responseView('XenForo_ViewPublic_Editor_ToBbCode', '', array(
			'bbCode' => $bbCode
		));
	}

	public function actionToHtml()
	{
		return $this->responseView('XenForo_ViewPublic_Editor_ToHtml', '', array(
			'bbCode' => $this->_input->filterSingle('bbCode', XenForo_Input::STRING)
		));
	}

	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_BbCode');
	}
}