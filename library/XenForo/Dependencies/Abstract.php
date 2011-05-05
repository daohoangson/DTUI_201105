<?php

/**
* Interface for objects that can be passed into the front controller to load all of
* its dependencies.
*
* @package XenForo_Mvc
*/
abstract class XenForo_Dependencies_Abstract
{
	/**
	 * A list of explicit view state changes from the controller. This will be used
	 * to modify how view rendering is done. For example, pages that want an explicit
	 * style ID can set it here.
	 *
	 * @var array Key-value pairs
	 */
	protected $_viewStateChanges = array();

	/**
	 * List of data to pre-load from the data registry. You must process this data
	 * via {@link _handleCustomPreLoadedData()}.
	 *
	 * @var array
	 */
	protected $_dataPreLoadFromRegistry = array();

	/**
	 * A list of template params that will be set in each template by default.
	 * Conflicting params are overridden by specific template params.
	 *
	 * @var array
	 */
	protected $_defaultTemplateParams = array();

	/**
	* Routes the request.
	*
	* @param Zend_Controller_Request_Http $request
	*
	* @return XenForo_RouteMatch
	*/
	abstract public function route(Zend_Controller_Request_Http $request);

	/**
	* Determines if the controller matched by the route can be dispatched. Use this
	* function to ensure, for example, that an admin page only shows an admin controller.
	*
	* @param mixed  Likely a XenForo_Controller object, but not guaranteed
	* @param string Name of the action to call
	*
	* @return boolean
	*/
	abstract public function allowControllerDispatch($controller, $action);

	/**
	* Gets the routing information for a not found error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	abstract public function getNotFoundErrorRoute();

	/**
	* Gets the routing information for a server error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	abstract public function getServerErrorRoute();

	/**
	 * Gets the name of the base view class for this type.
	 *
	 * @return string
	 */
	abstract public function getBaseViewClassName();

	/**
	* Helper method to create a template object for rendering.
	*
	* @param string Name of the template to be used
	* @param array  Key-value parameters to pass to the template
	*
	* @return XenForo_Template_Abstract
	*/
	abstract public function createTemplateObject($templateName, array $params = array());

	/**
	 * Gets the extra container data from template renders.
	 *
	 * @return array
	 */
	abstract public function getExtraContainerData();

	/**
	* Preloads a template with the template handler for use later.
	*
	* @param string Template name
	*/
	abstract public function preloadTemplate($templateName);

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
		switch ($responseType)
		{
			case 'json':      return new XenForo_ViewRenderer_Json($this, $response, $request);
			case 'json-text': return new XenForo_ViewRenderer_JsonText($this, $response, $request);
			case 'rss':       return new XenForo_ViewRenderer_Rss($this, $response, $request);
			case 'css':       return new XenForo_ViewRenderer_Css($this, $response, $request);
			case 'xml':       return new XenForo_ViewRenderer_Xml($this, $response, $request);
			case 'raw':       return new XenForo_ViewRenderer_Raw($this, $response, $request);
			default:          return false;
		}
	}

	/**
	 * Pre-loads globally required data for the system.
	 */
	public function preLoadData()
	{
		$required = array_merge(
			array('options', 'languages', 'contentTypes', 'codeEventListeners', 'cron', 'simpleCache'),
			$this->_dataPreLoadFromRegistry
		);
		$data = XenForo_Model::create('XenForo_Model_DataRegistry')->getMulti($required);

		if (XenForo_Application::get('config')->enableListeners)
		{
			if (!is_array($data['codeEventListeners']))
			{
				$data['codeEventListeners'] = XenForo_Model::create('XenForo_Model_CodeEvent')->rebuildEventListenerCache();
			}
			XenForo_CodeEvent::setListeners($data['codeEventListeners']);
		}

		if (!is_array($data['options']))
		{
			$data['options'] = XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();
		}
		$options = new XenForo_Options($data['options']);
		XenForo_Application::setDefaultsFromOptions($options);
		XenForo_Application::set('options', $options);

		if (!is_array($data['languages']))
		{
			$data['languages'] = XenForo_Model::create('XenForo_Model_Language')->rebuildLanguageCache();
		}
		XenForo_Application::set('languages', $data['languages']);

		if (!is_array($data['contentTypes']))
		{
			$data['contentTypes'] = XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
		}
		XenForo_Application::set('contentTypes', $data['contentTypes']);

		if (!is_int($data['cron']))
		{
			$data['cron'] = XenForo_Model::create('XenForo_Model_Cron')->updateMinimumNextRunTime();
		}
		XenForo_Application::set('cron', $data['cron']);

		if (!is_array($data['simpleCache']))
		{
			$data['simpleCache'] = array();
			XenForo_Model::create('XenForo_Model_DataRegistry')->set('simpleCache', $data['simpleCache']);
		}
		XenForo_Application::set('simpleCache', $data['simpleCache']);

		$this->_handleCustomPreloadedData($data);

		XenForo_CodeEvent::fire('init_dependencies', array($this, $data));
	}

	/**
	 * Handles the custom data that needs to be preloaded.
	 *
	 * @param array $data Data that was loaded. Unsuccessfully loaded items will have a value of null
	 */
	protected function _handleCustomPreloadedData(array &$data)
	{
	}

	/**
	 * Performs any pre-view rendering setup, such as getting style information and
	 * ensuring the correct data is registered.
	 *
	 * @param XenForo_ControllerResponse_Abstract|null $controllerResponse
	 */
	public function preRenderView(XenForo_ControllerResponse_Abstract $controllerResponse = null)
	{
		if (XenForo_Application::isRegistered('session'))
		{
			/* @var $session XenForo_Session */
			$session = XenForo_Application::get('session');
			$this->_defaultTemplateParams['session'] = $session->getAll();
			$this->_defaultTemplateParams['sessionId'] = $session->getSessionId();
		}
		$this->_defaultTemplateParams['requestPaths'] = XenForo_Application::get('requestPaths');

		$options = XenForo_Application::get('options')->getOptions();
		$options['cookieConfig'] = XenForo_Application::get('config')->cookie->toArray();
		$options['currentVersion'] = XenForo_Application::$version;
		$options['jsVersion'] = XenForo_Application::$jsVersion;

		$visitor = XenForo_Visitor::getInstance();
		$this->_defaultTemplateParams['visitor'] = $visitor->toArray();
		$this->_defaultTemplateParams['visitorLanguage'] = $visitor->getLanguage();
		$this->_defaultTemplateParams['xenOptions'] = $options;
		$this->_defaultTemplateParams['xenCache'] = XenForo_Application::get('simpleCache');
		$this->_defaultTemplateParams['serverTime'] = XenForo_Application::$time;
		$this->_defaultTemplateParams['debugMode'] = XenForo_Application::debugMode();

		if ($controllerResponse)
		{
			$this->_defaultTemplateParams['controllerName']   = $controllerResponse->controllerName;
			$this->_defaultTemplateParams['controllerAction'] = $controllerResponse->controllerAction;
			$this->_defaultTemplateParams['viewName']         = $controllerResponse->viewName;
		}
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
		if (XenForo_Application::get('config')->checkVersion)
		{
			$params['showUpgradePendingNotice'] = (
				XenForo_Application::debugMode()
				&& XenForo_Application::$versionId != XenForo_Application::get('options')->currentVersionId
			);
		}
		else
		{
			$params['showUpgradePendingNotice'] = false;
		}

		return $params;
	}

	/**
	 * Gets cron-related params for the container.
	 *
	 * @return array
	 */
	protected function _getCronContainerParams()
	{
		if (!XenForo_Application::isRegistered('cron'))
		{
			return array();
		}

		$nextRun = XenForo_Application::get('cron');
		if ($nextRun >= XenForo_Application::$time)
		{
			return array();
		}

		return array(
			'cronLink' => 'cron.php?' . XenForo_Application::$time
		);
	}

	/**
	 * Merge view state changes over any existing states.
	 *
	 * @param array $states Key-value pairs
	 */
	public function mergeViewStateChanges(array $states)
	{
		$this->_viewStateChanges = array_merge($this->_viewStateChanges, $states);
	}

	/**
	 * Fetch the path / URL to the jQuery core library
	 *
	 * @param boolean $forceLocal If true, forces the local version of jQuery
	 *
	 * @return string
	 */
	public static function getJquerySource($forceLocal = false)
	{
		// always serve the local jQuery, just in case the CDN is down
		return 'js/jquery/jquery-' . XenForo_Application::$jQueryVersion . '.min.js';
	}
}