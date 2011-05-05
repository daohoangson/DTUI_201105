<?php

/**
 * Controller to manage smilies in the admin control panel.
 *
 * @package XenForo_Smilie
 */
class XenForo_ControllerAdmin_Smilie extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	/**
	 * Displays a list of smilies.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionIndex()
	{
		$smilies = $this->_getSmilieModel()->getAllSmilies();

		$viewParams = array(
			'smilies' => $this->_getSmilieModel()->prepareSmiliesForList($smilies)
		);

		return $this->responseView('XenForo_ViewAdmin_Smilie_List', 'smilie_list', $viewParams);
	}

	/**
	 * Displays a form to add a smilie.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionAdd()
	{
		$viewParams = array(
			'smilie' => array()
		);
		return $this->responseView('XenForo_ViewAdmin_Smilie_Edit', 'smilie_edit', $viewParams);
	}

	/**
	 * Displays a form to edit an existing smilie.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionEdit()
	{
		$smilieId = $this->_input->filterSingle('smilie_id', XenForo_Input::UINT);
		$smilie = $this->_getSmilieOrError($smilieId);

		$viewParams = array(
			'smilie' => $smilie
		);
		return $this->responseView('XenForo_ViewAdmin_Smilie_Edit', 'smilie_edit', $viewParams);
	}

	/**
	 * Adds a new smilie or updates an existing one.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$smilieId = $this->_input->filterSingle('smilie_id', XenForo_Input::UINT);
		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'smilie_text' => XenForo_Input::STRING,
			'image_url' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Smilie');
		if ($smilieId)
		{
			$dw->setExistingData($smilieId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('smilies') . $this->getLastHash($smilieId)
		);
	}

	/**
	 * Validates the specified smilie field.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionValidateField()
	{
		$this->_assertPostOnly();

		return $this->_validateField('XenForo_DataWriter_Smilie', array(
			'existingDataKey' => $this->_input->filterSingle('smilie_id', XenForo_Input::INT)
		));
	}

	/**
	 * Deletes the specified smilie.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_Smilie', 'smilie_id',
				XenForo_Link::buildAdminLink('smilies')
			);
		}
		else
		{
			$smilieId = $this->_input->filterSingle('smilie_id', XenForo_Input::UINT);
			$smilie = $this->_getSmilieOrError($smilieId);

			$viewParams = array(
				'smilie' => $smilie
			);
			return $this->responseView('XenForo_ViewAdmin_Smilie_Delete', 'smilie_delete', $viewParams);
		}
	}

	/**
	 * Gets a valid smilie or throws an exception.
	 *
	 * @param string $smilieId
	 *
	 * @return array
	 */
	protected function _getSmilieOrError($smilieId)
	{
		$info = $this->_getSmilieModel()->getSmilieById($smilieId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_smilie_not_found'), 404));
		}

		return $info;
	}

	/**
	 * @return XenForo_Model_Smilie
	 */
	protected function _getSmilieModel()
	{
		return $this->getModelFromCache('XenForo_Model_Smilie');
	}
}