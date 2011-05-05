<?php

/**
 * Abstract base for CAPTCHA implementations.
 *
 * @package XenForo_Captcha
 */
abstract class XenForo_Captcha_Abstract
{
	/**
	 * Rendered output cache.
	 *
	 * @var XenForo_Template_Abstract|string|null
	 */
	protected $_rendered = null;

	/**
	 * Determines if the CAPTCHA has been passed.
	 *
	 * @param array $input Set of input to validate against
	 *
	 * @return boolean
	 */
	abstract public function isValid(array $input);

	/**
	 * Renders the CAPTCHA for use in a template. This should only render the CAPTCHA area itself.
	 * The CAPTCHA may be used in a form row or own its own.
	 *
	 * @param XenForo_View $view
	 *
	 * @return XenForo_Template_Abstract|string
	 */
	abstract public function renderInternal(XenForo_View $view);

	public function render(XenForo_View $view)
	{
		if ($this->_rendered === null)
		{
			$this->_rendered = $this->renderInternal($view);
		}

		return $this->_rendered;
	}

	/**
	 * To string method to warn if the CAPTCHA hasn't been rendered properly.
	 *
	 * @return string
	 */
	public final function __toString()
	{
		if ($this->_rendered === null)
		{
			return 'Please call render() on this CAPTCHA object in the view.';
		}
		else
		{
			return (string)$this->_rendered;
		}
	}

	/**
	 * Creates the default CAPTCHA handler. By default, this checks whether a CAPTCHA is needed
	 * for the visiting user. Note that you can never guarantee that this function will return an object!
	 *
	 * @param boolean $alwaysCreate If false, creates if visiting user needs; otherwise, creates the default object if configured
	 *
	 * @return XenForo_Captcha_Abstract|false
	 */
	public static function createDefault($alwaysCreate = false)
	{
		if (!$alwaysCreate && !XenForo_Visitor::getInstance()->showCaptcha())
		{
			return false;
		}

		$captchaType = XenForo_Application::get('options')->captcha;

		if (!$captchaType)
		{
			return false;
		}

		$class = "XenForo_Captcha_" . $captchaType;

		return new $class();
	}

	/**
	 * Validates the default CAPTCHA handler.
	 *
	 * @param array|XenForo_Input $input Input to check
	 * @param boolean $alwaysCheck If true, always checks; if false, checks if visiting user needs
	 *
	 * @return boolean
	 */
	public static function validateDefault($input, $alwaysCheck = false)
	{
		if (!$alwaysCheck && !XenForo_Visitor::getInstance()->showCaptcha())
		{
			return true;
		}

		if ($input instanceof XenForo_Input)
		{
			$input = $input->getInput();
		}

		if (!is_array($input))
		{
			throw new XenForo_Exception('Input must be an array or XenForo_Input object.');
		}

		$captcha = self::createDefault(true);
		if (!$captcha)
		{
			return true;
		}

		return $captcha->isValid($input);
	}
}