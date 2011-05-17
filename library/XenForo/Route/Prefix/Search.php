<?php

/**
 * Route and link builder for search-related actions.
 *
 * @package XenForo_Search
 */
class XenForo_Route_Prefix_Search implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'search_id');
		return $router->getRouteMatch('XenForo_ControllerPublic_Search', $action);
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		// add items to allow search to be repeated; query is checked to ensure user can view
		if ($data && !empty($data['search_query']))
		{
			if (!empty($data['search_query'])) { $extraParams['q'] = $data['search_query']; }
			if (!empty($data['search_type'])) { $extraParams['t'] = $data['search_type']; }
			if (!empty($data['search_order'])) { $extraParams['o'] = $data['search_order']; }
			if (!empty($data['search_grouping'])) { $extraParams['g'] = $data['search_grouping']; }
			if (!empty($data['search_constraints'])) { $extraParams['c'] = json_decode($data['search_constraints'], true); }
		}

		return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'search_id');
	}
}