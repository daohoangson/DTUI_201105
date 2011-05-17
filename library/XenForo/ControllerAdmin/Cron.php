<?php

class XenForo_ControllerAdmin_Cron extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('cron');
	}

	/**
	 * Displays a list of cron entries.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$cronModel = $this->_getCronModel();

		$viewParams = array(
			'entries' => $cronModel->prepareCronEntries($cronModel->getAllCronEntries())
		);

		return $this->responseView('XenForo_ViewAdmin_Cron_List', 'cron_list', $viewParams);
	}

	/**
	 * Gets the cron entry add/edit form response.
	 *
	 * @param array $entry
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getCronEntryAddEditResponse(array $entry)
	{
		if (!empty($entry['entry_id']))
		{
			$masterTitle = $this->_getCronModel()->getCronEntryMasterTitlePhraseValue($entry['entry_id']);
		}
		else
		{
			$masterTitle = '';
		}

		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'entry' => $entry,
			'masterTitle' => $masterTitle,
			'addOnOptions' => $this->_getAddOnModel()->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($entry['addon_id']) ? $entry['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Cron_Edit', 'cron_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new cron entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getCronEntryAddEditResponse($this->_getCronModel()->getDefaultCronEntry());
	}

	/**
	 * Displays a form to edit an existing cron entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$entryId = $this->_input->filterSingle('entry_id', XenForo_Input::STRING);
		$entry = $this->_getCronEntryOrError($entryId);

		return $this->_getCronEntryAddEditResponse($entry);
	}

	/**
	 * Inserts a new cron entry or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$originalEntryId = $this->_input->filterSingle('original_entry_id', XenForo_Input::STRING);
		$dwData = $this->_input->filter(array(
			'entry_id' => XenForo_Input::STRING,
			'cron_class' => XenForo_Input::STRING,
			'cron_method' => XenForo_Input::STRING,
			'run_rules' => XenForo_Input::ARRAY_SIMPLE,
			'active' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));
		$titlePhrase = $this->_input->filterSingle('title', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_CronEntry');
		if ($originalEntryId)
		{
			$dw->setExistingData($originalEntryId);
		}
		$dw->bulkSet($dwData);
		$dw->setExtraData(XenForo_DataWriter_CronEntry::DATA_TITLE, $titlePhrase);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('cron') . $this->getLastHash($dwData['entry_id'])
		);
	}

	/**
	 * Deletes a cron entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		// TODO: need to prevent cron entries from being deleted that belong to add-ons in non-debug mode

		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_CronEntry', 'entry_id',
				XenForo_Link::buildAdminLink('cron')
			);
		}
		else
		{
			$entryId = $this->_input->filterSingle('entry_id', XenForo_Input::STRING);
			$entry = $this->_getCronEntryOrError($entryId);

			$viewParams = array(
				'entry' => $entry
			);

			return $this->responseView('XenForo_ViewAdmin_Cron_Delete', 'cron_delete', $viewParams);
		}
	}

	/**
	 * Helper to switch the active state for an cron entry and get the controller response.
	 *
	 * @param string $entryId Cron entry ID
	 * @param integer $activeState O or 1
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _switchCronEntryActiveStateAndGetResponse($entryId, $activeState)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_CronEntry');
		$dw->setExistingData($entryId);
		$dw->set('active', $activeState);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('cron')
		);
	}

	/**
	 * Enables the specified cron entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEnable()
	{
		// can be requested over GET, so check for the token manually
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$entryId = $this->_input->filterSingle('entry_id', XenForo_Input::STRING);
		return $this->_switchCronEntryActiveStateAndGetResponse($entryId, 1);
	}

	/**
	 * Disables the specified cron entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDisable()
	{
		// can be requested over GET, so check for the token manually
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$entryId = $this->_input->filterSingle('entry_id', XenForo_Input::STRING);
		return $this->_switchCronEntryActiveStateAndGetResponse($entryId, 0);
	}

	/**
	 * Runs a cron entry. This does not relate to the next run time.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionRun()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$entryId = $this->_input->filterSingle('entry_id', XenForo_Input::STRING);
		$entry = $this->_getCronEntryOrError($entryId);

		$this->_getCronModel()->runEntry($entry); // TODO: capture output or something more useful

		return $this->responseMessage(new XenForo_Phrase('cron_entry_run_successfully'));
	}

	/**
	 * Gets the specified cron entry or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getCronEntryOrError($id)
	{
		$cronModel = $this->_getCronModel();

		return $cronModel->prepareCronEntry(
			$this->getRecordOrError(
				$id, $cronModel, 'getCronEntryById',
				'requested_cron_entry_not_found'
			)
		);
	}

	/**
	 * @return XenForo_Model_Cron
	 */
	protected function _getCronModel()
	{
		return $this->getModelFromCache('XenForo_Model_Cron');
	}

	/**
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}