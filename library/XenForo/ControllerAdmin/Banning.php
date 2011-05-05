<?php

/**
 * Controller for managing bannings.
 *
 * @package XenForo_Banning
 */
class XenForo_ControllerAdmin_Banning extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('ban');
	}

	/**
	 * Displays a list of banned users.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUsers()
	{
		if ($this->_input->filterSingle('user_id', XenForo_Input::UINT))
		{
			return $this->responseReroute(__CLASS__, 'usersEdit');
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$banningModel = $this->_getBanningModel();

		$viewParams = array(
			'bannedUsers' => $banningModel->getBannedUsers(array(), array('page' => $page, 'perPage' => $perPage)),

			'totalBanned' => $banningModel->countBannedUsers(),
			'page' => $page,
			'perPage' => $perPage
		);

		return $this->responseView('XenForo_ViewAdmin_Banning_User_List', 'ban_user_list', $viewParams);
	}

	/**
	 * Gets the user ban add/edit controller response.
	 *
	 * @param array $bannedUser
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getUserBanAddEditResponse(array $bannedUser)
	{
		$viewParams = array(
			'bannedUser' => $bannedUser,
		);

		return $this->responseView('XenForo_ViewAdmin_Banning_User_Edit', 'ban_user_edit', $viewParams);
	}

	/**
	 * Displays a form to edit a user's ban.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUsersEdit()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$bannedUser = $this->_getBanningModel()->getBannedUserById($userId);
		if (!$bannedUser)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
		}

		return $this->_getUserBanAddEditResponse($bannedUser);
	}

	/**
	 * Displays a form to ban a user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUsersAdd()
	{
		$bannedUser = array(
			'end_date' => 0,
			'user_id' => 0,
			'username' => '',
		);

		if ($user_id = $this->_input->filterSingle('user_id', XenForo_Input::UINT))
		{
			if ($user = $this->getModelFromCache('XenForo_Model_User')->getUserById($user_id))
			{
				$bannedUser['username'] = $user['username'];
			}
		}

		return $this->_getUserBanAddEditResponse($bannedUser);
	}

	/**
	 * Bans a user or updates an existing ban.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUsersSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'user_id' => XenForo_Input::UINT,
			'username' => XenForo_Input::STRING,
			'ban_length' => XenForo_Input::STRING,
			'end_date' => XenForo_Input::DATE_TIME,
			'user_reason' => XenForo_Input::STRING
		));

		$userModel = $this->getModelFromCache('XenForo_Model_User');

		$existing = ($input['user_id'] != 0);
		if (!$existing)
		{
			$user = $userModel->getUserByName($input['username']);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
			}

			$input['user_id'] = $user['user_id'];
		}

		if ($input['ban_length'] == 'permanent')
		{
			$input['end_date'] = 0;
		}

		if (!$userModel->ban($input['user_id'], $input['end_date'], $input['user_reason'], $existing, $errorKey))
		{
			return $this->responseError(new XenForo_Phrase($errorKey));
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('banning/users') . $this->getLastHash($input['user_id'])
		);
	}

	/**
	 * Lifts a user's ban.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUsersLift()
	{
		if ($this->isConfirmedPost())
		{
			$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

			$this->getModelFromCache('XenForo_Model_User')->liftBan($userId);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('banning/users') . $this->getLastHash($userId)
			);
		}
		else // show confirm dialog
		{
			$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
			$bannedUser = $this->_getBanningModel()->getBannedUserById($userId);
			if (!$bannedUser)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
			}

			$viewParams = array(
				'bannedUser' => $bannedUser
			);
			return $this->responseView('XenForo_ViewAdmin_Banning_User_Lift', 'ban_user_lift', $viewParams);
		}
	}

	/**
	 * Displays a list of banned IPs and shows a form to ban an additional one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIps()
	{
		$viewParams = array(
			'bannedIps' => $this->_getBanningModel()->getBannedIps()
		);

		return $this->responseView('XenForo_ViewAdmin_Banning_Ip_List', 'ban_ip_list', $viewParams);
	}

	/**
	 * Adds a new banned IP.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIpsAdd()
	{
		$this->_assertPostOnly();

		$this->_getBanningModel()->banIp($this->_input->filterSingle('ip', XenForo_Input::STRING));

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('banning/ips')
		);
	}

	/**
	 * Deletes the specified banned IPs.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIpsDelete()
	{
		$this->_assertPostOnly();

		$this->_getBanningModel()->deleteBannedIps(
			$this->_input->filterSingle('delete', array(XenForo_Input::STRING, 'array' => true))
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('banning/ips')
		);
	}

	/**
	 * Lists IPs subject to the Discourager - see XenForo_ControllerPublic_Abstract::_discourage()
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDiscouragedIps()
	{
		$viewParams = array(
			'discouragedIps' => $this->_getBanningModel()->getDiscouragedIps()
		);

		return $this->responseView('XenForo_ViewAdmin_Banning_DiscouragedIp_List', 'discouraged_ip_list', $viewParams);
	}

	/**
	 * Adds a new discouraged IP.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDiscouragedIpsAdd()
	{
		$this->_assertPostOnly();

		$this->_getBanningModel()->discourageIp($this->_input->filterSingle('ip', XenForo_Input::STRING));

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('banning/discouraged-ips')
		);
	}

	/**
	 * Deletes the specified discouraged IPs.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDiscouragedIpsDelete()
	{
		$this->_assertPostOnly();

		$this->_getBanningModel()->deleteDiscouragedIps(
			$this->_input->filterSingle('delete', array(XenForo_Input::STRING, 'array' => true))
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('banning/discouraged-ips')
		);
	}

	/**
	 * Displays a list of banned emails and shows a form to ban an additional one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEmails()
	{
		$viewParams = array(
			'bannedEmails' => $this->_getBanningModel()->getBannedEmails()
		);

		return $this->responseView('XenForo_ViewAdmin_Banning_Email_List', 'ban_email_list', $viewParams);
	}

	/**
	 * Adds a new banned email.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEmailsAdd()
	{
		$this->_assertPostOnly();

		$this->_getBanningModel()->banEmail($this->_input->filterSingle('email', XenForo_Input::STRING));

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('banning/emails')
		);
	}

	/**
	 * Deletes the specified banned emails.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEmailsDelete()
	{
		$this->_assertPostOnly();

		$this->_getBanningModel()->deleteBannedEmails(
			$this->_input->filterSingle('delete', array(XenForo_Input::STRING, 'array' => true))
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('banning/emails')
		);
	}

	/**
	 * @return XenForo_Model_Banning
	 */
	protected function _getBanningModel()
	{
		return $this->getModelFromCache('XenForo_Model_Banning');
	}
}