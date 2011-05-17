<?php

/**
 * Abstract controller helper base.
 *
 * @package XenForo_Mvc
 */
abstract class XenForo_ControllerHelper_Abstract
{
	/**
	 * Calling controller.
	 *
	 * @var XenForo_Controller
	 */
	protected $_controller;

	/**
	 * Constructor. Sets up controller.
	 *
	 * @param XenForo_Controller $controller
	 */
	public function __construct(XenForo_Controller $controller)
	{
		$this->_controller = $controller;
		$this->_constructSetup();
	}

	/**
	 * Additional constructor behavior.
	 */
	protected function _constructSetup() {}
}