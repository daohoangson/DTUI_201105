<?php

/**
 * Route prefix handler for route prefixes in the admin control panel.
 *
 * @package XenForo_RoutePrefixes
 */
class XenForo_Route_PrefixAdmin_RoutePrefixes implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$components = explode('/', $routePath);
		$componentCount = count($components);

		if ($componentCount >= 3)
		{
			$request->setParam('route_type', array_shift($components));
			$request->setParam('original_prefix', array_shift($components));
		}
		else if ($componentCount == 2)
		{
			$request->setParam('route_type', array_shift($components));
		}

		$action = implode('', $components);
		return $router->getRouteMatch('XenForo_ControllerAdmin_RoutePrefix', $action, 'routePrefixes');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (is_array($data) && isset($data['original_prefix'], $data['route_type']))
		{
			XenForo_Link::prepareExtensionAndAction($extension, $action);
			return "$outputPrefix/$data[route_type]/$data[original_prefix]/$action$extension";
		}
		else
		{
			return false;
		}
	}
}