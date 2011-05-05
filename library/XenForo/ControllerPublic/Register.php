<?php

/**
 * Controller for registration-related actions.
 *
 * @package XenForo_Users
 */
class XenForo_ControllerPublic_Register extends XenForo_ControllerPublic_Abstract
{
	protected function _preDispatch($action)
	{
		// prevent discouraged IP addresses from registering
		if (XenForo_Application::get('options')->preventDiscouragedRegistration && $this->_isDiscouraged())
		{
			throw $this->responseException($this->responseError(
				new XenForo_Phrase('new_registrations_currently_not_being_accepted')
			));
		}
	}

	/**
	 * Displays a form to register a new user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		if (XenForo_Visitor::getUserId())
		{
			throw $this->responseException(
				$this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
					$this->getDynamicRedirect()
				)
			);
		}

		$this->_assertRegistrationActive();

		$username = '';
		$email = '';

		if ($login = $this->_input->filterSingle('login', XenForo_Input::STRING))
		{
			if (Zend_Validate::is($login, 'EmailAddress'))
			{
				$email = $login;
			}
			else
			{
				$username = $login;
			}
		}

		$fields = array(
			'username' => $username,
			'email' => $email
		);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($username !== '')
		{
			$writer->set('username', $username);
		}
		if ($email !== '')
		{
			$writer->set('email', $email);
		}

		return $this->_getRegisterFormResponse($fields, $writer->getErrors());
	}

	protected function _getRegisterFormResponse(array $fields, array $errors = array())
	{
		$options = XenForo_Application::get('options');

		if (empty($fields['timezone']))
		{
			$fields['timezone'] = $options->guestTimeZone;
			$fields['timezoneAuto'] = true;
		}

		$viewParams = array(
			'fields' => $fields,
			'errors' => $errors,

			'timeZones' => XenForo_Helper_TimeZone::getTimeZones(),
			'dobRequired' => $options->get('registrationSetup', 'requireDob'),

			'captcha' => XenForo_Captcha_Abstract::createDefault(),
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
		);

		return $this->responseView(
			'XenForo_ViewPublic_Register_Form',
			'register_form',
			$viewParams,
			$this->_getRegistrationContainerParams()
		);
	}

	/**
	 * Validate a single field
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionValidateField()
	{
		$this->_assertPostOnly();

		return $this->_validateField('XenForo_DataWriter_User');
	}

	/**
	 * Registers a new user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionRegister()
	{
		$this->_assertPostOnly();
		$this->_assertRegistrationActive();

		$errors = array();

		if (!XenForo_Captcha_Abstract::validateDefault($this->_input))
		{
			$errors[] = new XenForo_Phrase('did_not_complete_the_captcha_verification_properly');
		}

		$data = $this->_input->filter(array(
			'username'   => XenForo_Input::STRING,
			'email'      => XenForo_Input::STRING,
			'timezone'   => XenForo_Input::STRING,
			'gender'     => XenForo_Input::STRING,
			'dob_day'    => XenForo_Input::UINT,
			'dob_month'  => XenForo_Input::UINT,
			'dob_year'   => XenForo_Input::UINT,
		));
		$passwords = $this->_input->filter(array(
			'password' => XenForo_Input::STRING,
			'password_confirm' => XenForo_Input::STRING,
		));

		if (XenForo_Dependencies_Public::getTosUrl() && !$this->_input->filterSingle('agree', XenForo_Input::UINT))
		{
			$errors[] = new XenForo_Phrase('you_must_agree_to_terms_of_service');
		}

		$options = XenForo_Application::get('options');

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($options->registrationDefaults)
		{
			$writer->bulkSet($options->registrationDefaults, array('ignoreInvalidFields' => true));
		}
		$writer->bulkSet($data);
		$writer->setPassword($passwords['password'], $passwords['password_confirm']);

		// if the email corresponds to an existing Gravatar, use it
		if ($options->gravatarEnable && XenForo_Model_Avatar::gravatarExists($data['email']))
		{
			$writer->set('gravatar', $data['email']);
		}

		$writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
		$writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));
		$writer->advanceRegistrationUserState();
		$writer->preSave();

		if ($options->get('registrationSetup', 'requireDob'))
		{
			// dob required
			if (!$data['dob_day'] || !$data['dob_month'] || !$data['dob_year'])
			{
				$writer->error(new XenForo_Phrase('please_enter_valid_date_of_birth'), 'dob');
			}
			else
			{
				$userAge = $this->_getUserProfileModel()->getUserAge($writer->getMergedData(), true);
				if ($userAge < 1)
				{
					$writer->error(new XenForo_Phrase('please_enter_valid_date_of_birth'), 'dob');
				}
				else if ($userAge < intval($options->get('registrationSetup', 'minimumAge')))
				{
					// TODO: set a cookie to prevent re-registration attempts
					$errors[] = new XenForo_Phrase('sorry_you_too_young_to_create_an_account');
				}
			}
		}

		$errors = array_merge($errors, $writer->getErrors());

		if ($errors)
		{
			$fields = $data;
			$fields['tos'] = $this->_input->filterSingle('agree', XenForo_Input::UINT);
			return $this->_getRegisterFormResponse($fields, $errors);
		}

		$writer->save();

		$user = $writer->getMergedData();

		// log the ip of the user registering
		XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register');

		if ($user['user_state'] == 'email_confirm')
		{
			$this->_getUserConfirmationModel()->sendEmailConfirmation($user);
		}

		XenForo_Application::get('session')->changeUserId($user['user_id']);
		XenForo_Visitor::setup($user['user_id']);

		$viewParams = array(
			'user' => $user
		);

		return $this->responseView(
			'XenForo_ViewPublic_Register_Process',
			'register_process',
			$viewParams,
			$this->_getRegistrationContainerParams()
		);
	}

	/**
	 * Displays a form to join using Facebook or logs in an existing account.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionFacebook()
	{
		$assocUserId = $this->_input->filterSingle('assoc', XenForo_Input::UINT);
		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		$options = XenForo_Application::get('options');

		$fbRedirectUri = XenForo_Link::buildPublicLink('canonical:register/facebook', false, array(
			'assoc' => ($assocUserId ? $assocUserId : false)
		));

		if ($this->_input->filterSingle('reg', XenForo_Input::UINT))
		{
			$redirect = XenForo_Link::convertUriToAbsoluteUri($this->getDynamicRedirect());
			$baseDomain = preg_replace('#^([a-z]+://[^/]+).*$#i', '$1', $options->boardUrl);
			if (strpos($redirect, $baseDomain) !== 0)
			{
				$redirect = XenForo_Link::buildPublicLink('canonical:index');
			}

			XenForo_Application::get('session')->set('fbRedirect', $redirect);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Helper_Facebook::getFacebookRequestUrl($fbRedirectUri)
			);
		}

		$fbToken = $this->_input->filterSingle('t', XenForo_Input::STRING);

		if (!$fbToken)
		{
			$error = $this->_input->filterSingle('error', XenForo_Input::STRING);
			if ($error == 'access_denied')
			{
				return $this->responseError(new XenForo_Phrase('access_to_facebook_account_denied'));
			}

			$code = $this->_input->filterSingle('code', XenForo_Input::STRING);
			if (!$code)
			{
				return $this->responseError(new XenForo_Phrase('error_occurred_while_connecting_with_facebook'));
			}

			$token = XenForo_Helper_Facebook::getAccessTokenFromCode($code, $fbRedirectUri);
			$fbError = XenForo_Helper_Facebook::getFacebookRequestErrorInfo($token, 'access_token');
			if ($fbError)
			{
				XenForo_Error::logException(new XenForo_Exception(strval($fbError)));
				return $this->responseError(new XenForo_Phrase('error_occurred_while_connecting_with_facebook'));
			}

			$fbToken = $token['access_token'];
		}

		$fbUser = XenForo_Helper_Facebook::getUserInfo($fbToken);
		$fbError = XenForo_Helper_Facebook::getFacebookRequestErrorInfo($fbUser, 'id');
		if ($fbError)
		{
			XenForo_Error::logException(new XenForo_Exception(strval($fbError)));
			return $this->responseError(new XenForo_Phrase('error_occurred_while_connecting_with_facebook'));
		}

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$fbAssoc = $userExternalModel->getExternalAuthAssociation('facebook', $fbUser['id']);
		if ($fbAssoc && $userModel->getUserById($fbAssoc['user_id']))
		{
			XenForo_Helper_Facebook::setUidCookie($fbUser['id']);
			XenForo_Application::get('session')->changeUserId($fbAssoc['user_id']);
			XenForo_Visitor::setup($fbAssoc['user_id']);

			$redirect = XenForo_Application::get('session')->get('fbRedirect');
			XenForo_Application::get('session')->remove('fbRedirect');
			if (!$redirect)
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}

		XenForo_Helper_Facebook::setUidCookie(0);

		parent::_assertBoardActive('facebook');

		$existingUser = false;
		$emailMatch = false;
		if (XenForo_Visitor::getUserId())
		{
			$existingUser = XenForo_Visitor::getInstance();
		}
		else if ($assocUserId)
		{
			$existingUser = $userModel->getUserById($assocUserId);
		}

		if (!$existingUser)
		{
			$existingUser = $userModel->getUserByEmail($fbUser['email']);
			$emailMatch = true;
		}

		if ($existingUser)
		{
			// must associate: matching user
			return $this->responseView('XenForo_ViewPublic_Register_Facebook', 'register_facebook', array(
				'associateOnly' => true,

				'fbToken' => $fbToken,
				'fbUser' => $fbUser,

				'existingUser' => $existingUser,
				'emailMatch' => $emailMatch,
				'redirect' => $redirect
			));
		}

		if (!XenForo_Application::get('options')->get('registrationSetup', 'enabled'))
		{
			$this->_assertRegistrationActive();
		}

		if (!empty($fbUser['birthday']))
		{
			$birthdayParts = explode('/', $fbUser['birthday']);
			if (count($birthdayParts) == 3)
			{
				list($month, $day, $year) = $birthdayParts;
				$userAge = $this->_getUserProfileModel()->calculateAge($year, $month, $day);
				if ($userAge < intval($options->get('registrationSetup', 'minimumAge')))
				{
					// TODO: set a cookie to prevent re-registration attempts
					return $this->responseError(new XenForo_Phrase('sorry_you_too_young_to_create_an_account'));
				}
			}
		}

		// give a unique username suggestion
		$i = 2;
		$origName = $fbUser['name'];
		while ($userModel->getUserByName($fbUser['name']))
		{
			$fbUser['name'] = $origName . ' ' . $i++;
		}

		return $this->responseView('XenForo_ViewPublic_Register_Facebook', 'register_facebook', array(
			'fbToken' => $fbToken,
			'fbUser' => $fbUser,
			'redirect' => $redirect,

			'timeZones' => XenForo_Helper_TimeZone::getTimeZones(),
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
		), $this->_getRegistrationContainerParams());
	}

	/**
	 * Registers a new account (or associates with an existing one) using Facebook.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionFacebookRegister()
	{
		$this->_assertPostOnly();

		$fbToken = $this->_input->filterSingle('fb_token', XenForo_Input::STRING);

		$fbUser = XenForo_Helper_Facebook::getUserInfo($fbToken);
		if (empty($fbUser['id']))
		{
			return $this->responseError(new XenForo_Phrase('error_occurred_while_connecting_with_facebook'));
		}

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$doAssoc = ($this->_input->filterSingle('associate', XenForo_Input::STRING)
			|| $this->_input->filterSingle('force_assoc', XenForo_Input::UINT)
		);

		if ($doAssoc)
		{
			$associate = $this->_input->filter(array(
				'associate_login' => XenForo_Input::STRING,
				'associate_password' => XenForo_Input::STRING
			));

			$loginModel = $this->_getLoginModel();

			if ($loginModel->requireLoginCaptcha($associate['associate_login']))
			{
				return $this->responseError(new XenForo_Phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
			}

			$userId = $userModel->validateAuthentication($associate['associate_login'], $associate['associate_password'], $error);
			if (!$userId)
			{
				$loginModel->logLoginAttempt($associate['associate_login']);
				return $this->responseError($error);
			}

			$userExternalModel->updateExternalAuthAssociation('facebook', $fbUser['id'], $userId);
			XenForo_Helper_Facebook::setUidCookie($fbUser['id']);

			XenForo_Application::get('session')->changeUserId($userId);
			XenForo_Visitor::setup($userId);

			$redirect = XenForo_Application::get('session')->get('fbRedirect');
			XenForo_Application::get('session')->remove('fbRedirect');
			if (!$redirect)
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}

		$this->_assertRegistrationActive();

		$data = $this->_input->filter(array(
			'username'   => XenForo_Input::STRING,
			'timezone'   => XenForo_Input::STRING,
		));

		if (XenForo_Dependencies_Public::getTosUrl() && !$this->_input->filterSingle('agree', XenForo_Input::UINT))
		{
			return $this->responseError(new XenForo_Phrase('you_must_agree_to_terms_of_service'));
		}

		$options = XenForo_Application::get('options');

		$gender = '';
		if (isset($fbUser['gender']))
		{
			switch ($fbUser['gender'])
			{
				case 'man':
				case 'male':
					$gender = 'male';
					break;

				case 'woman':
				case 'female':
					$gender = 'female';
					break;
			}
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($options->registrationDefaults)
		{
			$writer->bulkSet($options->registrationDefaults, array('ignoreInvalidFields' => true));
		}
		$writer->bulkSet($data);
		$writer->bulkSet(array(
			'gender' => $gender,
			'email' => $fbUser['email'],
			'location' => isset($fbUser['location']['name']) ? $fbUser['location']['name'] : ''
		));

		if (!empty($fbUser['birthday']))
		{
			$birthdayParts = explode('/', $fbUser['birthday']);
			if (count($birthdayParts) == 3)
			{
				list($month, $day, $year) = $birthdayParts;
				$userAge = $this->_getUserProfileModel()->calculateAge($year, $month, $day);
				if ($userAge < intval($options->get('registrationSetup', 'minimumAge')))
				{
					// TODO: set a cookie to prevent re-registration attempts
					return $this->responseError(new XenForo_Phrase('sorry_you_too_young_to_create_an_account'));
				}

				$writer->bulkSet(array(
					'dob_year' => $year,
					'dob_month' => $month,
					'dob_day' => $day
				));
			}
		}

		if (!empty($fbUser['website']))
		{
			list($website) = preg_split('/\r?\n/', $fbUser['website']);
			if ($website && Zend_Uri::check($website))
			{
				$writer->set('homepage', $website);
			}
		}

		$auth = XenForo_Authentication_Abstract::create('XenForo_Authentication_NoPassword');
		$writer->set('scheme_class', $auth->getClassName());
		$writer->set('data', $auth->generate(''), 'xf_user_authenticate');

		$writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
		$writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));
		$writer->advanceRegistrationUserState(false);
		$writer->preSave();

		// TODO: option for extra user group

		$writer->save();
		$user = $writer->getMergedData();

		$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
		if ($avatarFile)
		{
			$data = XenForo_Helper_Facebook::getUserPicture($fbToken);
			if ($data && $data[0] != '{') // ensure it's not a json response
			{
				file_put_contents($avatarFile, $data);

				try
				{
					$user = array_merge($user,
						$this->getModelFromCache('XenForo_Model_Avatar')->applyAvatar($user['user_id'], $avatarFile)
					);
				}
				catch (XenForo_Exception $e) {}
			}

			@unlink($avatarFile);
		}

		$userExternalModel->updateExternalAuthAssociation('facebook', $fbUser['id'], $user['user_id']);

		XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register');

		XenForo_Helper_Facebook::setUidCookie($fbUser['id']);

		XenForo_Application::get('session')->changeUserId($user['user_id']);
		XenForo_Visitor::setup($user['user_id']);

		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		$viewParams = array(
			'user' => $user,
			'redirect' => ($redirect ? XenForo_Link::convertUriToAbsoluteUri($redirect) : ''),
			'facebook' => true
		);

		return $this->responseView(
			'XenForo_ViewPublic_Register_Process',
			'register_process',
			$viewParams,
			$this->_getRegistrationContainerParams()
		);
	}

	protected function _assertRegistrationActive()
	{
		if (!XenForo_Application::get('options')->get('registrationSetup', 'enabled'))
		{
			throw $this->responseException($this->responseError(
				new XenForo_Phrase('new_registrations_currently_not_being_accepted')
			));
		}
	}

	protected function _assertBoardActive($action)
	{
		if (strtolower($action) != 'facebook')
		{
			parent::_assertBoardActive($action);
		}
	}

	protected function _assertCorrectVersion($action)
	{
		if (strtolower($action) != 'facebook')
		{
			parent::_assertBoardActive($action);
		}
	}

	protected function _assertViewingPermissions($action) {}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('registering');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_UserProfile
	 */
	protected function _getUserProfileModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserProfile');
	}

	/**
	 * @return XenForo_Model_UserConfirmation
	 */
	protected function _getUserConfirmationModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserConfirmation');
	}

	/**
	 * @return XenForo_Model_UserExternal
	 */
	protected function _getUserExternalModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserExternal');
	}

	/**
	 * @return XenForo_Model_Login
	 */
	protected function _getLoginModel()
	{
		return $this->getModelFromCache('XenForo_Model_Login');
	}
}