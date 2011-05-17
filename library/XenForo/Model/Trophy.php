<?php

/**
 * Model for trophies.
 *
 * @package XenForo_Trophy
 */
class XenForo_Model_Trophy extends XenForo_Model
{
	/**
	 * Gets the named trophy.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getTrophyById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_trophy
			WHERE trophy_id = ?
		', $id);
	}

	/**
	 * Gets all trophies, ordered by their points (ascending).
	 *
	 * @return array Format: [trophy id] => info
	 */
	public function getAllTrophies()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_trophy
			ORDER BY trophy_points
		', 'trophy_id');
	}

	/**
	 * Gets all trophies that the specified user has earned. Ordered by award date descending.
	 *
	 * @param integer $userId
	 *
	 * @return array Format: [trophy id] => trophy info plus award_date
	 */
	public function getTrophiesForUserId($userId)
	{
		return $this->fetchAllKeyed('
			SELECT trophy.*,
				user_trophy.award_date
			FROM xf_user_trophy AS user_trophy
			INNER JOIN xf_trophy AS trophy ON (trophy.trophy_id = user_trophy.trophy_id)
			WHERE user_trophy.user_id = ?
			ORDER BY user_trophy.award_date DESC
		', 'trophy_id', $userId);
	}

	/**
	 * Counts the number of trophies that have been awarded to the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function countTrophiesForUserId($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_trophy AS user_trophy
			INNER JOIN xf_trophy AS trophy ON (trophy.trophy_id = user_trophy.trophy_id)
			WHERE user_trophy.user_id = ?
		', $userId);
	}

	/**
	 * Prepares a trophy for display.
	 *
	 * @param array $trophy
	 *
	 * @return array
	 */
	public function prepareTrophy(array $trophy)
	{
		$trophy['title'] = new XenForo_Phrase($this->getTrophyTitlePhraseName($trophy['trophy_id']));
		$trophy['description'] = new XenForo_Phrase($this->getTrophyDescriptionPhraseName($trophy['trophy_id']));

		return $trophy;
	}

	/**
	 * Prepares a list of trophies for display.
	 *
	 * @param array $trophies
	 *
	 * @return array
	 */
	public function prepareTrophies(array $trophies)
	{
		foreach ($trophies AS &$trophy)
		{
			$trophy = $this->prepareTrophy($trophy);
		}

		return $trophies;
	}

	/**
	 * Gets information about the default trophy for use when adding
	 * a new trophy. Includes prepared data.
	 *
	 * @return array
	 */
	public function getDefaultTrophy()
	{
		return array(
			'trophy_id' => 0,
			'trophy_points' => 10,

			'criteria' => '',
			'criteriaList' => array(),
			'title' => '',
			'description' => ''
		);
	}

	/**
	 * Gets the name of a trophy's title phrase.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getTrophyTitlePhraseName($trophyId)
	{
		return 'trophy_' . $trophyId . '_title';
	}

	/**
	 * Gets a trophy's master title phrase text.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getTrophyMasterTitlePhraseValue($trophyId)
	{
		$phraseName = $this->getTrophyTitlePhraseName($trophyId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the name of a trophy's description phrase.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getTrophyDescriptionPhraseName($trophyId)
	{
		return 'trophy_' . $trophyId . '_description';
	}

	/**
	 * Gets a trophy's master description phrase text.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getTrophyMasterDescriptionPhraseValue($trophyId)
	{
		$phraseName = $this->getTrophyDescriptionPhraseName($trophyId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Get all trophies for the specified users.
	 *
	 * @return array Format: [user id][trophy id] => award date
	 */
	public function getUserTrophiesByUserIds(array $userIds)
	{
		if (!$userIds)
		{
			return array();
		}

		$db = $this->_getDb();

		$output = array();
		$userTrophiesResult = $db->query('
			SELECT user_id, trophy_id, award_date
			FROM xf_user_trophy
			WHERE user_id IN (' . $db->quote($userIds) . ')
		');
		while ($userTrophy = $userTrophiesResult->fetch())
		{
			$output[$userTrophy['user_id']][$userTrophy['trophy_id']] = $userTrophy['award_date'];
		}

		return $output;
	}

	/**
	 * Award the specified user with a specific trophy.
	 *
	 * @param array $user
	 * @param string $username
	 * @param array $trophy
	 * @param integer|null $awardDate If null, use current time
	 */
	public function awardUserTrophy(array $user, $username, array $trophy, $awardDate = null)
	{
		if ($awardDate === null)
		{
			$awardDate = XenForo_Application::$time;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $db->query('
			INSERT IGNORE INTO xf_user_trophy
				(user_id, trophy_id, award_date)
			VALUES
				(?, ?, ?)
		', array($user['user_id'], $trophy['trophy_id'], $awardDate));

		if ($result->rowCount())
		{
			$db->query('
				UPDATE xf_user SET
					trophy_points = trophy_points + ?
				WHERE user_id = ?
			', array($trophy['trophy_points'], $user['user_id']));

			if (XenForo_Model_Alert::userReceivesAlert($user, 'user', 'trophy'))
			{
				XenForo_Model_Alert::alert(
					$user['user_id'],
					$user['user_id'], $username,
					'user', $user['user_id'],
					'trophy',
					array('trophy_id' => $trophy['trophy_id'])
				);
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Gets all trophy user titles.
	 *
	 * @return array [minimum points] => info
	 */
	public function getAllTrophyUserTitles()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_trophy_user_title
			ORDER BY minimum_points
		', 'minimum_points');
	}

	/**
	 * Updates the given set of user titles. The set is assumed to be all user titles,
	 * as the existing ones are removed before updating.
	 *
	 * @param array $titles [] => [title, minimum_points]
	 * @param boolean $rebuildCache If true, rebuilds the user title cache
	 */
	public function updateTrophyUserTitles(array $titles, $rebuildCache = true)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->delete('xf_trophy_user_title');

		foreach ($titles AS $titleInfo)
		{
			if (isset($titleInfo['title'], $titleInfo['minimum_points']))
			{
				$this->insertTrophyUserTitle($titleInfo['title'], $titleInfo['minimum_points'], false);
			}
		}

		if ($rebuildCache)
		{
			$this->rebuildTrophyUserTitleCache();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Inserts a new trophy user title. Throws an exception if error occurs.
	 *
	 * @param string $title
	 * @param integer $minimumPoints
	 * @param boolean $rebuildCache
	 */
	public function insertTrophyUserTitle($title, $minimumPoints, $rebuildCache = true)
	{
		$minimumPoints = intval($minimumPoints);
		if ($minimumPoints < 0)
		{
			$minimumPoints = 0;
		}

		$existing = $this->_getDb()->fetchRow('
			SELECT minimum_points
			FROM xf_trophy_user_title
			WHERE minimum_points = ?
		', $minimumPoints);
		if ($existing)
		{
			throw new XenForo_Exception(new XenForo_Phrase('trophy_already_exists_for_x_points', array('count' => $minimumPoints)), true);
		}

		$this->_getDb()->insert('xf_trophy_user_title', array(
			'minimum_points' => $minimumPoints,
			'title' => utf8_substr($title, 0, 50)
		));

		if ($rebuildCache)
		{
			$this->rebuildTrophyUserTitleCache();
		}
	}

	/**
	 * Deletes the sepcified user titles.
	 *
	 * @param array $points List of minimum point values to delete
	 * @param boolean $rebuildCache
	 */
	public function deleteTrophyUserTitles(array $points, $rebuildCache = true)
	{
		if (!$points)
		{
			return;
		}

		$db = $this->_getDb();
		$db->delete('xf_trophy_user_title', 'minimum_points IN (' . $db->quote($points) . ')');

		if ($rebuildCache)
		{
			$this->rebuildTrophyUserTitleCache();
		}
	}

	/**
	 * Rebuilds the trophy user title cache.
	 *
	 * @return array [minimum_points] => title
	 */
	public function rebuildTrophyUserTitleCache()
	{
		$titles = $this->getAllTrophyUserTitles();
		$cache = array();
		foreach ($titles AS $title)
		{
			$cache[$title['minimum_points']] = $title['title'];
		}

		krsort($cache, SORT_NUMERIC);

		$this->_getDataRegistryModel()->set('trophyUserTitles', $cache);
		return $cache;
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}

	/**
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}
}