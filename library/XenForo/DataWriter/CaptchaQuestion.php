<?php
/**
* Data writer for QA CAPTCHAs.
*
* @package XenForo_Captcha
*/
class XenForo_DataWriter_CaptchaQuestion extends XenForo_DataWriter
{
	/**
	 * Returns all xf_captcha_question fields
	 *
	 * @see XenForo_DataWriter::_getFields()
	 */
	protected function _getFields()
	{
		return array('xf_captcha_question' => array(
			'captcha_question_id'
				=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
			'question'
				=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 250),
			'answers'
				=> array('type' => self::TYPE_SERIALIZED, 'required' => true, 'verification' => array('$this', '_verifyAnswers')),
			'active'
				=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
		));
	}

	/**
	 * @see XenForo_DataWriter::_getExistingData()
	 */
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_captcha_question' => $this->_getCaptchaQuestionModel()->getCaptchaQuestionById($id));
	}

	/**
	 * @see XenForo_DataWriter::_getUpdateCondition()
	 */
	protected function _getUpdateCondition($tableName)
	{
		return 'captcha_question_id = ' . $this->_db->quote($this->getExisting('captcha_question_id'));
	}

	/**
	 * Removes any empty answers, and ensures that at least one answer remains
	 *
	 * @param string Serialized $answers
	 *
	 * @return boolean
	 */
	protected function _verifyAnswers(&$answers)
	{
		$answers = unserialize($answers);

		foreach ($answers AS $i => &$answer)
		{
			$answer = trim($answer);
			if ($answer === '')
			{
				unset($answers[$i]);
			}
		}

		if (empty($answers))
		{
			$this->error(new XenForo_Phrase('please_provide_at_least_one_answer'), 'answers');
			return false;
		}

		$answers = serialize(array_values($answers));
		return true;
	}

	/**
	 * @return XenForo_Model_CaptchaQuestion
	 */
	protected function _getCaptchaQuestionModel()
	{
		return $this->getModelFromCache('XenForo_Model_CaptchaQuestion');
	}
}