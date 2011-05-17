<?php

/**
* Data writer for poll responses.
*
* @package XenForo_Poll
*/
class XenForo_DataWriter_PollResponse extends XenForo_DataWriter
{
	/**
	 * Option that controls whether response cache in the poll will be rebuilt.
	 * Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_REBUILD_RESPONSE_CACHE = 'rebuildResponseCache';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_poll_response' => array(
				'poll_response_id'    => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'poll_id'             => array('type' => self::TYPE_UINT,   'required' => true),
				'response'            => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 100),
				'response_vote_count' => array('type' => self::TYPE_UINT,   'default' => 0),
				'voters'              => array('type' => self::TYPE_BINARY, 'default' => ''),
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

		return array('xf_poll_response' => $this->_getPollModel()->getPollResponseById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'poll_response_id = ' . $this->_db->quote($this->getExisting('poll_response_id'));
	}

	/**
	 * Gets the default options.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_RESPONSE_CACHE => true
		);
	}

	/**
	 * Post-save handling.
	 */
	public function _postSave()
	{
		if ($this->getOption(self::OPTION_REBUILD_RESPONSE_CACHE))
		{
			$this->_getPollModel()->rebuildPollResponseCache($this->get('poll_id'));
		}
	}

	/**
	 * Post-delete handling.
	 */
	public function _postDelete()
	{
		$pollModel = $this->_getPollModel();
		$pollId = $this->get('poll_id');

		$this->_db->delete('xf_poll_vote',
			'poll_response_id = ' . $this->_db->quote($this->get('poll_response_id'))
		);

		$this->_db->update('xf_poll',
			array('voter_count' => $pollModel->getPollVoterCount($pollId)),
			'poll_id = ' . $this->_db->quote($pollId)
		);

		if ($this->getOption(self::OPTION_REBUILD_RESPONSE_CACHE))
		{
			$pollModel->rebuildPollResponseCache($pollId);
		}
	}

	/**
	 * @return XenForo_Model_Poll
	 */
	protected function _getPollModel()
	{
		return $this->getModelFromCache('XenForo_Model_Poll');
	}
}