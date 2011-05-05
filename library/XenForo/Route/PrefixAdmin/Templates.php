<?php

/**
 * Route prefix handler for templates in the admin control panel.
 *
 * @package XenForo_Templates
 */
class XenForo_Route_PrefixAdmin_Templates implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'template_id');

		// TODO: this says 'styles' for the purposes of the breadcrumb, but it would be nice to have a different approach here in due course
		return $router->getRouteMatch('XenForo_ControllerAdmin_Template', $action, 'styles');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'template_id', 'title');
	}
}