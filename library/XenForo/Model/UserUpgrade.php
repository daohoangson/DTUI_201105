<?php

/**
 * Model for user upgrades.
 *
 * @package XenForo_UserUpgrade
 */
class XenForo_Model_UserUpgrade extends XenForo_Model
{
	/**
	 * Joins the upgrade details to a user-specific upgrade record.
	 *
	 * @var int
	 */
	const JOIN_UPGRADE = 1;

	/**
	 * Gets the specified upgrade.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getUserUpgradeById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_upgrade
			WHERE user_upgrade_id = ?
		', $id);
	}

	/**
	 * Gets all upgrades in display order.
	 *
	 * @return array [user upgrade id] => info
	 */
	public function getAllUserUpgrades()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_user_upgrade
			ORDER BY display_order
		', 'user_upgrade_id');
	}

	/**
	 * Gets a list of upgrades that are applicable to the specified user.
	 *
	 * @param array|null $viewingUser
	 *
	 * @return array
	 * 		[available] -> list of upgrades that can be purchased,
	 * 		[purchased] -> list of purchased, with [record] key inside for specific info
	 */
	public function getUserUpgradesForPurchaseList(array $viewingUser = null)
	{
		$purchased = array();
		$upgrades = array();

		$this->standardizeViewingUserReference($viewingUser);
		if ($viewingUser['user_id'] && $upgrades = $this->getAllUserUpgrades())
		{
			$activeUpgrades = $this->getActiveUserUpgradeRecordsForUser($viewingUser['user_id']);

			foreach ($upgrades AS $upgradeId => $upgrade)
			{
				if (isset($activeUpgrades[$upgradeId]))
				{
					// purchased
					$purchased[$upgradeId] = $upgrades[$upgradeId];
					$purchased[$upgradeId]['record'] = $activeUpgrades[$upgradeId];
					unset($upgrades[$upgradeId]); // can't buy again

					// remove any upgrades disabled by this
					if ($upgrade['disabled_upgrade_ids'])
					{
						foreach (explode(',', $upgrade['disabled_upgrade_ids']) AS $disabledId)
						{
							unset($upgrades[$disabledId]);
						}
					}
				}
				else if (!$upgrade['can_purchase'])
				{
					unset($upgrades[$upgradeId]);
				}
			}
		}

		return array(
			'available' => $upgrades,
			'purchased' => $purchased
		);
	}

	/**
	 * Prepares a user upgrade for display.
	 *
	 * @param array $upgrade
	 *
	 * @return array
	 */
	public function prepareUserUpgrade(array $upgrade)
	{
		$upgrade['currency'] = strtoupper($upgrade['cost_currency']);

		switch ($upgrade['length_unit'])
		{
			case 'day': $upgrade['lengthUnitPP'] = 'D'; break;
			case 'month': $upgrade['lengthUnitPP'] = 'M'; break;
			case 'year': $upgrade['lengthUnitPP'] = 'Y'; break;
			default: $upgrade['lengthUnitPP'] = ''; break;
		}

		$cost = "$upgrade[cost_amount] $upgrade[currency]";

		if ($upgrade['length_unit'])
		{
			if ($upgrade['length_amount'] > 1)
			{
				if ($upgrade['recurring'])
				{
					$upgrade['costPhrase'] = new XenForo_Phrase("x_per_y_$upgrade[length_unit]s", array(
						'cost' => $cost,
						'length' => $upgrade['length_amount']
					));
				}
				else
				{
					$upgrade['costPhrase'] = new XenForo_Phrase("x_for_y_$upgrade[length_unit]s", array(
						'cost' => $cost,
						'length' => $upgrade['length_amount']
					));
				}
			}
			else
			{
				if ($upgrade['recurring'])
				{
					$upgrade['costPhrase'] = new XenForo_Phrase("x_per_$upgrade[length_unit]", array(
						'cost' => $cost
					));
				}
				else
				{
					$upgrade['costPhrase'] = new XenForo_Phrase("x_for_one_$upgrade[length_unit]", array(
						'cost' => $cost
					));
				}
			}
		}
		else
		{
			$upgrade['costPhrase'] = $cost;
		}

		return $upgrade;
	}

	/**
	 * Prepares a list of user upgrades for display
	 *
	 * @param array $upgrades
	 *
	 * @return array
	 */
	public function prepareUserUpgrades(array $upgrades)
	{
		foreach ($upgrades AS &$upgrade)
		{
			$upgrade = $this->prepareUserUpgrade($upgrade);
		}

		return $upgrades;
	}

	/**
	 * Gets a list of user upgrades as options.
	 *
	 * @param string|array $selectedOptions List of selected options
	 * @param integer|false $skip ID to skip
	 *
	 * @return array
	 */
	public function getUserUpgradeOptions($selectedOptions, $skip = false)
	{
		if (!is_array($selectedOptions))
		{
			$selectedOptions = ($selectedOptions ? explode(',', $selectedOptions) : array());
		}

		$options = array();
		foreach ($this->getAllUserUpgrades() AS $upgrade)
		{
			if ($upgrade['user_upgrade_id'] == $skip)
			{
				continue;
			}

			$options[] = array(
				'label' => $upgrade['title'],
				'value' => $upgrade['user_upgrade_id'],
				'selected' => in_array($upgrade['user_upgrade_id'], $selectedOptions)
			);
		}

		return $options;
	}

	/**
	 * Gets the specified user upgrade records. Queries active and expired records.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array [user upgrade record id]
	 */
	public function getUserUpgradeRecords(array $conditions = array(), array $fetchOptions = array())
	{
		$baseTable = (empty($conditions['active']) ? 'user_upgrade_expired' : 'user_upgrade_active');

		if (empty($conditions['active']))
		{
			$orderBy = 'user_upgrade_expired.end_date DESC';
		}
		else
		{
			$orderBy = 'user_upgrade_active.start_date DESC';
		}

		$whereClause = $this->prepareUserUpgradeRecordConditions($conditions, $baseTable, $fetchOptions);
		$sqlClauses = $this->prepareUserUpgradeRecordFetchOptions($fetchOptions, $baseTable);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT ' . $baseTable . '.*,
					user.*
				' . $sqlClauses['selectFields'] . '
				FROM xf_' . $baseTable . ' AS ' . $baseTable . '
				LEFT JOIN xf_user AS user ON (' . $baseTable . '.user_id = user.user_id)
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereClause . '
				ORDER BY ' . $orderBy . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_upgrade_record_id');
	}

	/**
	 * Counts the number of user upgrade records matching the conditions.
	 *
	 * @param array $conditions
	 *
	 * @return integer
	 */
	public function countUserUpgradeRecords(array $conditions = array())
	{
		$baseTable = (empty($conditions['active']) ? 'user_upgrade_expired' : 'user_upgrade_active');

		$fetchOptions = array();
		$whereClause = $this->prepareUserUpgradeRecordConditions($conditions, $baseTable, $fetchOptions);
		$sqlClauses = $this->prepareUserUpgradeRecordFetchOptions($fetchOptions, $baseTable);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_' . $baseTable . ' AS ' . $baseTable . '
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	/**
	 * Prepares a list of user upgrade record conditions.
	 *
	 * @param array $conditions
	 * @param string $baseTable Base table to query against
	 * @param array $fetchOptions
	 *
	 * @return string
	 */
	public function prepareUserUpgradeRecordConditions(array $conditions, $baseTable, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['user_upgrade_id']))
		{
			if (is_array($conditions['user_upgrade_id']))
			{
				$sqlConditions[] = $baseTable . '.user_upgrade_id IN (' . $db->quote($conditions['user_upgrade_id']) . ')';
			}
			else
			{
				$sqlConditions[] = $baseTable . '.user_upgrade_id = ' . $db->quote($conditions['user_upgrade_id']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Prepares user upgrade record fetch options.
	 *
	 * @param array $fetchOptions
	 * @param string $baseTable Base table to query against
	 *
	 * @return array
	 */
	public function prepareUserUpgradeRecordFetchOptions(array $fetchOptions, $baseTable)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::JOIN_UPGRADE)
			{
				$selectFields .= ',
					user_upgrade.*';
				$joinTables .= '
					LEFT JOIN xf_user_upgrade AS user_upgrade ON
						(user_upgrade.user_upgrade_id = ' . $baseTable . '.user_upgrade_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Gets the active upgrade records for the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return array [upgrade id] => info (note, the id of the upgrade; not the user-specific record!)
	 */
	public function getActiveUserUpgradeRecordsForUser($userId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_user_upgrade_active
			WHERE user_id = ?
		', 'user_upgrade_id', $userId);
	}

	/**
	 * Gets the specified active user upgrade record, based on user and upgrade.
	 *
	 * @param integer $userId
	 * @param integer $upgradeId
	 *
	 * @return array|false
	 */
	public function getActiveUserUpgradeRecord($userId, $upgradeId)
	{
		return $this->_getDb()->fetchRow('
			SELECT user_upgrade_active.*,
				user.*
			FROM xf_user_upgrade_active AS user_upgrade_active
			INNER JOIN xf_user AS user ON
				(user.user_id = user_upgrade_active.user_id)
			WHERE user_upgrade_active.user_id = ?
				AND user_upgrade_active.user_upgrade_id = ?
		', array($userId, $upgradeId));
	}

	/**
	 * Gets the specified active user upgrade record.
	 *
	 * @param integer $upgradeRecordId
	 *
	 * @return array|false
	 */
	public function getActiveUserUpgradeRecordById($upgradeRecordId)
	{
		return $this->_getDb()->fetchRow('
			SELECT user_upgrade_active.*,
				user.*
			FROM xf_user_upgrade_active AS user_upgrade_active
			INNER JOIN xf_user AS user ON
				(user.user_id = user_upgrade_active.user_id)
			WHERE user_upgrade_active.user_upgrade_record_id = ?
		', $upgradeRecordId);
	}

	/**
	 * Upgrades the user with the specified upgrade.
	 *
	 * @param integer $userId
	 * @param array $upgrade Info about upgrade to apply
	 * @param boolean $allowInsertUnpurchasable Allow insert of a new upgrade even if not purchasable
	 * @param integer|null $endDate Forces a specific end date; if null, don't overwrite
	 *
	 * @return integer|false User upgrade record ID
	 */
	public function upgradeUser($userId, array $upgrade, $allowInsertUnpurchasable = false, $endDate = null)
	{
		$db = $this->_getDb();

		$active = $this->getActiveUserUpgradeRecord($userId, $upgrade['user_upgrade_id']);
		if ($active)
		{
			// updating an existing upgrade - if no end date override specified, extend the upgrade
			$activeExtra = unserialize($active['extra']);

			if ($endDate === null)
			{
				if ($active['end_date'] == 0 || !$activeExtra['length_unit'])
				{
					$endDate = 0;
				}
				else
				{
					$endDate = strtotime('+' . $activeExtra['length_amount'] . ' ' . $activeExtra['length_unit'], $active['end_date']);
				}
			}
			else
			{
				$endDate = intval($endDate);
			}

			if ($endDate != $active['end_date'])
			{
				$db->update('xf_user_upgrade_active',
					array('end_date' => $endDate),
					'user_id = ' . $db->quote($userId) . ' AND user_upgrade_id = ' . $db->quote($upgrade['user_upgrade_id'])
				);
			}

			return $active['user_upgrade_record_id'];
		}
		else
		{
			if (!$upgrade['can_purchase'] && !$allowInsertUnpurchasable)
			{
				return false;
			}

			// inserting a new new upgrade
			if ($endDate === null)
			{
				if (!$upgrade['length_unit'])
				{
					$endDate = 0;
				}
				else
				{
					$endDate = strtotime('+' . $upgrade['length_amount'] . ' ' . $upgrade['length_unit']);
				}
			}
			else
			{
				$endDate = intval($endDate);
			}

			$extra = array(
				'cost_amount' => $upgrade['cost_amount'],
				'cost_currency' => $upgrade['cost_currency'],
				'length_amount' => $upgrade['length_amount'],
				'length_unit' => $upgrade['length_unit']
			);

			XenForo_Db::beginTransaction($db);

			$db->insert('xf_user_upgrade_active', array(
				'user_id' => $userId,
				'user_upgrade_id' => $upgrade['user_upgrade_id'],
				'extra' => serialize($extra),
				'start_date' => XenForo_Application::$time,
				'end_date' => $endDate
			));
			$upgradeRecordId = $db->lastInsertId();

			$this->getModelFromCache('XenForo_Model_User')->addUserGroupChange(
				$userId, 'userUpgrade-' . $upgrade['user_upgrade_id'], $upgrade['extra_group_ids']
			);

			XenForo_Db::commit($db);

			return $upgradeRecordId;
		}
	}

	/**
	 * Logs a payment processor callback request.
	 *
	 * @param integer $userUpgradeRecordId Upgrade record ID this applies to, if known
	 * @param string $processor
	 * @param string $transactionId
	 * @param string $transactionType Type of transaction: info, payment, cancel, error
	 * @param string $message
	 * @param array $details List of additional details about call
	 *
	 * @return integer Log record ID
	 */
	public function logProcessorCallback($userUpgradeRecordId, $processor, $transactionId, $transactionType, $message, array $details)
	{
		$this->_getDb()->insert('xf_user_upgrade_log', array(
			'user_upgrade_record_id' => $userUpgradeRecordId,
			'processor' => $processor,
			'transaction_id' => $transactionId,
			'transaction_type' => $transactionType,
			'message' => substr($message, 0, 255),
			'transaction_details' => serialize($details),
			'log_date' => XenForo_Application::$time
		));

		return $this->_getDb()->lastInsertId();
	}

	/**
	 * Gets any log records that apply to the specified transaction.
	 *
	 * @param string $transactionId
	 *
	 * @return array [log id] => info
	 */
	public function getLogByTransactionId($transactionId)
	{
		if ($transactionId === '')
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_user_upgrade_log
			WHERE transaction_id = ?
			ORDER BY log_date
		', 'user_upgrade_log_id', $transactionId);
	}

	/**
	 * Gets any log record that indicates a transaction has been processed.
	 *
	 * @param string $transactionId
	 *
	 * @return array|false
	 */
	public function getProcessedTransactionLog($transactionId)
	{
		if ($transactionId === '')
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_user_upgrade_log
			WHERE transaction_id = ?
				AND transaction_type IN (\'payment\', \'cancel\')
			ORDER BY log_date
		', 'user_upgrade_log_id', $transactionId);
	}

	/**
	 * Get all user upgrades that have expired but are still listed as active.
	 *
	 * @return array [upgrade record id] => info
	 */
	public function getExpiredUserUpgrades()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_user_upgrade_active
			WHERE end_date < ?
				AND end_date > 0
		', 'user_upgrade_record_id', XenForo_Application::$time);
	}

	/**
	 * Downgrades the specified user upgrade records.
	 *
	 * @param array $upgrades List of user upgrade records to downgrade
	 */
	public function downgradeUserUpgrades(array $upgrades)
	{
		if (!$upgrades)
		{
			return;
		}


		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$upgradeRecordIds = array();

		foreach ($upgrades AS $upgrade)
		{
			$this->getModelFromCache('XenForo_Model_User')->removeUserGroupChange(
				$upgrade['user_id'], 'userUpgrade-' . $upgrade['user_upgrade_id']
			);

			$upgradeRecordIds[] = $upgrade['user_upgrade_record_id'];
		}

		$db->query('
			INSERT IGNORE INTO xf_user_upgrade_expired
				(user_upgrade_record_id, user_id, user_upgrade_id, start_date, end_date)
			SELECT user_upgrade_record_id, user_id, user_upgrade_id, start_date, ?
			FROM xf_user_upgrade_active
			WHERE user_upgrade_record_id IN (' . $db->quote($upgradeRecordIds) . ')
		', XenForo_Application::$time);
		$db->delete('xf_user_upgrade_active', 'user_upgrade_record_id IN (' . $db->quote($upgradeRecordIds) . ')');

		XenForo_Db::commit($db);
	}

	/**
	 * Downgrades the specified user upgrade record.
	 *
	 * @param array $upgrade
	 */
	public function downgradeUserUpgrade(array $upgrade)
	{
		$this->downgradeUserUpgrades(array($upgrade));
	}

	/**
	 * Updates the count of purchasable user upgrades.
	 *
	 * @return integer
	 */
	public function updateUserUpgradeCount()
	{
		$upgrades = $this->getAllUserUpgrades();
		foreach ($upgrades AS $upgradeId => $upgrade)
		{
			if (!$upgrade['can_purchase'])
			{
				unset($upgrades[$upgradeId]);
			}
		}
		$upgradeCount = count($upgrades);

		XenForo_Application::setSimpleCacheData('userUpgradeCount', $upgradeCount);

		return $upgradeCount;
	}
}