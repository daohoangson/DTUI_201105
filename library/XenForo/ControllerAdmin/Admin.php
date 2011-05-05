<?php

/**
 * Controller for managing admins.
 *
 * @package XenForo_Admin
 */
class XenForo_ControllerAdmin_Admin extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('user');

		if (!XenForo_Visitor::getInstance()->isSuperAdmin())
		{
			throw $this->responseException(
				$this->responseReroute('XenForo_ControllerAdmin_Error', 'errorSuperAdmin')
			);
		}
	}

	/**
	 * Displays a list of admins.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		if ($this->_input->filterSingle('user_id', XenForo_Input::UINT))
		{
			return $this->responseReroute(__CLASS__, 'edit');
		}

		$adminModel = $this->_getAdminModel();

		$viewParams = array(
			'admins' => $adminModel->prepareAdminRecords($adminModel->getAllAdmins())
		);

		return $this->responseView('XenForo_ViewAdmin_Admin_List', 'admin_list', $viewParams);
	}

	/**
	 * Gets the admin add/edit form controller response.
	 *
	 * @param array $admin
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getAdminAddEditResponse(array $admin)
	{
		$userGroupOptions = $this->getModelFromCache('XenForo_Model_UserGroup')->getUserGroupOptions(
			$admin['extra_user_group_ids']
		);

		$viewParams = array(
			'admin' => $admin,
			'permissionOptions' => $this->_getAdminModel()->getAdminPermissionOptionsForUser($admin['user_id']),
			'userGroupOptions' => $userGroupOptions
		);

		return $this->responseView('XenForo_ViewAdmin_Admin_Edit', 'admin_edit', $viewParams);
	}

	/**
	 * Displays a form to add an admin.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getAdminAddEditResponse(array(
			'user_id' => 0,
			'username' => '',
			'extra_user_group_ids' => ''
		));
	}

	/**
	 * Displays a form to edit an admin.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$admin = $this->_getAdminOrError($userId);

		return $this->_getAdminAddEditResponse($admin);
	}

	/**
	 * Updates or inserts an admin.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'user_id' => XenForo_Input::UINT,
			'username' => XenForo_Input::STRING,
			'extra_user_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'permissions' => array(XenForo_Input::STRING, 'array' => true)
		));

		if ($input['username'])
		{
			$user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($input['username']);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
			}

			$input['user_id'] = $user['user_id'];
		}

		if (!$input['user_id'])
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
		}

		$admin = $this->_getAdminModel()->getAdminById($input['user_id']);

		$adminDw = XenForo_DataWriter::create('XenForo_DataWriter_Admin');
		if ($admin)
		{
			$adminDw->setExistingData($admin);
		}
		else
		{
			$adminDw->set('user_id', $input['user_id']);
		}
		$adminDw->set('extra_user_group_ids', $input['extra_user_group_ids']);
		$adminDw->save();

		$this->_getAdminModel()->updateUserAdminPermissions($input['user_id'], $input['permissions']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('admins') . $this->getLastHash($input['user_id'])
		);
	}

	/**
	 * Admin deletion process.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$admin = $this->_getAdminOrError($userId);

		if ($this->isConfirmedPost()) // delete administrator
		{
			return $this->_deleteData(
				'XenForo_DataWriter_Admin', 'user_id',
				XenForo_Link::buildAdminLink('admins')
			);
		}
		else // show confirm dialog
		{
			$adminDw = XenForo_DataWriter::create('XenForo_DataWriter_Admin', XenForo_DataWriter::ERROR_EXCEPTION);
			$adminDw->setExistingData($admin, true);
			$adminDw->preDelete();

			$viewParams = array(
				'admin' => $admin
			);

			return $this->responseView('XenForo_ViewAdmin_Admin_Delete', 'admin_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified admin or throws an error.
	 *
	 * @param integer $id User ID
	 *
	 * @return array
	 */
	protected function _getAdminOrError($id)
	{
		return $this->_getAdminModel()->prepareAdminRecord($this->getRecordOrError(
			$id, $this->_getAdminModel(), 'getAdminById',
			'requested_admin_not_found'
		));
	}

	/**
	 * @return XenForo_Model_Admin
	 */
	protected function _getAdminModel()
	{
		return $this->getModelFromCache('XenForo_Model_Admin');
	}
}