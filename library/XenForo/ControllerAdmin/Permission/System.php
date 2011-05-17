<?php

/**
 * Controller to manage system permissions.
 *
 * @package XenForo_Permissions
 */
class XenForo_ControllerAdmin_Permission_System extends XenForo_ControllerAdmin_Permission_Abstract
{
	/**
	 * Displays a form to edit the system-wide permissions.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$permissionModel = $this->_getPermissionModel();

		$viewParams = array(
			'permissions' => $permissionModel->getUserCollectionPermissionsForInterface(0, 0),
			'permissionChoices' => $permissionModel->getPermissionChoices('system', false)
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_SystemWide', 'permission_systemwide', $viewParams);
	}

	/**
	 * Updates system-wide permissions.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$permissions = $this->_input->filterSingle('permissions', XenForo_Input::ARRAY_SIMPLE);

		$this->_getPermissionModel()->updateGlobalPermissionsForUserCollection($permissions, 0, 0);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('system-permissions')
		);
	}
}