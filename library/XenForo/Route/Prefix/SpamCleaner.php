<?php

/**
 * Route prefix handler for the spam cleaner
 *
 * @package XenForo_Member
 */
class XenForo_Route_Prefix_SpamCleaner implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'user_id');
		return $router->getRouteMatch('XenForo_ControllerPublic_SpamCleaner', $action, 'members');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (!empty($data['ip_id']))
		{
			$extraParams['ip_id'] = $data['ip_id'];
		}

		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'user_id', 'username');
	}
}