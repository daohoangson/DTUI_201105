<?php

class XenForo_ControllerAdmin_CodeEventListener extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	/**
	 * Displays a list of all event listeners, grouped by the add-on they belong to.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$listeners = $this->_getCodeEventModel()->getEventListenersGroupedByAddOn();

		// get totals
		$totalListeners = 0;
		foreach ($listeners AS $addOnListeners)
		{
			$totalListeners += count($addOnListeners);
		}


		if (isset($listeners['']))
		{
			$customListeners = $listeners[''];
			unset($listeners['']);
		}
		else
		{
			$customListeners = array();
		}

		$viewParams = array(
			'addOns' => $this->_getAddOnModel()->getAllAddOns(),
			'listeners' => $listeners,
			'customListeners' => $customListeners,
			'totalListeners' => $totalListeners
		);

		return $this->responseView('XenForo_ViewAdmin_CodeEventListener_List', 'code_event_listener_list', $viewParams);
	}

	/**
	 * Helper to get the code event listener add/edit form controller response.
	 *
	 * @param array $listener
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getListenerAddEditResponse(array $listener)
	{
		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'listener' => $listener,
			'eventOptions' => $this->_getCodeEventModel()->getEventOptions(),
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(true, false),
			'addOnSelected' => (isset($listener['addon_id']) ? $listener['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_CodeEventListener_Edit', 'code_event_listener_edit', $viewParams);
	}

	/**
	 * Displays a form to add a code event listener.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getListenerAddEditResponse($this->_getCodeEventModel()->getDefaultEventListener());
	}

	/**
	 * Displays a form to edit a code event listener.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$eventListenerId = $this->_input->filterSingle('event_listener_id', XenForo_Input::STRING);
		$listener = $this->_getEventListenerOrError($eventListenerId);

		return $this->_getListenerAddEditResponse($listener);
	}

	/**
	 * Updates an existing event listener or inserts a new one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$eventListenerId = $this->_input->filterSingle('event_listener_id', XenForo_Input::UINT);
		$dwInput = $this->_input->filter(array(
			'event_id' => XenForo_Input::STRING,
			'execute_order' => XenForo_Input::UINT,
			'description' => XenForo_Input::STRING,
			'callback_class' => XenForo_Input::STRING,
			'callback_method' => XenForo_Input::STRING,
			'active' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_CodeEventListener');
		if ($eventListenerId)
		{
			$dw->setExistingData($eventListenerId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		$eventListenerId = $dw->get('event_listener_id');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('code-event-listeners') . $this->getLastHash($eventListenerId)
		);
	}

	/**
	 * Deletes the specififed code event listener.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_CodeEventListener', 'event_listener_id',
				XenForo_Link::buildAdminLink('code-event-listeners')
			);
		}
		else // show confirmation dialog
		{
			$eventListenerId = $this->_input->filterSingle('event_listener_id', XenForo_Input::STRING);
			$listener = $this->_getEventListenerOrError($eventListenerId);

			$viewParams = array(
				'listener' => $listener,
				'addOn' => $this->_getAddOnModel()->getAddOnById($listener['addon_id'])
			);

			return $this->responseView('XenForo_ViewAdmin_CodeEventListener_Delete', 'code_event_listener_delete', $viewParams);
		}
	}

	/**
	 * Selectively enables of disables specified code event listeners
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		$this->_assertPostOnly();

		$listenerExists = $this->_input->filterSingle('listenerExists', array(XenForo_Input::UINT, 'array' => true));
		$listeners = $this->_input->filterSingle('listener', array(XenForo_Input::UINT, 'array' => true));

		foreach ($this->_getCodeEventModel()->getAllEventListeners() AS $listenerId => $listener)
		{
			if (isset($listenerExists[$listenerId]))
			{
				$listenerActive = (isset($listeners[$listenerId]) && $listeners[$listenerId] ? 1 : 0);

				if ($listener['active'] != $listenerActive)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_CodeEventListener');
					$dw->setExistingData($listenerId);
					$dw->set('active', $listenerActive);
					$dw->save();
				}
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('code-event-listeners')
		);
	}

	/**
	 * Gets a valid code event listener or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getEventListenerOrError($id)
	{
		$info = $this->_getCodeEventModel()->getEventListenerById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_code_event_listener_not_found'), 404));
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