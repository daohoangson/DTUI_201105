<?php

class XenForo_ControllerPublic_Login extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		if (XenForo_Visitor::getUserId())
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect()
			);
		}

		$viewParams = array(
			'redirect' => $this->getDynamicRedirect()
		);

		return $this->responseView(
			'XenForo_ViewPublic_Login_Login',
			'login',
			$viewParams,
			$this->_getRegistrationContainerParams()
		);
	}

	public function actionLogin()
	{
		$this->_assertPostOnly();

		$data = $this->_input->filter(array(
			'login' => XenForo_Input::STRING,
			'password' => XenForo_Input::STRING,
			'remember' => XenForo_Input::UINT,
			'register' => XenForo_Input::UINT,
			'redirect' => XenForo_Input::STRING,
			'cookie_check' => XenForo_Input::UINT
		));

		if ($data['register'] || $data['password'] === '')
		{
			return $this->responseReroute('XenForo_ControllerPublic_Register', 'index');
		}

		$redirect = ($data['redirect'] ? $data['redirect'] : $this->getDynamicRedirect());

		$loginModel = $this->_getLoginModel();

		if ($data['cookie_check'] && count($_COOKIE) == 0)
		{
			// login came from a page, so we should at least have a session cookie.
			// if we don't, assume that cookies are disabled
			return $this->_loginErrorResponse(
				new XenForo_Phrase('cookies_required_to_log_in_to_site'),
				$data['login'],
				true,
				$redirect
			);
		}

		$needCaptcha = $loginModel->requireLoginCaptcha($data['login']);
		if ($needCaptcha)
		{
			if (!XenForo_Captcha_Abstract::validateDefault($this->_input, true))
			{
				$loginModel->logLoginAttempt($data['login']);

				return $this->_loginErrorResponse(
					new XenForo_Phrase('did_not_complete_the_captcha_verification_properly'),
					$data['login'],
					true,
					$redirect
				);
			}
		}

		$userModel = $this->_getUserModel();

		$userId = $userModel->validateAuthentication($data['login'], $data['password'], $error);
		if (!$userId)
		{
			$loginModel->logLoginAttempt($data['login']);

			return $this->_loginErrorResponse(
				$error,
				$data['login'],
				($needCaptcha || $loginModel->requireLoginCaptcha($data['login'])),
				$redirect
			);
		}

		$loginModel->clearLoginAttempts($data['login']);

		if ($data['remember'])
		{
			$userModel->setUserRememberCookie($userId);
		}

		XenForo_Model_Ip::log($userId, 'user', $userId, 'login');

		$userModel->deleteSessionActivity(0, $this->_request->getClientIp(false));

		$session = XenForo_Application::get('session');

		$session->changeUserId($userId);
		XenForo_Visitor::setup($userId);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$redirect
		);
	}

	protected function _loginErrorResponse($error, $defaultLogin, $needCaptcha, $redirect = false)
	{
		if ($needCaptcha)
		{
			$captcha = XenForo_Captcha_Abstract::createDefault(true);
		}
		else
		{
			$captcha = false;
		}

		return $this->responseView('XenForo_ViewPublic_Login', 'error_with_login', array(
			'text' => $error,
			'defaultLogin' => $defaultLogin,
			'captcha' => $captcha,
			'redirect' => $redirect
		));
	}

	/**
	 * Gets an updated CSRF token for pages that are left open for a long time.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCsrfTokenRefresh()
	{
		$this->_assertPostOnly();

		$visitor = XenForo_Visitor::getInstance();
		$viewParams = array(
			'csrfToken' => $visitor['csrf_token_page'],
			'sessionId' => XenForo_Application::get('session')->getSessionId()
		);

		return $this->responseView('XenForo_ViewPublic_Login_CsrfTokenRefresh', '', $viewParams);
	}

	protected function _checkCsrf($action)
	{
		if (strtolower($action) == 'login')
		{
			return;
		}

		return parent::_checkCsrf($action);
	}

	protected function _assertViewingPermissions($action) {}
	protected function _assertCorrectVersion($action) {}
	protected function _assertBoardActive($action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_Login
	 */
	protected function _getLoginModel()
	{
		return $this->getModelFromCache('XenForo_Model_Login');
	}
}
