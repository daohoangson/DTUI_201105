<?php

/**
 * Route prefix handler for moderators in the admin control panel.
 *
 * @package XenForo_Moderator
 */
class XenForo_Route_PrefixAdmin_Moderators implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$parts = explode('/', $routePath, 2);
		$action = $parts[0];

		if (isset($parts[1]))
		{
			switch ($action)
			{
				case 'super':
					$action .= $router->resolveActionWithIntegerParam($parts[1], $request, 'user_id');
					break;

				case 'content':
					$action .= $router->resolveActionWithIntegerParam($parts[1], $request, 'moderator_id');
					break;
			}
		}

		return $router->getRouteMatch('XenForo_ControllerAdmin_Moderator', $action, 'moderators');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (is_array($data))
		{
			$actionParts = explode('/', $action, 2);
			if (count($actionParts) == 2)
			{
				$outputPrefix .= '/' . $actionParts[0];
				$action = $actionParts[1];
			}

			if (isset($data['moderator_id']))
			{
				return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'moderator_id', 'username');
			}
			else if (isset($data['user_id']))
			{
				return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'user_id', 'username');
			}
		}

		return false;
	}
}