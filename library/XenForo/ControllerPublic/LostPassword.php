<?php

/**
 * Lost password handler.
 *
 * @package XenForo_UserConfirmation
 */
class XenForo_ControllerPublic_LostPassword extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Displays a form to retrieve a lost password.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		if (XenForo_Visitor::getUserId())
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('index')
			);
		}

		return $this->responseView('XenForo_ViewPublic_LostPassword', 'lost_password');
	}

	/**
	 * Submits a lost password reset request.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLost()
	{
		if (XenForo_Visitor::getUserId())
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('index')
			);
		}

		$this->_assertPostOnly();

		$usernameOrEmail = $this->_input->filterSingle('username_email', XenForo_Input::STRING);
		$user = $this->_getUserModel()->getUserByNameOrEmail($usernameOrEmail);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$this->_getUserConfirmationModel()->sendPasswordResetRequest($user);

		return $this->responseMessage(new XenForo_Phrase('password_reset_request_has_been_emailed_to_you'));
	}

	/**
	 * Confirms a lost password reset request and resets the password.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionConfirm()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		if (!$userId)
		{
			return $this->responseError(new XenForo_Phrase('no_account_specified'));
		}

		$confirmationModel = $this->_getUserConfirmationModel();

		$confirmation = $confirmationModel->getUserConfirmationRecord($userId, 'password');
		if (!$confirmation)
		{
			if (XenForo_Visitor::getUserId())
			{
				// probably already been reset
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
					XenForo_Link::buildPublicLink('index')
				);
			}
			else
			{
				return $this->responseError(new XenForo_Phrase('your_password_could_not_be_reset'));
			}
		}

		$confirmationKey = $this->_input->filterSingle('c', XenForo_Input::STRING);
		if ($confirmationKey)
		{
			$accountConfirmed = $confirmationModel->validateUserConfirmationRecord($confirmationKey, $confirmation);
		}
		else
		{
			$accountConfirmed = false;
		}

		if ($accountConfirmed)
		{
			$confirmationModel->resetPassword($userId);
			$confirmationModel->deleteUserConfirmationRecord($userId, 'password');
			XenForo_Visitor::setup(0);

			return $this->responseMessage(new XenForo_Phrase('your_password_has_been_reset'));
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('your_password_could_not_be_reset'));
		}
	}

	protected function _assertViewingPermissions($action) {}
	protected function _assertBoardActive($action) {}
	protected function _assertCorrectVersion($action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * @return XenForo_Model_UserConfirmation
	 */
	protected function _getUserConfirmationModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserConfirmation');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}