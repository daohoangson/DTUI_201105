<?php

/**
 * Controller to manage code events in the admin control panel.
 *
 * @package XenForo_CodeEvents
 */
class XenForo_ControllerAdmin_CodeEvent extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	/**
	 * List the currently available code events.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$viewParams = array(
			'events' => $this->_getCodeEventModel()->getAllEvents()
		);

		return $this->responseView('XenForo_ViewAdmin_CodeEvent_List', 'code_event_list', $viewParams);
	}

	public function actionDescription()
	{
		$eventId = $this->_input->filterSingle('event_id', XenForo_Input::STRING);
		$event = $this->_getCodeEventOrError($eventId);

		return $this->responseView('XenForo_ViewAdmin_CodeEvent_Description', '', array(
			'event' => $event
		));
	}

	/**
	 * Helper to get the code event add/edit form controller response.
	 *
	 * @param array $event
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getCodeEventAddEditResponse(array $event)
	{
		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'event' => $event,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(false),
			'addOnSelected' => (isset($event['addon_id']) ? $event['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_CodeEvent_Edit', 'code_event_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new code event.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getCodeEventAddEditResponse(array());
	}

	/**
	 * Displays a form to edit an existing code event.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$eventId = $this->_input->filterSingle('event_id', XenForo_Input::STRING);
		$event = $this->_getCodeEventOrError($eventId);

		return $this->_getCodeEventAddEditResponse($event);
	}

	/**
	 * Inserts a new code event or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$eventId = $this->_input->filterSingle('original_event_id', XenForo_Input::STRING);
		$dwInput = $this->_input->filter(array(
			'event_id' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'addon_id' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_CodeEvent');
		if ($eventId)
		{
			$dw->setExistingData($eventId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('code-events') . $this->getLastHash($dwInput['event_id'])
		);
	}

	/**
	 * Deletes the specififed code event.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost()) // delete item
		{
			return $this->_deleteData(
				'XenForo_DataWriter_CodeEvent', 'event_id',
				XenForo_Link::buildAdminLink('code-events')
			);
		}
		else // show confirmation dialog
		{
			$eventId = $this->_input->filterSingle('event_id', XenForo_Input::STRING);
			$event = $this->_getCodeEventOrError($eventId);

			$viewParams = array(
				'event' => $event
			);

			return $this->responseView('XenForo_ViewAdmin_CodeEvent_Delete', 'code_event_delete', $viewParams);
		}
	}

	/**
	 * Gets a valid code event or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getCodeEventOrError($id)
	{
		$info = $this->_getCodeEventModel()->getEventById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_code_event_not_found'), 404));
		}

		return $info;
	}

	/**
	 * Gets the code event model.
	 *
	 * @return XenForo_Model_CodeEvent
	 */
	protected function _getCodeEventModel()
	{
		return $this->getModelFromCache('XenForo_Model_CodeEvent');
	}

	/**
	 * Get the add-on model.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}