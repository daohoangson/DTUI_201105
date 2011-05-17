<?php

/**
 * View that combines the requested CSS and output them in one request.
 *
 * @package XenForo_CssInternal
 */
class XenForo_ViewAdmin_CssInternal extends XenForo_ViewAdmin_Base
{
	/**
	 * Render the CSS version of the... CSS!
	 *
	 * @return string
	 */
	public function renderCss()
	{
		XenForo_Template_Abstract::setLanguageId(0);

		$templates = array();
		foreach ($this->_params['css'] AS $cssTemplate)
		{
			if (strpos($cssTemplate, 'public:') === 0)
			{
				$templates[$cssTemplate] = new XenForo_Template_Public(substr($cssTemplate, strlen('public:')));
			}
			else
			{
				$templates[$cssTemplate] = $this->createTemplateObject($cssTemplate);
			}
		}

		if (XenForo_Application::isRegistered('adminStyleModifiedDate'))
		{
			$modifyDate = XenForo_Application::get('adminStyleModifiedDate');
		}
		else
		{
			$modifyDate = XenForo_Application::$time;
		}

		$this->_response->setHeader('Expires', 'Wed, 01 Jan 2020 00:00:00 GMT', true);
		$this->_response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $modifyDate) . ' GMT', true);
		$this->_response->setHeader('Cache-Control', 'private', true);

		return XenForo_CssOutput::renderCssFromObjects($templates, true);
	}
}