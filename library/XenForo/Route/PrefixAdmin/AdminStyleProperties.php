<?php

/**
 * Route prefix handler for admin style properties in the admin control panel.
 *
 * @package XenForo_StyleProperty
 */
class XenForo_Route_PrefixAdmin_AdminStyleProperties implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'property_id');
		return $router->getRouteMatch('XenForo_ControllerAdmin_AdminStyleProperty', $action, 'adminStyleProperties');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'property_id', 'property_name');
	}
}