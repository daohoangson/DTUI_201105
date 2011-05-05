<?php

/**
 * Route prefix handler for the CSS used by the control panel.
 *
 * @package XenForo_CssInternal
 */
class XenForo_Route_PrefixAdmin_CssInternal implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$match = $router->getRouteMatch('XenForo_ControllerAdmin_CssInternal', 'css');
		$match->setResponseType('css');
		return $match;
	}
}