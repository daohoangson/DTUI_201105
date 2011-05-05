<?php

/**
 * Cache rebuilder for users.
 *
 * @package XenForo_CacheRebuild
 */
class XenForo_CacheRebuilder_User extends XenForo_CacheRebuilder_Abstract
{
	/**
	 * Gets rebuild message.
	 */
	public function getRebuildMessage()
	{
		return new XenForo_Phrase('users');
	}

	/**
	 * Shows the exit link.
	 */
	public function showExitLink()
	{
		return true;
	}

	/**
	 * Rebuilds the data.
	 *
	 * @see XenForo_CacheRebuilder_Abstract::rebuild()
	 */
	public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
	{
		$options['batch'] = isset($options['batch']) ? $options['batch'] : 75;
		$options['batch'] = max(1, $options['batch']);

		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		/* @var $conversationModel XenForo_Model_Conversation */
		$conversationModel = XenForo_Model::create('XenForo_Model_Conversation');

		$userIds = $userModel->getUserIdsInRange($position, $options['batch']);
		if (sizeof($userIds) == 0)
		{
			return true;
		}

		XenForo_Db::beginTransaction();

		foreach ($userIds AS $userId)
		{
			$position = $userId;

			/* @var $userDw XenForo_DataWriter_User */
			$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			if ($userDw->setExistingData($userId))
			{
				$userDw->set('alerts_unread', $userModel->getUnreadAlertsCount($userId));
				$userDw->set('conversations_unread', $conversationModel->countUnreadConversationsForUser($userId));
				$userDw->save();
				$userDw->rebuildUserGroupRelations();
				$userDw->rebuildPermissionCombinationId();
				$userDw->rebuildDisplayStyleGroupId();
				$userDw->rebuildIdentities();
			}
		}

		XenForo_Db::commit();

		$detailedMessage = XenForo_Locale::numberFormat($position);

		return $position;
	}
}