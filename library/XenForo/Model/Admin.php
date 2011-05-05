<?php

/**
 * Model for admins and admin permissions.
 *
 * @package XenForo_Admin
 */
class XenForo_Model_Admin extends XenForo_Model
{
	/**
	 * @var array of super admin IDs
	 */
	protected $_superAdmins = null;

	/**
	 * Gets the admin with the specified user ID.
	 *
	 * @param integer $userId
	 *
	 * @return array|false
	 */
	public function getAdminById($userId)
	{
		return $this->_getDb()->fetchRow('
			SELECT admin.*, user.*
			FROM xf_admin AS admin
			INNER JOIN xf_user AS user ON (user.user_id = admin.user_id)
			WHERE admin.user_id = ?
		', $userId);
	}

	/**
	 * Gets all admins ordered by username.
	 *
	 * @return array Format: [user id] => info
	 */
	public function getAllAdmins()
	{
		return $this->prepareAdminRecords($this->fetchAllKeyed('
			SELECT admin.*, user.*
			FROM xf_admin AS admin
			INNER JOIN xf_user AS user ON (user.user_id = admin.user_id)
			ORDER BY user.username
		', 'user_id'));
	}

	/**
	 * Returns the total number of users who are administrators
	 *
	 * @return integer
	 */
	public function countAdmins()
	{
		return $this->_getDb()->fetchOne('SELECT COUNT(*) FROM xf_admin');
	}

	/**
	 * Gets all admin permissions, ordered by display order.
	 *
	 * @return array Format: [admin permission id] => info
	 */
	public function getAllAdminPermissions()
	{
		return $this->fetchAllKeyed('
			SELECT admin_permission.*,
				addon.addon_id, addon.title AS addonTitle
			FROM xf_admin_permission AS admin_permission
			LEFT JOIN xf_addon AS addon ON
				(addon.addon_id = admin_permission.addon_id)
			ORDER BY display_order
		', 'admin_permission_id');
	}

	/**
	 * Gets the specified admin permission.
	 *
	 * @param string $permissionId
	 *
	 * @return array|false
	 */
	public function getAdminPermissionById($permissionId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_admin_permission
			WHERE admin_permission_id = ?
		', $permissionId);
	}

	/**
	 * Gets admin permissions with the specified IDs.
	 *
	 * @param array $permissionIds
	 *
	 * @return array Format: [admin permission id] => info
	 */
	public function getAdminPermissionsByIds(array $permissionIds)
	{
		if (!$permissionIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_admin_permission
			WHERE admin_permission_id IN (' . $this->_getDb()->quote($permissionIds) . ')
		', 'admin_permission_id');
	}

	/**
	 * Gets the list of admin permissions, with has_permission values for
	 * the specified user. Can be used with user 0.
	 *
	 * @param integer $userId
	 *
	 * @return array Format: [admin permission id] => info, including has_permission
	 */
	public function getAdminPermissionsForUser($userId)
	{
		return $this->fetchAllKeyed('
			SELECT admin_permission.*,
				IF(admin_permission_entry.user_id IS NULL, 0, 1) AS has_permission
			FROM xf_admin_permission AS admin_permission
			LEFT JOIN xf_admin_permission_entry AS admin_permission_entry ON
				(admin_permission.admin_permission_id = admin_permission_entry.admin_permission_id
				AND admin_permission_entry.user_id = ?)
			ORDER BY admin_permission.display_order
		', 'admin_permission_id', $userId);
	}

	/**
	 * Gets the admin permissions as options for the specified user. User 0 is ok.
	 *
	 * @param integer $userId
	 *
	 * @return array Format: [] => keys: value (permission ID), label, selected
	 */
	public function getAdminPermissionOptionsForUser($userId)
	{
		$permissions = $this->getAdminPermissionsForUser($userId);
		$output = array();
		foreach ($permissions AS $permission)
		{
			$permission = $this->prepareAdminPermission($permission);
			$output[] = array(
				'value' => $permission['admin_permission_id'],
				'label' => $permission['title'],
				'selected' => $permission['has_permission']
			);
		}

		return $output;
	}

	/**
	 * Gets all admin permissions as simple key-value pairs.
	 *
	 * @return array Format: [admin permission id] => title (XenForo_Phrase)
	 */
	public function getAdminPermissionPairs()
	{
		$permissions = $this->prepareAdminPermissions($this->getAllAdminPermissions());
		$output = array();
		foreach ($permissions AS $permission)
		{
			$output[$permission['admin_permission_id']] = $permission['title'];
		}

		return $output;
	}

	/**
	 * Gets all admin permissions that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [admin permission id] => info
	 */
	public function getAdminPermissionsForAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_admin_permission
			WHERE addon_id = ?
			ORDER BY admin_permission_id
		', 'admin_permission_id', $addOnId);
	}

	/**
	 * Gets the admin permission cache for the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getAdminPermissionCacheForUser($userId)
	{
		$cache = $this->_getDb()->fetchOne('
			SELECT permission_cache
			FROM xf_admin
			WHERE user_id = ?
		', $userId);
		if (!$cache)
		{
			return array();
		}
		else
		{
			return unserialize($cache);
		}
	}

	/**
	 * Prepares an admin permission for display.
	 *
	 * @param array $permission
	 *
	 * @return array
	 */
	public function prepareAdminPermission(array $permission)
	{
		$permission['title'] = new XenForo_Phrase($this->getAdminPermissionTitlePhraseName(
			$permission['admin_permission_id']
		));

		return $permission;
	}

	/**
	 * Prepares a list of admin permissions for display.
	 *
	 * @param array $permissions
	 *
	 * @return array
	 */
	public function prepareAdminPermissions(array $permissions)
	{
		foreach ($permissions AS &$permission)
		{
			$permission = $this->prepareAdminPermission($permission);
		}

		return $permissions;
	}

	/**
	 * Gets the master value for the specified admin permission's title.
	 *
	 * @param string $permissionId
	 *
	 * @return string
	 */
	public function getAdminPermissionMasterTitlePhraseValue($permissionId)
	{
		$phraseName = $this->getAdminPermissionTitlePhraseName($permissionId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the name of the phrase for the specified admin permission title.
	 *
	 * @param string $permissionId
	 *
	 * @return string
	 */
	public function getAdminPermissionTitlePhraseName($permissionId)
	{
		return 'admin_permission_' . $permissionId;
	}

	/**
	 * Updates the admin permissions set for the specified user. All permissions
	 * are replaced by the given set. Set is expected to be an array with values
	 * of the permission IDs.
	 *
	 * @param integer $userId
	 * @param array $newPermissions Format: [] => permission ID
	 */
	public function updateUserAdminPermissions($userId, array $newPermissions)
	{
		$db = $this->_getDb();

		$db->delete('xf_admin_permission_entry', 'user_id = ' . $db->quote($userId));

		$keyCache = array();
		foreach ($newPermissions AS $newPermissionId)
		{
			$db->insert('xf_admin_permission_entry', array(
				'user_id' => $userId,
				'admin_permission_id' => $newPermissionId
			));
			$keyCache[$newPermissionId] = true;
		}

		$db->update('xf_admin', array(
			'permission_cache' => serialize($keyCache)
		), 'user_id = ' . $db->quote($userId));
	}

	/**
	 * Rebuilds the admin permission cache for all users. This is generally
	 * only needed when deleting/renaming a permission.
	 */
	public function rebuildUserAdminPermissionCache()
	{
		$db = $this->_getDb();
		$permissions = array();
		$permissionsSql = $db->query('
			SELECT admin_permission_entry.user_id, admin_permission_entry.admin_permission_id
			FROM xf_admin_permission_entry AS admin_permission_entry
			INNER JOIN xf_admin_permission AS admin_permission ON
				(admin_permission.admin_permission_id = admin_permission_entry.admin_permission_id)
		');
		while ($permission = $permissionsSql->fetch())
		{
			$permissions[$permission['user_id']][$permission['admin_permission_id']] = true;
		}

		foreach ($permissions AS $userId => $permissionCache)
		{
			$db->update('xf_admin', array(
				'permission_cache' => serialize($permissionCache)
			), 'user_id = ' . $db->quote($userId));
		}
	}

	/**
	 * Gets the file name for the development output.
	 *
	 * @return string
	 */
	public function getAdminPermissionsDevelopmentFileName()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/admin_permissions.xml';
	}

	/**
	 * Gets the admin permission development XML document.
	 *
	 * @return DOMDocument
	 */
	public function getAdminPermissionsDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('admin_permissions');
		$document->appendChild($rootNode);

		$this->appendAdminPermissionsAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Appends the admin permissions for an add-on XML to the given node.
	 *
	 * @param DOMElement $rootNode
	 * @param string $addOnId
	 */
	public function appendAdminPermissionsAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$adminPermissions = $this->getAdminPermissionsForAddOn($addOnId);

		$document = $rootNode->ownerDocument;

		foreach ($adminPermissions AS $permission)
		{
			$permissionNode = $document->createElement('admin_permission');
			$permissionNode->setAttribute('admin_permission_id', $permission['admin_permission_id']);
			$permissionNode->setAttribute('display_order', $permission['display_order']);
			$rootNode->appendChild($permissionNode);
		}
	}

	/**
	 * Deletes the admin permissions that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteAdminPermissionsForAddOn($addOnId)
	{
		$db = $this->_getDb();
		$db->delete('xf_admin_permission', 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports the development admin permissions XML data.
	 *
	 * @param string $fileName File to read the XML from
	 */
	public function importAdminPermissionsDevelopmentXml($fileName)
	{
		$document = new SimpleXMLElement($fileName, 0, true);
		$this->importAdminPermissionsAddOnXml($document, 'XenForo');
	}

	/**
	 * Imports the add-on admin permission XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the  data
	 * @param string $addOnId Add-on to import for
	 */
	public function importAdminPermissionsAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);
		$this->deleteAdminPermissionsForAddOn($addOnId);

		$xmlPermissions = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->admin_permission);

		$adminPermissionIds = array();
		foreach ($xmlPermissions AS $adminPermission)
		{
			$adminPermissionIds[] = (string)$adminPermission['admin_permission_id'];
		}

		$existingPermissions = $this->getAdminPermissionsByIds($adminPermissionIds);

		foreach ($xmlPermissions AS $adminPermission)
		{
			$adminPermissionId = (string)$adminPermission['admin_permission_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminPermission');
			if (isset($existingPermissions[$adminPermissionId]))
			{
				$dw->setExistingData($existingPermissions[$adminPermissionId], true);
			}
			$dw->bulkSet(array(
				'admin_permission_id' => $adminPermissionId,
				'display_order' => (string)$adminPermission['display_order'],
				'addon_id' => $addOnId
			));
			$dw->save();
		}

		$this->rebuildUserAdminPermissionCache();

		XenForo_Db::commit($db);
	}

	/**
	 * Determines whether or not a user is a super admin
	 *
	 * @param integer User ID
	 *
	 * @return boolean
	 */
	public function isSuperAdmin($userId)
	{
		if ($this->_superAdmins === null)
		{
			$this->_superAdmins = preg_split(
				'/\s*,\s*/', XenForo_Application::get('config')->superAdmins,
				-1, PREG_SPLIT_NO_EMPTY
			);
		}

		return in_array($userId, $this->_superAdmins);
	}

	public function prepareAdminRecords(array $admins)
	{
		return array_map(array($this, 'prepareAdminRecord'), $admins);
	}

	public function prepareAdminRecord(array $admin)
	{
		$admin['is_super_admin'] = $this->isSuperAdmin($admin['user_id']);

		return $admin;
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}