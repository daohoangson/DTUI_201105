<?php

/**
 * Alert handler for conversations.
 *
 * @package XenForo_Alert
 */
class XenForo_AlertHandler_Conversation extends XenForo_AlertHandler_Abstract
{
	/**
	 * Fetches the content required by alerts.
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_Alert $model Alert model invoking this
	 * @param integer $userId User ID the alerts are for
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		/* @var $conversationModel XenForo_Model_Conversation */
		$conversationModel = $model->getModelFromCache('XenForo_Model_Conversation');

		return $conversationModel->getConversationsForUserByIds($userId, $contentIds);
	}

	protected function _prepareReply(array $item)
	{
		if ($item['extra_data'])
		{
			$item['extra'] = unserialize($item['extra_data']);
		}
		unset($item['extra_data']);

		return $item;
	}
}