<?php

class XenForo_Route_Prefix_InlineMod implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$parts = explode('/', $routePath, 2);

		$controllerPart = str_replace(array('-', '/'), ' ', strtolower($parts[0]));
		$controllerPart = str_replace(' ', '', ucwords($controllerPart));

		$action = (isset($parts[1]) ? $parts[1] : '');

		return $router->getRouteMatch('XenForo_ControllerPublic_InlineMod_' . $controllerPart, $action, 'forums');
	}
}