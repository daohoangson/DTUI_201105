<?php

/**
* Data writer for smilies
*
* @package XenForo_Smilie
*/
class XenForo_DataWriter_Smilie extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_smilie_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_smilie' => array(
				'smilie_id'   => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'title'       => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_title'
				),
				'smilie_text' => array('type' => self::TYPE_STRING, 'required' => true,
						'verification' => array('$this', '_verifySmilieText'), 'requiredError' => 'please_enter_valid_smilie_text'
				),
				'image_url'   => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 200,
						'requiredError' => 'please_enter_valid_url'
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

		return array('xf_smilie' => $this->_getSmilieModel()->getSmilieById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'smilie_id = ' . $this->_db->quote($this->getExisting('smilie_id'));
	}

	/**
	 * Verifies that the smilie text is valid.
	 *
	 * @param string $smilieText
	 *
	 * @return boolean
	 */
	protected function _verifySmilieText($smilieText)
	{
		if ($this->isInsert() || $smilieText != $this->getExisting('smilie_text'))
		{
			$id = $this->get('smilie_id');

			$existing = $this->_getSmilieModel()->getSmiliesByText($smilieText);
			foreach ($existing AS $text => $smilie)
			{
				if (!$id || $smilie['smilie_id'] != $id)
				{
					$this->error(new XenForo_Phrase('smilie_replacement_text_must_be_unique_x_in_use', array('text' => $text)), 'smilie_text');
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$this->_rebuildSmilieCache();
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_rebuildSmilieCache();
	}

	/**
	 * Rebuilds the smilie cache.
	 */
	protected function _rebuildSmilieCache()
	{
		$this->_getSmilieModel()->rebuildSmilieCache();
	}

	/**
	 * @return XenForo_Model_Smilie
	 */
	protected function _getSmilieModel()
	{
		return $this->getModelFromCache('XenForo_Model_Smilie');
	}
}