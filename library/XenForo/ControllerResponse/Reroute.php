<?php

/**
* Reroute controller response. This will cause the request to internally be
* redirected to the named controller/action. The user will not be made aware of
* this redirection.
*
* @package XenForo_Mvc
*/
class XenForo_ControllerResponse_Reroute extends XenForo_ControllerResponse_Abstract
{
	/**
	* Name of the controller to reroute to
	*
	* @var string
	*/
	public $controllerName = '';

	/**
	* Name of the action to reroute to
	*
	* @var string
	*/
	public $action = '';
}