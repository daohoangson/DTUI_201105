<?php

/**
 * Moderation queue for profile posts.
 *
 * @package XenForo_Moderation
 */
class XenForo_ModerationQueueHandler_ProfilePost extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * Gets visible moderation queue entries for specified user.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::getVisibleModerationQueueEntriesForUser()
	 */
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		/* @var $profilePostModel XenForo_Model_ProfilePost */
		$profilePostModel = XenForo_Model::create('XenForo_Model_ProfilePost');
		$profilePosts = $profilePostModel->getProfilePostsByIds($contentIds);

		$profileUserIds = array();
		foreach ($profilePosts AS $profilePost)
		{
			$profileUserIds[] = $profilePost['profile_user_id'];
		}

		$users = XenForo_Model::create('XenForo_Model_User')->getUsersByIds($profileUserIds, array(
			'join' => XenForo_Model_User::FETCH_USER_PRIVACY,
			'followingUserId' => $viewingUser['user_id']
		));

		$output = array();
		foreach ($profilePosts AS $profilePost)
		{
			if (!isset($users[$profilePost['profile_user_id']]))
			{
				continue;
			}

			$user = $users[$profilePost['profile_user_id']];

			$canManage = true;
			if (!$profilePostModel->canViewProfilePostAndContainer($profilePost, $user, $null, $viewingUser))
			{
				$canManage = false;
			}
			else if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'editAny')
				|| !XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'deleteAny')
			)
			{
				$canManage = false;
			}

			if ($canManage)
			{
				$output[$profilePost['profile_post_id']] = array(
					'message' => $profilePost['message'],
					'user' => array(
						'user_id' => $profilePost['user_id'],
						'username' => $profilePost['username']
					),
					'title' => new XenForo_Phrase('profile_post_for_x', array('username' => $user['username'])),
					'link' => XenForo_Link::buildPublicLink('profile-posts', $profilePost),
					'contentTypeTitle' => new XenForo_Phrase('profile_post'),
					'titleEdit' => false
				);
			}
		}

		return $output;
	}

	/**
	 * Approves the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::approveModerationQueueEntry()
	 */
	public function approveModerationQueueEntry($contentId, $message, $title)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('message_state', 'visible');
		$dw->set('message', $message);

		return $dw->save();
	}

	/**
	 * Deletes the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::deleteModerationQueueEntry()
	 */
	public function deleteModerationQueueEntry($contentId)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('message_state', 'deleted');

		return $dw->save();
	}
}