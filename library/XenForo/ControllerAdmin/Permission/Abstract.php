<?php

/**
 * Abstract controller to provide helper methods to permission controllers.
 *
 * @package XenForo_Permissions
 */
class XenForo_ControllerAdmin_Permission_Abstract extends XenForo_ControllerAdmin_Abstract
{
	/**
	 * Gets a valid node record or raises a controller response exception.
	 *
	 * @param integer $nodeId
	 *
	 * @return array
	 */
	protected function _getValidNodeOrError($nodeId)
	{
		$node = $this->_getNodeModel()->getNodeById($nodeId);
		if (!$node)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_node_not_found'), 404));
		}

		return $node;
	}

	/**
	 * Gets a valid user record or raises a controller response exception.
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	protected function _getValidUserOrError($userId)
	{
		$user = $this->_getUserModel()->getUserById($userId);
		if (!$user)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_user_not_found'), 404));
		}

		return $user;
	}

	/**
	 * Gets a valid user group record or raises a controller response exception.
	 *
	 * @param integer $userGroupId
	 *
	 * @return array
	 */
	protected function _getValidUserGroupOrError($userGroupId)
	{
		$userGroup = $this->_getUserGroupModel()->getUserGroupById($userGroupId);
		if (!$userGroup)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_user_group_not_found'), 404));
		}

		return $userGroup;
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
	 * Gets the user group model.
	 *
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}

	/**
	 * Gets the user group model.
	 *
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * Gets the node model.
	 *
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}