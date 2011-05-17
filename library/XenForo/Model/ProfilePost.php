<?php

/**
 * Model for profile post related functions.
 *
 * @package XenForo_ProfilePost
 */
class XenForo_Model_ProfilePost extends XenForo_Model
{
	/**
	 * Constants to allow joins to extra tables in certain queries
	 *
	 * @var integer Join user table (poster)
	 * @var integer Join user table (receiver)
	 */
	const FETCH_USER_POSTER = 0x01;
	const FETCH_USER_RECEIVER = 0x02;
	const FETCH_DELETION_LOG = 0x04;

	/**
	 * Gets the specified profile post.
	 *
	 * @param integer $id
	 * @param array $fetchOptions
	 *
	 * @return array|false
	 */
	public function getProfilePostById($id, array $fetchOptions = array())
	{
		$sqlClauses = $this->prepareProfilePostFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT profile_post.*
				' . $sqlClauses['selectFields'] . '
			FROM xf_profile_post AS profile_post
			' . $sqlClauses['joinTables'] . '
			WHERE profile_post.profile_post_id = ?
		', $id);
	}

	/**
	 * Gets profile posts for the specified user that meet the given conditions.
	 *
	 * @param integer $userId
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array [profile post id] => info
	 */
	public function getProfilePostsForUserId($userId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['user_id'] = $userId;
		$whereClause = $this->prepareProfilePostConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareProfilePostFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT profile_post.*
					' . $sqlClauses['selectFields'] . '
				FROM xf_profile_post AS profile_post
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereClause . '
				ORDER BY profile_post.post_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'profile_post_id');
	}

	/**
	 * Counts the profile posts that were left for the specified user (with the given conditions).
	 *
	 * @param integer $userId
	 * @param array $conditions
	 *
	 * @return integer
	 */
	public function countProfilePostsForUserId($userId, array $conditions = array())
	{
		$fetchOptions = array();
		$conditions['user_id'] = $userId;
		$whereClause = $this->prepareProfilePostConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareProfilePostFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_profile_post AS profile_post
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	/**
	 * Gets the specified profile posts.
	 *
	 * @param array $messageIds
	 * @param array $fetchOptions
	 *
	 * @return array [profile post id] => info
	 */
	public function getProfilePostsByIds(array $messageIds, array $fetchOptions = array())
	{
		if (!$messageIds)
		{
			return array();
		}

		$sqlClauses = $this->prepareProfilePostFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT profile_post.*
				' . $sqlClauses['selectFields'] . '
			FROM xf_profile_post AS profile_post
			' . $sqlClauses['joinTables'] . '
			WHERE profile_post.profile_post_id IN(' . $this->_getDb()->quote($messageIds) . ')
		', 'profile_post_id');
	}

	/**
	 * Fetches all profile posts posted by the specified user, on their own and others' profile pages.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getProfilePostsByUserId($userId, array $fetchOptions = array())
	{
		$sqlClauses = $this->prepareProfilePostFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT profile_post.*
				' . $sqlClauses['selectFields'] . '
			FROM xf_profile_post AS profile_post
			' . $sqlClauses['joinTables'] . '
			WHERE profile_post.user_id = ?
		', 'profile_post_id', $userId);
	}

	/**
	 * Prepares a collection of profile post fetching related conditions into an SQL clause
	 *
	 * @param array $conditions List of conditions
	 * @param array $fetchOptions Modifiable set of fetch options (may have joins pushed on to it)
	 *
	 * @return string SQL clause (at least 1=1)
	 */
	public function prepareProfilePostConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['user_id']))
		{
			$sqlConditions[] = "profile_post.profile_user_id = " . $db->quote($conditions['user_id']);
		}

		if (!empty($conditions['post_date']) && is_array($conditions['post_date']))
		{
			list($operator, $cutOff) = $conditions['post_date'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "profile_post.post_date $operator " . $db->quote($cutOff);
		}

		if (isset($conditions['deleted']) || isset($conditions['moderated']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'profile_post', 'message_state');
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Checks the 'join' key of the incoming array for the presence of the FETCH_x bitfields in this class
	 * and returns SQL snippets to join the specified tables if required
	 *
	 * @param array $fetchOptions containing a 'join' integer key build from this class's FETCH_x bitfields
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys. Example: selectFields = ', user.*, foo.title'; joinTables = ' INNER JOIN foo ON (foo.id = other.id) '
	 */
	public function prepareProfilePostFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		$db = $this->_getDb();

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER_POSTER)
			{
				$selectFields .= ',
					posting_user.*,
					IF(posting_user.username IS NULL, profile_post.username, posting_user.username) AS username';
				$joinTables .= '
					LEFT JOIN xf_user AS posting_user ON
						(posting_user.user_id = profile_post.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER_RECEIVER)
			{
				$selectFields .= ',
					receiving_user.username AS profile_username';
				$joinTables .= '
					LEFT JOIN xf_user AS receiving_user ON
						(receiving_user.user_id = profile_post.profile_user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_DELETION_LOG)
				{
					$selectFields .= ',
						deletion_log.delete_date, deletion_log.delete_reason,
						deletion_log.delete_user_id, deletion_log.delete_username';
					$joinTables .= '
						LEFT JOIN xf_deletion_log AS deletion_log ON
							(deletion_log.content_type = \'profile_post\' AND deletion_log.content_id = profile_post.profile_post_id)';
				}
		}

		if (isset($fetchOptions['likeUserId']))
		{
			if (empty($fetchOptions['likeUserId']))
			{
				$selectFields .= ',
					0 AS like_date';
			}
			else
			{
				$selectFields .= ',
					liked_content.like_date';
				$joinTables .= '
					LEFT JOIN xf_liked_content AS liked_content
						ON (liked_content.content_type = \'profile_post\'
							AND liked_content.content_id = profile_post.profile_post_id
							AND liked_content.like_user_id = ' .$db->quote($fetchOptions['likeUserId']) . ')';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Prepares a profile post.
	 *
	 * @param array $profilePost
	 * @param array $user User whose profile the post is on
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return array
	 */
	public function prepareProfilePost(array $profilePost, array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$profilePost['canDelete'] = $this->canDeleteProfilePost($profilePost, $user, 'soft', $null, $viewingUser);
		$profilePost['canEdit'] = $this->canEditProfilePost($profilePost, $user, $null, $viewingUser);
		$profilePost['canLike'] = $this->canLikeProfilePost($profilePost, $user, $null, $viewingUser);
		$profilePost['canComment'] = $this->canCommentOnProfilePost($profilePost, $user, $null, $viewingUser);

		$profilePost['isDeleted'] = ($profilePost['message_state'] == 'deleted');
		$profilePost['isModerated'] = ($profilePost['message_state'] == 'moderated');

		$profilePost['canCleanSpam'] = (
			$profilePost['canDelete']
			&& $user['user_id']
			&& XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'cleanSpam')
			&& $this->_getUserModel()->couldBeSpammer($profilePost)
		);

		if (!empty($profilePost['delete_date']))
		{
			$profilePost['deleteInfo'] = array(
				'user_id' => $profilePost['delete_user_id'],
				'username' => $profilePost['delete_username'],
				'date' => $profilePost['delete_date'],
				'reason' => $profilePost['delete_reason'],
			);
		}

		if ($profilePost['likes'])
		{
			$profilePost['likeUsers'] = unserialize($profilePost['like_users']);
		}

		return $profilePost;
	}

	/**
	 * Prepares a batch of profile posts.
	 *
	 * @param array $profilePosts
	 * @param array $user User whose profile the post is on
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return array
	 */
	public function prepareProfilePosts(array $profilePosts, array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		foreach ($profilePosts AS &$profilePost)
		{
			$profilePost = $this->prepareProfilePost($profilePost, $user, $viewingUser);
		}

		return $profilePosts;
	}

	/**
	 * Determines if the given profile post can be viewed. This does not
	 * check user profile viewing permissions.
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canViewProfilePost(array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'view'))
		{
			return false;
		}

		if ($profilePost['message_state'] == 'moderated'
			&& !XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'viewModerated')
		)
		{
			if (!$viewingUser['user_id'] || $viewingUser['user_id'] != $profilePost['user_id'])
			{
				return false;
			}
		}
		else if ($profilePost['message_state'] == 'deleted'
			&& !XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'viewDeleted')
		)
		{
			return false;
		}

		return $this->_getUserProfileModel()->canViewProfilePosts($user, $errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if the given profile post can be viewed. This checks parent permissions.
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canViewProfilePostAndContainer(array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$this->_getUserProfileModel()->canViewFullUserProfile($user, $errorPhraseKey, $viewingUser))
		{
			return false;
		}

		return $this->canViewProfilePost($profilePost, $user, $errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if the given profile post can be edited. This does not
	 * check parent (viewing) permissions.
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canEditProfilePost(array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($viewingUser['user_id'] == $profilePost['user_id'])
		{
			return XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'editOwn');
		}
		else
		{
			return XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'editAny');
		}
	}

	/**
	 * Determines if the given profile post can be deleted. This does not
	 * check parent (viewing) permissions.
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteProfilePost(array $profilePost, array $user, $deleteType = 'soft', &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($deleteType != 'soft' && !XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'hardDeleteAny'))
		{
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'deleteAny'))
		{
			return true;
		}

		if ($viewingUser['user_id'] == $profilePost['user_id'])
		{
			return XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'deleteOwn');
		}
		else if ($viewingUser['user_id'] == $profilePost['profile_user_id'])
		{
			return XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'manageOwn');
		}
		else
		{
			return false;
		}
	}

	/**
	 * Determines if the given profile post can be undeleted. This does not
	 * check parent (viewing) permissions.
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUndeleteProfilePost(array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'undelete'));
	}

	/**
	 * Determines if the given profile post can be approved/unapproved. This does not
	 * check parent (viewing) permissions.
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canApproveUnapproveProfilePost(array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'approveUnapprove'));
	}

	/**
	 * Determines if the profile post can be liked with the given permissions.
	 * This does not check profile post viewing permissions.
	 *
	 * @param array $profilePost Profile post info
	 * @param array $user User info for where profile post is posted
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canLikeProfilePost(array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($profilePost['user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'liking_own_content_cheating';
			return false;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'like');
	}

	/**
	 * Determines if the given profile post can be commented on. This does not
	 * check parent (viewing) permissions.
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canCommentOnProfilePost(array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return $this->_getUserProfileModel()->canPostOnProfile($user, $errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if the viewing user can view the IP of the stated profile post
	 *
	 * @param array $profilePost
	 * @param array $user
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canViewIps(array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		return $this->getModelFromCache('XenForo_Model_User')->canViewIps($errorPhraseKey, $viewingUser);
	}

	/**
	 * Adds the inline mod option to a profile post.
	 *
	 * @param array $profilePost Profile post. By reference; canInlineMod key added
	 * @param array $user
	 * @param array|null $viewingUser
	 *
	 * @return array List of inline mod options the user can do
	 */
	public function addInlineModOptionToProfilePost(array &$profilePost, array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$options = array();
		$canInlineMod = ($viewingUser['user_id'] && (
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'deleteAny')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'undelete')
			|| XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'approveUnapprove')
		));

		if ($canInlineMod)
		{
			if ($this->canDeleteProfilePost($profilePost, $user, 'soft', $null, $viewingUser))
			{
				$options['delete'] = true;
			}
			if ($this->canUndeleteProfilePost($profilePost, $user, $null, $viewingUser))
			{
				$options['undelete'] = true;
			}
			if ($this->canApproveUnapproveProfilePost($profilePost, $user, $null, $viewingUser))
			{
				$options['approve'] = true;
				$options['unapprove'] = true;
			}
		}

		$profilePost['canInlineMod'] = (count($options) > 0);

		return $options;
	}

	/**
	 * Adds the inline mod option to a batch of profile posts.
	 *
	 * @param array $profilePosts Batch of profile posts. By reference; all modified to add canInlineMod key
	 * @param array $user
	 * @param array|null $viewingUser
	 *
	 * @return array List of inline mod options the user can do on at least one post
	 */
	public function addInlineModOptionToProfilePosts(array &$profilePosts, array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$options = array();
		foreach ($profilePosts AS &$profilePost)
		{
			$options += $this->addInlineModOptionToProfilePost($profilePost, $user, $viewingUser);
		}

		return $options;
	}

	/**
	 * Gets permission-based conditions that apply to profile post fetching functions.
	 *
	 * @param array $user User the profile posts will belong to
	 * @param array|null $viewingUser Viewing user ref; defaults to visitor
	 *
	 * @return array Keys: deleted (boolean), moderated (boolean, integer if can only view specific user ID)
	 */
	public function getPermissionBasedProfilePostConditions(array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'viewModerated'))
		{
			$viewModerated = true;
		}
		else if ($viewingUser['user_id'])
		{
			$viewModerated = $viewingUser['user_id'];
		}
		else
		{
			$viewModerated = false;
		}

		return array(
			'deleted' => XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'viewDeleted'),
			'moderated' => $viewModerated
		);
	}

	/**
	 * Gets the message state for a newly inserted profile post by the viewing user.
	 *
	 * @param array $user User whose profile is being posted on
	 * @param array|null $viewingUser
	 *
	 * @return string Message state (visible, moderated, deleted)
	 */
	public function getProfilePostInsertMessageState(array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'approveUnapprove'))
		{
			return 'visible';
		}
		else if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'followModerationRules'))
		{
			return 'visible'; // TODO: follow profile-level settings when they exist
		}
		else
		{
			return 'moderated';
		}
	}

	/**
	 * Constant for fetching the info about the comment user.
	 *
	 * @var integer
	 */
	const FETCH_COMMENT_USER = 0x01;

	/**
	 * Prepares the fetching options for profile post comments.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function prepareProfilePostCommentFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_COMMENT_USER)
			{
				$selectFields .= ',
					user.*,
					IF(user.username IS NULL, profile_post_comment.username, user.username) AS username';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = profile_post_comment.user_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Gets the specified profile post comment.
	 *
	 * @param integer $profilePostCommentId
	 * @param array $fetchOptions
	 *
	 * @return array|false
	 */
	public function getProfilePostCommentById($profilePostCommentId, array $fetchOptions = array())
	{
		$sqlClauses = $this->prepareProfilePostCommentFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT profile_post_comment.*
			' . $sqlClauses['selectFields'] . '
			FROM xf_profile_post_comment AS profile_post_comment
			' . $sqlClauses['joinTables'] . '
			WHERE profile_post_comment.profile_post_comment_id = ?
		', $profilePostCommentId);
	}

	/**
	 * Gets the profile post comments with the specified IDs.
	 *
	 * @param array $ids
	 * @param array $fetchOptions
	 *
	 * @return array [id] => info
	 */
	public function getProfilePostCommentsByIds(array $ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}

		$sqlClauses = $this->prepareProfilePostCommentFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT profile_post_comment.*
			' . $sqlClauses['selectFields'] . '
			FROM xf_profile_post_comment AS profile_post_comment
			' . $sqlClauses['joinTables'] . '
			WHERE profile_post_comment.profile_post_comment_id IN (' . $this->_getDb()->quote($ids) . ')
		', 'profile_post_comment_id');
	}

	/**
	 * Gets the profile post comments make by a particular user.
	 *
	 * @param array $ids
	 * @param array $fetchOptions
	 *
	 * @return array [id] => info
	 */
	public function getProfilePostCommentsByUserId($userId, array $fetchOptions = array())
	{
		$sqlClauses = $this->prepareProfilePostCommentFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT profile_post_comment.*
			' . $sqlClauses['selectFields'] . '
			FROM xf_profile_post_comment AS profile_post_comment
			' . $sqlClauses['joinTables'] . '
			WHERE profile_post_comment.user_id = ?
		', 'profile_post_comment_id', $userId);
	}

	/**
	 * Gets all comments that belong to the specified post. If a limit is specified,
	 * more recent comments are returned.
	 *
	 * @param integer $profilePostId
	 * @param integer $beforeDate If specified, gets posts before specified date only
	 * @param array $fetchOptions
	 *
	 * @return array [id] => info
	 */
	public function getProfilePostCommentsByProfilePost($profilePostId, $beforeDate = 0, array $fetchOptions = array())
	{
		$sqlClauses = $this->prepareProfilePostCommentFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		if ($beforeDate)
		{
			$beforeCondition = 'AND profile_post_comment.comment_date < ' . $this->_getDb()->quote($beforeDate);
		}
		else
		{
			$beforeCondition = '';
		}

		$results = $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT profile_post_comment.*
				' . $sqlClauses['selectFields'] . '
				FROM xf_profile_post_comment AS profile_post_comment
				' . $sqlClauses['joinTables'] . '
				WHERE profile_post_comment.profile_post_id = ?
					' . $beforeCondition . '
				ORDER BY profile_post_comment.comment_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'profile_post_comment_id', $profilePostId);

		return array_reverse($results, true);
	}

	/**
	 * Merges comments into existing profile post data.
	 *
	 * @param array $profilePosts
	 * @param array $fetchOptions
	 *
	 * @return array Posts with comments merged
	 */
	public function addProfilePostCommentsToProfilePosts(array $profilePosts, array $fetchOptions = array())
	{
		$commentIdMap = array();

		foreach ($profilePosts AS &$profilePost)
		{
			if ($profilePost['latest_comment_ids'])
			{
				foreach (explode(',', $profilePost['latest_comment_ids']) AS $commentId)
				{
					$commentIdMap[intval($commentId)] = $profilePost['profile_post_id'];
				}
			}

			$profilePost['comments'] = array();
		}

		if ($commentIdMap)
		{
			$comments = $this->getProfilePostCommentsByIds(array_keys($commentIdMap), $fetchOptions);
			foreach ($commentIdMap AS $commentId => $profilePostId)
			{
				if (isset($comments[$commentId]))
				{
					if (!isset($profilePosts[$profilePostId]['first_shown_comment_date']))
					{
						$profilePosts[$profilePostId]['first_shown_comment_date'] = $comments[$commentId]['comment_date'];
					}
					$profilePosts[$profilePostId]['comments'][$commentId] = $comments[$commentId];
				}
			}
		}

		return $profilePosts;
	}

	/**
	 * Gets the user IDs that have commented on a profile post.
	 *
	 * @param integer $profilePostId
	 *
	 * @return array
	 */
	public function getProfilePostCommentUserIds($profilePostId)
	{
		return $this->_getDb()->fetchCol('
			SELECT DISTINCT user_id
			FROM xf_profile_post_comment
			WHERE profile_post_id = ?
		', $profilePostId);
	}

	/**
	 * Determines if the given profile post comment can be deleted. This does not
	 * check parent (viewing) permissions.
	 *
	 * @param array $comment
	 * @param array $profilePost
	 * @param array $user
	 * @param string $errorPhraseKey By ref. More specific error, if available.
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canDeleteProfilePostComment(array $comment, array $profilePost, array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'deleteAny'))
		{
			return true;
		}

		if ($viewingUser['user_id'] == $comment['user_id'])
		{
			return XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'deleteOwn');
		}
		else if ($viewingUser['user_id'] == $profilePost['profile_user_id'])
		{
			return XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'manageOwn');
		}
		else
		{
			return false;
		}
	}

	/**
	 * Prepares a profile post comment for display.
	 *
	 * @param array $comment
	 * @param array $profilePost
	 * @param array $user
	 * @param array|null $viewingUser
	 *
	 * @return array
	 */
	public function prepareProfilePostComment(array $comment, array $profilePost, array $user, array $viewingUser = null)
	{
		$comment['canDelete'] = $this->canDeleteProfilePostComment($comment, $profilePost, $user, $null, $viewingUser);

		return $comment;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_UserProfile
	 */
	protected function _getUserProfileModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserProfile');
	}
}