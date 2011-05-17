<?php

/**
 * Controller for handling the users section and actions on users in the
 * admin control panel.
 *
 * @package XenForo_UserGroups
 */
class XenForo_ControllerAdmin_UserGroup extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('userGroup');
	}

	/**
	 * Displays a list of user groups.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$viewParams = array(
			'userGroups' => $this->_getUserGroupModel()->getAllUserGroups()
		);

		return $this->responseView('XenForo_ViewAdmin_UserGroup_List', 'user_group_list', $viewParams);
	}

	/**
	 * Gets the add/edit controller response for user groups.
	 *
	 * @param array $userGroup
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getUserGroupAddEditResponse(array $userGroup)
	{
		$permissionModel = $this->_getPermissionModel();

		$viewParams = array(
			'userGroup' => $userGroup,
			'permissions' => $permissionModel->getUserCollectionPermissionsForInterface($userGroup['user_group_id']),
			'permissionChoices' => $permissionModel->getPermissionChoices('userGroup', false)
		);

		return $this->responseView('XenForo_ViewAdmin_UserGroup_Edit', 'user_group_edit', $viewParams);
	}

	/**
	 * Displays a form to add a user group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getUserGroupAddEditResponse($this->_getUserGroupModel()->getDefaultUserGroup());
	}

	/**
	 * Displays a form to add a user group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$userGroupId = $this->_input->filterSingle('user_group_id', XenForo_Input::UINT);
		$userGroup = $this->_getUserGroupOrError($userGroupId);

		return $this->_getUserGroupAddEditResponse($userGroup);
	}

	/**
	 * Inserts a new user group or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'user_group_id' => XenForo_Input::UINT,

			'title' => XenForo_Input::STRING,

			'display_style_priority' => XenForo_Input::UINT,
			'username_css' => XenForo_Input::STRING,
			'user_title_override' => XenForo_Input::UINT,
			'user_title' => XenForo_Input::STRING,

			'permissions' => XenForo_Input::ARRAY_SIMPLE
		));

		$userGroupInfo = array(
			'title' => $input['title'],
			'display_style_priority' => $input['display_style_priority'],
			'username_css' => $input['username_css'],
			'user_title' => ($input['user_title_override'] ? $input['user_title'] : ''),
		);

		$userGroupId = $this->_getUserGroupModel()->updateUserGroupAndPermissions($input['user_group_id'], $userGroupInfo, $input['permissions']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-groups') . $this->getLastHash($userGroupId)
		);
	}

	/**
	 * Deletes a user group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_UserGroup', 'user_group_id',
				XenForo_Link::buildAdminLink('user-groups')
			);
		}
		else // show confirmation dialog
		{
			$userGroupId = $this->_input->filterSingle('user_group_id', XenForo_Input::UINT);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserGroup', XenForo_DataWriter::ERROR_EXCEPTION);
			$dw->setExistingData($userGroupId);
			$dw->preDelete();

			$viewParams = array(
				'userGroup' => $dw->getMergedData()
			);

			return $this->responseView('XenForo_ViewAdmin_UserGroup_Delete', 'user_group_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified user group or throws an error.
	 *
	 * @param integer $userGroupId
	 *
	 * @return array
	 */
	protected function _getUserGroupOrError($userGroupId)
	{
		$userGroup = $this->_getUserGroupModel()->getUserGroupById($userGroupId);
		if (!$userGroup)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_user_group_not_found'), 404));
		}

		return $userGroup;
	}

	/**
	 * Gets the user group model.
	 *
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
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
}