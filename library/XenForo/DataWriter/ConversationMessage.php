<?php

/**
* Data writer for conversation messages.
*
* @package XenForo_Conversation
*/
class XenForo_DataWriter_ConversationMessage extends XenForo_DataWriter
{
	/**
	 * Option that controls whether changes to the conversation should be done
	 * by this data writer. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_UPDATE_CONVERSATION = 'updateConversation';

	/**
	 * Option to control whether the message sender is in the recipient list.
	 * Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_CHECK_SENDER_RECIPIENT = 'checkSenderReceipient';

	/**
	 * Constant for extra data that holds the sending user information
	 *
	 * @var string
	 */
	const DATA_MESSAGE_SENDER = 'sendingUser';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_conversation_message' => array(
				'message_id'      => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'conversation_id' => array('type' => self::TYPE_UINT,   'required' => true),
				'message_date'    => array('type' => self::TYPE_UINT,   'default' => XenForo_Application::$time),
				'user_id'         => array('type' => self::TYPE_UINT,   'required' => true),
				'username'        => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
				'message'         => array('type' => self::TYPE_STRING, 'required' => true,
						'requiredError' => 'please_enter_valid_message'
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

		return array('xf_conversation_message' => $this->_getConversationModel()->getConversationMessageById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'message_id = ' . $this->_db->quote($this->getExisting('message_id'));
	}

	/**
	 * Gets the data writer's default options.
	 *
	 * @return array
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_UPDATE_CONVERSATION => true,
			self::OPTION_CHECK_SENDER_RECIPIENT => true
		);
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->getOption(self::OPTION_CHECK_SENDER_RECIPIENT))
		{
			$recipients = $this->_getConversationModel()->getConversationRecipients($this->get('conversation_id'));
			if (!isset($recipients[$this->get('user_id')]))
			{
				throw new XenForo_Exception('Non-recipients cannot reply to a conversation.');
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isInsert() && $this->getOption(self::OPTION_UPDATE_CONVERSATION))
		{
			$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
			$conversationDw->setExistingData($this->get('conversation_id'));
			$conversationDw->addReply($this->getMergedData());

			$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $this->getExtraData(self::DATA_MESSAGE_SENDER));
			$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_MESSAGE, $this->get('message'));

			$conversationDw->save();
		}
	}

	/**
	 * Pre-delete handling.
	 */
	protected function _preDelete()
	{
		throw new Exception('Conversation message deletion is not implemented at this time.');
	}

	/**
	 * @return XenForo_Model_Conversation
	 */
	protected function _getConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_Conversation');
	}
}