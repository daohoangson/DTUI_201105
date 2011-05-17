<?php

/**
 * Cron entry for cleaning up bans.
 *
 * @package XenForo_Banning
 */
class XenForo_CronEntry_Ban
{
	/**
	 * Deletes expired bans.
	 */
	public static function deleteExpiredBans()
	{
		XenForo_Model::create('XenForo_Model_Banning')->deleteExpiredUserBans();
	}
}