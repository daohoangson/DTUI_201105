<?php

/**
 * Route prefix handler for permissions in the admin control panel.
 *
 * @package XenForo_Permissions
 */
class XenForo_Route_PrefixAdmin_Permissions implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$components = explode('/', $routePath);
		$action = strtolower(array_shift($components));

		if (count($components) == 1)
		{
			// permission/<action>, etc
			$action .= reset($components);
		}
		else
		{
			switch ($action)
			{
				case 'permission':
					// permission/<group>/<permission>/<action>
					$request->setParam('permission_group_id', array_shift($components));
					$request->setParam('permission_id', array_shift($components));
					break;

				case 'permission-group':
					// permission-group/<group>/<action>
					$request->setParam('permission_group_id', array_shift($components));
					break;

				case 'interface-group':
					// interface-group/<group>/<action>
					$request->setParam('interface_group_id', array_shift($components));
					break;
			}

			$action .= implode('', $components);
		}

		return $router->getRouteMatch('XenForo_ControllerAdmin_Permission', $action, 'permissions');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if ($data)
		{
			XenForo_Link::prepareExtensionAndAction($extension, $action);

			$actionParts = explode('/', $action, 2);
			$url = $outputPrefix . '/' . $actionParts[0];

			switch (strtolower($actionParts[0]))
			{
				case 'permission':
					if (!empty($data['permission_group_id']) && !empty($data['permission_id']))
					{
						$url .= '/' . $data['permission_group_id'] . '/' . $data['permission_id'];
					}
					break;

				case 'permission-group':
					if (!empty($data['permission_group_id']))
					{
						$url .= '/' . $data['permission_group_id'];
					}
					break;

				case 'interface-group':
					if (!empty($data['interface_group_id']))
					{
						$url .= '/' . $data['interface_group_id'];
					}
					break;
			}

			if (isset($actionParts[1]))
			{
				$url .= '/' . $actionParts[1];
			}

			return $url . $extension;
		}

		return false;
	}
}