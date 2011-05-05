<?php

class XenForo_ControllerPublic_Account extends XenForo_ControllerPublic_Abstract
{
	## ------------------------------------------
	##
	## Settings Splash
	##

	public function actionIndex()
	{
		return $this->responseReroute(__CLASS__, 'personal-details');
	}

	/**
	 * Links to the various other settings components
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAlerts()
	{
		$alertModel = $this->_getAlertModel();
		$visitor = XenForo_Visitor::getInstance();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = XenForo_Application::get('options')->alertsPerPage;

		$alertResults = $alertModel->getAlertsForUser(
			$visitor['user_id'],
			XenForo_Model_Alert::FETCH_MODE_RECENT,
			array(
				'page' => $page,
				'perPage' => $perPage
			)
		);

		if ($page < 2 && $visitor['alerts_unread'])
		{
			$alertModel->markAllAlertsReadForUser($visitor['user_id']);
		}

		$viewParams = array(
			'alerts' => $alertResults['alerts'],
			'alertHandlers' => $alertResults['alertHandlers'],

			'page' => $page,
			'perPage' => $perPage,
			'totalAlerts' => $alertModel->countAlertsForUser($visitor['user_id'])
		);

		return $this->_getWrapper(
			'alerts', 'latest',
			$this->responseView('XenForo_ViewPublic_Account_Index', 'account_alerts', $viewParams)
		);
	}

	/**
	 * View alerts that are unread, or have been read
	 * in the last options->alertsPopupExpiryHours hours
	 *
	 * Used to fetch results for to alerts popup menu
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAlertsPopup()
	{
		$alertModel = $this->_getAlertModel();
		$visitor = XenForo_Visitor::getInstance();

		$alertResults = $alertModel->getAlertsForUser(
			$visitor['user_id'],
			XenForo_Model_Alert::FETCH_MODE_POPUP
		);

		if ($visitor['alerts_unread'])
		{
			$alertModel->markAllAlertsReadForUser($visitor['user_id']);
		}

		// separate read and unread alerts (for coloring reasons)
		$alertsUnread = array();
		$alertsRead = array();
		foreach ($alertResults['alerts'] AS $alertId => $alert)
		{
			if ($alert['unviewed'])
			{
				$alertsUnread[$alertId] = $alert;
			}
			else
			{
				$alertsRead[$alertId] = $alert;
			}
		}

		$viewParams = array(
			'alertsUnread' => $alertsUnread,
			'alertsRead' => $alertsRead,
			'alertHandlers' => $alertResults['alertHandlers'],
		);

		return $this->responseView(
			'XenForo_ViewPublic_Account_AlertsPopup',
			'account_alerts_popup',
			$viewParams
		);
	}

	/**
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}

	## ------------------------------------------
	##
	## Field Validation
	##

	/**
	 * Validate a single field
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionValidateField()
	{
		$this->_assertPostOnly();

		$field = $this->_getFieldValidationInputParams();

		if (preg_match('/^identity\[([^\]]+)\]$/', $field['name'], $match))
		{
			// handle identity fields
			$identityServiceId = $match[1];

			if ($field['value'] !== '')
			{
				if (!$this->_getUserModel()->verifyIdentity($identityServiceId, $field['value'], $error))
				{
					return $this->responseError(array($identityServiceId => $error));
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account'),
				new XenForo_Phrase('redirect_field_validated', array('name' => $identityServiceId, 'value' => $field['value']))
			);
		}
		else
		{
			// handle normal fields
			return $this->_validateField('XenForo_DataWriter_User', array(
				'name' => $this->_getFieldNameForValidation($field['name']),
				'existingDataKey' => XenForo_Visitor::getUserId()
			));
		}
	}

	/**
	 * Translates certain field names into others for auto-validation purposes
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	protected function _getFieldNameForValidation($name)
	{
		switch ($name)
		{
			case 'email_confirm':
			case 'password_confirm':
			{
				return preg_replace('/_confirm$/', '', $name);
			}
		}

		return $name;
	}

	/**
	 * Validates a single instant messaging account field
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionValidateIdentityField()
	{
		$this->_assertPostOnly();

		$field = $this->_getFieldValidationInputParams();

		if ($field['value'] !== '')
		{
			$identityServiceId = preg_replace('/^identity\[([^\]]+)\]$/', '\1', $field['name']);

			if (!$this->_getUserModel()->verifyIdentity($identityServiceId, $field['value'], $error))
			{
				return $this->responseError(array($identityServiceId => $error));
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account'),
			new XenForo_Phrase('redirect_field_validated', array('name' => $identityServiceId, 'value' => $field['value']))
		);
	}

	## ------------------------------------------
	##
	## Personal Details
	##

	/**
	 * Main profile editing control panel
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionPersonalDetails()
	{
		$visitor = XenForo_Visitor::getInstance();

		$viewParams = array(
			'birthday' => $this->_getUserProfileModel()->getUserBirthdayDetails(
				$visitor->toArray(), true
			),
			'canEditAvatar' => $visitor->canUploadAvatar(),
			'canEditCustomTitle' => $visitor->hasPermission('general', 'editCustomTitle'),
		);

		return $this->_getWrapper(
			'account', 'personalDetails',
			$this->responseView(
				'XenForo_ViewPublic_Account_PersonalDetails',
				'account_personal_details',
				$viewParams
			)
		);
	}

	/**
	 * Save profile data
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionPersonalDetailsSave()
	{
		$this->_assertPostOnly();

		$settings = $this->_input->filter(array(
			'gender'     => XenForo_Input::STRING,
			'custom_title' => XenForo_Input::STRING,
			// user_profile
			'status'     => XenForo_Input::STRING,
			'homepage'   => XenForo_Input::STRING,
			'location'   => XenForo_Input::STRING,
			'occupation' => XenForo_Input::STRING,
			'dob_day'    => XenForo_Input::UINT,
			'dob_month'  => XenForo_Input::UINT,
			'dob_year'   => XenForo_Input::UINT,
			// user_option
			'show_dob_year' => XenForo_Input::UINT,
			'show_dob_date' => XenForo_Input::UINT,
		));
		$settings['about'] = $this->getHelper('Editor')->getMessageText('about', $this->_input);
		$settings['about'] = XenForo_Helper_String::autoLinkBbCode($settings['about']);

		$visitor = XenForo_Visitor::getInstance();
		if ($visitor['dob_day'] && $visitor['dob_month'] && $visitor['dob_year'])
		{
			// can't change dob if set
			unset($settings['dob_day'], $settings['dob_month'], $settings['dob_year']);
		}

		if (!$visitor->hasPermission('general', 'editCustomTitle'))
		{
			unset($settings['custom_title']);
		}

		$status = $settings['status'];
		unset($settings['status']); // see below for status update

		if ($status !== '')
		{
			$this->assertNotFlooding('post');
		}

		if (!$writer = $this->_saveVisitorSettings($settings, $errors))
		{
			return $this->responseError($errors);
		}

		$redirectParams = array();

		if ($status !== '')
		{
			$this->getModelFromCache('XenForo_Model_UserProfile')->updateStatus($status);
			$redirectParams['status'] = $status;
		}

		if ($this->_noRedirect())
		{
			$user = $writer->getMergedData();

			// send new avatar URLs if the user's gender has changed
			if (!$user['avatar_date'] && !$user['gravatar'] && $writer->isChanged('gender'))
			{
				return $this->responseView('XenForo_ViewPublic_Account_GenderChange', '', array('user' => $user));
			}

		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/personal-details'),
			null,
			$redirectParams
		);
	}

	/**
	 * Signature form
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionSignature()
	{
		if (!XenForo_Visitor::getInstance()->canEditSignature())
		{
			return $this->responseNoPermission();
		}

		return $this->_getWrapper(
			'account', 'signature',
			$this->responseView(
				'XenForo_ViewPublic_Account_Signature',
				'account_signature'
			)
		);
	}

	/**
	 * Save signature
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionSignatureSave()
	{
		$this->_assertPostOnly();

		if (!XenForo_Visitor::getInstance()->canEditSignature())
		{
			return $this->responseNoPermission();
		}

		$signature = $this->getHelper('Editor')->getMessageText('signature', $this->_input);
		$signature = XenForo_Helper_String::autoLinkBbCode($signature);

		$settings = array('signature' => $signature);

		if (!$writer = $this->_saveVisitorSettings($settings, $errors))
		{
			return $this->responseError($errors);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/signature')
		);
	}

	/**
	 * Shows a preview of the signature.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSignaturePreview()
	{
		$this->_assertPostOnly();

		if (!XenForo_Visitor::getInstance()->canEditSignature())
		{
			return $this->responseNoPermission();
		}

		$signature = $this->getHelper('Editor')->getMessageText('signature', $this->_input);
		$signature = XenForo_Helper_String::autoLinkBbCode($signature);

		$viewParams = array(
			'signature' => $signature
		);

		return $this->responseView('XenForo_ViewPublic_Account_SignaturePreview', 'account_signature_preview', $viewParams);
	}

	public function actionAvatar()
	{
		if (!XenForo_Visitor::getInstance()->canUploadAvatar())
		{
			return $this->responseNoPermission();
		}

		$visitor = XenForo_Visitor::getInstance();

		$maxWidth = XenForo_Model_Avatar::getSizeFromCode('m');

		$gravatarEmail = $visitor['gravatar'] ? $visitor['gravatar'] : $visitor['email'];
		$gravatarUrl = 'http://www.gravatar.com/avatar/'
			. md5($gravatarEmail)
			. '?s=' . $maxWidth;

		$viewParams = array(
			'sizeCode' => 'm',
			'maxWidth' => $maxWidth,
			'maxDimension' => ($visitor['avatar_width'] > $visitor['avatar_height'] ? 'height' : 'width'),
			'width' => $visitor['avatar_width'],
			'height' => $visitor['avatar_height'],
			'cropX' => $visitor['avatar_crop_x'],
			'cropY' => $visitor['avatar_crop_y'],
			'gravatarEmail' => $gravatarEmail,
			'gravatarUrl' => $gravatarUrl,
		);

		return $this->_getWrapper(
			'account', 'personalDetails',
			$this->responseView(
				'XenForo_ViewPublic_Account_Avatar',
				'account_avatar',
				$viewParams
			)
		);
	}

	public function actionGravatarTest()
	{
		$this->_assertPostOnly();

		if (!XenForo_Visitor::getInstance()->canUploadAvatar())
		{
			return $this->responseNoPermission();
		}

		$data = $this->_input->filter(array(
			'email' => XenForo_Input::STRING,
			'size' => XenForo_Input::UINT
		));

		if (!XenForo_Model_Avatar::gravatarExists($data['email'], $errorText, $data['size'], $gravatarUrl))
		{
			return $this->responseError($errorText);
		}

		$viewParams = array(
			'gravatarUrl' => $gravatarUrl
		);

		return $this->responseView('XenForo_ViewPublic_Account_GravatarTest', 'gravatar_test', $viewParams);
	}

	public function actionAvatarUpload()
	{
		$this->_assertPostOnly();

		if (!XenForo_Visitor::getInstance()->canUploadAvatar())
		{
			return $this->responseNoPermission();
		}

		$avatar = XenForo_Upload::getUploadedFile('avatar');

		/* @var $avatarModel XenForo_Model_Avatar */
		$avatarModel = $this->getModelFromCache('XenForo_Model_Avatar');

		/* @var $visitor XenForo_Visitor */
		$visitor = XenForo_Visitor::getInstance();

		$inputData = $this->_input->filter(array(
			'delete' => XenForo_Input::UINT,
			'avatar_crop_x' => XenForo_Input::UINT,
			'avatar_crop_y' => XenForo_Input::UINT,
			'gravatar' => XenForo_Input::STRING,
			'use_gravatar' => XenForo_Input::UINT
		));

		// upload new avatar
		if ($avatar)
		{
			$avatarData = $avatarModel->uploadAvatar($avatar, $visitor['user_id'], $visitor->getPermissions());
		}
		// delete avatar
		else if ($inputData['delete'])
		{
			$avatarData = $avatarModel->deleteAvatar(XenForo_Visitor::getUserId());
		}
		// use Gravatar
		else if (XenForo_Application::get('options')->gravatarEnable && $inputData['use_gravatar'])
		{
			if (!$inputData['gravatar'])
			{
				$inputData['gravatar'] = $visitor['email'];
			}

			if (!XenForo_Model_Avatar::gravatarExists($inputData['gravatar'], $errorText))
			{
				return $this->responseError($errorText);
			}
			else
			{
				$avatarData = array('gravatar' => $inputData['gravatar']);

				$this->_saveVisitorSettings($avatarData, $errors);
			}
		}
		// re-crop avatar thumbnail
		else if ($inputData['avatar_crop_x'] != $visitor['avatar_crop_x'] || $inputData['avatar_crop_y'] != $visitor['avatar_crop_y'])
		{
			$avatarData = $avatarModel->recropAvatar(XenForo_Visitor::getUserId(), $inputData['avatar_crop_x'], $inputData['avatar_crop_y']);
		}
		// get rid of gravatar
		else if ($visitor['gravatar'] && !$inputData['use_gravatar'])
		{
			$avatarData = array('gravatar' => '');

			$this->_saveVisitorSettings($avatarData, $errors);
		}

		// merge new data into $visitor, if there is any
		if (isset($avatarData) && is_array($avatarData))
		{
			foreach ($avatarData AS $key => $val)
			{
				$visitor[$key] = $val;
			}
		}

		$message = new XenForo_Phrase('upload_completed_successfully');

		// return a view if noredirect has been requested and we are not deleting
		if ($this->_noRedirect())
		{
			return $this->responseView(
				'XenForo_ViewPublic_Account_AvatarUpload',
				'account_avatar_upload',
				array(
					'user' => $visitor->toArray(),
					'sizeCode' => 'm',
					'maxWidth' => XenForo_Model_Avatar::getSizeFromCode('m'),
					'maxDimension' => ($visitor['avatar_width'] > $visitor['avatar_height'] ? 'height' : 'width'),
					'width' => $visitor['avatar_width'],
					'height' => $visitor['avatar_height'],
					'cropX' => $visitor['avatar_crop_x'],
					'cropY' => $visitor['avatar_crop_y'],
					'user_id' => $visitor['user_id'],
					'avatar_date' => $visitor['avatar_date'],
					'gravatar' => $visitor['gravatar'],
					'message' => $message
				)
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account/personal-details'),
				$message
			);
		}
	}

	## ------------------------------------------
	##
	## Browsing Preferences
	##

	/**
	 * Main user options editing control panel
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionPreferences()
	{
		$styles = $this->getModelFromCache('XenForo_Model_Style')->getAllStylesAsFlattenedTree();
		$languages = $this->getModelFromCache('XenForo_Model_Language')->getAllLanguages();

		$options = XenForo_Application::get('options');
		$defaultStyle = isset($styles[$options->defaultStyleId]) ? $styles[$options->defaultStyleId] : array();

		$viewParams = array(
			'styles' => $styles,
			'languages' => $languages,
			'defaultStyle' => $defaultStyle,
			'timeZones' => XenForo_Helper_TimeZone::getTimeZones(),
		);

		return $this->_getWrapper(
			'account', 'preferences',
			$this->responseView(
				'XenForo_ViewPublic_Account_Preferences',
				'account_preferences',
				$viewParams
			)
		);
	}

	/**
	 * Save user options
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionPreferencesSave()
	{
		$this->_assertPostOnly();

		$settings = $this->_input->filter(array(
			//user
			'language_id' => XenForo_Input::UINT,
			'style_id' => XenForo_Input::UINT,
			'visible' => XenForo_Input::UINT,
			'timezone' => XenForo_Input::STRING,
			//user_option
			'content_show_signature' => XenForo_Input::UINT,
			'enable_rte' => XenForo_Input::UINT,
		));

		if ($this->_input->filterSingle('default_watch_state', XenForo_Input::UINT))
		{
			$settings['default_watch_state'] = ($this->_input->filterSingle('default_watch_state_email', XenForo_Input::UINT)
				? 'watch_email'
				: 'watch_no_email'
			);
		}
		else
		{
			$settings['default_watch_state'] = '';
		}

		if (!$this->_saveVisitorSettings($settings, $errors))
		{
			return $this->responseError($errors);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/preferences')
		);
	}

	## ------------------------------------------
	##
	## Alert preferences
	##

	public function actionAlertPreferences()
	{
		$viewParams = array(
			'alertOptOuts' => $this->getModelFromCache('XenForo_Model_Alert')->getAlertOptOuts(null, true)
		);

		return $this->_getWrapper(
			'account', 'alertPreferences',
			$this->responseView(
				'XenForo_ViewPublic_Account_AlertPreferences',
				'account_alert_preferences',
				$viewParams
			)
		);
	}

	public function actionAlertPreferencesSave()
	{
		$this->_assertPostOnly();

		$alert = $this->_input->filterSingle('alert', array(XenForo_Input::UINT, 'array' => true));

		$optOuts = array();
		foreach (array_keys($this->_input->filterSingle('alertSet', array(XenForo_Input::UINT, 'array' => true))) AS $optOut)
		{
			if (empty($alert[$optOut]))
			{
				$optOuts[$optOut] = $optOut;
			}
		}

		$specialCallbacks = array('setAlertOptOuts' => $optOuts);

		$writer = $this->_saveVisitorSettings(array(), $errors, $specialCallbacks);

		if (!empty($errors))
		{
			return $this->responseError($errors);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/alert-preferences')
		);
	}

	## ------------------------------------------
	##
	## Privacy
	##

	public function actionPrivacy()
	{
		$viewParams = array(
			'isPrivacySettings' => true,
		);

		return $this->_getWrapper(
			'account', 'privacy',
			$this->responseView(
				'XenForo_ViewPublic_Account_Privacy',
				'account_privacy',
				$viewParams
			)
		);
	}

	public function actionPrivacySave()
	{
		$this->_assertPostOnly();

		$settings = $this->_input->filter(array(
			// user
			'visible' => XenForo_Input::UINT,
			// user_option
			'show_dob_date' => XenForo_Input::UINT,
			'show_dob_year' => XenForo_Input::UINT,
			'receive_admin_email' => XenForo_Input::UINT,
			// user_privacy
			'allow_view_profile' => XenForo_Input::STRING,
			'allow_post_profile' => XenForo_Input::STRING,
			'allow_send_personal_conversation' => XenForo_Input::STRING,
			'allow_view_identities' => XenForo_Input::STRING,
			'allow_receive_news_feed' => XenForo_Input::STRING,
		));

		if (!$this->_saveVisitorSettings($settings, $errors))
		{
			return $this->responseError($errors);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/privacy')
		);
	}

	public function actionToggleVisibility()
	{
		$this->_assertPostOnly();

		$settings = $this->_input->filter(array(
			'visible' => XenForo_Input::UINT
		));

		if (!$this->_saveVisitorSettings($settings, $errors))
		{
			return $this->responseError($errors);
		}

		$phraseKey = ($settings['visible']
			? 'your_online_status_is_now_visible_to_visitors'
			: 'your_online_status_is_now_hidden_from_visitors');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/privacy'),
			new XenForo_Phrase($phraseKey)
		);
	}

	## ------------------------------------------
	##
	## Contact Details
	##

	public function actionContactDetails()
	{
		$userModel = $this->_getUserModel();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId(XenForo_Visitor::getUserId());
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		$identities = $userModel->getIdentities(XenForo_Visitor::getUserId());

		$viewParams = array(
			'identityServices' => $userModel->getIdentityServicesEditingData($identities),
			'hasPassword' => $auth->hasPassword()
		);

		return $this->_getWrapper(
			'account', 'contactDetails',
			$this->responseView(
				'XenForo_ViewPublic_Account_ContactPreferences',
				'account_contact_details',
				$viewParams
			)
		);
	}

	public function actionContactDetailsSave()
	{
		$this->_assertPostOnly();

		$settings = $this->_input->filter(array(
			// user
			'email' => XenForo_Input::STRING,
			// user_option
			'receive_admin_email' => XenForo_Input::UINT,
			'email_on_conversation' => XenForo_Input::UINT,
			// user privacy
			'allow_send_personal_conversation' => XenForo_Input::STRING,
		));

		$visitor = XenForo_Visitor::getInstance();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		if (!$auth->hasPassword())
		{
			unset($settings['email']);
		}

		if (isset($settings['email']) && $settings['email'] !== $visitor['email'])
		{
			$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
			if (!$auth->authenticate($visitor['user_id'], $this->_input->filterSingle('password', XenForo_Input::STRING)))
			{
				return $this->responseError(new XenForo_Phrase('your_existing_password_is_not_correct'));
			}
		}

		/**
		 * Deal with disabler wiping out email_on_conversation preference
		 */
		/*if (empty($settings['allow_send_personal_conversation']))
		{
			$settings['email_on_conversation'] = $visitor['email_on_conversation'];
		}*/

		$specialCallbacks = array('setIdentities' => $this->_input->filterSingle('identity', array(XenForo_Input::STRING, 'array' => true)));

		$writer = $this->_saveVisitorSettings($settings, $errors, $specialCallbacks);

		if (!empty($errors))
		{
			return $this->responseError($errors);
		}

		$user = $writer->getMergedData();
		if ($writer->isChanged('email')
			&& ($user['user_state'] == 'email_confirm_edit' || $user['user_state'] == 'email_confirm')
		)
		{
			$this->getModelFromCache('XenForo_Model_UserConfirmation')->sendEmailConfirmation($user);

			return $this->responseMessage(new XenForo_Phrase('your_account_must_be_reconfirmed'));
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account/contact-details')
			);
		}
	}

	## ------------------------------------------
	##
	## Security
	##

	/**
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionSecurity()
	{
		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId(XenForo_Visitor::getUserId());
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		$viewParams = array(
			'hasPassword' => $auth->hasPassword()
		);

		return $this->_getWrapper(
			'account', 'security',
			$this->responseView('XenForo_ViewPublic_Account_Security', 'account_security', $viewParams)
		);
	}

	/**
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionSecuritySave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'old_password' => XenForo_Input::STRING,
			'password' => XenForo_Input::STRING,
			'password_confirm' => XenForo_Input::STRING
		));

		$userId = XenForo_Visitor::getUserId();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($userId);
		if (!$auth->authenticate($userId, $input['old_password']))
		{
			return $this->responseError(new XenForo_Phrase('your_existing_password_is_not_correct'));
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$writer->setExistingData($userId);
		$writer->setPassword($input['password'], $input['password_confirm']);
		$writer->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('settings')
		);
	}

	## ------------------------------------------
	##
	## Following Users
	##

	/**
	 * Lists all the users the visitor is following
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionFollowing()
	{
		$userModel = $this->_getUserModel();

		$following = $userModel->getFollowedUserProfiles(XenForo_Visitor::getUserId());

		$viewParams = array(
			'following' => $userModel->prepareUserCards($following),
			'username' => $this->_input->filterSingle('username', XenForo_Input::STRING)
		);

		return $this->_getWrapper(
			'account', 'following',
			$this->responseView(
				'XenForo_ViewPublic_Account_Following',
				'account_following',
				$viewParams
			)
		);
	}

	/**
	 * Adds a new user to the visitors list of users they follow
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionFollow()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'user_id'  => XenForo_Input::UINT,
			'users' => XenForo_Input::STRING
		));

		$users = array();

		$userModel = $this->_getUserModel();

		if (!empty($input['user_id']))
		{
			if (!$user = $userModel->getUserById($input['user_id']))
			{
				return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
			}

			$users[$user['user_id']] = $user;
		}
		else if (!empty($input['users']))
		{
			$usernames = explode(',', $input['users']);
			$users = $userModel->getUsersByNames($usernames, array('join' => XenForo_Model_User::FETCH_USER_FULL), $notFound);

			if ($notFound)
			{
				return $this->responseError(new XenForo_Phrase('following_members_not_found_x', array('members' => implode(', ', $notFound))));
			}
		}

		$visitor = XenForo_Visitor::getInstance();

		// prevent following self
		if (isset($users[$visitor['user_id']]))
		{
			if (sizeof($users) == 1)
			{
				return $this->responseError(new XenForo_Phrase('you_may_not_follow_yourself'));
			}
			unset($users[$visitor['user_id']]);
		}

		if (empty($users))
		{
			return $this->responseError(new XenForo_Phrase('please_specify_one_or_more_members_to_follow'));
		}

		// remove duplicates
		$alreadyFollowing = explode(',', $visitor['following']);
		foreach ($users AS $userId => $user)
		{
			if (in_array($userId, $alreadyFollowing))
			{
				unset($users[$userId]);
			}
		}

		$following = $userModel->follow($users, false);

		if ($this->_noRedirect())
		{
			return $this->responseView(
				'XenForo_ViewPublic_Account_Follow',
				'account_follow_success',
				array(
					'followUsers' => $users,
					'following' => $following
				)
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/following')
		);
	}

	/**
	 * Asks for confirmation of the intention to stop following a user
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionStopFollowingConfirm()
	{
		$user_id = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if (empty($user_id)|| !($followed = $this->_getUserModel()->getUserById($user_id)))
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$viewParams = array(
			'followed' => $followed
		);

		return $this->_getWrapper(
			'account', 'following',
			$this->responseView(
				'XenForo_ViewPublic_Account_StopFollowing',
				'account_stop_following',
				$viewParams
			)
		);
	}

	/**
	 * Ends the following relationship of the visitor to another user
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionStopFollowing()
	{
		$this->_assertPostOnly();

		$user_id = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		$userModel = $this->_getUserModel();

		if (empty($user_id)|| !($followed = $userModel->getUserById($user_id)))
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$userModel->unfollow($followed['user_id']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/following')
		);
	}

	/**
	 * Displays the likes the visitor's content has received.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLikes()
	{
		/* @var $likeModel XenForo_Model_Like */
		$likeModel = $this->getModelFromCache('XenForo_Model_Like');

		$userId = XenForo_Visitor::getUserId();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$likes = $likeModel->getLikesForContentUser($userId, array(
			'page' => $page,
			'perPage' => $perPage
		));
		$likes = $likeModel->addContentDataToLikes($likes);

		$viewParams = array(
			'likes' => $likes,

			'totalLikes' => $likeModel->countLikesForContentUser($userId),
			'page' => $page,
			'likesPerPage' => $perPage
		);

		return $this->_getWrapper(
			'alerts', 'likes',
			$this->responseView('XenForo_ViewPublic_Account_Likes', 'account_likes', $viewParams)
		);
	}

	/**
	 * Displays a list of available account upgrades.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpgrades()
	{
		/* @var $upgradeModel XenForo_Model_UserUpgrade */
		$upgradeModel = $this->getModelFromCache('XenForo_Model_UserUpgrade');
		$purchaseList = $upgradeModel->getUserUpgradesForPurchaseList();

		if (!$purchaseList['available'] && !$purchaseList['purchased'])
		{
			return $this->responseMessage(new XenForo_Phrase('no_account_upgrades_can_be_purchased_at_this_time'));
		}

		$viewParams = array(
			'available' => $upgradeModel->prepareUserUpgrades($purchaseList['available']),
			'purchased' => $upgradeModel->prepareUserUpgrades($purchaseList['purchased']),
			//'payPalUrl' => 'https://www.sandbox.paypal.com/cgi-bin/websrc',
			'payPalUrl' => 'https://www.paypal.com/cgi-bin/websrc',
		);

		return $this->_getWrapper(
			'account', 'upgrades',
			$this->responseView('XenForo_ViewPublic_Account_Upgrades', 'account_upgrades', $viewParams)
		);
	}

	/**
	 * Displays a thank you page for purchasing an account upgrade.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpgradePurchase()
	{
		$viewParams = array();

		return $this->_getWrapper(
			'account', 'upgrades',
			$this->responseView('XenForo_ViewPublic_Account_UpgradePurchase', 'account_upgrade_purchase', $viewParams)
		);
	}

	public function actionFacebook()
	{
		$visitor = XenForo_Visitor::getInstance();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		if ($this->isConfirmedPost())
		{
			$disassociate = $this->_input->filter(array(
				'disassociate' => XenForo_Input::STRING,
				'disassociate_confirm' => XenForo_Input::STRING
			));
			if ($disassociate['disassociate'] && $disassociate['disassociate_confirm'])
			{
				XenForo_Helper_Facebook::setUidCookie(0);
				$this->getModelFromCache('XenForo_Model_UserExternal')->deleteExternalAuthAssociation(
					'facebook', $visitor['facebook_auth_id'], $visitor['user_id']
				);

				if (!$auth->hasPassword())
				{
					$this->getModelFromCache('XenForo_Model_UserConfirmation')->resetPassword($visitor['user_id']);
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account/facebook')
			);
		}
		else
		{
			if ($visitor['facebook_auth_id'])
			{
				$fbUser = XenForo_Helper_Facebook::getUserInfo('', $visitor['facebook_auth_id']);
			}
			else
			{
				$fbUser = false;
			}

			$viewParams = array(
				'fbUser' => $fbUser,
				'hasPassword' => $auth->hasPassword()
			);

			return $this->_getWrapper(
				'account', 'facebook',
				$this->responseView('XenForo_ViewPublic_Account_Facebook', 'account_facebook', $viewParams)
			);
		}
	}

	/**
	 * An action that allows users without a password to generate a new one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionRequestPassword()
	{
		$visitor = XenForo_Visitor::getInstance();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		if ($auth->hasPassword())
		{
			return $this->responseError(new XenForo_Phrase('your_account_already_has_password'));
		}

		if ($this->isConfirmedPost())
		{
			$this->getModelFromCache('XenForo_Model_UserConfirmation')->resetPassword($visitor['user_id']);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(false),
				new XenForo_Phrase('password_has_been_emailed_to_you')
			);
		}
		else
		{
			return $this->_getWrapper(
				'account', 'security',
				$this->responseView('XenForo_ViewPublic_Account_RequestPassword', 'account_request_password')
			);
		}
	}

	/**
	 * Show the most recent items for a user's news feed
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionNewsFeed()
	{
		$this->_assertNewsFeedEnabled();

		$visitor = XenForo_Visitor::getInstance();

		$newsFeedId = $this->_input->filterSingle('news_feed_id', XenForo_Input::UINT);

		return XenForo_ControllerHelper_Account::wrap(
			$this, 'alerts', 'newsFeed',
			$this->responseView(
				'XenForo_ViewPublic_NewsFeed_View',
				'news_feed_page',
				$this->_getNewsFeedModel()->getNewsFeedForUser($visitor->toArray(), $newsFeedId)
			)
		);
	}

	/**
	 * Throws a 503 error if the news feed is disabled
	 */
	protected function _assertNewsFeedEnabled()
	{
		if (!XenForo_Application::get('options')->enableNewsFeed)
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('news_feed_disabled'), 503) // 503 Service Unavailable
			);
		}
	}

	## ------------------------------------------
	##
	## Protected methods
	##

	/**
	 * Enforce registered-users only for all actions in this controller
	 *
	 * @see library/XenForo/XenForo_Controller#_preDispatch($action)
	 */
	protected function _preDispatch($action)
	{
		$this->_assertRegistrationRequired();
	}

	/**
	 * Disable CSRF checking for the upgrade purchase callback method.
	 */
	protected function _checkCsrf($action)
	{
		if (strtolower($action) == 'upgradepurchase')
		{
			// may be coming from external payment gateway
			return;
		}

		parent::_checkCsrf($action);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('managing_account_details');
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
	 * @return XenForo_Model_NewsFeed
	 */
	protected function _getNewsFeedModel()
	{
		return $this->getModelFromCache('XenForo_Model_NewsFeed');
	}

	/**
	 * Short-cut function to create a DataWriter for the visiting user and attempt to save the specified settings.
	 * If 'false' is returned, $errors will contain an array of the errors encountered.
	 *
	 * @param array $settings Array of name/value pairs to set into the DataWriter
	 * @param array $errors (reference) Container for any errors encountered
	 *
	 * @return XenForo_DataWriter_User|false
	 */
	protected function _saveVisitorSettings($settings, &$errors, $extras = array())
	{
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$writer->setExistingData(XenForo_Visitor::getUserId());
		$writer->bulkSet($settings);

		if ($writer->isChanged('email')
			&& XenForo_Application::get('options')->get('registrationSetup', 'emailConfirmation')
			&& !$writer->get('is_moderator')
			&& !$writer->get('is_admin')
		)
		{
			switch ($writer->get('user_state'))
			{
				case 'moderated':
				case 'email_confirm':
					$writer->set('user_state', 'email_confirm');
					break;

				default:
					$writer->set('user_state', 'email_confirm_edit');
			}
		}

		foreach ($extras AS $methodName => $data)
		{
			if (method_exists($writer, $methodName))
			{
				call_user_func(array($writer, $methodName), $data);
			}
		}

		$writer->preSave();

		if ($dwErrors = $writer->getErrors())
		{
			$errors = (is_array($errors) ? $dwErrors + $errors : $dwErrors);
			return false;
		}

		$writer->save();
		return $writer;
	}

	/**
	 * Gets the account pages wrapper.
	 *
	 * @param string $selectedGroup
	 * @param string $selectedLink
	 * @param XenForo_ControllerResponse_View $subView
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	protected function _getWrapper($selectedGroup, $selectedLink, XenForo_ControllerResponse_View $subView)
	{
		return $this->getHelper('Account')->getWrapper($selectedGroup, $selectedLink, $subView);
	}
}