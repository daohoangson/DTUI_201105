<?php

/**
 * Controller to manage node permissions.
 *
 * @package XenForo_Permissions
 */
class XenForo_ControllerAdmin_Permission_Node extends XenForo_ControllerAdmin_Permission_Abstract
{
	protected function _preDispatch($action)
	{
		parent::_preDispatch($action);
		$this->assertAdminPermission('node');
	}

	/**
	 * Displays a list of nodes with links to edit permissions for them.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$nodeModel = $this->_getNodeModel();

		$permissionSets = $this->_getPermissionModel()->getUserCombinationsWithContentPermissions('node');
		$nodesWithPerms = array();
		foreach ($permissionSets AS $set)
		{
			$nodesWithPerms[$set['content_id']] = true;
		}

		$viewParams = array(
			'nodes' => $nodeModel->prepareNodesForAdmin($nodeModel->getAllNodes()),
			'nodesWithPerms' => $nodesWithPerms
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_NodeList', 'permission_node_list', $viewParams);
	}

	/**
	 * For a single node, shows page with options to edit node/user group/user permissions.
	 * If no node is specified, uses the nodesindex action instead.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionNodeOptions()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$node = $this->_getValidNodeOrError($nodeId);

		$permissionSets = $this->_getPermissionModel()->getUserCombinationsWithContentPermissions('node');
		$groupsWithPerms = array();
		foreach ($permissionSets AS $set)
		{
			if ($set['user_group_id'] && $set['content_id'] == $nodeId)
			{
				$groupsWithPerms[$set['user_group_id']] = true;
			}
		}

		$viewParams = array(
			'node' => $node,
			'userGroups' => $this->_getUserGroupModel()->getAllUserGroups(),
			'groupsWithPerms' => $groupsWithPerms,
			'users' => $this->_getPermissionModel()->getUsersWithContentUserPermissions('node', $nodeId),
			'revoked' => $this->_permissionsAreRevoked($node['node_id'], 0, 0),
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_Node', 'permission_node', $viewParams);
	}

	/**
	 * Changes the revoke status for the node-wide settings.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionNodeWideRevoke()
	{
		$this->_assertPostOnly();

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$node = $this->_getValidNodeOrError($nodeId);

		$revoke = $this->_input->filterSingle('revoke', XenForo_Input::UINT);

		// TODO: better approach that doesn't rely on every permission having "revoke" value
		$this->_setPermissionRevokeStatus($node['node_id'], 0, 0, $revoke);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('node-permissions', $node)
		);
	}

	/**
	 * Displays a form to edit a user group's permissions for a node.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUserGroup()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$node = $this->_getValidNodeOrError($nodeId);

		$userGroupId = $this->_input->filterSingle('user_group_id', XenForo_Input::UINT);
		$userGroup = $this->_getValidUserGroupOrError($userGroupId);

		$permissionModel = $this->_getPermissionModel();

		$nodeTypePermissionGroups = $this->_getNodeModel()->getNodeTypesGroupedByPermissionGroup();
		$permissions = $permissionModel->getUserCollectionContentPermissionsForGroupedInterface(
			'node', $node['node_id'], array_keys($nodeTypePermissionGroups), $userGroup['user_group_id'], 0
		);

		$viewNodePermission = $permissionModel->preparePermission(
			$permissionModel->getViewNodeContentPermission($nodeId, $userGroupId, 0)
		);

		$viewParams = array(
			'node' => $node,
			'userGroup' => $userGroup,
			'permissions' => $permissions,
			'permissionChoices' => $permissionModel->getPermissionChoices('userGroup', true),
			'viewNodePermission' => $viewNodePermission
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_NodeUserGroup', 'permission_node_user_group', $viewParams);
	}

	/**
	 * Updates a user group's permissions for a node.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUserGroupSave()
	{
		$this->_assertPostOnly();

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$node = $this->_getValidNodeOrError($nodeId);

		$userGroupId = $this->_input->filterSingle('user_group_id', XenForo_Input::UINT);
		$userGroup = $this->_getValidUserGroupOrError($userGroupId);

		$permissions = $this->_input->filterSingle('permissions', XenForo_Input::ARRAY_SIMPLE);

		$this->_getPermissionModel()->updateContentPermissionsForUserCollection(
			$permissions, 'node', $node['node_id'], $userGroup['user_group_id'], 0
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('node-permissions', $node) . $this->getLastHash("user_group_{$userGroupId}")
		);
	}

	/**
	 * Redirects to the correct page to add permissions for the specified user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUserAdd()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$node = $this->_getValidNodeOrError($nodeId);

		$userName = $this->_input->filterSingle('username', XenForo_Input::STRING);
		$user = $this->_getUserModel()->getUserByName($userName);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildAdminLink('node-permissions/user', $node, array('user_id' => $user['user_id']))
		);
	}

	/**
	 * Displays a form to edit a user's permissions for a node.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUser()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$node = $this->_getValidNodeOrError($nodeId);

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getValidUserOrError($userId);

		$permissionModel = $this->_getPermissionModel();

		$nodeTypePermissionGroups = $this->_getNodeModel()->getNodeTypesGroupedByPermissionGroup();
		$permissions = $permissionModel->getUserCollectionContentPermissionsForGroupedInterface(
			'node', $node['node_id'], array_keys($nodeTypePermissionGroups), 0, $user['user_id']
		);

		$viewNodePermission = $permissionModel->preparePermission(
			$permissionModel->getViewNodeContentPermission($nodeId, 0, $user['user_id'])
		);

		$viewParams = array(
			'node' => $node,
			'user' => $user,
			'permissions' => $permissions,
			'permissionChoices' => $permissionModel->getPermissionChoices('user', true),
			'viewNodePermission' => $viewNodePermission
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_NodeUser', 'permission_node_user', $viewParams);
	}

	/**
	 * Updates a user's permissions for a node.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUserSave()
	{
		$this->_assertPostOnly();

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$node = $this->_getValidNodeOrError($nodeId);

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getValidUserOrError($userId);

		$permissions = $this->_input->filterSingle('permissions', XenForo_Input::ARRAY_SIMPLE);

		$this->_getPermissionModel()->updateContentPermissionsForUserCollection(
			$permissions, 'node', $node['node_id'], 0, $user['user_id']
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('node-permissions', $node) . $this->getLastHash("user_{$userId}")
		);
	}

	protected function _permissionsAreRevoked($nodeId, $userGroupId, $userId)
	{
		$permissions = $this->_getPermissionModel()->getContentPermissionsWithValues(
			'node', $nodeId, array('general'), $userGroupId, $userId
		);

		foreach ($permissions AS $permission)
		{
			if ($permission['permission_group_id'] == 'general'
				&& $permission['permission_id'] == 'viewNode'
				&& $permission['permission_value'] === 'reset'
			)
			{
				return true;
			}
		}

		return false;
	}

	protected function _setPermissionRevokeStatus($nodeId, $userGroupId, $userId, $revoke)
	{
		$update = array('general' => array('viewNode' => $revoke ? 'reset' : 'unset'));

		$this->_getPermissionModel()->updateContentPermissionsForUserCollection(
			$update, 'node', $nodeId, $userGroupId, $userId
		);
	}
}