<?php

/**
* Concrete renderer for HTML output for an install page.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_HtmlInstall extends XenForo_ViewRenderer_Abstract
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
		return $this->createTemplateObject('message', array('message' => $message));
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
		$templateName = (!empty($params['containerTemplate']) ? $params['containerTemplate'] : 'PAGE_CONTAINER');
		$template = $this->createTemplateObject($templateName, $params);

		if ($contents instanceof XenForo_Template_Abstract)
		{
			$contents = $contents->render();
		}

		$template->setParams($this->_dependencies->getExtraContainerData());
		$template->setParam('contents', $contents);

		$rendered = $template->render();
		return $rendered;
	}

	/**
	* Fallback for rendering an "unrepresentable" message.
	* @see XenForo_ViewRenderer_Abstract::renderUnrepresentable()
	*
	* @return string
	*/
	public function renderUnrepresentable()
	{
		return $this->renderError('The requested page is unrepresentable in HTML.');
	}
}