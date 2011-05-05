<?php

/**
 * Route prefix handler for link forums in the public system.
 *
 * @package XenForo_Nodes
 */
class XenForo_Route_Prefix_LinkForums implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'node_id');
		return $router->getRouteMatch('XenForo_ControllerPublic_LinkForum', $action, 'forums');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		// for situations such as an array with thread and node info
		if (isset($data['node_title']))
		{
			$data['title'] = $data['node_title'];
		}

		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'node_id', 'title');
	}
}