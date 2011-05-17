<?php

/**
 * Cron entry for timed counter updates.
 *
 * @package XenForo_Cron
 */
class XenForo_CronEntry_Counters
{
	/**
	 * Rebuilds the board totals counter.
	 */
	public static function rebuildBoardTotals()
	{
		XenForo_Model::create('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
	}
}