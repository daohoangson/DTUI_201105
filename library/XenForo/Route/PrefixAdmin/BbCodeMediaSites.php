<?php

/**
 * Route prefix handler for BB code media sites in the admin control panel.
 *
 * @package XenForo_BbCode
 */
class XenForo_Route_PrefixAdmin_BbCodeMediaSites implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithStringParam($routePath, $request, 'media_site_id');
		return $router->getRouteMatch('XenForo_ControllerAdmin_BbCodeMediaSite', $action, 'bbCodeMediaSites');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $data, 'media_site_id');
	}
}