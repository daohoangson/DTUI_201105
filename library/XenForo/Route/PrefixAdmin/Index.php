<?php

/**
 * Route prefix handler for the index of the control panel.
 *
 * @package XenForo_Index
 */
class XenForo_Route_PrefixAdmin_Index implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		return $router->getRouteMatch('XenForo_ControllerAdmin_Home', 'index', 'setup');
	}
}