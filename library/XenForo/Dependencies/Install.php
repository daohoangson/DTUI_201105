<?php

/**
* Handles front controller dependencies for install/upgrade pages.
*
* @package XenForo_Mvc
*/
class XenForo_Dependencies_Install extends XenForo_Dependencies_Abstract
{
	public $templateClass = 'XenForo_Template_Install';

	/**
	* Routes the request.
	*
	* @see XenForo_Dependencies_Abstract::route()
	*/
	public function route(Zend_Controller_Request_Http $request)
	{
		$router = new XenForo_Router();
		$router->addRule(new XenForo_Route_ResponseSuffix(), 'ResponseSuffix')
		       ->addRule(new XenForo_Install_Route(), 'Install');

		return $router->match($request);
	}

	/**
	* Determines if the controller matched by the route can be dispatched. Use this
	* function to ensure, for example, that an admin page only shows an admin controller.
	*
	* @param mixed  Likely a XenForo_Controller object, but not guaranteed
	* @param string Name of the action to call
	*
	* @return boolean
	*/
	public function allowControllerDispatch($controller, $action)
	{
		return ($controller instanceof XenForo_Install_Controller_Abstract);
	}

	/**
	* Gets the routing information for a not found error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	public function getNotFoundErrorRoute()
	{
		return array('XenForo_Install_Controller_Index', 'ErrorNotFound');
	}

	/**
	* Gets the routing information for a search error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	public function getServerErrorRoute()
	{
		return array('XenForo_Install_Controller_Index', 'ErrorServer');
	}

	/**
	* Creates the view renderer for a specified response type. If an invalid
	* type is specified, false is returned.
	*
	* @param Zend_Controller_Response_Http Response object
	* @param string                        Type of response
	* @param Zend_Controller_Request_Http  Request object
	*
	* @return XenForo_ViewRenderer_Abstract|false
	*/
	public function getViewRenderer(Zend_Controller_Response_Http $response, $responseType, Zend_Controller_Request_Http $request)
	{
		$renderer = parent::getViewRenderer($response, $responseType, $request);
		if (!$renderer)
		{
			$renderer = new XenForo_ViewRenderer_HtmlInstall($this, $response, $request);
		}
		return $renderer;
	}

	/**
	 * Gets the base view class name for this type.
	 */
	public function getBaseViewClassName()
	{
		return 'XenForo_Install_View_Base';
	}

	/**
	* Helper method to create a template object for rendering.
	*
	* @param string Name of the template to be used
	* @param array  Key-value parameters to pass to the template
	*
	* @return XenForo_Template_Admin
	*/
	public function createTemplateObject($templateName, array $params = array())
	{
		if ($params)
		{
			$params = XenForo_Application::mapMerge($this->_defaultTemplateParams, $params);
		}
		else
		{
			$params = $this->_defaultTemplateParams;
		}

		$class = $this->templateClass;
		return new $class($templateName, $params);
	}

	/**
	 * Gets extra container data from template renders.
	 */
	public function getExtraContainerData()
	{
		return XenForo_Template_Install::getExtraContainerData();
	}

	/**
	* Preloads a template with the template handler for use later.
	*
	* @param string Template name
	*/
	public function preloadTemplate($templateName)
	{
		XenForo_Template_Install::preloadTemplate($templateName);
	}

	/**
	 * Pre-loads globally required data for the system.
	 */
	public function preLoadData() {}

	/**
	 * Performs any pre-view rendering setup, such as getting style information and
	 * ensuring the correct data is registered.
	 *
	 * @param XenForo_ControllerResponse_Abstract|null $controllerResponse
	 */
	public function preRenderView(XenForo_ControllerResponse_Abstract $controllerResponse = null)
	{
		// note this intentionally doesn't call the abstract version

		if (XenForo_Application::isRegistered('session'))
		{
			$this->_defaultTemplateParams['session'] = XenForo_Application::get('session')->getAll();
		}
		if (XenForo_Visitor::hasInstance())
		{
			$this->_defaultTemplateParams['visitor'] = XenForo_Visitor::getInstance()->toArray();
		}

		$this->_defaultTemplateParams['requestPaths'] = XenForo_Application::get('requestPaths');
	}

	/**
	* @see XenForo_Dependencies_Abstract::getEffectiveContainerParams
	*/
	public function getEffectiveContainerParams(array $params, Zend_Controller_Request_Http $request)
	{
		$params['showUpgradePendingNotice'] = false;

		return $params;
	}
}