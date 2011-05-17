<?php

class XenForo_SpamHandler_ProfilePost extends XenForo_SpamHandler_Abstract
{
	/**
	 * Checks that the options array contains a non-empty 'delete_messages' key
	 *
	 * @param array $user
	 * @param array $options
	 *
	 * @return boolean
	 */
	public function cleanUpConditionCheck(array $user, array $options)
	{
		return !empty($options['delete_messages']);
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::cleanUp()
	 */
	public function cleanUp(array $user, array &$log, &$errorKey)
	{
		if ($profilePosts = $this->getModelFromCache('XenForo_Model_ProfilePost')->getProfilePostsByUserId($user['user_id']))
		{
			$profilePostIds = array_keys($profilePosts);

			$deleteType = (XenForo_Application::get('options')->spamMessageAction == 'delete' ? 'hard' : 'soft');

			$log['profile_post'] = array(
				'deleteType' => $deleteType,
				'profilePostIds' => $profilePostIds
			);

			$ret = $this->getModelFromCache('XenForo_Model_InlineMod_ProfilePost')->deleteProfilePosts(
				$profilePostIds, array('deleteType' => $deleteType, 'skipPermissions' => true), $errorKey
			);
			if (!$ret)
			{
				return false;
			}
		}

		if ($comments = $this->getModelFromCache('XenForo_Model_ProfilePost')->getProfilePostCommentsByUserId($user['user_id']))
		{
			foreach ($comments AS $comment)
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment');
				$dw->setExistingData($comment, true);
				$dw->delete();
			}
			// no logging as there is no way to restore yet
		}

		return true;
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::restore()
	 */
	public function restore(array $log, &$errorKey = '')
	{
		if ($log['deleteType'] == 'soft')
		{
			return $this->getModelFromCache('XenForo_Model_InlineMod_ProfilePost')->undeleteProfilePosts(
				$log['profilePostIds'], array('skipPermissions' => true), $errorKey
			);
		}

		return true;
	}
}