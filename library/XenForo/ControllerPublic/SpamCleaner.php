<?php

/**
 * Spam Cleaner.
 * - Deletes any posts by the spam-posting user
 * - Deletes or moves any threads started by the user
 * - Bans the user
 */
class XenForo_ControllerPublic_SpamCleaner extends XenForo_ControllerPublic_Abstract
{
	protected function _preDispatch($action)
	{
		if (!XenForo_Visitor::getInstance()->hasPermission('general', 'cleanSpam'))
		{
			throw $this->getErrorOrNoPermissionResponseException(false);
		}
	}

	public function actionIndex()
	{
		$userModel = $this->_getUserModel();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $userModel->getUserById($userId, array('join' => XenForo_Model_User::FETCH_LAST_ACTIVITY));
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		if (!$userModel->couldBeSpammer($user, $errorKey))
		{
			return $this->responseError(new XenForo_Phrase($errorKey));
		}

		$canViewIps = $userModel->canViewIps();

		if ($this->isConfirmedPost())
		{
			$options = $this->_input->filter(array(
				'action_threads'  => XenForo_Input::STRING,
				'delete_messages' => XenForo_Input::UINT,
				'ban_user'        => XenForo_Input::UINT,
				'check_ips'       => XenForo_Input::UINT,
				'email_user'      => XenForo_Input::UINT,
				'email'           => XenForo_Input::STRING,
			));

			$spamCleanerModel = $this->_getSpamCleanerModel();

			if (!$log = $spamCleanerModel->cleanUp($user, $options, $log, $errorKey))
			{
				return $this->responseError(new XenForo_Phrase($errorKey));
			}

			if ($options['check_ips'] && $canViewIps)
			{
				$users = $spamCleanerModel->checkIps($user['user_id'], XenForo_Application::get('options')->spamCheckIpsDaysLimit);

				$viewParams = array(
					'spammer' => $user,
					'users' => $users
				);

				return $this->responseView(
					'XenForo_ViewPublic_SpamCleaner_CheckIps',
					'spam_cleaner_check_ips',
					$viewParams
				);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('index'),
					new XenForo_Phrase('spam_deleted')
				);
			}
		}
		else
		{
			if ($ipId = $this->_input->filterSingle('ip_id', XenForo_Input::UINT))
			{
				$contentIpRecord = XenForo_Model_Ip::getById($ipId);
				$contentIp = $contentIpRecord['ip_address'];
			}
			else
			{
				$contentIp = '';
			}

			$options = XenForo_Application::get('options');

			$emailText = strtr(
				$options->spamEmailText,
				array(
					'{username}' => $user['username'],
					'{boardTitle}' => $options->boardTitle,
					'{contactUrl}' => XenForo_Link::buildPublicLink('canonical:misc/contact')
				)
			);

			$viewParams = array(
				'user' => $user,
				'canViewIps' => $canViewIps,
				'registrationIps' => $userModel->getRegistrationIps($user['user_id']),
				'contentIp' => $contentIp,
				'emailText' => $emailText,
			);

			return $this->responseView(
				'XenForo_ViewPublic_SpamCleaner',
				'spam_cleaner',
				$viewParams
			);
		}
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('performing_moderation_duties');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_SpamCleaner
	 */
	protected function _getSpamCleanerModel()
	{
		return $this->getModelFromCache('XenForo_Model_SpamCleaner');
	}
}