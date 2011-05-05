<?php

/**
 * Controller for trophies in the admin control panel.
 *
 * @package XenForo_Trophy
 */
class XenForo_ControllerAdmin_Trophy extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('trophy');
	}

	/**
	 * Lists all available trophies, grouped by level.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$trophyModel = $this->_getTrophyModel();

		$viewParams = array(
			'trophies' => $trophyModel->prepareTrophies($trophyModel->getAllTrophies())
		);

		return $this->responseView('XenForo_ViewAdmin_Trophy_List', 'trophy_list', $viewParams);
	}

	/**
	 * Gets the add/edit form response for a trophy.
	 *
	 * @param array $trophy
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getTrophyAddEditResponse(array $trophy)
	{
		$trophyModel = $this->_getTrophyModel();

		$viewParams = array(
			'trophy' => $trophy,
			'masterTitle' => $trophyModel->getTrophyMasterTitlePhraseValue($trophy['trophy_id']),
			'masterDescription' => $trophyModel->getTrophyMasterDescriptionPhraseValue($trophy['trophy_id']),
			'criteria' => XenForo_Helper_Criteria::prepareCriteriaForSelection($trophy['criteria'])
		);

		return $this->responseView('XenForo_ViewAdmin_Trophy_Edit', 'trophy_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new trophy.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getTrophyAddEditResponse($this->_getTrophyModel()->getDefaultTrophy());
	}

	/**
	 * Displays a form to edit an existing trophy.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$trophyId = $this->_input->filterSingle('trophy_id', XenForo_Input::UINT);
		$trophy = $this->_getTrophyOrError($trophyId);

		return $this->_getTrophyAddEditResponse($trophy);
	}

	/**
	 * Inserts a new trophy or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$trophyId = $this->_input->filterSingle('trophy_id', XenForo_Input::UINT);
		$dwData = $this->_input->filter(array(
			'trophy_points' => XenForo_Input::UINT,
			'criteria' => XenForo_Input::ARRAY_SIMPLE
		));
		$titlePhrase = $this->_input->filterSingle('title', XenForo_Input::STRING);
		$descriptionPhrase = $this->_input->filterSingle('description', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Trophy');
		if ($trophyId)
		{
			$dw->setExistingData($trophyId);
		}
		$dw->bulkSet($dwData);
		$dw->setExtraData(XenForo_DataWriter_Trophy::DATA_TITLE, $titlePhrase);
		$dw->setExtraData(XenForo_DataWriter_Trophy::DATA_DESCRIPTION, $descriptionPhrase);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('trophies') . $this->getLastHash($trophyId)
		);
	}

	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_Trophy', 'trophy_id',
				XenForo_Link::buildAdminLink('trophies')
			);
		}
		else
		{
			$trophyId = $this->_input->filterSingle('trophy_id', XenForo_Input::UINT);
			$trophy = $this->_getTrophyOrError($trophyId);

			$viewParams = array(
				'trophy' => $trophy
			);

			return $this->responseView('XenForo_ViewAdmin_Trophy_Delete', 'trophy_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified trophy or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getTrophyOrError($id)
	{
		$trophyModel = $this->_getTrophyModel();

		return $trophyModel->prepareTrophy(
			$this->getRecordOrError(
				$id, $trophyModel, 'getTrophyById',
				'requested_trophy_not_found'
			)
		);
	}

	/**
	 * @return XenForo_Model_Trophy
	 */
	protected function _getTrophyModel()
	{
		return $this->getModelFromCache('XenForo_Model_Trophy');
	}
}