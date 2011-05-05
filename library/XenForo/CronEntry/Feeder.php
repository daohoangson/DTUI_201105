<?php

/**
 * Cron entry for feed importer.
 *
 * @package XenForo_Cron
 */
class XenForo_CronEntry_Feeder
{
	/**
	 * Imports feeds.
	 */
	public static function importFeeds()
	{
		XenForo_Model::create('XenForo_Model_Feed')->scheduledImport();
	}
}