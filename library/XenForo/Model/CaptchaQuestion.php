<?php

class XenForo_Model_CaptchaQuestion extends XenForo_Model
{
	/**
	 * Returns a single QA CAPTCHA record
	 *
	 * @param integer $captchaQuestionId
	 *
	 * @return array
	 */
	public function getCaptchaQuestionById($captchaQuestionId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_captcha_question
			WHERE captcha_question_id = ?
		', $captchaQuestionId);
	}

	/**
	 * Returns a collection of QA CAPTCHAs
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getCaptchaQuestions(array $conditions = array(), array $fetchOptions = array())
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_captcha_question
			ORDER BY question
		', 'captcha_question_id');
	}

	/**
	 * Returns a collection of all QA CAPTCHAs
	 *
	 * @return array
	 */
	public function getAllCaptchaQuestions()
	{
		return $this->getCaptchaQuestions();
	}

	/**
	 * Prepares a collection of QA CAPTCHAs for use
	 *
	 * @param array $captchaQuestions
	 *
	 * @return array
	 */
	public function prepareCaptchaQuestions(array $captchaQuestions)
	{
		foreach ($captchaQuestions AS &$captchaQuestion)
		{
			$captchaQuestion = $this->prepareCaptchaQuestion($captchaQuestion);
		}

		return $captchaQuestions;
	}

	/**
	 * Prepares a single QA CAPTCHA for use
	 *
	 * @param array $captchaQuestion
	 *
	 * @return array $captchaQuestion
	 */
	public function prepareCaptchaQuestion(array $captchaQuestion)
	{
		$captchaQuestion = $this->_prepareCaptchaQuestionAnswersArray($captchaQuestion);

		return $captchaQuestion;
	}

	/**
	 * Creates the 'answers_array' entry from the existing 'answers'
	 * entry in the given QA CAPTCHA record
	 *
	 * @param array $captchaQuestion
	 *
	 * @return array
	 */
	protected function _prepareCaptchaQuestionAnswersArray(array $captchaQuestion)
	{
		$answersArray = unserialize($captchaQuestion['answers']);
		if (empty($answersArray))
		{
			$answersArray = array('');
		}

		$captchaQuestion['answersArray'] = $answersArray;

		return $captchaQuestion;
	}

	/**
	 * Checks that the provided answer matches one of the acceptable answers from the QA CAPTCHA.
	 * Answers are case-insensitive.
	 *
	 * @param string $answer
	 * @param integer $captchaQuestionId
	 *
	 * @return boolean
	 */
	public function checkAnswer($answer, $captchaQuestionId)
	{
		if ($answer !== '')
		{
			$captchaQuestion = $this->getCaptchaQuestionById($captchaQuestionId);

			if ($captchaQuestion)
			{
				$captchaQuestion = $this->prepareCaptchaQuestion($captchaQuestion);

				if (in_array(strtolower($answer), array_map('strtolower', $captchaQuestion['answersArray'])))
				{
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Fetches and prepares a random QA CAPTCHA
	 *
	 * @return array|false
	 */
	public function getRandomCaptchaQuestion()
	{
		$captchaQuestion = $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_captcha_question
			WHERE active = 1
			ORDER BY RAND()
		');
		if (!$captchaQuestion)
		{
			return false;
		}

		return $this->prepareCaptchaQuestion($captchaQuestion);
	}

	/**
	 * Removes all CAPTCHAs that are older than the specified expiry length.
	 *
	 * @param integer $expiry Delete CAPTCHAs older than this (in seconds)
	 */
	public function deleteOldCaptchas($expiry = 86400)
	{
		$this->_getDb()->delete('xf_captcha_log', 'captcha_date < ' . (XenForo_Application::$time - $expiry));
	}

	/**
	 * Inserts a hash into the xf_captcha_log table,
	 * and adds the hash to the returned $captcha array.
	 *
	 * @param integer
	 *
	 * @return string The inserted hash
	 */
	protected function _insertHash($questionId)
	{
		$hash = sha1(
			'Question'
			. $questionId
			. XenForo_Application::get('config')->globalSalt
			. uniqid(microtime(), true)
		);

		$this->_getDb()->insert('xf_captcha_log', array(
			'hash' => $hash,
			'captcha_type' => 'Question',
			'captcha_data' => $questionId,
			'captcha_date' => XenForo_Application::$time
		));

		return $hash;
	}

	/**
	 * Static version of checkAnswer()
	 *
	 * @see XenForo_Model_CaptchaQuestion::checkAnswer()
	 */
	public static function isCorrect($answer, $questionHash)
	{
		$model = XenForo_Model::create(__CLASS__);
		$db = $model->_getDb();

		if ($log = $db->fetchRow('SELECT * FROM xf_captcha_log WHERE hash = ?', $questionHash))
		{
			if ($db->delete('xf_captcha_log', 'hash = ' . $db->quote($questionHash)))
			{
				if (strval($log['captcha_data']) === '0')
				{
					// we didn't have a question, so always pass
					return true;
				}

				return $model->checkAnswer($answer, $log['captcha_data']);
			}
		}

		return false;
	}

	/**
	 * Gets the a random question and inserts it into the captcha log.
	 *
	 * @return array
	 */
	public static function getQuestion()
	{
		$model = XenForo_Model::create(__CLASS__);

		$question = $model->getRandomCaptchaQuestion();
		if (!$question)
		{
			$question = array(
				'captcha_question_id' => 0,
				'question' => ''
			);
		}

		$question['hash'] = $model->_insertHash($question['captcha_question_id']);
		return $question;
	}
}