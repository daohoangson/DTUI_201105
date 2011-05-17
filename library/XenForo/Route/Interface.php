<?php

/**
* Interface for a routing rule. Without implementing this interface, a rule will
* not be able to be added via {@link XenForo_Router::addRule()}.
*
* @package XenForo_Mvc
*/
interface XenForo_Route_Interface
{
	/**
	* Method to be called when attempting to match this rule against a routing path.
	* Should return false if no matching happened or a {@link XenForo_RouteMatch} if
	* some level of matching happened. If no {@link XenForo_RouteMatch::$controllerName}
	* is returned, the {@link XenForo_Router} will continue to the next rule.
	*
	* @param string                       Routing path
	* @param Zend_Controller_Request_Http Request object
	* @param XenForo_Router                  Router that routing is done within
	*
	* @return false|XenForo_RouteMatch
	*/
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router);
}