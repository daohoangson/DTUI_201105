<?php

class XenForo_Route_Prefix_Watched implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		// TODO: majorSection changes required when this handles more than just threads

		return $router->getRouteMatch('XenForo_ControllerPublic_Watched', $routePath, 'forums');
	}
}