<?php

class XenForo_Route_Prefix_Members implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'user_id');
		return $router->getRouteMatch('XenForo_ControllerPublic_Member', $action, 'members');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (isset($extraParams['page']))
		{
			if (strval($extraParams['page']) !== XenForo_Application::$integerSentinel && $extraParams['page'] <= 1)
			{
				unset($extraParams['page']);
			}
		}

		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'user_id', 'username');
	}
}