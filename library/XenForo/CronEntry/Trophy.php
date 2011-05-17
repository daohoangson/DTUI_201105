<?php

/**
 * Cron entry for manipulating trophies.
 *
 * @package XenForo_Trophy
 */
class XenForo_CronEntry_Trophy
{
	/**
	 * Runs the cron-based check for new trophies that users should be awarded.
	 */
	public static function runTrophyCheck()
	{
		/* @var $trophyModel XenForo_Model_Trophy */
		$trophyModel = XenForo_Model::create('XenForo_Model_Trophy');
		$trophies = $trophyModel->getAllTrophies();
		if (!$trophies)
		{
			return;
		}

		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		$users = $userModel->getUsers(array(
			'user_state' => 'valid',
			'is_banned' => 0,
			'last_activity' => array('>', XenForo_Application::$time - 86400)
		), array(
			'join' => XenForo_Model_User::FETCH_USER_OPTION
		));

		$userTrophies = $trophyModel->getUserTrophiesByUserIds(array_keys($users));

		foreach ($users AS $user)
		{
			foreach ($trophies AS $trophy)
			{
				if (isset($userTrophies[$user['user_id']][$trophy['trophy_id']]))
				{
					continue;
				}

				if (XenForo_Helper_Criteria::userMatchesCriteria($trophy['criteria'], false, $user))
				{
					$trophyModel->awardUserTrophy($user, $user['username'], $trophy);
				}
			}
		}
	}
}