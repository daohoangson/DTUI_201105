<?php

/**
 * Route prefix handler for style property definitions in the admin control panel.
 *
 * @package XenForo_StyleProperty
 */
class XenForo_Route_PrefixAdmin_StylePropertyDefinitions implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'property_definition_id');
		return $router->getRouteMatch('XenForo_ControllerAdmin_StylePropertyDefinition', $action, 'styleProperties');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'property_definition_id', 'property_name');
	}
}