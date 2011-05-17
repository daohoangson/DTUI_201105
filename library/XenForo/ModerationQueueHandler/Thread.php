<?php

/**
 * Moderation queue handler for threads.
 *
 * @package XenForo_Moderation
 */
class XenForo_ModerationQueueHandler_Thread extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * Gets visible moderation queue entries for specified user.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::getVisibleModerationQueueEntriesForUser()
	 */
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = XenForo_Model::create('XenForo_Model_Thread');
		$threads = $threadModel->getThreadsByIds($contentIds, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_FIRSTPOST,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		$threads = $threadModel->unserializePermissionsInList($threads, 'node_permission_cache');

		$output = array();
		foreach ($threads AS $thread)
		{
			$canManage = true;
			if (!$threadModel->canViewThreadAndContainer(
				$thread, $thread, $null, $thread['permissions'], $viewingUser
			))
			{
				$canManage = false;
			}
			else if (!XenForo_Permission::hasContentPermission($thread['permissions'], 'editAnyPost')
				|| !XenForo_Permission::hasContentPermission($thread['permissions'], 'deleteAnyThread')
			)
			{
				$canManage = false;
			}

			if ($canManage)
			{
				$output[$thread['thread_id']] = array(
					'message' => $thread['message'],
					'user' => array(
						'user_id' => $thread['user_id'],
						'username' => $thread['username']
					),
					'title' => $thread['title'],
					'link' => XenForo_Link::buildPublicLink('threads', $thread),
					'contentTypeTitle' => new XenForo_Phrase('thread'),
					'titleEdit' => true
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
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('discussion_state', 'visible');
		$dw->set('title', $title);

		if ($dw->save())
		{
			$messageDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
			$messageDw->setExistingData($dw->get('first_post_id'));
			$messageDw->set('message_state', 'visible');
			$messageDw->set('message', $message);

			return $messageDw->save();
		}
		else
		{
			return false;
		}
	}

	/**
	 * Deletes the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::deleteModerationQueueEntry()
	 */
	public function deleteModerationQueueEntry($contentId)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('discussion_state', 'deleted');

		return $dw->save();
	}
}