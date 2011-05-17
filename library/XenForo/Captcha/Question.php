<?php

/**
 * Implementation for Question & Answer CAPTCHA.
 *
 * @package XenForo_Captcha
 */
class XenForo_Captcha_Question extends XenForo_Captcha_Abstract
{
	/**
	 * Determines if CAPTCHA is valid (passed).
	 *
	 * @see XenForo_Captcha_Abstract::isValid()
	 */
	public function isValid(array $input)
	{
		$cleaner = new XenForo_Input($input);

		$answer = $cleaner->filterSingle('captcha_question_answer', XenForo_Input::STRING);
		$hash = $cleaner->filterSingle('captcha_question_hash', XenForo_Input::STRING);

		return XenForo_Model_CaptchaQuestion::isCorrect($answer, $hash);
	}

	/**
	 * Renders the CAPTCHA template.
	 *
	 * @see XenForo_Captcha_Abstract::renderInternal()
	 */
	public function renderInternal(XenForo_View $view)
	{
		return $view->createTemplateObject('captcha_question', array(
			'captchaQuestion' => XenForo_Model_CaptchaQuestion::getQuestion()
		));
	}
}