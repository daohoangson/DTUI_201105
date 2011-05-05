<?php

/**
 * Interface that all content permission handlers must implement.
 *
 * @package XenForo_Permissions
 */
interface XenForo_ContentPermission_Interface
{
	/**
	 * Builds the content permissions for a user collection, for the permission type
	 * the implementing class is designed to handle.
	 *
	 * @param XenForo_Model_Permission $permissionModel Permission model that called this.
	 * @param array $userGroupIds List of user groups for the collection
	 * @param integer $userId User ID for the collection, if there are custom permissions
	 * @param array $permissionsGrouped List of all valid permissions, grouped
	 * @param array $globalPerms The global permissions that apply to this combination
	 *
	 * @return array
	 */
	public function rebuildContentPermissions(
		$permissionModel, array $userGroupIds, $userId, array $permissionsGrouped, array &$globalPerms
	);
	// note: $permissionModel isn't type cast to allow possible decoration
}