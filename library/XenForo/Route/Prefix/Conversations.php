<?php

/**
 * Route prefix handler for conversations in the public system.
 *
 * @package XenForo_Conversation
 */
class XenForo_Route_Prefix_Conversations implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'conversation_id');
		$action = $router->resolveActionAsPageNumber($action, $request);
		return $router->getRouteMatch('XenForo_ControllerPublic_Conversation', $action, 'account');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		$action = XenForo_Link::getPageNumberAsAction($action, $extraParams);
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'conversation_id', 'title');
	}
}