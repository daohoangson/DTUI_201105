<?php

/**
 * Implements the general purpose flood check solution.
 *
 * @package XenForo_FloodCheck
 */
class XenForo_Model_FloodCheck extends XenForo_Model
{
	/**
	 * Determine if the given has to wait to perform a given action because
	 * of the flood check. If you only need to do this check, the static
	 * {@link passFloodCheck()} method is simpler.
	 *
	 * @param string $action Name of the action. Users may flood different actions, but not the same one.
	 * @param integer|null $floodingLimit Amount of time where a user performing this action is flooding. If null, use default.
	 * @param integer|null $userId User doing the action. Guests never flood. If null, uses visitor
	 *
	 * @return integer Number of seconds remaining until the flood check is passed
	 */
	public function checkFloodingInternal($action, $floodingLimit = null, $userId = null)
	{
		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		if (!$userId)
		{
			return 0;
		}

		if ($floodingLimit === null)
		{
			$floodingLimit = XenForo_Application::get('options')->floodCheckLength;
		}
		if ($floodingLimit <= 0)
		{
			return 0;
		}

		$time = XenForo_Application::$time;
		$floodLimitTime = $time - $floodingLimit;

		$db = $this->_getDb();

		$updateResult = $db->query('
			UPDATE xf_flood_check
			SET flood_time = ?
			WHERE user_id = ?
				AND flood_action = ?
				AND flood_time < ?
		', array($time, $userId, $action, $floodLimitTime));
		if ($updateResult->rowCount())
		{
			// flood_time was more thant $floodingLimit ago -> no flooding
			return 0;
		}

		$insertResult = $db->query('
			INSERT IGNORE INTO xf_flood_check
				(user_id, flood_action, flood_time)
			VALUES
				(?, ?, ?)
		', array($userId, $action, $time));
		if ($insertResult->rowCount())
		{
			// no flooding information stored -> no flooding
			return 0;
		}

		// flooding - get the time remaining
		$floodTime = $db->fetchOne('
			SELECT flood_time
			FROM xf_flood_check
			WHERE user_id = ?
				AND flood_action = ?
		', array($userId, $action));

		$seconds = $floodTime - $floodLimitTime;
		if ($seconds < 0)
		{
			$seconds = 0;
		}

		return $seconds;
	}

	/**
	 * Determine if the given has to wait to perform a given action because
	 * of the flood check. If you only need to do this check, the static
	 * {@link passFloodCheck()} method is simpler.
	 *
	 * @param string $action Name of the action. Users may flood different actions, but not the same one.
	 * @param integer|null $floodingLimit Amount of time where a user performing this action is flooding. If null, use default
	 * @param integer|null $userId User doing the action. Guests never flood. If null, uses visitor
	 *
	 * @return integer Number of seconds remaining until the flood check is passed
	 */
	public static function checkFlooding($action, $floodingLimit = null, $userId = null)
	{
		$model = XenForo_Model::create('XenForo_Model_FloodCheck');
		return $model->checkFloodingInternal($action, $floodingLimit, $userId);
	}

	/**
	 * Prune flood check data older than so many seconds.
	 *
	 * @param integer $age Minimum age (in seconds) of data that will be pruned.
	 */
	public function pruneFloodCheckData($age)
	{
		$maxTime = XenForo_Application::$time - $age;

		$db = $this->_getDb();
		$db->delete('xf_flood_check', 'flood_time < ' . $db->quote($maxTime));
	}
}