<?php

/**
* Data writer for user upgrades.
*
* @package XenForo_UserUpgrade
*/
class XenForo_DataWriter_UserUpgrade extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_user_upgrade_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_user_upgrade' => array(
				'user_upgrade_id'      => array('type' => self::TYPE_UINT,    'autoIncrement' => true),
				'title'                => array('type' => self::TYPE_STRING,  'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_title'
				),
				'description'          => array('type' => self::TYPE_STRING,  'default' => ''),
				'display_order'        => array('type' => self::TYPE_UINT,    'default' => 0),
				'extra_group_ids'      => array('type' => self::TYPE_UNKNOWN, 'default' => '',
						'verification' => array('$this', '_verifyExtraGroupIds')
				),
				'recurring'            => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'cost_amount'          => array('type' => self::TYPE_FLOAT,   'required' => true,
						'verification' => array('$this', '_verifyCostAmount')
				),
				'cost_currency'        => array('type' => self::TYPE_STRING,  'required' => true,
						'allowedValues' => array('usd', 'cad', 'aud', 'gbp', 'eur')
				),
				'length_amount'        => array('type' => self::TYPE_UINT,    'required' => true),
				'length_unit'          => array('type' => self::TYPE_STRING,  'default' => '',
						'allowedValues' => array('day', 'month', 'year', '')
				),
				'disabled_upgrade_ids' => array('type' => self::TYPE_UNKNOWN, 'default' => '',
						'verification' => array('$this', '_verifyDisabledUpgradeIds')
				),
				'can_purchase'         => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_user_upgrade' => $this->_getUserUpgradeModel()->getUserUpgradeById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'user_upgrade_id = ' . $this->_db->quote($this->getExisting('user_upgrade_id'));
	}

	/**
	 * Verifies the extra user group IDs.
	 *
	 * @param array|string $userGroupIds Array or comma-delimited list
	 *
	 * @return boolean
	 */
	protected function _verifyExtraGroupIds(&$userGroupIds)
	{
		if (!is_array($userGroupIds))
		{
			$userGroupIds = preg_split('#,\s*#', $userGroupIds);
		}

		$userGroupIds = array_map('intval', $userGroupIds);
		$userGroupIds = array_unique($userGroupIds);
		sort($userGroupIds, SORT_NUMERIC);
		$userGroupIds = implode(',', $userGroupIds);

		return true;
	}

	/**
	 * Verifies that the cost of the upgrade is valid.
	 *
	 * @param float $cost
	 *
	 * @return boolean
	 */
	protected function _verifyCostAmount(&$cost)
	{
		if ($cost <= 0)
		{
			$this->error(new XenForo_Phrase('please_enter_an_upgrade_cost_greater_than_zero'), 'cost_amount');
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Verifies list of disabled upgrade IDs.
	 *
	 * @param string|array $disabledIds
	 *
	 * @return boolean
	 */
	protected function _verifyDisabledUpgradeIds(&$disabledIds)
	{
		if (!is_array($disabledIds))
		{
			$disabledIds = preg_split('#,\s*#', $disabledIds);
		}

		$disabledIds = array_map('intval', $disabledIds);
		$disabledIds = array_unique($disabledIds);
		sort($disabledIds, SORT_NUMERIC);
		$disabledIds = implode(',', $disabledIds);

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if (!$this->get('length_amount') || !$this->get('length_unit'))
		{
			$this->set('length_amount', 0);
			$this->set('length_unit', '');
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$this->_getUserUpgradeModel()->updateUserUpgradeCount();
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_getUserUpgradeModel()->updateUserUpgradeCount();
	}

	/**
	 * @return XenForo_Model_UserUpgrade
	 */
	protected function _getUserUpgradeModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserUpgrade');
	}
}