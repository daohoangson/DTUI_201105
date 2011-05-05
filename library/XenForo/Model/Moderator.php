<?php

/**
 * Model for moderators.
 *
 * @package XenForo_Moderator
 */
class XenForo_Model_Moderator extends XenForo_Model
{
	/**
	 * Gets a general moderator records based on user ID.
	 *
	 * @param integer $userId
	 *
	 * @return array|false
	 */
	public function getGeneralModeratorByUserId($userId)
	{
		return $this->_getDb()->fetchRow('
			SELECT moderator.*, user.username
			FROM xf_moderator AS moderator
			INNER JOIN xf_user AS user ON (user.user_id = moderator.user_id)
			WHERE moderator.user_id = ?
		', $userId);
	}

	/**
	 * Gets all general moderators, potentially limited by super moderator status.
	 *
	 * @param boolean|null $isSuperModerator If not null, limits to super or non-super mods only
	 *
	 * @return array Format: [user id] => info
	 */
	public function getAllGeneralModerators($isSuperModerator = null)
	{
		if ($isSuperModerator === null)
		{
			$moderatorClause = '1=1';
		}
		else if ($isSuperModerator)
		{
			$moderatorClause = 'moderator.is_super_moderator = 1';
		}
		else
		{
			$moderatorClause = 'moderator.is_super_moderator = 0';
		}

		return $this->fetchAllKeyed('
			SELECT moderator.*, user.username
			FROM xf_moderator AS moderator
			INNER JOIN xf_user AS user ON (user.user_id = moderator.user_id)
			WHERE ' . $moderatorClause . '
			ORDER BY user.username
		', 'user_id');
	}

	/**
	 * Gets a matching content moderator.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array|false
	 */
	public function getContentModerator(array $conditions, array $fetchOptions = array())
	{
		$moderators = $this->getContentModerators($conditions, $fetchOptions);
		return reset($moderators);
	}

	/**
	 * Gets all matching content moderators.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array Format: [moderator id] => info
	 */
	public function getContentModerators(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareContentModeratorConditions($conditions, $fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$sqlClauses = $this->prepareContentModeratorFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT moderator_content.*, user.username
					' . $sqlClauses['selectFields'] . '
				FROM xf_moderator_content AS moderator_content
				INNER JOIN xf_user AS user ON (user.user_id = moderator_content.user_id)
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . '
				' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'moderator_id');
	}

	/**
	 * Prepares the set of content moderator conditions.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return string SQL clause value for conditions
	 */
	public function prepareContentModeratorConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (isset($conditions['moderator_id']))
		{
			$sqlConditions[] = 'moderator_content.moderator_id = ' . $db->quote($conditions['moderator_id']);
		}

		if (!empty($conditions['content']))
		{
			if (is_array($conditions['content']))
			{
				$sqlConditions[] = 'moderator_content.content_type = ' . $db->quote($conditions['content'][0]);
				$sqlConditions[] = 'moderator_content.content_id = ' . $db->quote($conditions['content'][1]);
			}
			else
			{
				$sqlConditions[] = 'moderator_content.content_type = ' . $db->quote($conditions['content']);
			}
		}

		if (isset($conditions['user_id']))
		{
			$sqlConditions[] = 'moderator_content.user_id = ' . $db->quote($conditions['user_id']);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Prepares the content moderator fetch options into select fields, joins, and ordering.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Keys: selectFields, joinTables, orderClause
	 */
	public function prepareContentModeratorFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (isset($fetchOptions['order']))
		{
			switch ($fetchOptions['order'])
			{
				case 'username':
					$orderBy = 'user.username';
					break;
			}
		}
		else
		{
			$orderBy = 'user.username';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	/**
	 * Gets a content moderator by its unique ID.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getContentModeratorById($id)
	{
		return $this->getContentModerator(array('moderator_id' => $id));
	}

	/**
	 * Gets a content moderator by the unique combination of content and user ID.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param integer $userId
	 *
	 * @return array|false
	 */
	public function getContentModeratorByContentAndUserId($contentType, $contentId, $userId)
	{
		return $this->getContentModerator(array(
			'content' => array($contentType, $contentId),
			'user_id' => $userId
		));
	}

	/**
	 * Gets all content moderator info for a specified user ID
	 *
	 * @param integer $userId
	 *
	 * @return array Format: [moderator id] => info
	 */
	public function getContentModeratorsByUserId($userId)
	{
		return $this->getContentModerators(
			array('user_id' => $userId),
			array('order' => false)
		);
	}

	/**
	 * Inserts or updates the necessary content moderator record.
	 *
	 * @param integer $userId
	 * @param string $contentType
	 * @param integer $contentId
	 * @param array $modPerms List of moderator permissions to apply to this content
	 * @param array $extra Extra info. Includes general_moderator_permissions and extra_user_group_ids
	 *
	 * @return integer Moderator ID
	 */
	public function insertOrUpdateContentModerator($userId, $contentType, $contentId, array $modPerms, array $extra = array())
	{
		$contentModerator = $this->getContentModeratorByContentAndUserId($contentType, $contentId, $userId);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorContent');
		if ($contentModerator)
		{
			$dw->setExistingData($contentModerator, true);
		}
		else
		{
			$dw->set('content_type', $contentType);
			$dw->set('content_id', $contentId);
			$dw->set('user_id', $userId);
		}

		if (isset($extra['general_moderator_permissions']))
		{
			$dw->setExtraData(XenForo_DataWriter_ModeratorContent::DATA_GENERAL_PERMISSIONS, $extra['general_moderator_permissions']);
		}
		if (isset($extra['extra_user_group_ids']))
		{
			$dw->setExtraData(XenForo_DataWriter_ModeratorContent::DATA_EXTRA_GROUP_IDS, $extra['extra_user_group_ids']);
		}

		$dw->set('moderator_permissions', $modPerms);
		$dw->save();

		return $dw->get('moderator_id');
	}

	/**
	 * Inserts or updates the necessary general moderator record.
	 *
	 * @param integer $userId
	 * @param array $modPerms General moderator permissions. Does not include content-specific super mod perms.
	 * @param boolean|null $isSuperModerator If non-null, the new super moderator setting
	 * @param array $extra Extra data, including extra_user_group_ids and super_moderator_permissions
	 *
	 * @return integer Moderator ID
	 */
	public function insertOrUpdateGeneralModerator($userId, array $modPerms, $isSuperModerator = null, array $extra = array())
	{
		$moderator = $this->getGeneralModeratorByUserId($userId);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Moderator');
		if ($moderator)
		{
			$dw->setExistingData($moderator, true);
		}
		else
		{
			$dw->set('user_id', $userId);
		}

		if ($isSuperModerator !== null)
		{
			$dw->set('is_super_moderator', $isSuperModerator);
		}

		if (isset($extra['extra_user_group_ids']))
		{
			$dw->set('extra_user_group_ids', $extra['extra_user_group_ids']);
		}

		if (isset($extra['super_moderator_permissions']))
		{
			$modPerms = $this->mergeModeratorPermissions($modPerms, $extra['super_moderator_permissions']);
		}

		$dw->set('moderator_permissions', $modPerms);
		$dw->save();

		return $userId;
	}

	/**
	 * Merges 2 sets of "grouped" moderator permissions.
	 *
	 * @param array $modPerms Existing permissions like [group][permission] => info
	 * @param array $merge Merging permissions like [group][permission] => info
	 *
	 * @return array Merged set
	 */
	public function mergeModeratorPermissions(array $modPerms, array $merge)
	{
		foreach ($merge AS $generalGroupId => $generalGroup)
		{
			foreach ($generalGroup AS $generalId => $general)
			{
				$modPerms[$generalGroupId][$generalId] = $general;
			}
		}

		return $modPerms;
	}

	/**
	 * Merges only general moderator permissions into a set of grouped permissions.
	 * The initial set may contain more than general moderator permissions.
	 *
	 * @param array $modPerms Existing permissions like [group][permission] => info
	 * @param array $merge Merging permissions like [group][permission] => info
	 *
	 * @return array Merged set
	 */
	public function mergeGeneralModeratorPermissions(array $modPerms, array $merge)
	{
		$generalModeratorPermissions = $this->getGeneralModeratorPermissions();

		foreach ($merge AS $generalGroupId => $generalGroup)
		{
			foreach ($generalGroup AS $generalId => $general)
			{
				if (isset($generalModeratorPermissions[$generalGroupId][$generalId]))
				{
					$modPerms[$generalGroupId][$generalId] = $general;
				}
			}
		}

		return $modPerms;
	}

	/**
	 * Merges a set of permission differences for setting/updating permission entries.
	 *
	 * @param array|string $newPermissions Set of new permissions (ie, new effective value). May be serialized.
	 * @param array|string $existingPermissions Set of old permissions (ie, old effective value). May be serialized.
	 * @param string $allowValue If a permission is to be allowed, the name of the allow state (allow or content_allow).
	 *
	 * @return array New effective permissions, with non-matching old values returned to "unset" state
	 */
	public function getModeratorPermissionsForUpdate($newPermissions, $existingPermissions, $allowValue = 'allow')
	{
		$finalPermissions = array();

		if (is_string($newPermissions))
		{
			$newPermissions = unserialize($newPermissions);
		}
		else if (!is_array($newPermissions))
		{
			$newPermissions = array();
		}

		foreach ($newPermissions AS $permissionGroupId => $permissionGroup)
		{
			foreach ($permissionGroup AS $permissionId => $value)
			{
				$finalPermissions[$permissionGroupId][$permissionId] = $allowValue;
			}
		}

		if (is_string($existingPermissions))
		{
			$existingPermissions = unserialize($existingPermissions);
		}
		else if (!is_array($existingPermissions))
		{
			$existingPermissions = array();
		}

		foreach ($existingPermissions AS $permissionGroupId => $permissionGroup)
		{
			foreach ($permissionGroup AS $permissionId => $value)
			{
				if (!isset($finalPermissions[$permissionGroupId][$permissionId]))
				{
					$finalPermissions[$permissionGroupId][$permissionId] = 'unset';
				}
			}
		}

		return $finalPermissions;
	}

	/**
	 * Gets the permission interface group IDs that apply to all general moderators.
	 *
	 * @return array
	 */
	public function getGeneralModeratorInterfaceGroupIds()
	{
		return array('generalModeratorPermissions', 'profilePostModeratorPermissions', 'conversationModeratorPermissions');
	}

	/**
	 * Gets the permission interface group IDs that apply to the moderator in question.
	 * If a content moderator, only includes general and that content's groups;
	 * if a super moderator, includes all matching groups;
	 * otherwise, includes only the general groups.
	 *
	 * @param array $moderator
	 *
	 * @return array List of interface group IDs
	 */
	public function getModeratorInterfaceGroupIds(array $moderator)
	{
		$interfaceGroupIds = $this->getGeneralModeratorInterfaceGroupIds();

		if (!empty($moderator['content_type']))
		{
			$handler = $this->getContentModeratorHandlers($moderator['content_type']);
			$interfaceGroupIds = array_merge($interfaceGroupIds, $handler->getModeratorInterfaceGroupIds());
		}
		else if (!empty($moderator['is_super_moderator']))
		{
			foreach($this->getContentModeratorHandlers() AS $handler)
			{
				$interfaceGroupIds = array_merge($interfaceGroupIds, $handler->getModeratorInterfaceGroupIds());
			}
		}

		return $interfaceGroupIds;
	}

	/**
	 * Gets all general moderator permissions.
	 *
	 * @return array Format: [group id][permission id] => permission info
	 */
	public function getGeneralModeratorPermissions()
	{
		return $this->getModeratorPermissions($this->getGeneralModeratorInterfaceGroupIds());
	}

	/**
	 * Gets moderator permissions from the specified interface groups.
	 *
	 * @param array $interfaceGroupIds
	 *
	 * @return array Format: [group id][permission id] => permission info
	 */
	public function getModeratorPermissions(array $interfaceGroupIds)
	{
		$permissions = $this->_getLocalCacheData('permissions');
		if ($permissions === false)
		{
			$permissions = $this->_getPermissionModel()->getAllPermissions();
			$this->setLocalCacheData('permissions', $permissions);
		}

		$validPermissions = array();
		foreach ($permissions AS $permission)
		{
			if ($permission['permission_type'] != 'flag')
			{
				continue;
			}

			if (in_array($permission['interface_group_id'], $interfaceGroupIds))
			{
				$validPermissions[$permission['permission_group_id']][$permission['permission_id']] = $permission;
			}
		}

		return $validPermissions;
	}

	/**
	 * Gets the necessary moderator permissions and interface groups for the UI,
	 *
	 * @param array $interfaceGroupIds List of interface groups to pull permissions from
	 * @param array $existingPermissions Existing permissions ([group id][permission id]), for selected values
	 *
	 * @return array List of interface groups, with "permissions" key (flat array)
	 */
	public function getModeratorPermissionsForInterface(array $interfaceGroupIds, array $existingPermissions = array())
	{
		$permissionModel = $this->_getPermissionModel();

		$interfaceGroups = $permissionModel->getAllPermissionInterfaceGroups();
		foreach ($interfaceGroups AS $interfaceGroupId => &$interfaceGroup)
		{
			if (!in_array($interfaceGroupId, $interfaceGroupIds))
			{
				unset($interfaceGroups[$interfaceGroupId]);
			}
			else
			{
				$interfaceGroup = $permissionModel->preparePermissionInterfaceGroup($interfaceGroup);
			}
		}

		foreach ($this->getModeratorPermissions($interfaceGroupIds) AS $groupId => $group)
		{
			foreach ($group AS $permissionId => $permission)
			{
				if (isset($interfaceGroups[$permission['interface_group_id']]))
				{
					$permission = $permissionModel->preparePermission($permission);
					$interfaceGroups[$permission['interface_group_id']]['permissions'][] = array(
						'label' => $permission['title'],
						'name'  => "[$permission[permission_group_id]][$permission[permission_id]]",
						'selected' => !empty($existingPermissions[$permission['permission_group_id']][$permission['permission_id']])
					);
				}
			}
		}

		return $interfaceGroups;
	}

	/**
	 * Gets the list of possible extra user groups in "option" format.
	 *
	 * @param string|array $extraGroupIds List of existing extra group IDs; may be serialized.
	 *
	 * @return array List of user group options (keys: label, value, selected)
	 */
	public function getExtraUserGroupOptions($extraGroupIds)
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup')->getUserGroupOptions(
			$extraGroupIds
		);
	}

	/**
	 * Gets all content moderator handler objects, or one for the specified content type.
	 *
	 * @param string|array|null $limitContentType If specified, gets handler for specified type(s) only
	 *
	 * @return XenForo_ModeratorHandler_Abstract|array|false
	 */
	public function getContentModeratorHandlers($limitContentType = null)
	{
		$contentTypes = $this->_getLocalCacheData('moderatorHandlerPairs');
		if ($contentTypes === false)
		{
			$contentTypes = $this->_getDb()->fetchPairs('
				SELECT content_type, field_value
				FROM xf_content_type_field
				WHERE field_name = \'moderator_handler_class\'
			');

			$this->setLocalCacheData('moderatorHandlerPairs', $contentTypes);
		}

		if (is_string($limitContentType))
		{
			if (isset($contentTypes[$limitContentType]))
			{
				$class = $contentTypes[$limitContentType];
				return new $class();
			}
			else
			{
				return false;
			}
		}
		else if (is_array($limitContentType))
		{
			$handlers = array();
			foreach ($contentTypes AS $contentType => $handlerClass)
			{
				if (in_array($contentType, $limitContentType))
				{
					$handlers[$contentType] = new $handlerClass();
				}
			}
		}
		else
		{
			$handlers = array();
			foreach ($contentTypes AS $contentType => $handlerClass)
			{
				$handlers[$contentType] = new $handlerClass();
			}
		}

		return $handlers;
	}

	/**
	 * Goes through a list of content moderators and fetches the content titles for all of them.
	 * Items that are not returned by the handler will not have a "title" key.
	 *
	 * @param array $moderators
	 *
	 * @return array Moderators with "title" key where given
	 */
	public function addContentTitlesToModerators(array $moderators)
	{
		$types = array();
		foreach ($moderators AS $key => $moderator)
		{
			if (!$moderator['content_type'])
			{
				continue;
			}

			$types[$moderator['content_type']][$key] = $moderator['content_id'];
		}

		if ($types)
		{
			$handlers = $this->getContentModeratorHandlers(array_keys($types));
			foreach ($handlers AS $contentType => $handler)
			{
				$titles = $handler->getContentTitles($types[$contentType]);
				foreach ($titles AS $key => $title)
				{
					$moderators[$key]['title'] = $title;
				}
			}
		}

		return $moderators;
	}

	/**
	 * Fetches an array containing $value for the value of each permission.
	 * Useful for automatically populating super moderator records with a full permission set.
	 *
	 * @param mixed Value for every permission
	 *
	 * @return array $permissionSet[$groupId][$permId] = $value;
	 */
	public function getFullPermissionSet($value = true)
	{
		$permissionSet = array();

		foreach ($this->getModeratorPermissions($this->getModeratorInterfaceGroupIds(array('is_super_moderator' => true))) AS $groupId => $group)
		{
			foreach ($group AS $permId => $perm)
			{
				$permissionSet[$groupId][$permId] = $value;
			}
		}

		return $permissionSet;
	}

	/**
	 * Returns the total number of members who are moderators
	 * Note: distinct on user_id
	 *
	 * @return integer
	 */
	public function countModerators()
	{
		return $this->_getDb()->fetchOne('SELECT COUNT(*) FROM xf_moderator');
	}

	/**
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}
}