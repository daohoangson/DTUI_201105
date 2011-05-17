<?php

/**
* Concrete renderer for RSS output.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_Rss extends XenForo_ViewRenderer_Xml
{
	/**
	 * Constructor
	 * @see XenForo_ViewRenderer_Abstract::__construct()
	 */
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		parent::__construct($dependencies, $response, $request);
		$this->_response->setHeader('Content-Type', 'text/xml; charset=UTF-8', true);
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

		return $this->renderViewObject($viewName, 'Rss', $params, $templateName);
	}

	/**
	* Fallback for rendering an "unrepresentable" message.
	* @see XenForo_ViewRenderer_Abstract::renderUnrepresentable()
	*
	* @return string XML
	*/
	public function renderUnrepresentable()
	{
		return $this->renderError(new XenForo_Phrase('requested_page_is_unrepresentable_as_rss'));
	}
}