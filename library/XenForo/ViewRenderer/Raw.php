<?php

/**
* Concrete renderer for raw output. This does not support anything other than views.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_Raw extends XenForo_ViewRenderer_Abstract
{
	/**
	 * Constructor
	 * @see XenForo_ViewRenderer_Abstract::__construct()
	 */
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		parent::__construct($dependencies, $response, $request);
	}

	/**
	* Renders an error.
	* @see XenForo_ViewRenderer_Abstract::renderError()
	*
	* @param string
	*
	* @return string|false
	*/
	public function renderError($errorText)
	{
		return '';
	}

	/**
	 * Renders a message.
	 *
	 * @see XenForo_ViewRenderer_Abstract::renderMessage()
	 */
	public function renderMessage($message)
	{
		return '';
	}

	/**
	* Renders a view.
	* @see XenForo_ViewRenderer_Abstract::renderView()
	*/
	public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
	{
		if ($subView)
		{
			return $this->renderSubView($subView);
		}

		return $this->renderViewObject($viewName, 'Raw', $params, $templateName);
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
		return $contents;
	}

	/**
	* Fallback for rendering an "unrepresentable" message.
	* @see XenForo_ViewRenderer_Abstract::renderUnrepresentable()
	*
	* @return string
	*/
	public function renderUnrepresentable()
	{
		return '';
	}
}