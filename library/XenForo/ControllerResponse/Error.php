<?php

/**
* Error page controller response. Note that this represents an expected error
* more than a server/PHP error. It should be used for generating application
* error pages.
*
* @package XenForo_Mvc
*/
class XenForo_ControllerResponse_Error extends XenForo_ControllerResponse_Abstract
{
	/**
	* Text of the error that occurred
	*
	* @var string|array
	*/
	public $errorText = '';
}