<?php

/**
 * Controller for admin navigation tasks the admin control panel.
 *
 * @package XenForo_AdminNavigation
 */
class XenForo_ControllerAdmin_AdminNavigation extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	/**
	 * Display a tree of admin navigation entries.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$navigationModel = $this->_getAdminNavigationModel();

		$viewParams = array(
			'navigation' => $navigationModel->prepareAdminNavigationEntries($navigationModel->getAdminNavigationInOrder())
		);

		return $this->responseView('XenForo_ViewAdmin_AdminNavigation_List', 'admin_navigation_list', $viewParams);
	}

	/**
	 * Gets the controller response for adding/editing a navigation entry.
	 *
	 * @param array $navigation
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getNavigationAddEditResponse(array $navigation)
	{
		$addOnModel = $this->_getAddOnModel();
		$navigationModel = $this->_getAdminNavigationModel();

		$viewParams = array(
			'navigation' => $navigation,
			'navigationOptions' => $navigationModel->getAdminNavigationOptions(),
			'masterTitle' => $navigationModel->getAdminNavigationMasterTitlePhraseValue($navigation['navigation_id']),
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($navigation['addon_id']) ? $navigation['addon_id'] : $addOnModel->getDefaultAddOnId()),
			'adminPermissionOptions' => $this->getModelFromCache('XenForo_Model_Admin')->getAdminPermissionPairs()
		);

		return $this->responseView('XenForo_ViewAdmin_AdminNavigation_Edit', 'admin_navigation_edit', $viewParams);
	}

	/**
	 * Displays a form to add a navigation entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$navigation = array(
			'navigation_id' => '',
			'parent_navigation_id' => $this->_input->filterSingle('parent', XenForo_Input::STRING),
			'display_order' => 1,
			'debug_only' => 0,
			'hide_no_children' => 0
		);
		return $this->_getNavigationAddEditResponse($navigation);
	}

	/**
	 * Displays a form to edit a navigation entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$navigationId = $this->_input->filterSingle('navigation_id', XenForo_Input::STRING);
		$navigation = $this->_getAdminNavigationOrError($navigationId);

		return $this->_getNavigationAddEditResponse($navigation);
	}

	/**
	 * Updates or inserts a navigation entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$navigationId = $this->_input->filterSingle('navigation_id', XenForo_Input::STRING);
		$newNavigationId = $this->_input->filterSingle('new_navigation_id', XenForo_Input::STRING);
		$dwInput = $this->_input->filter(array(
			'parent_navigation_id' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'link' => XenForo_Input::STRING,
			'admin_permission_id' => XenForo_Input::STRING,
			'debug_only' => XenForo_Input::UINT,
			'hide_no_children' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));
		$titlePhrase = $this->_input->filterSingle('title', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminNavigation');
		if ($navigationId)
		{
			$dw->setExistingData($navigationId);
		}
		$dw->set('navigation_id', $newNavigationId);
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_AdminNavigation::DATA_TITLE, $titlePhrase);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('admin-navigation') . $this->getLastHash($newNavigationId)
		);
	}

	/**
	 * Deletes a navigation entry.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_AdminNavigation', 'navigation_id',
				XenForo_Link::buildAdminLink('admin-navigation')
			);
		}
		else // show confirmation dialog
		{
			$navigationId = $this->_input->filterSingle('navigation_id', XenForo_Input::STRING);
			$navigation = $this->_getAdminNavigationOrError($navigationId);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminNavigation', XenForo_DataWriter::ERROR_EXCEPTION);
			$dw->setExistingData($navigation, true);
			$dw->preDelete();

			$viewParams = array(
				'navigation' => $navigation
			);

			return $this->responseView(
				'XenForo_ViewAdmin_AdminNavigation_Delete',
				'admin_navigation_delete', $viewParams
			);
		}
	}

	/**
	 * Gets the specified navigation entry or errors.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getAdminNavigationOrError($id)
	{
		$info = $this->_getAdminNavigationModel()->getAdminNavigationEntryById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_admin_navigation_entry_not_found'), 404));
		}

		return $this->_getAdminNavigationModel()->prepareAdminNavigationEntry($info);
	}

	/**
	 * @return XenForo_Model_AdminNavigation
	 */
	protected function _getAdminNavigationModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminNavigation');
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