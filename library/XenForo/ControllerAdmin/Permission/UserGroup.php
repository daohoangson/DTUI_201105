<?php

/**
 * Controller to manage user group permissions.
 *
 * @package XenForo_Permissions
 */
class XenForo_ControllerAdmin_Permission_UserGroup extends XenForo_ControllerAdmin_Permission_Abstract
{
	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);
		$this->assertAdminPermission('userGroup');
	}

	/**
	 * Displays a list of user groups.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		if ($this->_input->filterSingle('user_group_id', XenForo_Input::UINT))
		{
			return $this->responseReroute(__CLASS__, 'edit');
		}

		$viewParams = array(
			'userGroups' => $this->getModelFromCache('XenForo_Model_UserGroup')->getAllUserGroups()
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_UserGroupList', 'permission_user_group_list', $viewParams);
	}

	/**
	 * Displays a form to edit permissions for a particular user group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$userGroupId = $this->_input->filterSingle('user_group_id', XenForo_Input::UINT);
		$userGroup = $this->_getValidUserGroupOrError($userGroupId);

		$permissionModel = $this->_getPermissionModel();

		$viewParams = array(
			'userGroup' => $userGroup,
			'permissions' => $permissionModel->getUserCollectionPermissionsForInterface($userGroup['user_group_id']),
			'permissionChoices' => $permissionModel->getPermissionChoices('userGroup', false)
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_UserGroupEdit', 'permission_user_group_edit', $viewParams);
	}

	/**
	 * Updates permissions for a particular user group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$userGroupId = $this->_input->filterSingle('user_group_id', XenForo_Input::UINT);
		$userGroup = $this->_getValidUserGroupOrError($userGroupId);

		$permissions = $this->_input->filterSingle('permissions', XenForo_Input::ARRAY_SIMPLE);

		$this->_getPermissionModel()->updateGlobalPermissionsForUserCollection($permissions, $userGroupId, 0);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-group-permissions') . $this->getLastHash($userGroupId)
		);
	}
}