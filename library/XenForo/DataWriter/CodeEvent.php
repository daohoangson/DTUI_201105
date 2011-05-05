<?php

/**
* Data writer for code events.
*
* @package XenForo_CodeEvents
*/
class XenForo_DataWriter_CodeEvent extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_code_event_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_code_event' => array(
				'event_id' => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'required' => true,
						'verification' => array('$this', '_verifyEventId'), 'requiredError' => 'please_enter_valid_code_event_id'
				),
				'description' => array('type' => self::TYPE_STRING, 'default' => ''),
				'addon_id' => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true)
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'event_id'))
		{
			return false;
		}

		return array('xf_code_event' => $this->_getCodeEventModel()->getEventById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'event_id = ' . $this->_db->quote($this->getExisting('event_id'));
	}

	/**
	 * Verifies that the event ID is valid.
	 *
	 * @param string $eventId
	 *
	 * @return boolean
	 */
	protected function _verifyEventId($eventId)
	{
		if (preg_match('/[^a-zA-Z0-9_]/', $eventId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'addon_id');
			return false;
		}

		if ($this->isInsert() || $eventId != $this->getExisting('event_id'))
		{
			$existing = $this->_getCodeEventModel()->getEventById($eventId);
			if ($existing)
			{
				$this->error(new XenForo_Phrase('code_event_ids_must_be_unique'), 'addon_id');
				return false;
			}
		}

		return true;
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('event_id'))
		{
			$this->_db->update('xf_code_event_listener', array(
				'event_id' => $this->get('event_id')
			), 'event_id = ' . $this->_db->quote($this->getExisting('event_id')));

			$this->_getCodeEventModel()->rebuildEventListenerCache();
		}
	}

	/**
	 * Gets the code event model object.
	 *
	 * @return XenForo_Model_CodeEvent
	 */
	protected function _getCodeEventModel()
	{
		return $this->getModelFromCache('XenForo_Model_CodeEvent');
	}
}