<?php

/**
* Concrete renderer for XML output.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_Xml extends XenForo_ViewRenderer_Abstract
{
	/**
	 * Constructor
	 * @see XenForo_ViewRenderer_Abstract::__construct()
	 */
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		parent::__construct($dependencies, $response, $request);
		$this->_response->setHeader('Content-Type', 'application/xml; charset=UTF-8', true);
	}

	/**
	 * Simple handler for XML redirects - do not redirect, just send status:ok and redirect:$redirectTarget
	 *
	 * @param integer Type of redirect. See {@link XenForo_ControllerResponse_Redirect}
	 * @param string  Target to redirect to
	 * @param mixed   Redirect message
	 *
	 * @return string XML response (response tag)
	 */
	public function renderRedirect($redirectType, $redirectTarget, $redirectMessage = null, array $redirectParams = array())
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('response');
		XenForo_Helper_DevelopmentXml::createDomElements($rootNode, array(
			'_redirectStatus' => 'ok',
			'_redirectTarget' => $redirectTarget,
			'_redirectMessage' => (is_null($redirectMessage) ? new XenForo_Phrase('redirect_changes_saved_successfully') : $redirectMessage),
			'jsonParams' => XenForo_ViewRenderer_Json::jsonEncodeForOutput($redirectParams)
		));
		$document->appendChild($rootNode);

		return $document->saveXML();
	}

	/**
	* Renders an error.
	* @see XenForo_ViewRenderer_Abstract::renderError()
	*
	* @param string|array Error message
	*
	* @return string XML, with errors root node and error tags under
	*/
	public function renderError($error)
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		if (!is_array($error))
		{
			$error = array($error);
		}

		$rootNode = $document->createElement('errors');
		$document->appendChild($rootNode);

		foreach ($error AS $errorMessage)
		{
			$errorNode = $rootNode->appendChild($document->createElement('error'));
			$errorNode->appendChild($document->createCDATASection($errorMessage));
		}

		return $document->saveXML();
	}

	/**
	 * Renders a message.
	 *
	 * @see XenForo_ViewRenderer_Abstract::renderMessage()
	 */
	public function renderMessage($message)
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('response');
		$rootNode->appendChild($document->createElement('status', 'ok'));
		$rootNode->appendChild($document->createElement('message', $message));
		$document->appendChild($rootNode);

		return $document->saveXML();
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

		return $this->renderViewObject($viewName, 'Xml', $params, $templateName);
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
	* @return string XML
	*/
	public function renderUnrepresentable()
	{
		return $this->renderError(new XenForo_Phrase('requested_page_is_unrepresentable_in_xml'));
	}
}