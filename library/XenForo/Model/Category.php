<?php

/**
 * Model for categories.
 *
 * @package XenForo_Category
 */
class XenForo_Model_Category extends XenForo_Model
{
	/**
	 * Determines if the specified category can be viewed with the given permissions.
	 *
	 * @param array $category Info about the category posting in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions List of permissions for this page; if not provided, use visitor's permissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewCategory(array $category, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($category['node_id'], $viewingUser, $nodePermissions);

		return XenForo_Permission::hasContentPermission($nodePermissions, 'view');
	}
}