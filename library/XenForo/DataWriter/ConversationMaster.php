<?php

/**
* Data writer for conversation masters.
*
* @package XenForo_Conversation
*/
class XenForo_DataWriter_ConversationMaster extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds information about the user that is sending the message,
	 * or inviting additional users to the conversation.
	 *
	 * @var string
	 */
	const DATA_ACTION_USER = 'actionUser';

	/**
	 * Constant for extra data that holds the message information
	 *
	 * @var string
	 */
	const DATA_MESSAGE = 'messageBbCode';

	/**
	 * Additional recipients to add to the conversation.
	 *
	 * @var array List of user IDs
	 */
	protected $_newRecipients = array();

	/**
	 * The first message data writer. This is applicable only
	 * when inserting the initial conversation.
	 *
	 * @var XenForo_DataWriter_ConversationMessage
	 */
	protected $_firstMessageDw = null;

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_conversation_master' => array(
				'conversation_id'       => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'title'                 => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 100,
						'requiredError' => 'please_enter_valid_title'
				),
				'user_id'               => array('type' => self::TYPE_UINT,   'required' => true),
				'username'              => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
				'start_date'            => array('type' => self::TYPE_UINT,   'default' => 0),
				'open_invite'           => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'conversation_open'     => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'reply_count'           => array('type' => self::TYPE_UINT,   'default' => 0),
				'recipient_count'       => array('type' => self::TYPE_UINT,   'default' => 0),
				'first_message_id'      => array('type' => self::TYPE_UINT,   'default' => 0),
				'last_message_date'     => array('type' => self::TYPE_UINT,   'default' => 0),
				'last_message_id'       => array('type' => self::TYPE_UINT,   'default' => 0),
				'last_message_user_id'  => array('type' => self::TYPE_UINT,   'default' => 0),
				'last_message_username' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 50),
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

		return array('xf_conversation_master' => $this->_getConversationModel()->getConversationMasterById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'conversation_id = ' . $this->_db->quote($this->getExisting('conversation_id'));
	}

	/**
	 * Add recipients via a list of user IDs. These users are not checked for sending permissions!
	 *
	 * @param array $userIds
	 */
	public function addRecipientUserIds(array $userIds)
	{
		$this->_newRecipients = array_merge($this->_newRecipients, $userIds);
	}

	/**
	 * Add recipients via a list of user names. This checks that the visitor/invite user can send to the recipients.
	 *
	 * @param array $usernames
	 */
	public function addRecipientUserNames(array $usernames)
	{
		$permUser = $this->getExtraData(self::DATA_ACTION_USER);
		if (!$permUser || $permUser == XenForo_Visitor::getUserId())
		{
			$permUser = null;
			$permUserId = XenForo_Visitor::getUserId();
		}
		else
		{
			$permUserId = $permUser['user_id'];
		}

		$users = $this->_getUserModel()->getUsersByNames(
			$usernames,
			array(
				'join' => XenForo_Model_User::FETCH_USER_PRIVACY + XenForo_Model_User::FETCH_USER_OPTION,
				'followingUserId' => $permUserId
			),
			$notFound
		);

		if ($notFound)
		{
			$this->error(new XenForo_Phrase('the_following_recipients_could_not_be_found_x', array('names' => implode(', ', $notFound))), 'recipients');
		}
		else
		{
			$conversationModel = $this->_getConversationModel();
			$noStart = array();
			foreach ($users AS $user)
			{
				if (!$conversationModel->canStartConversationWithUser($user, $null, $permUser))
				{
					$noStart[] = $user['username'];
				}
			}

			if ($noStart)
			{
				$this->error(new XenForo_Phrase('you_may_not_start_a_conversation_with_the_following_recipients_x', array('names' => implode(', ', $noStart))), 'recipients');
			}
			else
			{
				$this->_newRecipients = array_merge($this->_newRecipients, array_keys($users));

				$remaining = $conversationModel->allowedAdditionalConversationRecipients($this->getMergedExistingData(), $permUser);
				if ($remaining > -1 && count($this->_newRecipients) > $remaining)
				{
					$this->error(new XenForo_Phrase('you_may_only_invite_x_members_to_join_this_conversation', array('count' => $remaining)), 'recipients');
				}
			}
		}
	}

	/**
	 * Adds the data from a reply to the conversation. This does not actually
	 * add the reply; it will normally be called by the message DW.
	 *
	 * @param array $reply Information about the reply
	 */
	public function addReply(array $reply)
	{
		$this->set('reply_count', $this->get('reply_count') + 1);
		if ($reply['message_date'] > $this->get('last_message_date'))
		{
			$this->set('last_message_date',     $reply['message_date']);
			$this->set('last_message_id',       $reply['message_id']);
			$this->set('last_message_user_id',  $reply['user_id']);
			$this->set('last_message_username', $reply['username']);
		}
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->_newRecipients)
		{
			$creatorUserId = $this->get('user_id');
			foreach ($this->_newRecipients AS $key => $recipientUserId)
			{
				if ($recipientUserId == $creatorUserId)
				{
					unset($this->_newRecipients[$key]);
				}
			}
		}

		if ($this->isInsert() && !$this->_newRecipients)
		{
			$this->error(new XenForo_Phrase('please_enter_at_least_one_valid_recipient'), 'recipients', false);
		}

		if ($this->isInsert() && !$this->_firstMessageDw)
		{
			throw new XenForo_Exception('Must create a first message DW on insert.');
		}

		if ($this->isInsert())
		{
			if (!$this->isChanged('start_date'))
			{
				$this->set('start_date', XenForo_Application::$time);
			}

			$fieldMap = array(
				'last_message_date' => 'start_date',
				'last_message_user_id' => 'user_id',
				'last_message_username' => 'username'
			);
			foreach ($fieldMap AS $childField => $parentField)
			{
				if (!$this->isChanged($childField))
				{
					$this->set($childField, $this->get($parentField));
				}
			}
		}

		if ($this->_firstMessageDw)
		{
			$messageDw = $this->_firstMessageDw;
			$messageDw->set('conversation_id', 0);

			foreach ($this->_newData AS $table => $newData)
			{
				foreach ($newData AS $field => $value)
				{
					$messageDw->set($field, $value, '', array('ignoreInvalidFields' => true));
				}
			}

			$messageDw->preSave();
			$firstMessageErrors = $messageDw->getErrors();
			if ($firstMessageErrors)
			{
				$this->_errors = array_merge($this->_errors, $firstMessageErrors);
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$conversationId = $this->get('conversation_id');

		if ($this->_firstMessageDw)
		{
			$this->_firstMessageDw->set('conversation_id', $conversationId, '', array('setAfterPreSave' => true));
			$this->_firstMessageDw->save();
			$firstMessageId = $this->_firstMessageDw->get('message_id');

			$toUpdate = array(
				'first_message_id' => $firstMessageId,
				'last_message_id' => $firstMessageId
			);
			$this->_db->update('xf_conversation_master', $toUpdate, 'conversation_id = ' . $this->_db->quote($conversationId));
			$this->bulkSet($toUpdate, array('setAfterPreSave' => true));
		}

		$conversationModel = $this->_getConversationModel();
		$conversation = $this->getMergedData();

		$actionUser = $this->getExtraData(self::DATA_ACTION_USER);
		$messageInfo = array('message' => $this->getExtraData(self::DATA_MESSAGE));

		if ($this->isUpdate() && $this->isChanged('reply_count'))
		{
			$conversationModel->addConversationReplyToRecipients($conversation, $actionUser, $messageInfo);
		}
		else if ($this->isInsert())
		{
			$this->_newRecipients[] = $this->get('user_id');
		}

		if ($recipients = $this->_getUserModel()->getUsersByIds($this->_newRecipients, array('join' => XenForo_Model_User::FETCH_USER_OPTION)))
		{
			// on initial insert, don't need to look for recipient records
			$existingRecipient = ($this->isInsert() ? array() : null);
			$alertAction = ($this->isInsert() ? 'insert' : 'join');

			foreach ($recipients AS $recipient)
			{
				if ($conversationModel->insertConversationRecipient($conversation, $recipient['user_id'], $existingRecipient))
				{
					$conversationModel->insertConversationAlert($conversation, $recipient, $alertAction, $actionUser, null, $messageInfo);
				}
			}
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$db = $this->_db;

		$conversationIdQuoted = $db->quote($this->get('conversation_id'));

		$db->delete('xf_conversation_message', 'conversation_id = ' . $conversationIdQuoted);
		$db->delete('xf_conversation_recipient', 'conversation_id = ' . $conversationIdQuoted);
		$db->delete('xf_conversation_user', 'conversation_id = ' . $conversationIdQuoted);
		$db->delete('xf_user_alert', 'content_type = \'conversation\' AND content_id = ' . $conversationIdQuoted);
	}

	/**
	 * Gets the first message DW. This can (and must) only be done on inserts.
	 *
	 * @return XenForo_DataWriter_ConversationMessage
	 */
	public function getFirstMessageDw()
	{
		if ($this->isUpdate())
		{
			throw new XenForo_Exception('Cannot manage first message on updates.');
		}

		if (!$this->_firstMessageDw)
		{
			$this->_firstMessageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage', $this->_errorHandler);
			$this->_firstMessageDw->setOption(XenForo_DataWriter_ConversationMessage::OPTION_UPDATE_CONVERSATION, false);
			$this->_firstMessageDw->setOption(XenForo_DataWriter_ConversationMessage::OPTION_CHECK_SENDER_RECIPIENT, false);
		}

		return $this->_firstMessageDw;
	}

	/**
	 * @return XenForo_Model_Conversation
	 */
	protected function _getConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_Conversation');
	}
}