<?php

/**
* Data writer for polls.
*
* @package XenForo_Poll
*/
class XenForo_DataWriter_Poll extends XenForo_DataWriter
{
	/**
	 * Maximum number of responses that can be added to this poll. If the poll response
	 * DW is used directly, this can be circumvented. Defaults to the value of the option.
	 *
	 * @var string
	 */
	const OPTION_MAX_RESPONSES = 'maxResponses';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_poll_not_found';

	/**
	 * List of new responses to add.
	 *
	 * @var array
	 */
	protected $_newResponses = array();

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_poll' => array(
				'poll_id'      => array('type' => self::TYPE_UINT,    'autoIncrement' => true),
				'content_type' => array('type' => self::TYPE_STRING,  'required' => true, 'maxLength' => 25),
				'content_id'   => array('type' => self::TYPE_UINT,    'required' => true),
				'question'     => array('type' => self::TYPE_STRING,  'required' => true, 'maxLength' => 100,
						 'requiredError' => 'please_enter_poll_question'
				),
				'responses'    => array('type' => self::TYPE_BINARY,  'default' => ''),
				'voter_count'  => array('type' => self::TYPE_UINT,    'default' => 0),
				'public_votes' => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'multiple'     => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'close_date'   => array('type' => self::TYPE_UINT,    'default' => 0)
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

		return array('xf_poll' => $this->_getPollModel()->getPollById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'poll_id = ' . $this->_db->quote($this->getExisting('poll_id'));
	}

	/**
	 * Gets the default options.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_MAX_RESPONSES => XenForo_Application::get('options')->pollMaximumResponses
		);
	}

	/**
	 * Adds any number of responses to the poll. Blank options are ignored.
	 *
	 * @param array $responses
	 */
	public function addResponses(array $responses)
	{
		foreach ($responses AS $key => $response)
		{
			if (!is_string($response) || $response === '')
			{
				unset($responses[$key]);
			}
		}
		$this->_newResponses = array_merge($this->_newResponses, $responses);
	}

	/**
	 * Determines if the poll has new responses.
	 *
	 * @return boolean
	 */
	public function hasNewResponses()
	{
		return (count($this->_newResponses) > 0);
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isInsert() && count($this->_newResponses) < 2)
		{
			$this->error(new XenForo_Phrase('please_enter_at_least_two_poll_responses'), 'responses');
		}

		if ($this->_newResponses && $this->getOption(self::OPTION_MAX_RESPONSES))
		{
			if ($this->isUpdate())
			{
				$existingResponseCount = count($this->_getPollModel()->getPollResponsesInPoll($this->get('poll_id')));
			}
			else
			{
				$existingResponseCount = 0;
			}

			if (count($this->_newResponses) + $existingResponseCount > $this->getOption(self::OPTION_MAX_RESPONSES))
			{
				$this->error(new XenForo_Phrase('too_many_poll_responses_have_been_entered'), 'responses');
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$pollId = $this->get('poll_id');

		if ($this->_newResponses)
		{
			foreach ($this->_newResponses AS $newResponse)
			{
				$responseDw = XenForo_DataWriter::create('XenForo_DataWriter_PollResponse', XenForo_DataWriter::ERROR_SILENT);
				$responseDw->setOption(XenForo_DataWriter_PollResponse::OPTION_REBUILD_RESPONSE_CACHE, false);
				$responseDw->bulkSet(array(
					'poll_id' => $pollId,
					'response' => $newResponse
				));
				$responseDw->save();
			}
		}

		if (($this->_newResponses || $this->isChanged('voter_count')))
		{
			$this->_getPollModel()->rebuildPollResponseCache($pollId);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$pollIdQuoted = $this->_db->quote($this->get('poll_id'));

		$this->_db->delete('xf_poll_response', 'poll_id = ' . $pollIdQuoted);
		$this->_db->delete('xf_poll_vote', 'poll_id = ' . $pollIdQuoted);

		if ($this->get('content_type') == 'thread')
		{
			$this->_db->update('xf_thread',
				array('discussion_type' => ''),
				'thread_id = ' . $this->_db->quote($this->get('content_id'))
			);
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