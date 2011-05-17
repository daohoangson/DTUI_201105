<?php

/**
* Concrete renderer for JSON text/plain output.
*
* This renderer is identical to the JSON renderer, except that it outputs a content-type header
* of 'text/plain' for those applications that require a plain text header rather than application/json,
* such as our inline file uploader.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_JsonText extends XenForo_ViewRenderer_Json
{
	/**
	 * Constructor
	 * @see XenForo_ViewRenderer_Abstract::__construct()
	 */
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		parent::__construct($dependencies, $response, $request);
		$this->_response->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
		$this->_response->setHeader('Content-Disposition', 'inline; filename=json.txt', true);
	}
}