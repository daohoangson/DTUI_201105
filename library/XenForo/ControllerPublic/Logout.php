<?php

class XenForo_ControllerPublic_Logout extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Single-stage logout procedure
	 */
	public function actionIndex()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		// remove an admin session if we're logged in as the same person
		if (XenForo_Visitor::getInstance()->get('is_admin'))
		{
			$adminSession = new XenForo_Session(array('admin' => true));
			$adminSession->start();
			if ($adminSession->get('user_id') == XenForo_Visitor::getUserId())
			{
				$adminSession->delete();
			}
		}

		$this->getModelFromCache('XenForo_Model_Session')->processLastActivityUpdateForLogOut(XenForo_Visitor::getUserId());

		XenForo_Application::get('session')->delete();
		XenForo_Helper_Cookie::deleteAllCookies(
			array('session'),
			array('user' => array('httpOnly' => false))
		);

		XenForo_Visitor::setup(0);

		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$redirect ? $redirect : XenForo_Link::buildPublicLink('index')
		);
	}

	protected function _assertViewingPermissions($action) {}
	protected function _assertBoardActive($action) {}
	protected function _assertCorrectVersion($action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * Gets the user model.
	 *
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}