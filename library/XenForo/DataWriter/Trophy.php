<?php

/**
* Data writer for trophies
*
* @package XenForo_Trophy
*/
class XenForo_DataWriter_Trophy extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this section.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';

	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the description of this data.
	 *
	 * @var string
	 */
	const DATA_DESCRIPTION = 'phraseDescription';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_trophy_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_trophy' => array(
				'trophy_id'   => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'trophy_points' => array('type' => self::TYPE_UINT, 'required' => true),
				'criteria' => array('type' => self::TYPE_UNKNOWN, 'required' => true,
						'verification' => array('$this', '_verifyCriteria')
				)
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

		return array('xf_trophy' => $this->_getTrophyModel()->getTrophyById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'trophy_id = ' . $this->_db->quote($this->getExisting('trophy_id'));
	}

	/**
	 * Verifies that the criteria is valid and formats is correctly.
	 * Expected input format: [] with children: [rule] => name, [data] => info
	 *
	 * @param array|string $criteria Criteria array or serialize string; see above for format. Modified by ref.
	 *
	 * @return boolean
	 */
	protected function _verifyCriteria(&$criteria)
	{
		$criteria = XenForo_Helper_Criteria::unserializeCriteria($criteria);

		$criteriaFiltered = array();
		foreach ($criteria AS $criterion)
		{
			if (!empty($criterion['rule']))
			{
				if (empty($criterion['data']) || !is_array($criterion['data']))
				{
					$criterion['data'] = array();
				}

				$criteriaFiltered[] = array(
					'rule' => $criterion['rule'],
					'data' => $criterion['data']
				);
			}
		}

		$criteria = serialize($criteriaFiltered);
		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$trophyId = $this->get('trophy_id');

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($this->get('trophy_id')), $titlePhrase, ''
			);
		}

		$descriptionPhrase = $this->getExtraData(self::DATA_DESCRIPTION);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getDescriptionPhraseName($this->get('trophy_id')), $descriptionPhrase, ''
			);
		}

		if ($this->isUpdate() && $this->isChanged('trophy_points'))
		{
			$this->_updateTrophyPoints($trophyId, $this->getExisting('trophy_points'), $this->get('trophy_points'));
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$trophyId = $this->get('trophy_id');

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($trophyId));
		$this->_deleteMasterPhrase($this->_getDescriptionPhraseName($trophyId));

		$db = $this->_db;

		$this->_updateTrophyPoints($trophyId, $this->get('trophy_points'), 0);

		$db->delete('xf_user_trophy', 'trophy_id = ' . $db->quote($trophyId));
	}

	/**
	 * Updates the number of points a trophy is worth.
	 *
	 * @param integer $trophyId
	 * @param integer $oldPoints
	 * @param integer $newPoints
	 */
	protected function _updateTrophyPoints($trophyId, $oldPoints, $newPoints)
	{
		$adjust = $oldPoints - $newPoints;

		$this->_db->query('
			UPDATE xf_user SET
				trophy_points = IF(trophy_points > ?, trophy_points - ?, 0)
			WHERE user_id IN (
				SELECT user_id
				FROM xf_user_trophy
				WHERE trophy_id = ?
			)
		', array($adjust, $adjust, $trophyId));
	}

	/**
	 * Gets the name of the trophy's title phrase.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($id)
	{
		return $this->_getTrophyModel()->getTrophyTitlePhraseName($id);
	}

	/**
	 * Gets the name of the trophy's description phrase.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	protected function _getDescriptionPhraseName($id)
	{
		return $this->_getTrophyModel()->getTrophyDescriptionPhraseName($id);
	}

	/**
	 * @return XenForo_Model_Trophy
	 */
	protected function _getTrophyModel()
	{
		return $this->getModelFromCache('XenForo_Model_Trophy');
	}
}