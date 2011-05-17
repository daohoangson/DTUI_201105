<?php

/**
* Default route that looks for a string before the first slash, then dynamically loads
* a sub-rule and attempts to match against that. Before starting the sub-rule, the
* route path is modified to strip off the prefix and the first slash.
*
* Returns false if there is no prefix, if the prefix contains invalid characters (not a-z, 0-9, _),
* or if the sub-rule cannot be loaded. All other return values are dictated by the sub rule.
*
* @package XenForo_Mvc
*/
class XenForo_Route_Prefix implements XenForo_Route_Interface
{
	/**
	 * Type of route that should be handled. This is either "admin" or "public".
	 *
	 * @var string
	 */
	protected $_routeType = '';

	public function __construct($routeType)
	{
		if ($routeType !== 'admin' && $routeType !== 'public')
		{
			throw new XenForo_Exception('Invalid route type. Must be "admin" or "public"');
		}

		$this->_routeType = $routeType;
	}

	/**
	* Attempts to match the routing path. See {@link XenForo_Route_Interface} for further details.
	*
	* @param string Routing path
	* @param Zend_Controller_Request_Http Request object
	* @param XenForo_Router Routing object
	*
	* @return false|XenForo_RouteMatch
	*/
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		list($prefix) = explode('/', $routePath);
		if ($prefix === '')
		{
			return false;
		}

		if (preg_match('#[^a-zA-Z0-9_-]#', $prefix))
		{
			return false;
		}

		$routeClass = XenForo_Link::getPrefixHandlerClassName($this->_routeType, $prefix);
		if (!$routeClass)
		{
			return false;
		}

		$newRoutePath = substr($routePath, strlen($prefix) + 1);
		if (!is_string($newRoutePath))
		{
			$newRoutePath = '';
		}

		return $this->_loadAndRunSubRule($routeClass, $newRoutePath, $request, $router);
	}

	/**
	* Loads the specified sub-rule and then tries to match it.
	*
	* @param string Route class name
	* @param string Route path to pass to match
	* @param Zend_Controller_Request_Http
	* @param XenForo_Router
	*
	* @return XenForo_RouteMatch|false
	*/
	protected function _loadAndRunSubRule($routeClass, $newRoutePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		if (XenForo_Application::autoload($routeClass))
		{
			$routeClass = XenForo_Application::resolveDynamicClass($routeClass, 'route_prefix');

			$route = new $routeClass();
			return $route->match($newRoutePath, $request, $router);
		}
		else
		{
			return false;
		}
	}
}