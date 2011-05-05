<?php

/**
 * Class that represents a controller response via an exception. This type
 * of exception is caught by the front controller and handled as if it were
 * returned by the controller as normal.
 *
 * @package XenForo_Mvc
 */
class XenForo_ControllerResponse_Exception extends Exception
{
	/**
	 * Controller response this object encapsulates.
	 *
	 * @var XenForo_ControllerResponse_Abstract
	 */
	protected $_controllerResponse;

	/**
	 * Constructor.
	 *
	 * @param XenForo_ControllerResponse_Abstract $controllerResponse
	 */
	public function __construct(XenForo_ControllerResponse_Abstract $controllerResponse)
	{
		parent::__construct('Controller response exception: ' . get_class($controllerResponse));

		$this->_controllerResponse = $controllerResponse;
	}

	/**
	 * Gets the encapsulated controller response.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function getControllerResponse()
	{
		return $this->_controllerResponse;
	}
}