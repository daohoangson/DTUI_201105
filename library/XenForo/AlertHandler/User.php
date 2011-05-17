<?php

/**
 * Alert handler for users.
 *
 * @package XenForo_Alert
 */
class XenForo_AlertHandler_User extends XenForo_AlertHandler_Abstract
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
		/* @var $userModel XenForo_Model_User */
		$userModel = $model->getModelFromCache('XenForo_Model_User');

		$visitor = XenForo_Visitor::getInstance()->toArray();
		$users = array();

		foreach ($contentIds AS $key => $contentId)
		{
			if ($contentId == $visitor['user_id'])
			{
				$users[$visitor['user_id']] = $visitor;
				unset($contentIds[$key]);
				break;
			}
		}

		return $users + $userModel->getUsersByIds($contentIds);
	}

	protected function _prepareTrophy(array $item)
	{
		if ($item['extra_data'])
		{
			$item['extra'] = unserialize($item['extra_data']);

			$item['trophy'] = new XenForo_Phrase(
				XenForo_Model::create('XenForo_Model_Trophy')->getTrophyTitlePhraseName($item['extra']['trophy_id'])
			);
		}
		unset($item['extra_data']);

		return $item;
	}
}