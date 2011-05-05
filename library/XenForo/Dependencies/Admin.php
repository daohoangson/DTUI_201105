<?php

/**
* Handles front controller dependencies for admin pages.
*
* @package XenForo_Mvc
*/
class XenForo_Dependencies_Admin extends XenForo_Dependencies_Abstract
{
	/**
	 * List of data to pre-load from the data registry. You must process this data
	 * via {@link _handleCustomPreLoadedData()}.
	 *
	 * @var array
	 */
	protected $_dataPreLoadFromRegistry = array('routesAdmin', 'adminStyleProperties', 'adminStyleModifiedDate');

	/**
	 * Handles the custom data that needs to be preloaded.
	 *
	 * @param array $data Data that was loaded. Unsuccessfully loaded items will have a value of null
	 */
	protected function _handleCustomPreloadedData(array &$data)
	{
		if (!is_array($data['routesAdmin']))
		{
			$data['routesAdmin'] = XenForo_Model::create('XenForo_Model_RoutePrefix')->rebuildRoutePrefixTypeCache('admin');
		}
		XenForo_Link::setHandlerInfoForGroup('admin', $data['routesAdmin']);

		if (!is_array($data['adminStyleProperties']))
		{
			$data['adminStyleProperties'] = XenForo_Model::create('XenForo_Model_StyleProperty')->rebuildPropertyCacheInStyleAndChildren(-1);
		}
		XenForo_Application::set('adminStyleProperties', $data['adminStyleProperties']);

		if (!is_int($data['adminStyleModifiedDate']))
		{
			$data['adminStyleModifiedDate'] = 0;
		}
		XenForo_Application::set('adminStyleModifiedDate', $data['adminStyleModifiedDate']);
	}

	/**
	* Routes the request.
	*
	* @see XenForo_Dependencies_Abstract::route()
	*/
	public function route(Zend_Controller_Request_Http $request)
	{
		$router = new XenForo_Router();
		$router->addRule(new XenForo_Route_ResponseSuffix(), 'ResponseSuffix')
		       ->addRule(new XenForo_Route_Prefix('admin'), 'PrefixAdmin');

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
		return ($controller instanceof XenForo_ControllerAdmin_Abstract);
	}

	/**
	* Gets the routing information for a not found error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	public function getNotFoundErrorRoute()
	{
		return array('XenForo_ControllerAdmin_Error', 'ErrorNotFound');
	}

	/**
	* Gets the routing information for a search error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	public function getServerErrorRoute()
	{
		return array('XenForo_ControllerAdmin_Error', 'ErrorServer');
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
			$renderer = new XenForo_ViewRenderer_HtmlAdmin($this, $response, $request);
		}
		return $renderer;
	}

	/**
	 * Gets the base view class name for this type.
	 */
	public function getBaseViewClassName()
	{
		return 'XenForo_ViewAdmin_Base';
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

		return new XenForo_Template_Admin($templateName, $params);
	}

	/**
	 * Gets extra container data from template renders.
	 */
	public function getExtraContainerData()
	{
		return XenForo_Template_Admin::getExtraContainerData();
	}

	/**
	* Preloads a template with the template handler for use later.
	*
	* @param string Template name
	*/
	public function preloadTemplate($templateName)
	{
		XenForo_Template_Admin::preloadTemplate($templateName);
	}

	/**
	* Gets the effective set of container params. This includes combining
	* and specific container params with any global ones. For example, a specific
	* container param may refer to the section the page is in, so this function
	* could load the other options that are specific to this section.
	*
	* @param array $params Container params from the controller/view
	* @param Zend_Controller_Request_Http $request
	*
	* @return array
	*/
	public function getEffectiveContainerParams(array $params, Zend_Controller_Request_Http $request)
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor['is_admin'] && !isset($params['containerTemplate']))
		{
			$params['containerTemplate'] = 'LOGIN_PAGE';
		}

		$options = XenForo_Application::get('options');
		$params['homeLink'] = ($options->homePageUrl ? $options->homePageUrl : XenForo_Link::buildPublicLink('index'));

		$params['jQuerySource'] = self::getJquerySource();

		$params = XenForo_Application::mapMerge($params,
			parent::getEffectiveContainerParams($params, $request),
			$this->_getCronContainerParams(),
			$this->_getNavigationContainerParams(empty($params['majorSection']) ? '' : $params['majorSection'])
		);

		XenForo_CodeEvent::fire('container_admin_params', array(&$params, $this));

		return $params;
	}

	/**
	 * Gets the appropriate container parameters for navigation based
	 * on the requested tab and section, taking into account the visitor's
	 * admin permissions.
	 *
	 * @param string $breadCrumbId Name of the last element in the breadcrumb
	 *
	 * @return array Navigation params
	 */
	protected function _getNavigationContainerParams($breadCrumbId)
	{
		/* @var $navigationModel XenForo_Model_AdminNavigation */
		$navigationModel = XenForo_Model::create('XenForo_Model_AdminNavigation');
		return array(
			'adminNavigation' => $navigationModel->getAdminNavigationForDisplay($breadCrumbId)
		);
	}

	/**
	 * Performs any pre-view rendering setup, such as getting style information and
	 * ensuring the correct data is registered.
	 *
	 * @param XenForo_ControllerResponse_Abstract|null $controllerResponse
	 */
	public function preRenderView(XenForo_ControllerResponse_Abstract $controllerResponse = null)
	{
		parent::preRenderView($controllerResponse);

		XenForo_Template_Abstract::setLanguageId(XenForo_Phrase::getLanguageId());

		$properties = XenForo_Application::get('adminStyleProperties');
		XenForo_Template_Helper_Core::setStyleProperties($properties);

		$this->_defaultTemplateParams['_styleModifiedDate'] = XenForo_Application::get('adminStyleModifiedDate');
	}
}