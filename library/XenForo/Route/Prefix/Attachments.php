<?php

/**
 * Route prefix handler for categories in the public system.
 *
 * @package XenForo_Attachment
 */
class XenForo_Route_Prefix_Attachments implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'attachment_id');
		return $router->getRouteMatch('XenForo_ControllerPublic_Attachment', $action);
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (!empty($data['temp_hash']) && empty($data['content_id']))
		{
			$extraParams['temp_hash'] = $data['temp_hash'];
		}

		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'attachment_id', 'filename');
	}
}