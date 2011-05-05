<?php

/**
 * Model to handle inline mod-style actions on profile posts. Generally, these are simply
 * bulk actions. They can be applied to other circumstances if desired.
 *
 * @package XenForo_ProfilePost
 */
class XenForo_Model_InlineMod_ProfilePost extends XenForo_Model
{
	/**
	 * Determines if the selected profile post IDs can be deleted.
	 *
	 * @param array $profilePostIds List of IDs check
	 * @param string $deleteType The type of deletion being requested (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteProfilePosts(array $profilePostIds, $deleteType = 'soft', &$errorKey = '', array $viewingUser = null)
	{
		list($profilePosts, $users) = $this->getProfilePostsAndParentData($profilePostIds);
		return $this->canDeleteProfilePostsData($profilePosts, $deleteType, $users, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected profile post data can be deleted.
	 *
	 * @param array $profilePosts List of data to be deleted
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param array $users List of information about users whose profiles the posts are on
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteProfilePostsData(array $profilePosts, $deleteType, array $users, &$errorKey = '', array $viewingUser = null)
	{
		// note: this cannot use _checkPermissionOnPosts because of extra param
		if (!$profilePosts)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$profilePostModel = $this->_getProfilePostModel();

		foreach ($profilePosts AS $profilePost)
		{
			$user = $users[$profilePost['profile_user_id']];
			if (!$profilePostModel->canDeleteProfilePost($profilePost, $user, $deleteType, $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes the specified profile posts if permissions are sufficient.
	 *
	 * @param array $profilePostIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function deleteProfilePosts(array $profilePostIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'deleteType' => '',
				'reason' => ''
			), $options
		);

		if (!$options['deleteType'])
		{
			throw new XenForo_Exception('No deletion type specified.');
		}

		list($profilePosts, $users) = $this->getProfilePostsAndParentData($profilePostIds);

		if (empty($options['skipPermissions']) && !$this->canDeleteProfilePostsData($profilePosts, $options['deleteType'], $users, $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($profilePosts AS $profilePost)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($profilePost);
			if (!$dw->get('profile_post_id'))
			{
				// this may happen if the post was already removed
				continue;
			}
			if ($options['deleteType'] == 'hard')
			{
				$dw->delete();
			}
			else
			{
				$dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_DELETE_REASON, $options['reason']);
				$dw->set('message_state', 'deleted');
				$dw->save();
			}
		}

		return true;
	}

	/**
	 * Determines if the selected profile post IDs can be undeleted.
	 *
	 * @param array $profilePostIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUndeleteProfilePosts(array $profilePostIds, &$errorKey = '', array $viewingUser = null)
	{
		list($profilePosts, $users) = $this->getProfilePostsAndParentData($profilePostIds);
		return $this->canUndeleteProfilePostsData($profilePosts, $users, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected profile post data can be undeleted.
	 *
	 * @param array $profilePosts List of data to be checked
	 * @param array $users List of information about users whose profiles the posts are on
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUndeleteProfilePostsData(array $profilePosts, array $users, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnProfilePosts('canUndeleteProfilePost', $profilePosts, $users, $errorKey, $viewingUser);
	}

	/**
	 * Undeletes the specified profile posts if permissions are sufficient.
	 *
	 * @param array $profilePostIds List of IDs to undelete
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function undeleteProfilePosts(array $profilePostIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($profilePosts, $users) = $this->getProfilePostsAndParentData($profilePostIds);

		if (empty($options['skipPermissions']) && !$this->canUndeleteProfilePostsData($profilePosts, $users, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateProfilePostsMessageState($profilePosts, $users, 'visible', 'deleted');

		return true;
	}

	/**
	 * Determines if the selected profile post IDs can be approved/unapproved.
	 *
	 * @param array $profilePostIds List of IDs to check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canApproveUnapproveProfilePosts(array $profilePostIds, &$errorKey = '', array $viewingUser = null)
	{
		list($profilePosts, $users) = $this->getProfilePostsAndParentData($profilePostIds);
		return $this->canApproveUnapproveProfilePostsData($profilePosts, $users, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected profile post data can be approved/unapproved. T
	 *
	 * @param array $profilePosts List of data to be checked
	 * @param array $users List of information about users whose profiles the posts are on
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canApproveUnapproveProfilePostsData(array $profilePosts, array $users, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnProfilePosts('canApproveUnapproveProfilePost', $profilePosts, $users, $errorKey, $viewingUser);
	}

	/**
	 * Approves the specified profile posts if permissions are sufficient.
	 *
	 * @param array $profilePostIds List of IDs to approve
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function approveProfilePosts(array $profilePostIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($profilePosts, $users) = $this->getProfilePostsAndParentData($profilePostIds);

		if (empty($options['skipPermissions']) && !$this->canApproveUnapproveProfilePostsData($profilePosts, $users, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateProfilePostsMessageState($profilePosts, $users, 'visible', 'moderated');

		return true;
	}

	/**
	 * Unapproves the specified profile posts if permissions are sufficient.
	 *
	 * @param array $profilePostIds List of IDs to unapprove
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function unapproveProfilePosts(array $profilePostIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($profilePosts, $users) = $this->getProfilePostsAndParentData($profilePostIds);

		if (empty($options['skipPermissions']) && !$this->canApproveUnapproveProfilePostsData($profilePosts, $users, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateProfilePostsMessageState($profilePosts, $users, 'moderated', 'visible');

		return true;
	}


	/**
	 * Checks a standard post permission against a collection of profile posts.
	 * True is returned only if the action is possible on all profile posts.
	 *
	 * @param string $permissionMethod Name of the permission method to call in the post model
	 * @param array $profilePosts List of profile posts to check
	 * @param array $users List of users whose profiles the posts are on
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	protected function _checkPermissionOnProfilePosts($permissionMethod, array $profilePosts, array $users, &$errorKey = '', array $viewingUser = null)
	{
		if (!$profilePosts)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);
		$profilePostModel = $this->_getProfilePostModel();

		foreach ($profilePosts AS $profilePost)
		{
			$user = $users[$profilePost['profile_user_id']];
			if (!$profilePostModel->$permissionMethod($profilePost, $user, $errorKey, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Internal helper to update the message_state of a collection of profile posts.
	 *
	 * @param array $profilePosts Information about the profile posts to update
	 * @param array $users List of users whose profiles the posts are on
	 * @param string $newState New message state (visible, moderated, deleted)
	 * @param string|false $expectedOldState If specified, only updates if the old state matches
	 */
	protected function _updateProfilePostsMessageState(array $profilePosts, array $users, $newState, $expectedOldState = false)
	{
		foreach ($profilePosts AS $profilePost)
		{
			if ($expectedOldState && $profilePost['message_state'] != $expectedOldState)
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($profilePost);
			$dw->set('message_state', $newState);
			$dw->save();
		}
	}

	/**
	 * Bulk update 1 or more fields in the given profile posts.
	 *
	 * @param array $profilePosts List of profile posts to update
	 * @param array $users List of users whose profiles the posts are on
	 * @param array $updates Key-value pairs to update
	 */
	protected function _updateProfilePostsBulk(array $profilePosts, array $users, array $updates)
	{
		foreach ($profilePosts AS $profilePost)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($profilePost);
			$dw->bulkSet($updates);
			$dw->save();
		}
	}

	/**
	 * From a List of IDs, gets info about the profile posts and the users whose profiles
	 * they are on.
	 *
	 * @param array $profilePostIds List of IDs
	 *
	 * @return array Format: [0] => list of profile posts, [1] => list of users
	 */
	public function getProfilePostsAndParentData(array $profilePostIds)
	{
		$profilePosts = $this->_getProfilePostModel()->getProfilePostsByIds($profilePostIds);

		$userIds = array();
		foreach ($profilePosts AS $profilePost)
		{
			$userIds[$profilePost['profile_user_id']] = true;
		}
		$users = $this->_getUserModel()->getUsersByIds(array_keys($userIds), array(
			'join' => XenForo_Model_User::FETCH_USER_FULL
		));

		return array($profilePosts, $users);
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		return $this->getModelFromCache('XenForo_Model_ProfilePost');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}