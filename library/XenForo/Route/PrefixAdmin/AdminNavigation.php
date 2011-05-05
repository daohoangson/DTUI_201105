<?php

/**
 * Route prefix handler for admin navigation in the admin control panel.
 *
 * @package XenForo_AdminNavigation
 */
class XenForo_Route_PrefixAdmin_AdminNavigation implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithStringParam($routePath, $request, 'navigation_id');
		return $router->getRouteMatch('XenForo_ControllerAdmin_AdminNavigation', $action, 'adminNavigation');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $data, 'navigation_id');
	}
}