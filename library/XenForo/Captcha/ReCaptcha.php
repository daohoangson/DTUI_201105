<?php

/**
 * Implementation for ReCAPTCHA. Uses a global key by default.
 *
 * @package XenForo_Captcha
 */
class XenForo_Captcha_ReCaptcha extends XenForo_Captcha_Abstract
{
	/**
	 * Configuration of public and private keys.
	 *
	 * @var array
	 */
	protected $_config = array(
		'publicKey' => '6LeQEL0SAAAAADlCM4jr62ge9LGlF54uTbWh0NBT',
		'privateKey' => '6LeQEL0SAAAAAD0CVWDANuDlJK73cHTPWaHrkRo2'
	);

	/**
	 * Constructor.
	 *
	 * @param array|null $config
	 */
	public function __construct(array $config = null)
	{
		if ($config)
		{
			$this->_config = array_merge($this->_config, $config);
		}
	}

	/**
	 * Determines if CAPTCHA is valid (passed).
	 *
	 * @see XenForo_Captcha_Abstract::isValid()
	 */
	public function isValid(array $input)
	{
		if (!$this->_config['privateKey'] || !$this->_config['publicKey'])
		{
			return true; // if not configured, always pass
		}

		if (empty($input['recaptcha_challenge_field']) || empty($input['recaptcha_response_field']))
		{
			return false;
		}

		try
		{
			$recaptcha = new Zend_Service_ReCaptcha($this->_config['publicKey'], $this->_config['privateKey']);
			$result = $recaptcha->verify($input['recaptcha_challenge_field'], $input['recaptcha_response_field']);
			return $result->isValid();
		}
		catch (Zend_Http_Client_Adapter_Exception $e)
		{
			// this is an exception with the underlying request, so let it go through
			XenForo_Error::logException($e, false);
			return true;
		}
	}

	/**
	 * Renders the CAPTCHA template.
	 *
	 * @see XenForo_Captcha_Abstract::renderInternal()
	 */
	public function renderInternal(XenForo_View $view)
	{
		if (!$this->_config['publicKey'])
		{
			return '';
		}

		return $view->createTemplateObject('captcha_recaptcha', array(
			'publicKey' => $this->_config['publicKey']
		));
	}
}