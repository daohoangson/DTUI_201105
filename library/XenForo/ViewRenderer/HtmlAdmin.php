<?php

/**
* Concrete renderer for HTML output for an admin page.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_HtmlAdmin extends XenForo_ViewRenderer_Abstract
{
	/**
	 * Constructor
	 * @see XenForo_ViewRenderer_Abstract::__construct()
	 */
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		parent::__construct($dependencies, $response, $request);
		$this->_response->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
	}

	/**
	* Renders an error.
	* @see XenForo_ViewRenderer_Abstract::renderError()
	*
	* @param string
	*
	* @return string|false
	*/
	public function renderError($error)
	{
		if (!is_array($error))
		{
			$error = array($error);
		}

		return $this->createTemplateObject('error', array('error' => $error));
	}

	/**
	 * Renders a message.
	 *
	 * @see XenForo_ViewRenderer_Abstract::renderMessage()
	 */
	public function renderMessage($message)
	{
		return strval($message);
	}

	/**
	* Renders a view.
	* @see XenForo_ViewRenderer_Abstract::renderView()
	*/
	public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
	{
		if ($subView)
		{
			if ($templateName)
			{
				$this->preloadTemplate($templateName);
			}
			$params['_subView'] = $this->renderSubView($subView);
		}

		$viewOutput = $this->renderViewObject($viewName, 'Html', $params, $templateName);
		if ($viewOutput === null)
		{
			if (!$templateName)
			{
				return false;
			}
			else
			{
				return $this->createTemplateObject($templateName, $params);
			}
		}
		else
		{
			return $viewOutput;
		}
	}

	/**
	* Renders the container.
	* @see XenForo_ViewRenderer_Abstract::renderContainer()
	*
	* @param string
	* @param array
	*
	* @return string
	*/
	public function renderContainer($contents, array $params = array())
	{
		$options = XenForo_Application::get('options');

		$params['debugMode'] = XenForo_Application::debugMode();
		$params['debugFormBackground'] = $options->debugFormBackground;

		if (!empty($params['adminNavigation']['sideLinks']))
		{
			$params['sideNav'] = $this->_renderSideNav(
				$params['adminNavigation']['sideLinksRoot'], $params['adminNavigation']['sideLinks']
			);
		}
		else
		{
			$params['sideNav'] = array();
		}

		$params['serverTimeInfo'] = XenForo_Locale::getDayStartTimestamps();

		$templateName = (!empty($params['containerTemplate']) ? $params['containerTemplate'] : 'PAGE_CONTAINER');
		$template = $this->createTemplateObject($templateName, $params);

		if ($contents instanceof XenForo_Template_Abstract)
		{
			$contents = $contents->render();
		}

		$template->setParams($this->_dependencies->getExtraContainerData());
		$template->setParam('contents', $contents);

		if ($params['debugMode'])
		{
			$template->setParams(XenForo_Debug::getDebugTemplateParams());
		}

		$rendered = $template->render();

		return $this->replaceRequiredExternalPlaceholders($template, $rendered);
	}

	protected function _renderSideNav($root, array $sideLinks)
	{
		if (!isset($sideLinks[$root]))
		{
			return array();
		}

		$output = array();
		foreach ($sideLinks[$root] AS $link)
		{
			$children = $this->_renderSideNav($link['navigation_id'], $sideLinks);

			$output[] = $this->createTemplateObject('sidenav_entry', array(
				'link' => $link,
				'children' => $children
			));
		}

		return $output;
	}

	/**
	* Data that should be preloaded for the container. Templates/phrases may be
	* accidentally (or intentionally) rendered in the view or before the container
	* is set to be rendered. Preloading data here can allow all the data to be fetched
	* at once.
	*/
	protected function _preloadContainerData()
	{
		$this->preloadTemplate('page_nav');
	}

	/**
	* Fallback for rendering an "unrepresentable" message.
	* @see XenForo_ViewRenderer_Abstract::renderUnrepresentable()
	*
	* @return string
	*/
	public function renderUnrepresentable()
	{
		return $this->renderError(new XenForo_Phrase('requested_page_is_unrepresentable_in_html'));
	}
}