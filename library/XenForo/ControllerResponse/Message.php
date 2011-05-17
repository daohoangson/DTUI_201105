<?php

/**
* General message page controller response. Use this for really basic pages
* that just want to display a message of some sort, with no template/view.
*
* @package XenForo_Mvc
*/
class XenForo_ControllerResponse_Message extends XenForo_ControllerResponse_Abstract
{
	/**
	* Text of the message that occurred
	*
	* @var string
	*/
	public $message = '';
}