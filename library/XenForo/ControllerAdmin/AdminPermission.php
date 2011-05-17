<?php

/**
 * Controller for managing admin permissions.
 *
 * @package XenForo_Admin
 */
class XenForo_ControllerAdmin_AdminPermission extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	/**
	 * Displays a list of admin permissions.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$adminModel = $this->_getAdminModel();
		$permissions =  $adminModel->prepareAdminPermissions($adminModel->getAllAdminPermissions());

		$viewParams = array(
			'adminPermissions' => $permissions
		);

		return $this->responseView('XenForo_ViewAdmin_AdminPermission_List', 'admin_permission_list', $viewParams);
	}

	/**
	 * Gets the response for the permission add/edit form.
	 *
	 * @param array $adminPermission
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getAdminPermissionAddEditResponse(array $adminPermission)
	{
		$addOnModel = $this->_getAddOnModel();

		$masterTitle = $this->_getAdminModel()->getAdminPermissionMasterTitlePhraseValue(
			$adminPermission['admin_permission_id']
		);

		$viewParams = array(
			'adminPermission' => $this->_getAdminModel()->prepareAdminPermission($adminPermission),
			'masterTitle' => $masterTitle,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($adminPermission['addon_id']) ? $adminPermission['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_AdminPemission_Edit', 'admin_permission_edit', $viewParams);
	}

	/**
	 * Displays a form to add an admin permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getAdminPermissionAddEditResponse(array(
			'admin_permission_id' => '',
			'display_order' => 1,
			'addon_id' => null
		));
	}

	/**
	 * Displays a form to edit an admin permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$adminPermissionId = $this->_input->filterSingle('admin_permission_id', XenForo_Input::STRING);

		$adminPermission = $this->_getAdminModel()->getAdminPermissionById($adminPermissionId);
		if (!$adminPermission)
		{
			return $this->responseError(new XenForo_Phrase('requested_permission_not_found'), 404);
		}

		return $this->_getAdminPermissionAddEditResponse($adminPermission);
	}

	/**
	 * Inserts or updates an admin permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'admin_permission_id' => XenForo_Input::STRING,
			'new_admin_permission_id' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminPermission');
		if ($input['admin_permission_id'])
		{
			$dw->setExistingData($input['admin_permission_id']);
		}
		$dw->setExtraData(XenForo_DataWriter_AdminPermission::DATA_TITLE, $input['title']);
		$dw->bulkSet(array(
			'admin_permission_id' => $input['new_admin_permission_id'],
			'display_order' => $input['display_order'],
			'addon_id' => $input['addon_id']
		));
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('admin-permissions') . $this->getLastHash($input['new_admin_permission_id'])
		);
	}

	/**
	 * Deletes an admin permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_AdminPermission', 'admin_permission_id',
				XenForo_Link::buildAdminLink('admin-permissions')
			);
		}
		else // show confirmation dialog
		{
			$adminPermissionId = $this->_input->filterSingle('admin_permission_id', XenForo_Input::STRING);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminPermission', XenForo_DataWriter::ERROR_EXCEPTION);
			$dw->setExistingData($adminPermissionId);
			$dw->preDelete();

			$viewParams = array(
				'adminPermission' => $this->_getAdminModel()->prepareAdminPermission($dw->getMergedData())
			);

			return $this->responseView('XenForo_ViewAdmin_AdminPermission_Delete', 'admin_permission_delete', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_Admin
	 */
	protected function _getAdminModel()
	{
		return $this->getModelFromCache('XenForo_Model_Admin');
	}

	/**
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}