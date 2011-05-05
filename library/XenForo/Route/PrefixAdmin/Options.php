<?php

/**
 * Route prefix handler for options in the admin control panel.
 *
 * @package XenForo_Options
 */
class XenForo_Route_PrefixAdmin_Options implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		if (strpos($routePath, '/') !== false)
		{
			list($action, $value) = explode('/', $routePath);
			if (strpos($action, '-option') !== false)
			{
				$request->setParam('option_id', $value);
			}
			else
			{
				$request->setParam('group_id', $value);
			}
		}
		else
		{
			$action = $routePath;
		}

		return $router->getRouteMatch('XenForo_ControllerAdmin_Option', $action, 'optionsLink');
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
			XenForo_Link::prepareExtensionAndAction($extension, $action);

			if (strpos($action, '-option') !== false)
			{
				if (isset($data['option_id']))
				{
					return "$outputPrefix/$action/$data[option_id]$extension";
				}
			}
			else if (isset($data['group_id']))
			{
				return "$outputPrefix/$action/$data[group_id]$extension";
			}
		}

		return false;
	}
}