<?php

/**
 * Class for reading data from the various permission caches.
 *
 * @package XenForo_Permission
 */
class XenForo_Model_PermissionCache extends XenForo_Model
{
	/**
	 * Gets the content permissions for a specified item.
	 *
	 * @param integer $permissionCombinationId Permission combination to read
	 * @param string $contentType Permission content type
	 * @param integer $contentId
	 *
	 * @return array
	 */
	public function getContentPermissionsForItem($permissionCombinationId, $contentType, $contentId)
	{
		return XenForo_Permission::unserializePermissions($this->_getDb()->fetchOne('
			SELECT cache_value
			FROM xf_permission_cache_content
			WHERE permission_combination_id = ?
				AND content_type = ?
				AND content_id = ?
		', array($permissionCombinationId, $contentType, $contentId)));
	}
}