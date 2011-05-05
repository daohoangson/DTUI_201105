<?php

/**
* Abstract class for a contoller response. New response types must extend this.
*
* @package XenForo_Mvc
*/
abstract class XenForo_ControllerResponse_Abstract
{
	/**
	* Key-value parameters to pass to the container view.
	*
	* @var array
	*/
	public $containerParams = array();

	/**
	* An optional HTTP response code to output
	*
	* @var integer
	*/
	public $responseCode = 200;

	public $controllerName = '';
	public $controllerAction = '';
	public $viewName = '';
}