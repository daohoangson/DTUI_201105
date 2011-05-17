<?php

/**
 * Permission model.
 *
 * @package XenForo_Permissions
 */
class XenForo_Model_Permission extends XenForo_Model
{
	/**
	 * List of priorites for permission values (unset, allow, deny, etc).
	 * Lower numbers are higher priority.
	 *
	 * @var array
	 */
	protected static $_permissionPriority = array(
		'deny' => 1,
		'content_allow' => 2,
		'reset' => 3,
		'allow' => 4,
		'unset' => 5,
		'use_int' => 6
	);

	/**
	 * Get all permissions (ungrouped), in their relative display order.
	 * Proper display order cannot be gained unless the permissions are
	 * grouped into their interface groups.
	 *
	 * @return array Format: [] => permission info
	 */
	public function getAllPermissions()
	{
		return $this->_getDb()->fetchAll('
			SELECT *,
				default_value AS value, default_value_int AS value_int
			FROM xf_permission
			ORDER BY display_order
		');
	}

	/**
	 * Gets the named permission based on it's group and ID. Both the group
	 * and the permission ID are required for unique identification.
	 *
	 * @param string $permissionGroupId
	 * @param string $permissionId
	 *
	 * @return array|false
	 */
	public function getPermissionByGroupAndId($permissionGroupId, $permissionId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_permission
			WHERE permission_group_id = ? AND permission_id = ?
		', array($permissionGroupId, $permissionId));
	}

	/**
	 * Gets a collection of permissions by their group-permissions
	 * pairs. Key 0 must be the group and key 1 must be the permission.
	 *
	 * @param array $pairs Format: [] => [0 => group id, 1 => permission id]
	 *
	 * @return array Array of permissions grouped: [group id][permission id] => info
	 */
	public function getPermissionsByPairs(array $pairs)
	{
		if (!$pairs)
		{
			return array();
		}

		$db = $this->_getDb();

		$clauses = array();
		foreach ($pairs AS $pair)
		{
			$clauses[] = '(permission_group_id = ' . $db->quote($pair[0]) . ' AND permission_id = ' . $db->quote($pair[1]) . ')';
		}

		$permissions = array();
		$permissionsResult = $db->query('
			SELECT *
			FROM xf_permission
			WHERE ' . implode(' OR ', $clauses)
		);
		while ($permission = $permissionsResult->fetch())
		{
			$permissions[$permission['permission_group_id']][$permission['permission_id']] = $permission;
		}

		return $permissions;
	}

	/**
	 * Gets the default permission data.
	 *
	 * @return array
	 */
	public function getDefaultPermission()
	{
		return array(
			'permission_id' => '',
			'permission_group_id' => '',
			'permission_type' => 'flag',
			'interface_group_id' => '',
			'depend_permisssion_id' => '',
			'display_order' => 1,
			'default_value' => 'unset',
			'default_value_int' => 0
		);
	}

	/**
	 * Prepares a set of permissions that were grouped for display.
	 *
	 * @param array $permissions Format: [group id][] => permission info
	 *
	 * @return array Prepared array
	 */
	public function preparePermissionsGrouped(array $permissions)
	{
		foreach ($permissions AS $groupId => $group)
		{
			foreach ($group AS $permissionId => $permission)
			{
				$permissions[$groupId][$permissionId] = $this->preparePermission($permission);
			}
		}

		return $permissions;
	}

	/**
	 * Prepares an ungrouped list of permissions for display.
	 *
	 * @param array $permissions Format: [] => permission info
	 *
	 * @return array
	 */
	public function preparePermissions(array $permissions)
	{
		foreach ($permissions AS &$permission)
		{
			$permission = $this->preparePermission($permission);
		}

		return $permissions;
	}

	/**
	 * Prepares a permission for display.
	 *
	 * @param array $permission
	 *
	 * @return array
	 */
	public function preparePermission(array $permission)
	{
		$permission['title'] = new XenForo_Phrase($this->getPermissionTitlePhraseName(
			$permission['permission_group_id'], $permission['permission_id']
		));

		$permission['groupTitle'] = new XenForo_Phrase($this->getPermissionGroupTitlePhraseName(
			$permission['permission_group_id']
		));

		return $permission;
	}

	/**
	 * Perpares a list of permission groups for display.
	 *
	 * @param array $permissionGroups Format: [] => permission group info
	 *
	 * @return array
	 */
	public function preparePermissionGroups(array $permissionGroups)
	{
		foreach ($permissionGroups AS &$group)
		{
			$group = $this->preparePermissionGroup($group);
		}

		return $permissionGroups;
	}

	/**
	 * Prepares a permission group for display.
	 *
	 * @param array $permissionGroup
	 *
	 * @return array
	 */
	public function preparePermissionGroup(array $permissionGroup)
	{
		$permissionGroup['title'] = new XenForo_Phrase($this->getPermissionGroupTitlePhraseName($permissionGroup['permission_group_id']));

		return $permissionGroup;
	}

	/**
	 * Perpares a list of permission interface groups for display.
	 *
	 * @param array $interfaceGroups Format: [] => interface group info
	 *
	 * @return array
	 */
	public function preparePermissionInterfaceGroups(array $interfaceGroups)
	{
		foreach ($interfaceGroups AS &$group)
		{
			$group = $this->preparePermissionInterfaceGroup($group);
		}

		return $interfaceGroups;
	}

	/**
	 * Prepares a permission interface group for display.
	 *
	 * @param array $interfaceGroup
	 *
	 * @return array
	 */
	public function preparePermissionInterfaceGroup(array $interfaceGroup)
	{
		$interfaceGroup['title'] = new XenForo_Phrase(
			$this->getPermissionInterfaceGroupTitlePhraseName($interfaceGroup['interface_group_id'])
		);

		return $interfaceGroup;
	}

	/**
	 * Gets all permission grouped based on their internal permission groups.
	 * This does not return based on interface groups.
	 *
	 * @return array Format: [permission group id][permission id] => permission info
	 */
	public function getAllPermissionsGrouped()
	{
		$groupedPermissions = array();
		foreach ($this->getAllPermissions() AS $permission)
		{
			$groupedPermissions[$permission['permission_group_id']][$permission['permission_id']] = $permission;
		}

		return $groupedPermissions;
	}

	/**
	 * Internal function to sanitize the user and user group values for
	 * use in a query against permission entries. Only one of the user group
	 * and user ID may be specified; if both are specified, the user ID takes
	 * precedence. If neither are specified, this relates to system-wide permissions.
	 *
	 * @param integer $userGroupId Modified by reference
	 * @param integer $userId Modified by reference
	 */
	protected function _sanitizeUserIdAndUserGroupForQuery(&$userGroupId, &$userId)
	{
		if ($userId) // user perms
		{
			$userGroupId = 0;
			$userId = intval($userId);
		}
		else if ($userGroupId) // group perms
		{
			$userGroupId = intval($userGroupId);
			$userId = 0;
		}
		else // system-wide perms
		{
			$userGroupId = 0;
			$userId = 0;
		}
	}

	/**
	 * Gets all permissions in their relative display order, with the correct/effective
	 * value for the specified user group or user.
	 *
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return array Format: [] => permission info, permission_value/permission_value_int from entry,
	 * 			value/value_int for effective value
	 */
	public function getAllPermissionsWithValues($userGroupId = 0, $userId = 0)
	{
		$this->_sanitizeUserIdAndUserGroupForQuery($userGroupId, $userId);

		return $this->_getDb()->fetchAll('
			SELECT permission.*,
				entry.permission_value, entry.permission_value_int,
				COALESCE(entry.permission_value, \'unset\') AS value,
				COALESCE(entry.permission_value_int, 0) AS value_int
			FROM xf_permission AS permission
			LEFT JOIN xf_permission_entry AS entry ON
				(entry.permission_id = permission.permission_id
				AND entry.permission_group_id = permission.permission_group_id
				AND entry.user_group_id = ?
				AND entry.user_id = ?)
			ORDER BY permission.display_order
		', array($userGroupId, $userId));
	}

	/**
	 * Gets content permissions from the specified groups in their relative display order, with the
	 * correct/effective value for the specified user group or user.
	 *
	 * @param string $contentTypeId
	 * @param integer $contentId
	 * @param mixed|array If array, only pulls permissions from the specified groups; otherwise, all
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return array Format: [] => permission info, permission_value/permission_value_int from entry,
	 * 			value/value_int for effective value
	 */
	public function getContentPermissionsWithValues($contentTypeId, $contentId, $permissionGroups, $userGroupId = 0, $userId = 0)
	{
		$this->_sanitizeUserIdAndUserGroupForQuery($userGroupId, $userId);

		$db = $this->_getDb();

		if (is_string($permissionGroups))
		{
			$permissionGroups = array($permissionGroups);
		}

		if (is_array($permissionGroups))
		{
			if (empty($permissionGroups))
			{
				return array();
			}
			else
			{
				$groupLimit = 'permission.permission_group_id IN (' . $db->quote($permissionGroups) . ')';
			}
		}
		else
		{
			$groupLimit = '1=1';
		}

		return $db->fetchAll('
			SELECT permission.*,
				entry_content.permission_value, entry_content.permission_value_int,
				COALESCE(entry_content.permission_value, \'unset\') AS value,
				COALESCE(entry_content.permission_value_int, 0) AS value_int
			FROM xf_permission AS permission
			LEFT JOIN xf_permission_entry_content AS entry_content ON
				(entry_content.permission_id = permission.permission_id
				AND entry_content.permission_group_id = permission.permission_group_id
				AND entry_content.content_type = ?
				AND entry_content.content_id = ?
				AND entry_content.user_group_id = ?
				AND entry_content.user_id = ?)
			WHERE ' . $groupLimit . '
			ORDER BY permission.display_order
		', array($contentTypeId, $contentId, $userGroupId, $userId));
	}

	/**
	 * Gets the view node permission attached to a specific node. This permission is a bit
	 * weird since it doesn't fit in the expected groups, so it has to be handled specially.
	 *
	 * @param integer $nodeId
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getViewNodeContentPermission($nodeId, $userGroupId, $userId)
	{
		return $this->_getDb()->fetchRow('
			SELECT permission.*,
				entry_content.permission_value, entry_content.permission_value_int,
				COALESCE(entry_content.permission_value, \'unset\') AS value,
				COALESCE(entry_content.permission_value_int, 0) AS value_int
			FROM xf_permission AS permission
			LEFT JOIN xf_permission_entry_content AS entry_content ON
				(entry_content.permission_id = permission.permission_id
				AND entry_content.permission_group_id = permission.permission_group_id
				AND entry_content.content_type = \'node\'
				AND entry_content.content_id = ?
				AND entry_content.user_group_id = ?
				AND entry_content.user_id = ?)
			WHERE permission.permission_group_id = \'general\'
				AND permission.permission_id = \'viewNode\'
		', array($nodeId, $userGroupId, $userId));
	}

	/**
	 * Gets all permission interface groups in order.
	 *
	 * @return array Format: [interface group id] => interface group info
	 */
	public function getAllPermissionInterfaceGroups()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_permission_interface_group
			ORDER BY display_order
		', 'interface_group_id');
	}

	/**
	 * Gets permission interface groups names in their display order.
	 *
	 * @return array Format: [interface group id] => name
	 */
	public function getPermissionInterfaceGroupNames()
	{
		$groups = $this->preparePermissionInterfaceGroups($this->getAllPermissionInterfaceGroups());

		$output = array();
		foreach ($groups AS $group)
		{
			$output[$group['interface_group_id']] = $group['title'];
		}

		return $output;
	}

	/**
	 * Gets all permissions, grouped into the interface groups, with values
	 * for the permissions specified for a particular group or user.
	 *
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return array Format: [interface group id] => interface group info + key:permissions => [] => permission info with effective value
	 */
	public function getUserCollectionPermissionsForInterface($userGroupId = 0, $userId = 0)
	{
		$permissions = $this->preparePermissions($this->getAllPermissionsWithValues($userGroupId, $userId));
		$interfaceGroups = $this->preparePermissionInterfaceGroups($this->getAllPermissionInterfaceGroups());

		return $this->getInterfaceGroupedPermissions($permissions, $interfaceGroups);
	}

	/**
	 * Gets all content permissions, grouped into the interface groups, with values
	 * for the permissions specified for a particular group or user.
	 *
	 * @param string $contentTypeId
	 * @param integer $contentId
	 * @param mixed|string|array $permissionGroups If array, only those permission groups; if string, only that group; otherwise, all
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return array Format: [interface group id] => interface group info + key:permissions => [] => permission info with effective value
	 */
	public function getUserCollectionContentPermissionsForInterface($contentTypeId, $contentId, $permissionGroups, $userGroupId = 0, $userId = 0)
	{
		$permissions = $this->getContentPermissionsWithValues($contentTypeId, $contentId, $permissionGroups, $userGroupId, $userId);
		$interfaceGroups = $this->preparePermissionInterfaceGroups($this->getAllPermissionInterfaceGroups());

		return $this->getInterfaceGroupedPermissions($permissions, $interfaceGroups);
	}

	/**
	 * Gets all permissions, grouped into the interface groups, with values
	 * for the permissions coming from the default values.
	 *
	 * @return array Format: [interface group id] => interface group info + key:permissions => [] => permission info with effective value
	 */
	public function getDefaultPermissionsForInterface()
	{
		$permissions = $this->preparePermissions($this->getAllPermissions());
		$interfaceGroups = $this->preparePermissionInterfaceGroups($this->getAllPermissionInterfaceGroups());

		return $this->getInterfaceGroupedPermissions($permissions, $interfaceGroups);
	}

	/**
	 * Groups a list of permissions based on the interface group they belong to.
	 *
	 * @param array $permissions
	 * @param array $interfaceGroups
	 *
	 * @return array Format: [interface group id] => interface group info + key:permissions => [] => permission info with effective value
	 */
	public function getInterfaceGroupedPermissions(array $permissions, array $interfaceGroups)
	{
		$permissionsGrouped = array();
		foreach ($permissions AS $permission)
		{
			$permissionsGrouped[$permission['interface_group_id']][] = $permission;
		}

		foreach ($interfaceGroups AS $groupKey => &$group)
		{
			if (!isset($permissionsGrouped[$group['interface_group_id']]))
			{
				unset($interfaceGroups[$groupKey]);
			}
			else
			{
				$group['permissions'] = $permissionsGrouped[$group['interface_group_id']];
			}
		}

		return $interfaceGroups;
	}

	/**
	 * Gets all content permissions, grouped into the permission groups and then
	 * interface groups, with values  for the permissions specified for a
	 * particular group or user.
	 *
	 * @param string $contentTypeId
	 * @param integer $contentId
	 * @param mixed|string|array $permissionGroups If array, only those permission groups; if string, only that group; otherwise, all
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return array Format: [permission group id][interface group id] => interface group info, with key permissions => permissions in interface group
	 */
	public function getUserCollectionContentPermissionsForGroupedInterface($contentTypeId, $contentId, $permissionGroups, $userGroupId = 0, $userId = 0)
	{
		$permissions = $this->getContentPermissionsWithValues($contentTypeId, $contentId, $permissionGroups, $userGroupId, $userId);
		$permissions = $this->preparePermissions($permissions);

		$interfaceGroups = $this->preparePermissionInterfaceGroups($this->getAllPermissionInterfaceGroups());

		return $this->getPermissionAndInterfaceGroupedPermissions($permissions, $interfaceGroups);
	}

	/**
	 * Gets permissions grouped by their permission group and then their interface group.
	 * This is needed when a system requires all permissions in one or more permission
	 * groups for display, but keeping the permissions together based on permission group.
	 *
	 * @param array $permissions
	 * @param array $interfaceGroups
	 *
	 * @return array Format: [permission group id][interface group id] => interface group info, with key permissions => permissions in interface group
	 */
	public function getPermissionAndInterfaceGroupedPermissions(array $permissions, array $interfaceGroups)
	{
		$permissionsGrouped = array();
		$permissionGroups = array();
		foreach ($permissions AS $permission)
		{
			$permissionsGrouped[$permission['permission_group_id']][$permission['interface_group_id']][] = $permission;
			$permissionGroups[] = $permission['permission_group_id'];
		}

		$outputGroups = array();
		foreach ($permissionGroups AS $permissionGroupId)
		{
			foreach ($interfaceGroups AS $interfaceGroupId => $interfaceGroup)
			{
				if (isset($permissionsGrouped[$permissionGroupId][$interfaceGroupId]))
				{
					$interfaceGroup['permissions'] = $permissionsGrouped[$permissionGroupId][$interfaceGroupId];
					$outputGroups[$permissionGroupId][$interfaceGroupId] = $interfaceGroup;
				}
			}
		}

		return $outputGroups;
	}

	/**
	 * Gets all permission groups ordered by their ID.
	 *
	 * @return array Format: [] => permission group info
	 */
	public function getAllPermissionGroups()
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_permission_group
			ORDER BY permission_group_id
		');
	}

	/**
	 * Gets all permission group names ordered by their ID.
	 *
	 * @return array Format: [group id] => name
	 */
	public function getPermissionGroupNames()
	{
		$groups = $this->preparePermissionGroups($this->getAllPermissionGroups());

		$output = array();
		foreach ($groups AS $group)
		{
			$output[$group['permission_group_id']] = $group['title'];
		}

		return $output;
	}

	/**
	 * Gets the specified permission group.
	 *
	 * @param string $permissionGroupId
	 *
	 * @return array|false
	 */
	public function getPermissionGroupById($permissionGroupId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_permission_group
			WHERE permission_group_id = ?
		', $permissionGroupId);
	}

	/**
	 * Gets the named permission groups.
	 *
	 * @param array $groupIds
	 *
	 * @return array Format: [section id] => info
	 */
	public function getPermissionGroupsByIds(array $groupIds)
	{
		if (!$groupIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_permission_group
			WHERE permission_group_id IN (' . $this->_getDb()->quote($groupIds) . ')
		', 'permission_group_id');
	}

	/**
	 * Gets the default permission group data.
	 *
	 * @return array
	 */
	public function getDefaultPermissionGroup()
	{
		return array(
			'permission_group_id' => ''
		);
	}

	/**
	 * Gets the specified permission interface group.
	 *
	 * @param string $interfaceGroupId
	 *
	 * @return array|false
	 */
	public function getPermissionInterfaceGroupById($interfaceGroupId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_permission_interface_group
			WHERE interface_group_id = ?
		', $interfaceGroupId);
	}

	/**
	 * Gets the named permission interface groups.
	 *
	 * @param array $groupIds
	 *
	 * @return array Format: [section id] => info
	 */
	public function getPermissionInterfaceGroupsByIds(array $groupIds)
	{
		if (!$groupIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_permission_interface_group
			WHERE interface_group_id IN (' . $this->_getDb()->quote($groupIds) . ')
		', 'interface_group_id');
	}

	/**
	 * Gets the default permission interface group data.
	 *
	 * @return array
	 */
	public function getDefaultPermissionInterfaceGroup()
	{
		return array(
			'interface_group_id' => '',
			'display_order' => 1
		);
	}

	/**
	 * Gets a permission entry (for a user or group) by its entry ID
	 *
	 * @param integer $id
	 *
	 * @return array|false Permission entry info
	 */
	public function getPermissionEntryById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_permission_entry
			WHERE permission_entry_id = ?
		', $id);
	}

	/**
	 * Gets a content permission entry (for a user or group) by its entry ID
	 *
	 * @param integer $id
	 *
	 * @return array|false Permission entry info
	 */
	public function getContentPermissionEntryById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_permission_entry_content
			WHERE permission_entry_id = ?
		', $id);
	}

	/**
	 * Gets all permission entries in an undefined order, grouped by the "level"
	 * of the permission. This is generally only needed for internal cache rebuilds.
	 *
	 * Note that entries with a value of "unset" will not be returned by this.
	 *
	 * @return array Format: ['users'][user id][group][permission] => permission value;
	 * 		['userGroups'][user group id][group][permission] => permission value;
	 * 		['system'][group][permission] => permission value
	 */
	public function getAllPermissionEntriesGrouped()
	{
		$entries = array(
			'users' => array(),
			'userGroups' => array(),
			'system' => array()
		);

		$entryResult = $this->_getDb()->query('
			SELECT entry.*, permission.permission_type
			FROM xf_permission_entry AS entry
			INNER JOIN xf_permission AS permission ON
				(permission.permission_id = entry.permission_id
				AND permission.permission_group_id = entry.permission_group_id)
			WHERE entry.permission_value <> \'unset\'
		');
		while ($entry = $entryResult->fetch())
		{
			$value = ($entry['permission_type'] == 'flag' ? $entry['permission_value'] : $entry['permission_value_int']);
			$pgId = $entry['permission_group_id'];
			$pId = $entry['permission_id'];

			if ($entry['user_id'])
			{
				$entries['users'][$entry['user_id']][$pgId][$pId] = $value;
			}
			else if ($entry['user_group_id'])
			{
				$entries['userGroups'][$entry['user_group_id']][$pgId][$pId] = $value;
			}
			else
			{
				$entries['system'][$pgId][$pId] = $value;
			}
		}

		return $entries;
	}

	/**
	 * Gets all global-level permission entries for a user collection,
	 * grouped into their respective permission (not interface) groups.
	 *
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return array Format: [permission_group_id][permission_id] => permission_info
	 */
	public function getAllGlobalPermissionEntriesForUserCollectionGrouped($userGroupId = 0, $userId = 0)
	{
		$this->_sanitizeUserIdAndUserGroupForQuery($userGroupId, $userId);

		$permissionResult = $this->_getDb()->query('
			SELECT *
			FROM xf_permission_entry
			WHERE user_group_id = ? AND user_id = ?
		', array($userGroupId, $userId));
		$permissions = array();
		while ($permission = $permissionResult->fetch())
		{
			$permissions[$permission['permission_group_id']][$permission['permission_id']] = $permission;
		}

		return $permissions;
	}

	/**
	 * Gets all content-level permission entries for a user collection,
	 * grouped into their respective permission (not interface) groups.
	 *
	 * @param string $contentTypeId
	 * @param integer $contentId
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return array Format: [permission_group_id][permission_id] => permission_info
	 */
	public function getAllContentPermissionEntriesForUserCollectionGrouped(
		$contentTypeId, $contentId, $userGroupId = 0, $userId = 0
	)
	{
		$this->_sanitizeUserIdAndUserGroupForQuery($userGroupId, $userId);

		$permissionResult = $this->_getDb()->query('
			SELECT *
			FROM xf_permission_entry_content
			WHERE content_type = ? AND content_id = ?
				AND user_group_id = ? AND user_id = ?
		', array($contentTypeId, $contentId, $userGroupId, $userId));
		$permissions = array();
		while ($permission = $permissionResult->fetch())
		{
			$permissions[$permission['permission_group_id']][$permission['permission_id']] = $permission;
		}

		return $permissions;
	}

	/**
	 * Gets all content permission entries for a type in an undefined order, grouped by the
	 * "level" of the permission. This is generally only needed for internal cache rebuilds.
	 *
	 * Note that entries with a value of "unset" will not be returned by this.
	 *
	 * @return array Format: ['users'][user id][content id][group][permission] => permission value;
	 * 		['userGroups'][user group id][content id][group][permission] => permission value;
	 * 		['system'][content id][group][permission] => permission value
	 */
	public function getAllContentPermissionEntriesByTypeGrouped($permissionType)
	{
		$entries = array(
			'users' => array(),
			'userGroups' => array(),
			'system' => array()
		);

		$entryResult = $this->_getDb()->query('
			SELECT entry_content.*, permission.permission_type
			FROM xf_permission_entry_content AS entry_content
			INNER JOIN xf_permission AS permission ON
				(permission.permission_id = entry_content.permission_id
				AND permission.permission_group_id = entry_content.permission_group_id)
			WHERE entry_content.content_type = ?
				AND entry_content.permission_value <> \'unset\'
		', $permissionType);
		while ($entry = $entryResult->fetch())
		{
			$value = ($entry['permission_type'] == 'flag' ? $entry['permission_value'] : $entry['permission_value_int']);
			$pgId = $entry['permission_group_id'];
			$pId = $entry['permission_id'];
			$cId = $entry['content_id'];

			if ($entry['user_id'])
			{
				$entries['users'][$entry['user_id']][$cId][$pgId][$pId] = $value;
			}
			else if ($entry['user_group_id'])
			{
				$entries['userGroups'][$entry['user_group_id']][$cId][$pgId][$pId] = $value;
			}
			else
			{
				$entries['system'][$cId][$pgId][$pId] = $value;
			}
		}

		return $entries;
	}

	/**
	 * Returns true if a user has specific permissions set.
	 *
	 * @param integer $userId
	 *
	 * @return boolean
	 */
	public function permissionsForUserExist($userId)
	{
		if (!$userId)
		{
			return false;
		}

		$db = $this->_getDb();

		if ($db->fetchOne($db->limit('
			SELECT 1
			FROM xf_permission_entry
			WHERE user_id = ?
				AND permission_value <> \'unset\'
		', 1), $userId))
		{
			return true;
		}
		else if ($db->fetchOne($db->limit('
			SELECT 1
			FROM xf_permission_entry_content
			WHERE user_id = ?
				AND permission_value <> \'unset\'
		', 1), $userId))
		{
			return true;
		}

		return false;
	}

	/**
	 * Gets information about all permission combinations. Note that this function
	 * does not return the cached permission data!
	 *
	 * @return array Format: [] => permission combo info (id, user, user group list)
	 */
	public function getAllPermissionCombinations()
	{
		return $this->_getDb()->fetchAll('
			SELECT permission_combination_id, user_id, user_group_list
			FROM xf_permission_combination
			ORDER BY permission_combination_id
		');
	}

	/**
	 * Gets the specified permission combination, including permission cache.
	 *
	 * @param integer $combinationId
	 *
	 * @return false|array Permission combination if, it it exists
	 */
	public function getPermissionCombinationById($combinationId)
	{
		if (!$combinationId)
		{
			return false;
		}

		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_permission_combination
			WHERE permission_combination_id = ?
		', $combinationId);
	}

	/**
	 * Gets the permission combination that applies to a user. Returns false if
	 * no user ID is specified.
	 *
	 * @param integer $userId
	 *
	 * @return false|array Permission combo info
	 */
	public function getPermissionCombinationByUserId($userId)
	{
		if (!$userId)
		{
			return false;
		}

		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_permission_combination
			WHERE user_id = ?
		', $userId);
	}

	/**
	 * Gets all permission combinations that involve the specified user group.
	 *
	 * @param integer $userGroupId
	 *
	 * @return array Format: [permission_combination_id] => permission combination info
	 */
	public function getPermissionCombinationsByUserGroupId($userGroupId)
	{
		return $this->fetchAllKeyed('
			SELECT combination.permission_combination_id, combination.user_id, combination.user_group_list
			FROM xf_permission_combination_user_group AS combination_user_group
			INNER JOIN xf_permission_combination AS combination ON
				(combination.permission_combination_id = combination_user_group.permission_combination_id)
			WHERE combination_user_group.user_group_id = ?
		', 'permission_combination_id', $userGroupId);
	}

	/**
	 * Updates the provded set of global permissions for a user collection
	 * (user group, user, system-wide).
	 *
	 * @param array $newPermissions Permissions to update, format: [permission_group_id][permission_id] => value
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return boolean
	 */
	public function updateGlobalPermissionsForUserCollection(array $newPermissions, $userGroupId = 0, $userId = 0)
	{
		$existingEntries = $this->getAllGlobalPermissionEntriesForUserCollectionGrouped($userGroupId, $userId);
		$dwData = array(
			'user_group_id' => $userGroupId,
			'user_id' => $userId
		);

		return $this->_updatePermissionsForUserCollection(
			$newPermissions, $existingEntries, $userGroupId, $userId,
			'XenForo_DataWriter_PermissionEntry', $dwData
		);
	}

	/**
	 * Updates the provded set of global permissions for a user collection
	 * (user group, user, system-wide).
	 *
	 * @param array $newPermissions Permissions to update, format: [permission_group_id][permission_id] => value
	 * @param string $contentTypeId
	 * @param integer $contentId
	 * @param integer $userGroupId
	 * @param integer $userId
	 *
	 * @return boolean
	 */
	public function updateContentPermissionsForUserCollection(
		array $newPermissions, $contentTypeId, $contentId, $userGroupId = 0, $userId = 0
	)
	{
		$existingEntries = $this->getAllContentPermissionEntriesForUserCollectionGrouped(
			$contentTypeId, $contentId, $userGroupId, $userId
		);
		$dwData = array(
			'user_group_id' => $userGroupId,
			'user_id' => $userId,
			'content_type' => $contentTypeId,
			'content_id' => $contentId
		);

		return $this->_updatePermissionsForUserCollection(
			$newPermissions, $existingEntries, $userGroupId, $userId,
			'XenForo_DataWriter_PermissionEntryContent', $dwData
		);
	}

	/**
	 * Internal handler to update global or content permissions for the specified user collection.
	 *
	 * @param array $newPermissions Permissions to update, format: [permission_group_id][permission_id] => value
	 * @param array $existingEntries Existing permission entries for this collection
	 * @param integer $userGroupId
	 * @param integer $userId
	 * @param string $dwName Name of the data writer to use to insert/update data
	 * @param array $bulkData Bulk data to give to the datawriter
	 *
	 * @return boolean
	 */
	protected function _updatePermissionsForUserCollection(
		array $newPermissions, array $existingEntries, $userGroupId, $userId,
		$dwName, array $bulkData
	)
	{
		$existingPermissions = $this->getAllPermissionsGrouped();

		XenForo_Db::beginTransaction();

		foreach ($newPermissions AS $groupId => $groupPermissions)
		{
			if (!is_array($groupPermissions) || !isset($existingPermissions[$groupId]))
			{
				continue;
			}

			foreach ($groupPermissions AS $permissionId => $permissionValue)
			{
				if (!isset($existingPermissions[$groupId][$permissionId]))
				{
					continue;
				}

				$permissionDw = XenForo_DataWriter::create($dwName);
				if (isset($existingEntries[$groupId][$permissionId]))
				{
					$permissionDw->setExistingData($existingEntries[$groupId][$permissionId], true);
				}
				else
				{
					$permissionDw->bulkSet($bulkData);
					$permissionDw->set('permission_group_id', $groupId);
					$permissionDw->set('permission_id', $permissionId);
				}

				if ($existingPermissions[$groupId][$permissionId]['permission_type'] == 'integer')
				{
					if (intval($permissionValue) == 0)
					{
						if (isset($existingEntries[$groupId][$permissionId]))
						{
							$permissionDw->delete();
						}
						continue;
					}

					$permissionDw->set('permission_value', 'use_int');
					$permissionDw->set('permission_value_int', $permissionValue);
				}
				else
				{
					if ($permissionValue == 'unset')
					{
						if (isset($existingEntries[$groupId][$permissionId]))
						{
							$permissionDw->delete();
						}
						continue;
					}

					$permissionDw->set('permission_value', $permissionValue);
					$permissionDw->set('permission_value_int', 0);
				}

				$permissionDw->save();
			}
		}

		if ($userId)
		{
			$this->updateUserPermissionCombination($userId, false);
			$this->rebuildPermissionCacheForUserId($userId);
		}
		else if ($userGroupId)
		{
			$this->rebuildPermissionCacheForUserGroup($userGroupId);
		}
		else
		{
			$this->rebuildPermissionCache();
		}

		XenForo_Db::commit();

		return true;
	}

	/**
	 * Prepares an array of user groups into the list that is used in permission
	 * combination lookups (comma delimited, ascending order).
	 *
	 * @param array $userGroupIds List of user group IDs
	 *
	 * @return string Comma delimited, sorted string of user group IDs
	 */
	protected function _prepareCombinationUserGroupList(array $userGroupIds)
	{
		$userGroupIds = array_unique($userGroupIds);
		sort($userGroupIds, SORT_NUMERIC);

		return implode(',', $userGroupIds);
	}

	/**
	 * Gets a permission combination ID based on a specific user role (user ID if there are specific
	 * permissions and a list of user group ID).
	 *
	 * @param integer $userId
	 * @param array $userGroupIds
	 *
	 * @return integer|false Combination ID or false
	 */
	public function getPermissionCombinationIdByUserRole($userId, array $userGroupIds)
	{
		$userGroupList = $this->_prepareCombinationUserGroupList($userGroupIds);

		return $this->_getDb()->fetchOne('
			SELECT permission_combination_id
			FROM xf_permission_combination
			WHERE user_id = ? AND user_group_list = ?
		', array($userId, $userGroupList));
	}

	/**
	 * Updates a user's permission combination based on the current state in the database.
	 *
	 * @param integer|array $userId Integer user ID or array of user info
	 * @param boolean $buildOnCreate If true, the permission cache for a combination will be built if it's created
	 * @param boolean $checkForUserPerms If false, doesn't look for user perms. Mostly an optimization
	 *
	 * @return false|integer Combination ID for the user if possible
	 */
	public function updateUserPermissionCombination($userId, $buildOnCreate = true, $checkForUserPerms = true)
	{
		if (is_array($userId))
		{
			$user = $userId;
			if (!isset($user['user_id']))
			{
				return false;
			}
			$userId = $user['user_id'];
		}
		else
		{
			$user = $this->_getUserModel()->getUserById($userId);
			if (!$user)
			{
				return false;
			}
		}

		$originalCombination = $this->getPermissionCombinationById($user['permission_combination_id']);

		$combinationId = $this->findOrCreatePermissionCombinationFromUser($user, $buildOnCreate, $checkForUserPerms);
		if ($combinationId != $user['permission_combination_id'])
		{
			$db = $this->_getDb();
			$db->update('xf_user',
				array('permission_combination_id' => $combinationId),
				'user_id = ' . $db->quote($userId)
			);

			// if changing combinations and the old combination used this user_id, delete it
			if ($originalCombination && $originalCombination['user_id'] == $userId)
			{
				$this->deletePermissionCombination($originalCombination['permission_combination_id']);
			}
		}

		return $combinationId;
	}

	/**
	 * Updates the permission combinations for a bunch of users.
	 *
	 * @param array $userIds
	 * @param boolean $buildOnCreate
	 */
	public function updateUserPermissionCombinations(array $userIds, $buildOnCreate = true)
	{
		$users = $this->_getUserModel()->getUsersByIds($userIds);
		if (!$users)
		{
			return;
		}

		foreach ($users AS $user)
		{
			$combinationId = $this->findOrCreatePermissionCombinationFromUser($user, $buildOnCreate);
			if ($combinationId != $user['permission_combination_id'])
			{
				$db = $this->_getDb();
				$db->update('xf_user',
					array('permission_combination_id' => $combinationId),
					'user_id = ' . $db->quote($user['user_id'])
				);
			}
		}
	}

	/**
	 * Deletes the sepcified permission combination.
	 *
	 * @param integer $combinationId
	 */
	public function deletePermissionCombination($combinationId)
	{
		$db = $this->_getDb();

		$combinationCondition = 'permission_combination_id = ' . $db->quote($combinationId);

		$db->delete('xf_permission_combination', $combinationCondition);
		$db->delete('xf_permission_combination_user_group', $combinationCondition);
		$db->delete('xf_permission_cache_content', $combinationCondition);
		$db->delete('xf_permission_cache_content_type', $combinationCondition);
		$db->delete('xf_permission_cache_global_group', $combinationCondition);
	}

	/**
	 * Finds an existing permission combination or creates a new one from a user info array.
	 *
	 * @param array $user User info
	 * @param boolean $buildOnCreate Build the permission combo cache if it must be created
	 * @param boolean $checkForUserPerms If false, assumes there are no user perms (optimization)
	 *
	 * @return integer Permission combination ID
	 */
	public function findOrCreatePermissionCombinationFromUser(array $user, $buildOnCreate = true, $checkForUserPerms = true)
	{
		$userId = $user['user_id'];
		if ($checkForUserPerms)
		{
			$userIdForPermissions = ($this->permissionsForUserExist($userId) ? $userId : 0);
		}
		else
		{
			$userIdForPermissions = 0;
		}

		if (isset($user['secondary_group_ids']) && $user['secondary_group_ids'] != '')
		{
			$userGroups = explode(',', $user['secondary_group_ids']);
		}
		else
		{
			$userGroups = array();
		}
		$userGroups[] = $user['user_group_id'];

		return $this->findOrCreatePermissionCombination($userIdForPermissions, $userGroups, $buildOnCreate);
	}

	/**
	 * Finds or creates a permission combination using the specified combination parameters.
	 * The user ID should only be provided if permissions exist for that user.
	 *
	 * @param integer $userId User ID, if there are user-specific permissions
	 * @param array $userGroupIds List of user group IDs
	 * @param boolean $buildOnCreate Build permission combo cache if created
	 *
	 * @return integer Permission combination ID
	 */
	public function findOrCreatePermissionCombination($userId, array $userGroupIds, $buildOnCreate = true)
	{
		$permissionCombinationId = $this->getPermissionCombinationIdByUserRole($userId, $userGroupIds);
		if ($permissionCombinationId)
		{
			return $permissionCombinationId;
		}

		$db = $this->_getDb();

		$userGroupList = $this->_prepareCombinationUserGroupList($userGroupIds);

		$combination = array(
			'user_id' => $userId,
			'user_group_list' => $userGroupList,
			'cache_value' => ''
		);

		$db->insert('xf_permission_combination', $combination);
		$combination['permission_combination_id'] = $db->lastInsertId('xf_permission_combination', 'permission_combination_id');

		foreach (explode(',', $userGroupList) AS $userGroupId)
		{
			$db->insert('xf_permission_combination_user_group', array(
				'user_group_id' => $userGroupId,
				'permission_combination_id' => $combination['permission_combination_id']
			));
		}

		if ($buildOnCreate)
		{
			$this->rebuildPermissionCache();
		}

		return $combination['permission_combination_id'];
	}

	/**
	* Rebuilds the permission cache for the specified user ID. A combination with
	* this user ID must exist for a rebuild to be triggered.
	*
	* @param integer $userId
	*
	* @return boolean True on success (false if no cache needs to be updated)
	*/
	public function rebuildPermissionCacheForUserId($userId)
	{
		$combination = $this->getPermissionCombinationByUserId($userId);
		if (!$combination)
		{
			return false;
		}

		$entries = $this->getAllPermissionEntriesGrouped();
		$permissionsGrouped = $this->getAllPermissionsGrouped();

		$this->rebuildPermissionCombination($combination, $permissionsGrouped, $entries);

		return true;
	}

	/**
	 * Rebuilds all permission cache data for combinations that involve the specified
	 * user group.
	 *
	 * @param integer $userGroupId
	 *
	 * @return boolean True on success
	 */
	public function rebuildPermissionCacheForUserGroup($userGroupId)
	{
		$combinations = $this->getPermissionCombinationsByUserGroupId($userGroupId);
		if (!$combinations)
		{
			return false;
		}

		$entries = $this->getAllPermissionEntriesGrouped();
		$permissionsGrouped = $this->getAllPermissionsGrouped();

		foreach ($combinations AS $combination)
		{
			$this->rebuildPermissionCombination($combination, $permissionsGrouped, $entries);
		}

		return true;
	}

	/**
	 * Rebuilds all permission cache entries.
	 *
	 * @param integer $maxExecution Limit execution time
	 * @param integer $startCombinationId If specified, starts the rebuild at the specified combination ID
	 *
	 * @return boolean|integer True when totally complete; the next combination ID to start with otherwise
	 */
	public function rebuildPermissionCache($maxExecution = 0, $startCombinationId = 0)
	{
		$entries = $this->getAllPermissionEntriesGrouped();
		$permissionsGrouped = $this->getAllPermissionsGrouped();
		$combinations = $this->getAllPermissionCombinations();

		$startTime = microtime(true);
		$restartCombinationId = false;

		XenForo_Db::beginTransaction();

		foreach ($combinations AS $combination)
		{
			if ($combination['permission_combination_id'] < $startCombinationId)
			{
				continue;
			}

			$this->rebuildPermissionCombination($combination, $permissionsGrouped, $entries);

			if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
			{
				$restartCombinationId = $combination['permission_combination_id'] + 1; // next one
				break;
			}
		}

		XenForo_Db::commit();

		return ($restartCombinationId ? $restartCombinationId : true);
	}

	/**
	 * Rebuilds the specified permission combination and updates the cache.
	 *
	 * @param array $combination Permission combination info
	 * @param array $permissionsGrouped List of valid permissions, grouped
	 * @param array $entries List of permission entries, with keys system/users/userGroups
	 *
	 * @return array Permission cache for this combination.
	 */
	public function rebuildPermissionCombination(array $combination, array $permissionsGrouped, array $entries)
	{
		$userGroupIds = explode(',', $combination['user_group_list']);
		$userId = $combination['user_id'];

		$groupEntries = array();
		foreach ($userGroupIds AS $userGroupId)
		{
			if (isset($entries['userGroups'][$userGroupId]))
			{
				$groupEntries[$userGroupId] = $entries['userGroups'][$userGroupId];
			}
		}

		if ($userId && isset($entries['users'][$userId]))
		{
			$userEntries = $entries['users'][$userId];
		}
		else
		{
			$userEntries = array();
		}

		$db = $this->_getDb();

		$permCache = $this->buildPermissionCacheForCombination(
			$permissionsGrouped, $entries['system'], $groupEntries, $userEntries
		);

		$finalCache = $this->canonicalizePermissionCache($permCache);

		XenForo_Db::beginTransaction($db);

		$db->update('xf_permission_combination', array(
			'cache_value' => serialize($finalCache)
		), 'permission_combination_id = ' . $db->quote($combination['permission_combination_id']));

		foreach ($finalCache AS $groupId => $groupCache)
		{
			$db->query('
				INSERT INTO xf_permission_cache_global_group
					(permission_combination_id, permission_group_id, cache_value)
				VALUES
					(?, ?, ?)
				ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value)
			', array($combination['permission_combination_id'], $groupId, serialize($groupCache)));
		}

		$this->rebuildContentPermissionCombination($combination, $permissionsGrouped, $permCache);

		XenForo_Db::commit($db);

		return $permCache;
	}

	/**
	 * Rebuilds the content permission cache for the specified combination. This
	 * function will rebuild permissions for all types of content and all pieces
	 * of content for that type.
	 *
	 * @param array $combination Array of combination information
	 * @param array $permissionsGrouped List of permissions, grouped
	 * @param array $permCache Global permission cache for this combination, with values of unset, etc. May be modified by ref.
	 */
	public function rebuildContentPermissionCombination(array $combination, array $permissionsGrouped, array &$permCache)
	{
		$userGroups = explode(',', $combination['user_group_list']);
		$db = $this->_getDb();

		$contentHandlers = $this->getContentPermissionTypeHandlers();

		foreach ($contentHandlers AS $contentTypeId => $handler)
		{
			$cacheEntries = $handler->rebuildContentPermissions(
				$this, $userGroups, $combination['user_id'], $permissionsGrouped, $permCache
			);

			if (!is_array($cacheEntries))
			{
				continue;
			}

			$db->query('
				INSERT INTO xf_permission_cache_content_type
					(permission_combination_id, content_type, cache_value)
				VALUES
					(?, ?, ?)
				ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value)
			', array($combination['permission_combination_id'], $contentTypeId, serialize($cacheEntries)));

			$rows = array();

			foreach ($cacheEntries AS $contentId => $entry)
			{
				$rows[] = '(' . $db->quote($combination['permission_combination_id'])
					. ', ' . $db->quote($contentTypeId)
					. ', ' . $db->quote($contentId)
					. ', ' . $db->quote(serialize($entry)) . ')';

				if (count($rows) >= 150)
				{
					$db->query('
						INSERT INTO xf_permission_cache_content
							(permission_combination_id, content_type, content_id, cache_value)
						VALUES
							' . implode(', ', $rows) . '
						ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value)
					');
					$rows = array();
				}
			}

			if ($rows)
			{
				$db->query('
					INSERT INTO xf_permission_cache_content
						(permission_combination_id, content_type, content_id, cache_value)
					VALUES
						' . implode(', ', $rows) . '
					ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value)
				');
			}
		}
	}

	/**
	 * Builds the permission cache for a given combination (via user groups and user ID).
	 *
	 * @param array $permissions List of valid permissions, grouped
	 * @param array $systemEntries List of system-wide permission entries
	 * @param array $goupEntries List of user group permission entries; an array of arrays
	 * @param array $userEntries List of user-specific permission entries (if any)
	 * @param array $basePermissions Base set of permissions to use as a starting point
	 *
	 * @return array Permission cache details
	 */
	public function buildPermissionCacheForCombination(
		array $permissionsGrouped, array $systemEntries, array $groupEntries, array $userEntries,
		array $basePermissions = array()
	)
	{
		$entrySets = $groupEntries;
		if ($systemEntries)
		{
			$entrySets[] = $systemEntries;
		}
		if ($userEntries)
		{
			$entrySets[] = $userEntries;
		}

		$cache = array();
		foreach ($permissionsGrouped AS $groupId => $permissions)
		{
			foreach ($permissions AS $permissionId => $permission)
			{
				$permissionType = $permission['permission_type'];

				if (isset($basePermissions[$groupId], $basePermissions[$groupId][$permissionId]))
				{
					$permissionValue = $basePermissions[$groupId][$permissionId];
				}
				else
				{
					$permissionValue = ($permissionType == 'integer' ? 0 : 'unset');
				}

				foreach ($entrySets AS $entries)
				{
					$permissionValue = $this->_getPermissionPriorityValueFromList(
						$permissionValue, $entries, $permissionType, $groupId, $permissionId, $permission['depend_permission_id']
					);
				}

				$cache[$groupId][$permissionId] = $permissionValue;
			}

			// second pass to catch dependent permissions that shouldn't be more than their parent
			foreach ($permissions AS $permissionId => $permission)
			{
				if ($permission['depend_permission_id'] && isset($cache[$groupId][$permission['depend_permission_id']]))
				{
					$parentValue = $cache[$groupId][$permission['depend_permission_id']];

					if ($parentValue == 'deny' || $parentValue == 'reset')
					{
						$cache[$groupId][$permissionId] = ($permission['permission_type'] == 'integer' ? 0 : 'deny');
					}
				}
			}
		}

		return $cache;
	}

	/**
	 * Canonicalizes permission cache data into integers or true/false values from
	 * a version with deny/allow/unset/etc values. This is the actual representation
	 * to be used externally.
	 *
	 * @param array $cache Permission cache info with allow/unset/deny/etc values
	 *
	 * @return array Permission cache with true/false values
	 */
	public function canonicalizePermissionCache(array $cache)
	{
		$newCache = array();
		foreach ($cache AS $cacheKey => $value)
		{
			if (is_array($value))
			{
				$newCache[$cacheKey] = $this->canonicalizePermissionCache($value);
			}
			else
			{
				if (is_int($value))
				{
					$newCache[$cacheKey] = intval($value);
				}
				else
				{
					$newCache[$cacheKey] = ($value == 'allow' || $value == 'content_allow');
				}
			 }
		}

		return $newCache;
	}

	/**
	 * Gets the value of a permission using the priority list. For flag permissions,
	 * higher priority (lower numbers) will take priority over the already existing values.
	 * For integers, -1 (unlimited) is highest priority; otherwise, higher numbers are better.
	 *
	 * @param string $existingValue Existing permission value (strings like unset, allow, deny, etc)
	 * @param array $permissionEntries List of permission entries to look through. First key is group, second is permission ID.
	 * @param string $permissionType Type of permission (integer or flag)
	 * @param string $permissionGroupId Permission Group ID to check
	 * @param string $permissionId Permission ID to check
	 * @param string $dependPermissionId The permission this one depends on; if this permission is not active, this permission is ignored
	 *
	 * @return string New priority value
	 */
	protected function _getPermissionPriorityValueFromList(
		$existingValue, array $permissionEntries, $permissionType,
		$permissionGroupId, $permissionId, $dependPermissionId
	)
	{
		$newValue = null;

		/*if ($dependPermissionId)
		{
			if (isset($permissionEntries[$permissionGroupId][$dependPermissionId]))
			{
				$dependValue = $permissionEntries[$permissionGroupId][$dependPermissionId];
			}
			else
			{
				$dependValue = 'unset';
			}

			if ($dependValue != 'allow' && $dependValue != 'content_allow')
			{
				$newValue = ($permissionType == 'integer' ? 0 : $dependValue);
			}
		}*/

		if ($newValue === null)
		{
			if (isset($permissionEntries[$permissionGroupId][$permissionId]))
			{
				$newValue = $permissionEntries[$permissionGroupId][$permissionId];
			}
			else
			{
				$newValue = ($permissionType == 'integer' ? 0 : 'unset');
			}
		}

		return $this->_getMergedPermissionPriorityValue($existingValue, $newValue, $permissionType);
	}

	/**
	 * Gets the merged the permission priority value.
	 *
	 * @param string|int $existingValue Existing value for the permission (int, or unset/allow/etc)
	 * @param string|int $newValue New value for the permission (int, unset/allow/etc)
	 * @param string $permissionType "integer" or "flag"
	 *
	 * @return string|int Effective value for the permission, using the priority list
	 */
	protected function _getMergedPermissionPriorityValue($existingValue, $newValue, $permissionType)
	{
		if ($permissionType == 'integer')
		{
			if (strval($existingValue) === '-1')
			{
				return $existingValue;
			}
			else if (strval($newValue) === '-1' || $newValue > $existingValue)
			{
				return intval($newValue);
			}
		}
		else if (self::$_permissionPriority[$newValue] < self::$_permissionPriority[$existingValue])
		{
			return $newValue;
		}

		return $existingValue;
	}

	/**
	 * Gets all content permission types in an undefined order.
	 *
	 * @return array Format: [content type] => permission handler class name
	 */
	public function getContentPermissionTypes()
	{
		return $this->_getDb()->fetchPairs('
			SELECT content_type, field_value
			FROM xf_content_type_field
			WHERE field_name = \'permission_handler_class\'
		');
	}

	/**
	 * Gets objects that handle permission type build requests.
	 *
	 * @return array Format: [permission type id] => XenForo_ContentPermission_Interface object
	 */
	public function getContentPermissionTypeHandlers()
	{
		$localCacheKey = 'contentPermissionTypeHandlers';
		if (($handlers = $this->_getLocalCacheData($localCacheKey)) !== false)
		{
			return $handlers;
		}

		$permissionContentTypes = $this->getContentPermissionTypes();
		$handlers = array();

		foreach ($permissionContentTypes AS $contentType => $handlerClass)
		{
			if (!XenForo_Application::autoload($handlerClass))
			{
				continue;
			}

			$handler = new $handlerClass();
			if (!($handler instanceof XenForo_ContentPermission_Interface))
			{
				continue;
			}

			$handlers[$contentType] = $handler;
		}

		$this->setLocalCacheData($localCacheKey, $handlers);

		return $handlers;
	}

	/**
	 * Gets the valid permission choices for the selected type of permission
	 * (based on context).
	 *
	 * @param string $type Type of permission. Values: system, user, userGroup
	 * @param boolean $contentSpecific True if dealing with content-specific permissions.
	 *
	 * @return array Key-value pairs of choices for this type of permission
	 */
	public function getPermissionChoices($type, $contentSpecific)
	{
		switch (strtolower($type))
		{
			case 'system':
				if ($contentSpecific)
				{
					return array('unset' => new XenForo_Phrase('inherit'), 'reset' => new XenForo_Phrase('revoke'));
				}
				else
				{
					return array('unset' => new XenForo_Phrase('not_set_no'), 'deny' => new XenForo_Phrase('never'));
				}
				break;

			case 'user':
			case 'usergroup':
				if ($contentSpecific)
				{
					return array(
						'unset' => new XenForo_Phrase('inherit'),
						'content_allow' => new XenForo_Phrase('allow'),
						'reset' => new XenForo_Phrase('revoke'),
						'deny' => new XenForo_Phrase('never')
					);
				}
				else
				{
					return array(
						'unset' => new XenForo_Phrase('not_set_no'),
						'allow' => new XenForo_Phrase('allow'),
						'deny' => new XenForo_Phrase('never')
					);
				}
				break;

			default:
				throw new XenForo_Exception('Invalid permission choice type');
		}
	}

	/**
	 * Gets the phrase name for a permission.
	 *
	 * @param string $permissionGroupId
	 * @param string $permissionId
	 *
	 * @return string
	 */
	public function getPermissionTitlePhraseName($permissionGroupId, $permissionId)
	{
		return 'permission_' . $permissionGroupId . '_' . $permissionId;
	}

	/**
	 * Gets a permission's master title phrase text.
	 *
	 * @param string $permissionGroupId
	 * @param string $permissionId
	 *
	 * @return string
	 */
	public function getPermissionMasterTitlePhraseValue($permissionGroupId, $permissionId)
	{
		$phraseName = $this->getPermissionTitlePhraseName($permissionGroupId, $permissionId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the phrase name for a permission group.
	 *
	 * @param string $permissionGroupId
	 *
	 * @return string
	 */
	public function getPermissionGroupTitlePhraseName($permissionGroupId)
	{
		return 'permission_group_' . $permissionGroupId;
	}

	/**
	 * Gets a permission group's master title phrase text.
	 *
	 * @param string $permissionGroupId
	 *
	 * @return string
	 */
	public function getPermissionGroupMasterTitlePhraseValue($permissionGroupId)
	{
		$phraseName = $this->getPermissionGroupTitlePhraseName($permissionGroupId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the phrase name for a permission interface group.
	 *
	 * @param string $interfaceGroupId
	 *
	 * @return string
	 */
	public function getPermissionInterfaceGroupTitlePhraseName($interfaceGroupId)
	{
		return 'permission_interface_' . $interfaceGroupId;
	}

	/**
	 * Gets a permission interface group's master title phrase text.
	 *
	 * @param string $interfaceGroupId
	 *
	 * @return string
	 */
	public function getPermissionInterfaceGroupMasterTitlePhraseValue($interfaceGroupId)
	{
		$phraseName = $this->getPermissionInterfaceGroupTitlePhraseName($interfaceGroupId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets all permission groups that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [] => permission group info
	 */
	public function getPermissionGroupsByAddOn($addOnId)
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_permission_group
			WHERE addon_id = ?
			ORDER BY permission_group_id
		', $addOnId);
	}

	/**
	 * Gets all permissions that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [] => permission info
	 */
	public function getPermissionsByAddOn($addOnId)
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_permission
			WHERE addon_id = ?
			ORDER BY permission_group_id, permission_id
		', $addOnId);
	}

	/**
	 * Gets all permission interface groups that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [] => permission interface group info
	 */
	public function getPermissionInterfaceGroupsByAddOn($addOnId)
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_permission_interface_group
			WHERE addon_id = ?
			ORDER BY interface_group_id
		', $addOnId);
	}

	/**
	 * Gets the file name for the development output.
	 *
	 * @return string
	 */
	public function getPermissionsDevelopmentFileName()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/permissions.xml';
	}

	/**
	 * Determines if the permissions development file is writable. If the file
	 * does not exist, it checks whether the parent directory is writable.
	 *
	 * @param $fileName
	 *
	 * @return boolean
	 */
	public function canWritePermissionsDevelopmentFile($fileName)
	{
		return file_exists($fileName) ? is_writable($fileName) : is_writable(dirname($fileName));
	}

	/**
	 * Gets the permission development XML document.
	 *
	 * @return DOMDocument
	 */
	public function getPermissionsDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('permissions');
		$document->appendChild($rootNode);

		$this->appendPermissionsAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Appends the add-on navigation XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all navigation elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendPermissionsAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$permissionGroups = $this->getPermissionGroupsByAddOn($addOnId);
		$permissions = $this->getPermissionsByAddOn($addOnId);
		$interfaceGroups = $this->getPermissionInterfaceGroupsByAddOn($addOnId);

		$document = $rootNode->ownerDocument;

		$groupsNode = $document->createElement('permission_groups');
		$rootNode->appendChild($groupsNode);

		foreach ($permissionGroups AS $permissionGroup)
		{
			$groupNode = $document->createElement('permission_group');
			$groupNode->setAttribute('permission_group_id', $permissionGroup['permission_group_id']);
			$groupsNode->appendChild($groupNode);
		}

		$permissionsNode = $document->createElement('permissions');
		$rootNode->appendChild($permissionsNode);

		foreach ($permissions AS $permission)
		{
			$permissionNode = $document->createElement('permission');
			$permissionNode->setAttribute('permission_group_id', $permission['permission_group_id']);
			$permissionNode->setAttribute('permission_id', $permission['permission_id']);
			$permissionNode->setAttribute('permission_type', $permission['permission_type']);
			if ($permission['depend_permission_id'])
			{
				$permissionNode->setAttribute('depend_permission_id', $permission['depend_permission_id']);
			}
			if ($permission['permission_type'] == 'integer')
			{
				$permissionNode->setAttribute('default_value_int', $permission['default_value_int']);
			}
			else
			{
				$permissionNode->setAttribute('default_value', $permission['default_value']);
			}
			$permissionNode->setAttribute('interface_group_id', $permission['interface_group_id']);
			$permissionNode->setAttribute('display_order', $permission['display_order']);

			$permissionsNode->appendChild($permissionNode);
		}

		$interfaceGroupsNode = $document->createElement('interface_groups');
		$rootNode->appendChild($interfaceGroupsNode);

		foreach ($interfaceGroups AS $interfaceGroup)
		{
			$groupNode = $document->createElement('interface_group');
			$groupNode->setAttribute('interface_group_id', $interfaceGroup['interface_group_id']);
			$groupNode->setAttribute('display_order', $interfaceGroup['display_order']);

			$interfaceGroupsNode->appendChild($groupNode);
		}
	}

	/**
	 * Deletes the permissions that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deletePermissionsForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$addOnClause = 'addon_id = ' . $db->quote($addOnId);

		$db->delete('xf_permission', $addOnClause);
		$db->delete('xf_permission_group', $addOnClause);
		$db->delete('xf_permission_interface_group', $addOnClause);
	}

	/**
	 * Imports the development permissions XML data.
	 *
	 * @param string $fileName File to read the XML from
	 */
	public function importPermissionsDevelopmentXml($fileName)
	{
		$document = new SimpleXMLElement($fileName, 0, true);
		$this->importPermissionsAddOnXml($document, 'XenForo');
	}

	/**
	 * Imports the add-on permissions XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the navigation data
	 * @param string $addOnId Add-on to import for
	 */
	public function importPermissionsAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);
		$this->deletePermissionsForAddOn($addOnId);

		$groups = ($xml->permission_groups
			? XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->permission_groups->permission_group)
			: array()
		);
		$permissions = ($xml->permissions
			? XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->permissions->permission)
			: array()
		);
		$interfaceGroups = ($xml->interface_groups
			? XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->interface_groups->interface_group)
			: array()
		);

		$permissionGroupIds = array();
		foreach ($groups AS $group)
		{
			$permissionGroupIds[] = (string)$group['permission_group_id'];
		}

		$permissionIdPairs = array();
		foreach ($permissions AS $permission)
		{
			$permissionIdPairs[] = array(
				(string)$permission['permission_group_id'],
				(string)$permission['permission_id']
			);
		}

		$interfaceGroupIds = array();
		foreach ($interfaceGroups AS $group)
		{
			$interfaceGroupIds[] = (string)$group['interface_group_id'];
		}

		$existingGroups = $this->getPermissionGroupsByIds($permissionGroupIds);
		$existingPermissions = $this->getPermissionsByPairs($permissionIdPairs);
		$existingInterfaceGroups = $this->getPermissionInterfaceGroupsByIds($interfaceGroupIds);

		foreach ($groups AS $group)
		{
			$groupId = (string)$group['permission_group_id'];

			$groupDw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionGroup');
			if (isset($existingGroups[$groupId]))
			{
				$groupDw->setExistingData($existingGroups[$groupId], true);
			}
			$groupDw->setOption(XenForo_DataWriter_PermissionGroup::OPTION_REBUILD_CACHE, false);
			$groupDw->bulkSet(array(
				'permission_group_id' => $groupId,
				'addon_id' => $addOnId
			));
			$groupDw->save();
		}

		foreach ($permissions AS $permission)
		{
			$groupId = (string)$permission['permission_group_id'];
			$permissionId = (string)$permission['permission_id'];

			$permissionDw = XenForo_DataWriter::create('XenForo_DataWriter_Permission');
			if (isset($existingPermissions[$groupId], $existingPermissions[$groupId][$permissionId]))
			{
				$permissionDw->setExistingData($existingPermissions[$groupId][$permissionId], true);
			}
			$permissionDw->setOption(XenForo_DataWriter_Permission::OPTION_REBUILD_CACHE, false);
			$permissionDw->setOption(XenForo_DataWriter_Permission::OPTION_DEPENDENT_CHECK, false);
			$permissionDw->bulkSet(array(
				'permission_id' => (string)$permission['permission_id'],
				'permission_group_id' => (string)$permission['permission_group_id'],
				'permission_type' => (string)$permission['permission_type'],
				'depend_permission_id' => (string)$permission['depend_permission_id'],
				'interface_group_id' => (string)$permission['interface_group_id'],
				'display_order' => (string)$permission['display_order'],
				'addon_id' => $addOnId
			));
			if ((string)$permission['permission_type'] == 'integer')
			{
				$permissionDw->set('default_value_int', (string)$permission['default_value_int']);
			}
			else
			{
				$permissionDw->set('default_value', (string)$permission['default_value']);
			}

			$permissionDw->save();
		}

		foreach ($interfaceGroups AS $group)
		{
			$groupId = (string)$group['interface_group_id'];

			$groupDw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionInterfaceGroup');
			if (isset($existingInterfaceGroups[$groupId]))
			{
				$groupDw->setExistingData($existingInterfaceGroups[$groupId], true);
			}
			$groupDw->bulkSet(array(
				'interface_group_id' => $groupId,
				'display_order' => (string)$group['display_order'],
				'addon_id' => $addOnId
			));
			$groupDw->save();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Gets all users that have global, custom user permissions.
	 *
	 * @return array [user id] => info
	 */
	public function getUsersWithGlobalUserPermissions()
	{
		return $this->fetchAllKeyed('
			SELECT user.*
			FROM xf_permission_entry AS permission_entry
			INNER JOIN xf_user AS user ON
				(user.user_id = permission_entry.user_id)
			INNER JOIN xf_permission AS permission ON
				(permission.permission_group_id = permission_entry.permission_group_id
				AND permission.permission_id = permission_entry.permission_id)
			WHERE permission_entry.user_group_id = 0
				AND permission_entry.user_id > 0
			GROUP BY permission_entry.user_id
			ORDER BY user.username
		', 'user_id');
	}

	public function getUsersWithContentUserPermissions($contentType, $contentId)
	{
		return $this->fetchAllKeyed('
			SELECT user.*
			FROM xf_permission_entry_content AS permission_entry_content
			INNER JOIN xf_user AS user ON
				(user.user_id = permission_entry_content.user_id)
			INNER JOIN xf_permission AS permission ON
				(permission.permission_group_id = permission_entry_content.permission_group_id
				AND permission.permission_id = permission_entry_content.permission_id)
			WHERE permission_entry_content.content_type = ?
				AND permission_entry_content.content_id = ?
				AND permission_entry_content.user_group_id = 0
				AND permission_entry_content.user_id > 0
			GROUP BY permission_entry_content.user_id
			ORDER BY user.username
		', 'user_id', array($contentType, $contentId));
	}

	public function getUserCombinationsWithContentPermissions($contentType, $contentId = null)
	{
		$db = $this->_getDb();

		return $db->fetchAll('
			SELECT DISTINCT entry.content_id, entry.user_group_id, entry.user_id
			FROM xf_permission_entry_content AS entry
			INNER JOIN xf_permission AS permission ON
				(permission.permission_group_id = entry.permission_group_id
				AND permission.permission_id = entry.permission_id)
			LEFT JOIN xf_user AS user ON (user.user_id = entry.user_id AND entry.user_id > 0)
			LEFT JOIN xf_user_group AS user_group ON (user_group.user_group_id = entry.user_group_id AND entry.user_group_id > 0)
			WHERE entry.content_type = ?
				AND (
					user.user_id IS NOT NULL
					OR user_group.user_group_id IS NOT NULL
					OR (entry.user_id = 0 AND entry.user_group_id = 0)
				)
				' . ($contentId !== null ? ' AND entry.content_id = ' . $db->quote($contentId) : '') . '
		', $contentType);
	}

	/**
	 * Get user group model.
	 *
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}

	/**
	 * Get user model.
	 *
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * Gets the phrase model object.
	 *
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}