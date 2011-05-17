<?php

class XenForo_Install_Route implements XenForo_Route_Interface
{
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
		if (!$routePath)
		{
			$component = 'index';
			$action = '';
		}
		else
		{
			$parts = explode('/', $routePath, 2);

			$component = $parts[0];
			$action = (isset($parts[1]) ? $parts[1] : '');
		}

		switch($component)
		{
			case 'install': $controller = 'XenForo_Install_Controller_Install'; break;
			case 'upgrade': $controller = 'XenForo_Install_Controller_Upgrade'; break;
			default:
				$controller = 'XenForo_Install_Controller_Index';
				$action = $component . $action;
		}

		return $router->getRouteMatch($controller, $action, $component);
	}
}