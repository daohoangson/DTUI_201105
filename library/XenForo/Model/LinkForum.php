<?php

class XenForo_Model_LinkForum extends XenForo_Model
{
	/**
	 * Fetches the combined node-link record for the specified node id
	 *
	 * @param integer $id Node ID
	 *
	 * @return array
	 */
	public function getLinkForumById($id, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareLinkForumJoinOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT node.*, link_forum.*
				' . $joinOptions['selectFields'] . '
			FROM xf_link_forum AS link_forum
			INNER JOIN xf_node AS node ON (node.node_id = link_forum.node_id)
			' . $joinOptions['joinTables'] . '
			WHERE node.node_id = ?
		', $id);
	}

	/**
	 * Checks the 'join' key of the incoming array for the presence of the FETCH_x bitfields in this class
	 * and returns SQL snippets to join the specified tables if required
	 *
	 * @param array $fetchOptions Array containing a 'join' integer key build from this class's FETCH_x bitfields and other keys
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys. Example: selectFields = ', user.*, foo.title'; joinTables = ' INNER JOIN foo ON (foo.id = other.id) '
	 */
	public function prepareLinkForumJoinOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		$db = $this->_getDb();

		if (!empty($fetchOptions['permissionCombinationId']))
		{
			$selectFields .= ',
				permission.cache_value AS node_permission_cache';
			$joinTables .= '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $db->quote($fetchOptions['permissionCombinationId']) . '
						AND permission.content_type = \'node\'
						AND permission.content_id = link_forum.node_id)';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Determines if the specified link forum can be viewed with the given permissions.
	 *
	 * @param array $linkForum Info about the link forum
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions List of permissions for this page; if not provided, use visitor's permissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewLinkForum(array $linkForum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($linkForum['node_id'], $viewingUser, $nodePermissions);

		return XenForo_Permission::hasContentPermission($nodePermissions, 'view');
	}
}