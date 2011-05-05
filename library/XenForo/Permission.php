<?php

/**
 * Permission accessor helper.
 *
 * @package XenForo_Permissions
 */
class XenForo_Permission
{
	/**
	 * Private constructor. Use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Gets the value (true, false, or int) of the specified permission if it exists.
	 * Used for global permissions which have a group and an individual permission.
	 *
	 * @param array $permissions Grouped permissions ([group][permission] => value)
	 * @param string $group Permission group
	 * @param string $permission Permission ID
	 *
	 * @return true|false|int False if the permission isn't found; the value of the permission otherwise
	 */
	public static function hasPermission(array $permissions, $group, $permission)
	{
		if (isset($permissions[$group], $permissions[$group][$permission]))
		{
			return $permissions[$group][$permission];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets the value (true, false, or int) of the specified content permission,
	 * if it exists. This differs from {@link hasPermission()} in that there is no group
	 * specified. The first dimension has the permissions.
	 *
	 * If the specified permission exists but is an array, an exception will be thrown.
	 *
	 * @param array $contentPermissions Format: [permission] => value
	 * @param string $permission Permission ID
	 *
	 * @return true|false|int False if the permission isn't found; the value of the permission otherwise
	 */
	public static function hasContentPermission(array $contentPermissions, $permission)
	{
		if (isset($contentPermissions[$permission]))
		{
			if (is_array($contentPermissions[$permission]))
			{
				throw new XenForo_Exception('Unexpected sub-array found in content permissions; looks more like global permissions');
			}

			return $contentPermissions[$permission];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Unserialize permissions from their format in the database to the array format
	 * that the other helper functions expect.
	 *
	 * @param string $permissionString
	 *
	 * @return array
	 */
	public static function unserializePermissions($permissionString)
	{
		if ($permissionString && !is_array($permissionString))
		{
			$permissions = unserialize($permissionString);
			if (is_array($permissions))
			{
				return $permissions;
			}
		}

		return array();
	}
}