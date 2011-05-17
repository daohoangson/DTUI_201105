<?php

/**
* Default route that modifies the response type based on an extension applied to
* the route path. For example, "test/index.json" will return a response type of json.
* If a match is found, the extension is stripped off so subsequent rules won't receive
* it. Using the example above, it would return a route path of "test/index".
*
* This class never returns a route match with a controller and action specified.
*
* @package XenForo_Mvc
*/
class XenForo_Route_ResponseSuffix implements XenForo_Route_Interface
{
	/**
	* Attempts to match the routing path. See {@link XenForo_Route_Interface} for further details.
	*
	* @param string Routing path
	* @param Zend_Controller_Request_Http Request object
	* @param XenForo_Router Routing object
	*
	* @return false|XenForo_RouteMatch
	*/
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$lastDot = strrpos($routePath, '.');
		if ($lastDot === false)
		{
			return false;
		}

		$lastSlash = strrpos($routePath, '/');
		if ($lastSlash !== false && $lastDot < $lastSlash)
		{
			return false;
		}

		$responseType = substr($routePath, $lastDot + 1);
		if ($responseType === strval(intval($responseType)))
		{
			return false;
		}

		$newRoutePath = substr($routePath, 0, $lastDot);
		if (!is_string($newRoutePath))
		{
			$newRoutePath = '';
		}

		$match = $router->getRouteMatch();

		$match->setModifiedRoutePath($newRoutePath);
		if ($responseType !== '')
		{
			$match->setResponseType($responseType);
		}

		return $match;
	}
}