<?php

/**
 * Controller to manage the permission splash page and other actions that deal with
 * permissions themselves (editing permission definitions, etc).
 *
 * @package XenForo_Permissions
 */
class XenForo_ControllerAdmin_Permission extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		if (strtolower($action) != 'test')
		{
			$this->assertDebugMode();
		}
	}

	public function actionTest()
	{
		$this->assertAdminPermission('user');

		$publicSession = new XenForo_Session();
		$publicSession->start();
		if ($publicSession->get('user_id') != XenForo_Visitor::getUserId())
		{
			return $this->responseError(new XenForo_Phrase('please_login_via_public_login_page_before_testing_permissions'));
		}

		if ($this->_request->isPost())
		{
			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);

			/* @var $userModel XenForo_Model_User */
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			$user = $userModel->getUserByName($username);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
			}

			$publicSession->set('permissionTest', array(
				'user_id' => $user['user_id'],
				'username' => $user['username']
			));
			$publicSession->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('index')
			);
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_Permission_Test', 'permission_test');
		}
	}

	/**
	 * Shows the permission, permission group, and interface group definitions.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDefinitions()
	{
		$permissionModel = $this->_getPermissionModel();

		$permissionGroups = $permissionModel->preparePermissionGroups($permissionModel->getAllPermissionGroups());
		$interfaceGroups = $permissionModel->preparePermissionInterfaceGroups($permissionModel->getAllPermissionInterfaceGroups());

		$permissions = $permissionModel->preparePermissions($permissionModel->getAllPermissions());
		$permissionsGrouped = array();
		$permissionsUngrouped = array();
		foreach ($permissions AS $permission)
		{
			if (isset($interfaceGroups[$permission['interface_group_id']]))
			{
				$permissionsGrouped[$permission['interface_group_id']][] = $permission;
			}
			else
			{
				$permissionsUngrouped[] = $permission;
			}
		}

		$viewParams = array(
			'permissionGroups' => $permissionGroups,
			'permissionsGrouped' => $permissionsGrouped,
			'permissionsUngrouped' => $permissionsUngrouped,
			'interfaceGroups' => $interfaceGroups,
			'totalPermissions' => count($permissions),
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_Definition', 'permission_definition', $viewParams);
	}

	/**
	 * Helper function to handle displaying the permission add/edit form.
	 *
	 * @param array $permission Array of information about the permission being editor (or the default set if adding)
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getPermissionAddEditResponse(array $permission)
	{
		$permissionModel = $this->_getPermissionModel();
		$addOnModel = $this->_getAddOnModel();

		$masterTitle = $permissionModel->getPermissionMasterTitlePhraseValue(
			$permission['permission_group_id'], $permission['permission_id']
		);

		$viewParams = array(
			'permission' => $permission,
			'masterTitle' => $masterTitle,
			'permissionGroups' => $permissionModel->getPermissionGroupNames(),
			'interfaceGroups' => $permissionModel->getPermissionInterfaceGroupNames(),
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($permission['addon_id']) ? $permission['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_PermissionEdit', 'permission_permission_edit', $viewParams);
	}

	/**
	 * Form to create a new permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionAdd()
	{
		return $this->_getPermissionAddEditResponse($this->_getPermissionModel()->getDefaultPermission());
	}

	/**
	 * Form to edit an existing permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionEdit()
	{
		$permissionGroupId = $this->_input->filterSingle('permission_group_id', XenForo_Input::STRING);
		$permissionId = $this->_input->filterSingle('permission_id', XenForo_Input::STRING);

		$permission = $this->_getValidPermissionOrError($permissionGroupId, $permissionId);
		return $this->_getPermissionAddEditResponse($permission);
	}

	/**
	 * Inserts a new permission or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionSave()
	{
		$this->_assertPostOnly();

		$originalPermissionId = $this->_input->filterSingle('original_permission_id', XenForo_Input::STRING);
		$originalPermissionGroupId = $this->_input->filterSingle('original_permission_group_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'permission_id' => XenForo_Input::STRING,
			'permission_group_id' => XenForo_Input::STRING,
			'depend_permission_id' => XenForo_Input::STRING,
			'permission_type' => XenForo_Input::STRING,
			'default_value' => array(XenForo_Input::STRING, 'default' => 'unset'),
			'default_value_int' => XenForo_Input::INT,
			'interface_group_id' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));
		$titleValue = $this->_input->filterSingle('title', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Permission');
		if ($originalPermissionId && $originalPermissionGroupId)
		{
			$dw->setExistingData(array($originalPermissionGroupId, $originalPermissionId));
		}
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_Permission::DATA_TITLE, $titleValue);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('permissions/definitions') . $this->getLastHash($dwInput['permission_group_id'] . '_' . $dwInput['permission_id'])
		);
	}

	/**
	 * Deletes a permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionDelete()
	{
		$permissionGroupId = $this->_input->filterSingle('permission_group_id', XenForo_Input::STRING);
		$permissionId = $this->_input->filterSingle('permission_id', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Permission');
			$dw->setExistingData(array($permissionGroupId, $permissionId));
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('permissions/definitions')
			);
		}
		else // show confirmation dialog
		{
			$permission = $this->_getValidPermissionOrError($permissionGroupId, $permissionId);

			$viewParams = array(
				'permission' => $permission
			);

			return $this->responseView('XenForo_ViewAdmin_Permission_PermissionDelete', 'permission_permission_delete', $viewParams);
		}
	}

	/**
	 * Helper to get the permission group add/edit form controller response.
	 *
	 * @param array $permissionGroup
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getPermissionGroupAddEditResponse(array $permissionGroup)
	{
		$addOnModel = $this->_getAddOnModel();

		$masterTitle = $this->_getPermissionModel()->getPermissionGroupMasterTitlePhraseValue($permissionGroup['permission_group_id']);

		$viewParams = array(
			'permissionGroup' => $permissionGroup,
			'masterTitle' => $masterTitle,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($permissionGroup['addon_id']) ? $permissionGroup['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_PermissionGroupEdit', 'permission_permission_group_edit', $viewParams);
	}

	/**
	 * Form to create a new permission group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionGroupAdd()
	{
		return $this->_getPermissionGroupAddEditResponse(
			$this->_getPermissionModel()->getDefaultPermissionGroup()
		);
	}

	/**
	 * Form to edit an existing permission group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionGroupEdit()
	{
		$permissionGroupId = $this->_input->filterSingle('permission_group_id', XenForo_Input::STRING);
		$permissionGroup = $this->_getValidPermissionGroupOrError($permissionGroupId);

		return $this->_getPermissionGroupAddEditResponse($permissionGroup);
	}

	/**
	 * Inserts a new permission group or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionGroupSave()
	{
		$this->_assertPostOnly();

		$originalPermissionGroupId = $this->_input->filterSingle('original_permission_group_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'permission_group_id' => XenForo_Input::STRING,
			'addon_id' => XenForo_Input::STRING
		));
		$titleValue = $this->_input->filterSingle('title', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionGroup');
		if ($originalPermissionGroupId)
		{
			$dw->setExistingData($originalPermissionGroupId);
		}
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_PermissionGroup::DATA_TITLE, $titleValue);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('permissions/definitions') . $this->getLastHash("group_{$dwInput['permission_group_id']}")
		);
	}

	/**
	 * Deletes a permission group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionGroupDelete()
	{
		$permissionGroupId = $this->_input->filterSingle('permission_group_id', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionGroup');
			$dw->setExistingData($permissionGroupId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('permissions/definitions')
			);
		}
		else // show confirmation dialog
		{
			$permissionGroup = $this->_getValidPermissionGroupOrError($permissionGroupId);

			$viewParams = array(
				'permissionGroup' => $permissionGroup
			);

			return $this->responseView('XenForo_ViewAdmin_Permission_PermissionGroupDelete', 'permission_permission_group_delete', $viewParams);
		}
	}

	protected function _getInterfaceGroupAddEditResponse(array $interfaceGroup)
	{
		$addOnModel = $this->_getAddOnModel();

		$masterTitle = $this->_getPermissionModel()->getPermissionInterfaceGroupMasterTitlePhraseValue(
			$interfaceGroup['interface_group_id']
		);

		$viewParams = array(
			'interfaceGroup' => $interfaceGroup,
			'masterTitle' => $masterTitle,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($interfaceGroup['addon_id']) ? $interfaceGroup['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_InterfaceGroupEdit', 'permission_interface_group_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new permission interface group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInterfaceGroupAdd()
	{
		return $this->_getInterfaceGroupAddEditResponse(
			$this->_getPermissionModel()->getDefaultPermissionInterfaceGroup()
		);
	}

	/**
	 * Displays a form to edit an existing permission interface group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInterfaceGroupEdit()
	{
		$interfaceGroupId = $this->_input->filterSingle('interface_group_id', XenForo_Input::STRING);
		$interfaceGroup = $this->_getValidPermissionInterfaceGroupOrError($interfaceGroupId);

		return $this->_getInterfaceGroupAddEditResponse($interfaceGroup);
	}

	/**
	 * Inserts a new permission interface group or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInterfaceGroupSave()
	{
		$this->_assertPostOnly();

		$originalInterfaceGroupId = $this->_input->filterSingle('original_interface_group_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'interface_group_id' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));
		$titleValue = $this->_input->filterSingle('title', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionInterfaceGroup');
		if ($originalInterfaceGroupId)
		{
			$dw->setExistingData($originalInterfaceGroupId);
		}
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_PermissionInterfaceGroup::DATA_TITLE, $titleValue);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('permissions/definitions') . $this->getLastHash("igroup_{$dwInput['interface_group_id']}")
		);
	}

	/**
	 * Deletes a permission interface group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInterfaceGroupDelete()
	{
		$interfaceGroupId = $this->_input->filterSingle('interface_group_id', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionInterfaceGroup');
			$dw->setExistingData($interfaceGroupId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('permissions/definitions')
			);
		}
		else // show confirm dialog
		{
			$interfaceGroup = $this->_getValidPermissionInterfaceGroupOrError($interfaceGroupId);

			$viewParams = array(
				'interfaceGroup' => $interfaceGroup
			);

			return $this->responseView('XenForo_ViewAdmin_Permission_InterfaceGroupDelete', 'permission_interface_group_delete', $viewParams);
		}
	}

	/**
	 * Gets a valid permission record or raises a controller response exception.
	 *
	 * @param string $groupId
	 * @param string $permissionId
	 *
	 * @return array
	 */
	protected function _getValidPermissionOrError($groupId, $permissionId)
	{
		$info = $this->_getPermissionModel()->getPermissionByGroupAndId($groupId, $permissionId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_permission_not_found'), 404));
		}

		return $this->_getPermissionModel()->preparePermission($info);
	}

	/**
	 * Gets a valid permission group record or raises a controller response exception.
	 *
	 * @param string $permissionGroupId
	 *
	 * @return array
	 */
	protected function _getValidPermissionGroupOrError($permissionGroupId)
	{
		$permissionGroup = $this->_getPermissionModel()->getPermissionGroupById($permissionGroupId);
		if (!$permissionGroup)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_permission_group_not_found'), 404));
		}

		return $this->_getPermissionModel()->preparePermissionGroup($permissionGroup);
	}

	/**
	 * Gets a valid permission interface group record or raises a controller response exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getValidPermissionInterfaceGroupOrError($id)
	{
		$info = $this->_getPermissionModel()->getPermissionInterfaceGroupById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_permission_interface_group_not_found'), 404));
		}

		return $this->_getPermissionModel()->preparePermissionInterfaceGroup($info);
	}

	/**
	 * Gets the permission model.
	 *
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
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