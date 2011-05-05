<?php

/**
 * Controller to manage user permissions.
 *
 * @package XenForo_Permissions
 */
class XenForo_ControllerAdmin_Permission_User extends XenForo_ControllerAdmin_Permission_Abstract
{
	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);
		$this->assertAdminPermission('user');
	}

	/**
	 * Displays a list of users with custom user permissions.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		if ($this->_input->filterSingle('user_id', XenForo_Input::UINT))
		{
			return $this->responseReroute(__CLASS__, 'edit');
		}

		$viewParams = array(
			'users' => $this->_getPermissionModel()->getUsersWithGlobalUserPermissions()
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_UserList', 'permission_user_list', $viewParams);
	}

	/**
	 * Redirects to the correct page to add permissions for the specified user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$userName = $this->_input->filterSingle('username', XenForo_Input::STRING);
		$user = $this->_getUserModel()->getUserByName($userName);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildAdminLink('user-permissions', $user)
		);
	}

	/**
	 * Displays a form to edit permissions for a particular user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getValidUserOrError($userId);

		$permissionModel = $this->_getPermissionModel();

		$viewParams = array(
			'user' => $user,
			'permissions' => $permissionModel->getUserCollectionPermissionsForInterface(0, $user['user_id']),
			'permissionChoices' => $permissionModel->getPermissionChoices('user', false)
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_UserEdit', 'permission_user_edit', $viewParams);
	}

	/**
	 * Updates permissions for a particular user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getValidUserOrError($userId);

		$permissions = $this->_input->filterSingle('permissions', XenForo_Input::ARRAY_SIMPLE);

		$this->_getPermissionModel()->updateGlobalPermissionsForUserCollection($permissions, 0, $userId);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-permissions') . $this->getLastHash($userId)
		);
	}
}