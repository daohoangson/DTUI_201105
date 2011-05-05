<?php

class XenForo_ControllerPublic_Error extends XenForo_ControllerPublic_Abstract
{
	public function actionErrorNotFound()
	{
		if (XenForo_Application::debugMode())
		{
			$controllerName = $this->_request->getParam('_controllerName');

			if (empty($controllerName))
			{
				return $this->responseError(
					new XenForo_Phrase('controller_for_route_not_found', array
					(
						'routePath' => $this->_request->getParam('_origRoutePath'),
					)), 404
				);
			}
			else
			{
				return $this->responseError(
					new XenForo_Phrase('controller_x_does_not_define_action_y', array
					(
						'controller' => $controllerName,
						'action' => $this->_request->getParam('_action')
					)), 404
				);
			}
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
		}
	}

	public function actionErrorServer()
	{
		if (XenForo_Application::debugMode())
		{
			$showDetails = true;
		}
		else if (XenForo_Visitor::hasInstance() && XenForo_Visitor::getInstance()->is_admin)
		{
			$showDetails = true;
		}
		else
		{
			$showDetails = false;
		}

		if ($showDetails)
		{
			$view = $this->responseView(
				'XenForo_ViewPublic_Error_ServerError',
				'error_server_error',
				array('exception' => $this->_request->getParam('_exception'))
			);
			$view->responseCode = 500;
			return $view;
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('server_error_occurred'), 500);
		}
	}

	public function actionNoPermission()
	{
		if (!XenForo_Visitor::getUserId())
		{
			// show login / registration form
			return $this->responseReroute(__CLASS__, 'registrationRequired');
		}
		else
		{
			// show no permission error without login form
			return $this->responseError(new XenForo_Phrase('do_not_have_permission'), 403);
		}
	}

	/**
	 * Response when a user a guest attempts to perform a restricted action
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionRegistrationRequired()
	{
		$viewParams = array(
			//'text' => new XenForo_Phrase('must_be_registered')
			'text' => new XenForo_Phrase('login_required')
		);

		$view = $this->responseView('XenForo_ViewPublic_Error_RegistrationRequired', 'error_with_login', $viewParams);
		$view->responseCode = 403;

		return $view;
	}

	public function actionBanned()
	{
		$bannedUser = $this->getModelFromCache('XenForo_Model_Banning')->getBannedUserById(XenForo_Visitor::getUserId());
		if (!$bannedUser)
		{
			return $this->responseNoPermission();
		}
		else
		{
			// TODO: better display/message for banned user
			if ($bannedUser['user_reason'])
			{
				$message = new XenForo_Phrase('you_have_been_banned_for_following_reason_x', array('reason' => $bannedUser['user_reason']));
			}
			else
			{
				$message = new XenForo_Phrase('you_have_been_banned');
			}
			if ($bannedUser['end_date'] > XenForo_Application::$time)
			{
				$message.= ' ' . new XenForo_Phrase('your_ban_will_be_lifted_on_x', array('date' => XenForo_Locale::dateTime($bannedUser['end_date'])));
			}

			return $this->responseError($message, 403);
		}
	}

	public function actionBannedIp()
	{
		return $this->responseError(new XenForo_Phrase('your_ip_address_has_been_banned'), 403);
	}

	protected function _assertIpNotBanned() {}
	protected function _assertViewingPermissions($action) {}
	protected function _assertNotBanned() {}
	protected function _assertBoardActive($action) {}
	protected function _assertCorrectVersion($action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}
}