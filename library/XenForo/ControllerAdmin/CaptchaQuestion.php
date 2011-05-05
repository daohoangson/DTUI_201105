<?php

class XenForo_ControllerAdmin_CaptchaQuestion extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('option');
	}

	public function actionIndex()
	{
		$captchaQuestionModel = $this->_getCaptchaQuestionModel();

		$captchaQuestions = $captchaQuestionModel->getAllCaptchaQuestions();

		$viewParams = array(
			'captchaQuestions' => $captchaQuestionModel->prepareCaptchaQuestions($captchaQuestions)
		);

		return $this->responseView('XenForo_ViewAdmin_CaptchaQuestion_List', 'captcha_question_list', $viewParams);
	}

	public function actionAdd()
	{
		$viewParams = array('captchaQuestion' => array(
			'answersArray' => array(''),
			'active' => true
		));

		return $this->responseView('XenForo_ViewAdmin_CaptchaQuestion_Add', 'captcha_question_edit', $viewParams);
	}

	public function actionEdit()
	{
		$captchaQuestionId = $this->_input->filterSingle('captcha_question_id', XenForo_Input::UINT);
		$captchaQuestion = $this->_getCaptchaQuestionOrError($captchaQuestionId);

		$viewParams = array(
			'captchaQuestion' => $this->_getCaptchaQuestionModel()->prepareCaptchaQuestion($captchaQuestion)
		);

		return $this->responseView('XenForo_ViewAdmin_CaptchaQuestion_Edit', 'captcha_question_edit', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		$captchaQuestionId = $this->_input->filterSingle('captcha_question_id', XenForo_Input::UINT);

		$data = $this->_input->filter(array(
			'question' => XenForo_Input::STRING,
			'answers' => array(XenForo_Input::STRING, 'array' => true),
			'active' => XenForo_Input::UINT,
		));

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_CaptchaQuestion');

		if ($captchaQuestionId)
		{
			$writer->setExistingData($captchaQuestionId);
		}

		$writer->bulkSet($data);
		$writer->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('captcha-questions') . $this->getLastHash($writer->get('captcha_question_id'))
		);
	}

	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_CaptchaQuestion', 'captcha_question_id',
				XenForo_Link::buildAdminLink('captcha-questions')
			);
		}
		else
		{
			$captchaQuestionId = $this->_input->filterSingle('captcha_question_id', XenForo_Input::UINT);
			$captchaQuestion = $this->_getCaptchaQuestionOrError($captchaQuestionId);

			$viewParams = array(
				'captchaQuestion' => $this->_getCaptchaQuestionModel()->prepareCaptchaQuestion($captchaQuestion)
			);
			return $this->responseView('XenForo_ViewAdmin_CaptchaQuestion_Delete', 'captcha_question_delete', $viewParams);
		}
	}

	protected function _getCaptchaQuestionOrError($captchaQuestionId)
	{
		$captchaQuestion = $this->_getCaptchaQuestionModel()->getCaptchaQuestionById($captchaQuestionId);
		if (!$captchaQuestion)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_captcha_question_not_found'), 404));
		}

		return $captchaQuestion;

	}

	/**
	 * @return XenForo_Model_CaptchaQuestion
	 */
	protected function _getCaptchaQuestionModel()
	{
		return $this->getModelFromCache('XenForo_Model_CaptchaQuestion');
	}
}