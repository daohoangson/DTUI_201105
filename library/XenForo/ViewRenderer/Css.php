<?php

/**
* Concrete renderer for CSS output.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_Css extends XenForo_ViewRenderer_Abstract
{
	/**
	 * Constructor
	 * @see XenForo_ViewRenderer_Abstract::__construct()
	 */
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		parent::__construct($dependencies, $response, $request);
		$this->_response->setHeader('Content-Type', 'text/css; charset=UTF-8', true);
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
		return '/* error: ' . str_replace('*/', '', $errorText) . ' */';
	}

	/**
	 * Renders a message.
	 *
	 * @see XenForo_ViewRenderer_Abstract::renderMessage()
	 */
	public function renderMessage($message)
	{
		return '/* error: ' . str_replace('*/', '', $message) . ' */';
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

		return $this->renderViewObject($viewName, 'Css', $params, $templateName);
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
		return '/* unrepresentable */';
	}

	/**
	 * Writes out a clear-fix for the given selector, which will not interfere
	 * with absolutely positioned children, unlike the more common overflow: hidden solution.
	 *
	 * @param string $selector
	 *
	 * @return string CSS clearfix rules
	 */
	public static function helperClearfix($cssSelector)
	{
		return "/* clearfix */ $cssSelector { zoom: 1; } $cssSelector:after { content: '.'; display: block; height: 0; clear: both; visibility: hidden; }";
	}

	/**
	 * Adds !important to all CSS rules (by searching for semi-colons)
	 *
	 * @param string $cssString
	 *
	 * @return string
	 */
	public static function helperCssImportant($cssString)
	{
		return str_replace(';', ' !important;', $cssString);
	}
}