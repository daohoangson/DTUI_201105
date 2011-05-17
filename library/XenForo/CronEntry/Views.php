<?php

/**
 * Cron entry for updating view counts.
 */
class XenForo_CronEntry_Views
{
	/**
	 * Updates view counters for various content types.
	 */
	public static function runViewUpdate()
	{
		XenForo_Model::create('XenForo_Model_Thread')->updateThreadViews();
		XenForo_Model::create('XenForo_Model_Attachment')->updateAttachmentViews();
	}
}