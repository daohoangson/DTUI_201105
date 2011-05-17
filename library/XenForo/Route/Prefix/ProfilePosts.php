<?php

/**
 * Route and link builder for profile post-related actions.
 *
 * @package XenForo_ProfilePost
 */
class XenForo_Route_Prefix_ProfilePosts implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'profile_post_id');
		return $router->getRouteMatch('XenForo_ControllerPublic_ProfilePost', $action, 'members');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'profile_post_id');
	}
}