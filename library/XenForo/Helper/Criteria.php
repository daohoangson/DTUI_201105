<?php

/**
 * Helper to manage/check the criteria that are used in things
 * like trophies and user notices.
 *
 * @package XenForo_Criteria
 */
class XenForo_Helper_Criteria
{
	/**
	 * Determines if the given user matches the criteria. The provided
	 * user should be a full user record; if fields are missing, an error
	 * will not be thrown, and the criteria check will fail.
	 *
	 * @param array|string $criteria List of criteria, format: [] with keys rule and data; may be serialized
	 * @param boolean $matchOnEmpty If true and there's no criteria, true is returned; otherwise, false
	 * @param array|null $user Full user record to check against; if null, user visitor
	 *
	 * @return boolean
	 */
	public static function userMatchesCriteria($criteria, $matchOnEmpty = false, array $user = null)
	{
		$criteria = self::unserializeCriteria($criteria);
		if (!$user)
		{
			$user = XenForo_Visitor::getInstance()->toArray();
		}

		if (!$criteria)
		{
			return (boolean)$matchOnEmpty;
		}

		foreach ($criteria AS $criterion)
		{
			$data = $criterion['data'];

			switch ($criterion['rule'])
			{
				case 'registered_days':
					if (!isset($user['register_date']))
					{
						return false;
					}
					$daysRegistered = floor(
						(XenForo_Application::$time - $user['register_date']) / 86400
					);
					if ($daysRegistered < $data['days'])
					{
						return false;
					}
				break;

				case 'messages_posted':
					if (!isset($user['message_count']) || $user['message_count'] < $data['messages'])
					{
						return false;
					}
				break;

				case 'like_count':
					if (!isset($user['like_count']) || $user['like_count'] < $data['likes'])
					{
						return false;
					}
				break;

				case 'trophy_points':
					if (!isset($user['trophy_points']) || $user['trophy_points'] < $data['points'])
					{
						return false;
					}
				break;

				default:
					// unknown criteria, assume failed
					return false;
			}
		}

		return true;
	}

	/**
	 * Prepares a list of criteria for selection by a user via the UI.
	 * This will change if a criteria is repeatable.
	 *
	 * @param array|sstring $criteria Criteria in format: [], with keys rule and data; may be serialized
	 *
	 * @return array Format: [rule] => rule data or true if none
	 */
	public static function prepareCriteriaForSelection($criteria)
	{
		$criteria = self::unserializeCriteria($criteria);

		$output = array();
		foreach ($criteria AS $criterion)
		{
			$data = (!empty($criterion['data']) ? $criterion['data'] : true);
			$output[$criterion['rule']] = $data;
		}

		return $output;
	}

	public static function unserializeCriteria($criteria)
	{
		if (!is_array($criteria))
		{
			$criteria = unserialize($criteria);
			if (!is_array($criteria))
			{
				return array();
			}
		}

		return $criteria;
	}
}