<?php

/**
 * Abstract controller for admin actions.
 *
 * @package XenForo_Mvc
 */
abstract class XenForo_ControllerAdmin_Abstract extends XenForo_Controller
{
	/**
	 * Pre-dispatch behaviors for the whole set of admin controllers.
	 */
	final protected function _preDispatchType($action)
	{
		$this->_assertCorrectVersion($action);
		$this->_assertInstallLocked($action);
		$this->assertAdmin();
	}

	/**
	* Setup the session.
	*
	* @param string $action
	*/
	protected function _setupSession($action)
	{
		if (XenForo_Application::isRegistered('session'))
		{
			return;
		}

		XenForo_Session::startAdminSession($this->_request);
	}

	/**
	 * Gets the response for a generic no permission page.
	 *
	 * @return XenForo_ControllerResponse_Error
	 */
	public function responseNoPermission()
	{
		return $this->responseError(new XenForo_Phrase('do_not_have_permission'), 403);
	}

	/**
	 * Asserts that the installed version of the board matches the files.
	 *
	 * @param string $action
	 */
	protected function _assertCorrectVersion($action)
	{
		if (XenForo_Application::debugMode())
		{
			return;
		}

		if (!XenForo_Application::get('config')->checkVersion)
		{
			return;
		}

		if (XenForo_Application::$versionId != XenForo_Application::get('options')->currentVersionId)
		{
			$response = $this->responseMessage(new XenForo_Phrase('board_waiting_to_be_upgraded_admin'));
			$response->containerParams = array(
				'containerTemplate' => 'PAGE_CONTAINER_SIMPLE'
			);

			throw $this->responseException($response);
		}
	}

	protected function _assertInstallLocked($action)
	{
		$installModel = XenForo_Model::create('XenForo_Install_Model_Install');
		if (!$installModel->isInstalled())
		{
			$installModel->writeInstallLock();
		}
	}

	/**
	 * Returns the hash necessary to find an item in a filter list.
	 *
	 * @param mixed $id
	 *
	 * @return string
	 */
	public function getLastHash($id)
	{
		return '#' . XenForo_Template_Helper_Admin::getListItemId($id);
	}

	/**
	 * Ensures that the user trying to access the admin control panel is actually
	 * an admin.
	 */
	public function assertAdmin()
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor['is_admin'])
		{
			throw $this->responseException(
				$this->responseReroute('XenForo_ControllerAdmin_Login', 'form')
			);
		}
	}

	/**
	 * Asserts that debug mode is enabled.
	 */
	public function assertDebugMode()
	{
		if (!XenForo_Application::debugMode())
		{
			throw new XenForo_Exception(new XenForo_Phrase('page_only_available_debug_mode'), true);
		}
	}

	/**
	 * Asserts that the visiting user has the specified admin permission.
	 *
	 * @param string $permissionId
	 */
	public function assertAdminPermission($permissionId)
	{
		if (!XenForo_Visitor::getInstance()->hasAdminPermission($permissionId))
		{
			throw $this->responseException($this->responseNoPermission());
		}
	}

	/**
	 * Disable updating a user's session activity in the ACP
	 */
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}
}