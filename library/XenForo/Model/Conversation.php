<?php

/**
 * Model for conversations.
 *
 * @package XenForo_Conversation
 */
class XenForo_Model_Conversation extends XenForo_Model
{
	const FETCH_LAST_MESSAGE_AVATAR = 0x01;

	/**
	 * Gets the specified conversation master record.
	 *
	 * @param integer $conversationId
	 *
	 * @return array|false
	 */
	public function getConversationMasterById($conversationId)
	{
		return $this->_getDb()->fetchRow('
			SELECT conversation_master.*
			FROM xf_conversation_master AS conversation_master
			WHERE conversation_master.conversation_id = ?
		', $conversationId);
	}

	/**
	 * Gets the specified conversation message record.
	 *
	 * @param integer $messageId
	 *
	 * @return array|false
	 */
	public function getConversationMessageById($messageId)
	{
		return $this->_getDb()->fetchRow('
			SELECT message.*,
				user.*, IF(user.username IS NULL, message.username, user.username) AS username,
				user_profile.*
			FROM xf_conversation_message AS message
			LEFT JOIN xf_user AS user ON
				(user.user_id = message.user_id)
			LEFT JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = message.user_id)
			WHERE message.message_id = ?
		', $messageId);
	}

	/**
	 * Gets the specified user conversation.
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array|false
	 */
	public function getConversationForUser($conversationId, $userId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareConversationFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT conversation_master.*,
				conversation_user.*,
				conversation_recipient.recipient_state, conversation_recipient.last_read_date
				' . $joinOptions['selectFields'] . '
			FROM xf_conversation_user AS conversation_user
			INNER JOIN xf_conversation_master AS conversation_master ON
				(conversation_user.conversation_id = conversation_master.conversation_id)
			INNER JOIN xf_conversation_recipient AS conversation_recipient ON
					(conversation_user.conversation_id = conversation_recipient.conversation_id
					AND conversation_user.owner_user_id = conversation_recipient.user_id)
				' . $joinOptions['joinTables'] . '
			WHERE conversation_user.conversation_id = ?
				AND conversation_user.owner_user_id = ?
		', array($conversationId, $userId));
	}

	/**
	 * Gets information about all recipients of a conversation.
	 *
	 * @param integer $conversationId
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array Format: [user id] => info
	 */
	public function getConversationRecipients($conversationId, array $fetchOptions = array())
	{
		return $this->fetchAllKeyed('
			SELECT conversation_recipient.*,
				user.*, user_option.*
			FROM xf_conversation_recipient AS conversation_recipient
			INNER JOIN xf_user AS user ON
				(user.user_id = conversation_recipient.user_id)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			WHERE conversation_recipient.conversation_id = ?
			ORDER BY user.username
		', 'user_id', $conversationId);
	}

	/**
	 * Gets info about a single recipient of a conversation.
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array|false
	 */
	public function getConversationRecipient($conversationId, $userId, array $fetchOptions = array())
	{
		return $this->_getDb()->fetchRow('
			SELECT conversation_recipient.*,
				user.*, user_option.*
			FROM xf_conversation_recipient AS conversation_recipient
			INNER JOIN xf_user AS user ON
				(user.user_id = conversation_recipient.user_id)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			WHERE conversation_recipient.conversation_id = ?
				AND conversation_recipient.user_id = ?
		', array($conversationId, $userId));
	}

	/**
	 * Get conversations that a user can see, ordered by the latest message first.
	 *
	 * @param integer $userId
	 * @param array $conditions Conditions for the WHERE clause
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array Format: [conversation id] => info
	 */
	public function getConversationsForUser($userId, array $conditions = array(), array $fetchOptions = array())
	{
		$joinOptions = $this->prepareConversationFetchOptions($fetchOptions);
		$whereClause = $this->prepareConversationConditions($conditions, $fetchOptions);

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT conversation_master.*,
					conversation_user.*,
					conversation_starter.*,
					conversation_recipient.recipient_state, conversation_recipient.last_read_date
					' . $joinOptions['selectFields'] . '
				FROM xf_conversation_user AS conversation_user
				INNER JOIN xf_conversation_master AS conversation_master ON
					(conversation_user.conversation_id = conversation_master.conversation_id)
				INNER JOIN xf_conversation_recipient AS conversation_recipient ON
					(conversation_user.conversation_id = conversation_recipient.conversation_id
					AND conversation_user.owner_user_id = conversation_recipient.user_id)
				LEFT JOIN xf_user AS conversation_starter ON
					(conversation_starter.user_id = conversation_master.user_id)
					' . $joinOptions['joinTables'] . '
				WHERE conversation_user.owner_user_id = ?
					AND ' . $whereClause . '
				ORDER BY conversation_user.last_message_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'conversation_id', $userId);
	}

	/**
	 * Get the specified conversations that a user can see, ordered by last message first.
	 *
	 * @param integer $userId
	 * @param array $conversationIds
	 *
	 * @return array Format: [conversation id] => info
	 */
	public function getConversationsForUserByIds($userId, array $conversationIds)
	{
		if (!$conversationIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT conversation_master.*,
				conversation_user.*,
				conversation_starter.*,
				conversation_recipient.recipient_state, conversation_recipient.last_read_date
			FROM xf_conversation_user AS conversation_user
			INNER JOIN xf_conversation_master AS conversation_master ON
				(conversation_user.conversation_id = conversation_master.conversation_id)
			INNER JOIN xf_conversation_recipient AS conversation_recipient ON
				(conversation_user.conversation_id = conversation_recipient.conversation_id
				AND conversation_user.owner_user_id = conversation_recipient.user_id)
			INNER JOIN xf_user AS conversation_starter ON
				(conversation_starter.user_id = conversation_master.user_id)
			WHERE conversation_user.owner_user_id = ?
				AND conversation_user.conversation_id IN (' . $this->_getDb()->quote($conversationIds) . ')
			ORDER BY conversation_user.last_message_date DESC
		', 'conversation_id', $userId);
	}

	/**
	 * Gets the total number of conversations that a user has.
	 *
	 * @param integer $userId
	 * @param array $conditions Conditions for the WHERE clause
	 * @param array $fetchConditions Only used in tandem with $conditions at this point
	 *
	 * @return integer
	 */
	public function countConversationsForUser($userId, array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareConversationConditions($conditions, $fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_conversation_user AS conversation_user
			INNER JOIN xf_conversation_master AS conversation_master ON
				(conversation_user.conversation_id = conversation_master.conversation_id)
			INNER JOIN xf_conversation_recipient AS conversation_recipient ON
				(conversation_user.conversation_id = conversation_recipient.conversation_id
				AND conversation_user.owner_user_id = conversation_recipient.user_id)
			WHERE conversation_user.owner_user_id = ?
				AND ' . $whereClause
		, $userId);
	}

	public function prepareConversationFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_LAST_MESSAGE_AVATAR)
			{
				$selectFields .= ',
					last_message_user.avatar_date AS last_message_avatar_date,
					last_message_user.gender AS last_message_gender,
					last_message_user.gravatar AS last_message_gravatar';
				$joinTables .= '
					LEFT JOIN xf_user AS last_message_user ON
						(last_message_user.user_id = conversation_user.last_message_user_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Prepares a set of conditions against which to select conversations.
	 *
	 * @param array $conditions List of conditions.
	 * --popupMode (boolean) constrains results to unread, or sent within timeframe specified by options->conversationPopupExpiryHours
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareConversationConditions(array $conditions, array $fetchOptions)
	{
		$sqlConditions = array();

		$options = XenForo_Application::get('options');

		if (!empty($conditions['popupMode']))
		{
			$cutOff = XenForo_Application::$time - 3600 * $options->conversationPopupExpiryHours;

			$sqlConditions[] = 'conversation_user.is_unread = 1 OR conversation_user.last_message_date > ' . $cutOff;
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Get messages within a given conversation.
	 *
	 * @param integer $conversationId
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array Format [message id] => info
	 */
	public function getConversationMessages($conversationId, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT message.*,
					user.*, IF(user.username IS NULL, message.username, user.username) AS username,
					user_profile.*
				FROM xf_conversation_message AS message
				LEFT JOIN xf_user AS user ON
					(user.user_id = message.user_id)
				LEFT JOIN xf_user_profile AS user_profile ON
					(user_profile.user_id = message.user_id)
				WHERE message.conversation_id = ?
				ORDER BY message.message_date
			', $limitOptions['limit'], $limitOptions['offset']
		), 'message_id', $conversationId);
	}

	/**
	 * Finds the newest conversation messages after the specified date.
	 *
	 * @param integer $conversationId
	 * @param integer $date
	 * @param array $fetchOptions
	 *
	 * @return array [message id] => info
	 */
	public function getNewestConversationMessagesAfterDate($conversationId, $date, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT message.*,
					user.*, IF(user.username IS NULL, message.username, user.username) AS username,
					user_profile.*
				FROM xf_conversation_message AS message
				LEFT JOIN xf_user AS user ON
					(user.user_id = message.user_id)
				LEFT JOIN xf_user_profile AS user_profile ON
					(user_profile.user_id = message.user_id)
				WHERE message.conversation_id = ?
					AND message.message_date > ?
				ORDER BY message.message_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'message_id', array($conversationId, $date));
	}

	/**
	 * Gets the next message in a conversation, post after the specified date. This is useful
	 * for finding the first unread message, for example.
	 *
	 * @param integer $conversationId
	 * @param integer $messageDate Finds first message posted after this
	 *
	 * @return array|false
	 */
	public function getNextMessageInConversation($conversationId, $messageDate)
	{
		$db = $this->_getDb();

		return $db->fetchRow($db->limit('
			SELECT *
			FROM xf_conversation_message
			WHERE conversation_id = ?
				AND message_date > ?
			ORDER BY message_date
		', 1), array($conversationId, $messageDate));
	}

	/**
	 * Count the number of messages before a given date in a conversation.
	 *
	 * @param integer $conversationId
	 * @param integer $messageDate
	 *
	 * @return integer
	 */
	public function countMessagesBeforeDateInConversation($conversationId, $messageDate)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_conversation_message AS conversation_message
			WHERE conversation_message.conversation_id = ?
				AND conversation_message.message_date < ?
		', array($conversationId, $messageDate));
	}

	/**
	 * Prepare a conversation for display or further processing.
	 *
	 * @param array $conversation
	 *
	 * @return array
	 */
	public function prepareConversation(array $conversation)
	{
		$conversation['isNew'] = ($conversation['last_message_date'] > $conversation['last_read_date']);
		$conversation['title'] = XenForo_Helper_String::censorString($conversation['title']);

		$conversation['lastPageNumbers'] = $this->getLastPageNumbers($conversation['reply_count']);

		$conversation['last_message'] = array(
			'message_id' => $conversation['last_message_id'],
			'message_date' => $conversation['last_message_date'],
			'user_id' => $conversation['last_message_user_id'],
			'username' => $conversation['last_message_username']
		);

		if (isset($conversation['last_message_avatar_date']))
		{
			$conversation['last_message']['avatar_date'] = $conversation['last_message_avatar_date'];
		}

		if (isset($conversation['last_message_gender']))
		{
			$conversation['last_message']['gender'] = $conversation['last_message_gender'];
		}

		if (isset($conversation['last_message_gravatar']))
		{
			$conversation['last_message']['gravatar'] = $conversation['last_message_gravatar'];
		}

		return $conversation;
	}

	/**
	 * Prepare a collection of conversations for display or further processing.
	 *
	 * @param array $conversations
	 *
	 * @return array
	 */
	public function prepareConversations(array $conversations)
	{
		foreach ($conversations AS &$conversation)
		{
			$conversation = $this->prepareConversation($conversation);
		}

		return $conversations;
	}

	/**
	 * Prepare a message for display or further processing.
	 *
	 * @param array $message
	 * @param array $conversation
	 *
	 * @return array Prepared message
	 */
	public function prepareMessage(array $message, array $conversation)
	{
		$message['isNew'] = ($message['message_date'] > $conversation['last_read_date']);

		$message['canEdit'] = $this->canEditMessage($message, $conversation);

		return $message;
	}

	/**
	 * Prepare a collection of messages (in the same conversation) for display or
	 * further processing.
	 *
	 * @param array $messages
	 * @param array $conversation
	 *
	 * @return array Prepared messages
	 */
	public function prepareMessages(array $messages, array $conversation)
	{
		$pagePosition = 0;

		foreach ($messages AS &$message)
		{
			$message = $this->prepareMessage($message, $conversation);

			$message['position_on_page'] = ++$pagePosition;
		}

		return $messages;
	}

	/**
	 * Gets the maximum message date in a list of messages.
	 *
	 * @param array $messages
	 *
	 * @return integer Max message date timestamp; 0 if no messages
	 */
	public function getMaximumMessageDate(array $messages)
	{
		$max = 0;
		foreach ($messages AS $message)
		{
			if ($message['message_date'] > $max)
			{
				$max = $message['message_date'];
			}
		}

		return $max;
	}

	/**
	 * Add the details of a new conversation reply to conversation recipients.
	 *
	 * @param array $conversation Conversation info
	 * @param array|null $replyUser Information about the user who replied
	 * @param array|null $messageInfo Array containing 'message', which is the text the message being sent
	 *
	 * @return array $recipients
	 */
	public function addConversationReplyToRecipients(array $conversation, array $replyUser = null, array $messageInfo = null)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$extraData = array('message_id' => $conversation['last_message_id']);

		$recipients = $this->getConversationRecipients($conversation['conversation_id']);
		foreach ($recipients AS $recipient)
		{
			switch ($recipient['recipient_state'])
			{
				case 'active':
					$db->query('
						UPDATE xf_conversation_user SET
							is_unread = 1,
							reply_count = ' . $db->quote($conversation['reply_count']) . ',
							last_message_date = ' . $db->quote($conversation['last_message_date']) . ',
							last_message_id = ' . $db->quote($conversation['last_message_id']) . ',
							last_message_user_id = ' . $db->quote($conversation['last_message_user_id']) . ',
							last_message_username = ' . $db->quote($conversation['last_message_username']) . '
						WHERE conversation_id = ?
							AND owner_user_id = ?
					', array($conversation['conversation_id'], $recipient['user_id']));

					$this->rebuildUnreadConversationCountForUser($recipient['user_id']);

					$this->insertConversationAlert($conversation, $recipient, 'reply', $replyUser, $extraData, $messageInfo);
					break;

				case 'deleted':
					$this->insertConversationRecipient($conversation, $recipient['user_id'], $recipient);
					$this->insertConversationAlert($conversation, $recipient, 'reply', $replyUser, $extraData, $messageInfo);
					break;
			}
		}

		XenForo_Db::commit($db);

		return $recipients;
	}

	/**
	 * Insert a new conversation recipient record.
	 *
	 * @param array $conversation Conversation info
	 * @param integer $user User to insert for
	 * @param array $existingRecipient Information about the existing recipient record (if there is one)
	 *
	 * @return boolean True if an insert was required (may be false if user is already an active recipient or is ignoring)
	 */
	public function insertConversationRecipient(array $conversation, $userId, array $existingRecipient = null)
	{
		if ($existingRecipient === null)
		{
			$existingRecipient = $this->getConversationRecipient($conversation['conversation_id'], $userId);
		}

		if ($existingRecipient)
		{
			if ($existingRecipient['recipient_state'] == 'deleted_ignored' || $existingRecipient['recipient_state'] == 'active')
			{
				return false;
			}
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->query('
			INSERT INTO xf_conversation_recipient
				(conversation_id, user_id, recipient_state, last_read_date)
			VALUES
				(?, ?, \'active\', 0)
			ON DUPLICATE KEY UPDATE recipient_state = VALUES(recipient_state)
		', array($conversation['conversation_id'], $userId));

		$db->query('
			INSERT IGNORE INTO xf_conversation_user
				(conversation_id, owner_user_id, is_unread, reply_count,
				last_message_date, last_message_id, last_message_user_id, last_message_username)
			VALUES
				(?, ?, 1, ?,
				?, ?, ?, ?)
		', array(
			$conversation['conversation_id'], $userId, $conversation['reply_count'],
			$conversation['last_message_date'], $conversation['last_message_id'],
			$conversation['last_message_user_id'], $conversation['last_message_username']
		));

		$this->rebuildUnreadConversationCountForUser($userId);

		$db->query('
			UPDATE xf_conversation_master SET
				recipient_count = recipient_count + 1
			WHERE conversation_id = ?
		', $conversation['conversation_id']);

		XenForo_Db::commit($db);

		return true;
	}

	/**
	 * Inserts an alert for this conversation.
	 *
	 * @param array $conversation
	 * @param array $alertUser User to notify
	 * @param string $action Action taken out (values: insert, reply, join)
	 * @param array|null $triggerUser User triggering the alert; defaults to last user to reply
	 * @param array|null $extraData
	 * @param array|null $messageInfo
	 */
	public function insertConversationAlert(array $conversation, array $alertUser, $action,
		array $triggerUser = null, array $extraData = null, array &$messageInfo = null
	)
	{
		if (!$triggerUser)
		{
			$triggerUser = array(
				'user_id' => $conversation['last_message_user_id'],
				'username' => $conversation['last_message_username']
			);
		}

		if ($triggerUser['user_id'] == $alertUser['user_id'])
		{
			return;
		}

		if ($alertUser['email_on_conversation'] && $alertUser['user_state'] == 'valid')
		{
			if (!isset($conversation['titleCensored']))
			{
				$conversation['titleCensored'] = XenForo_Helper_String::censorString($conversation['title']);
			}

			$mail = XenForo_Mail::create("conversation_{$action}", array(
				'receiver' => $alertUser,
				'sender' => $triggerUser,
				'options' => XenForo_Application::get('options'),
				'conversation' => $conversation,
				'message' => $messageInfo,
			), $alertUser['language_id']);

			$mail->enableAllLanguagePreCache();
			$mail->queue($alertUser['email'], $alertUser['username']);
		}

		// exit before we actually insert an alert, as the unread counter and the "inbox" link provides what's necessary
		return;

		if (XenForo_Model_Alert::userReceivesAlert($alertUser, 'conversation', $action))
		{
			XenForo_Model_Alert::alert(
				$alertUser['user_id'],
				$triggerUser['user_id'],
				$triggerUser['username'],
				'conversation',
				$conversation['conversation_id'],
				$action,
				$extraData
			);
		}
	}

	/**
	 * Delets a conversation record for a specific user. If all users have deleted the conversation,
	 * it will be completely removed.
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 * @param string $deleteType Type of deletion (either delete, or delete_ignore)
	 */
	public function deleteConversationForUser($conversationId, $userId, $deleteType)
	{
		$recipientState = ($deleteType == 'delete_ignore' ? 'deleted_ignored' : 'deleted');

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$conversationUserCondition = 'conversation_id = ' . $db->quote($conversationId)
			. ' AND user_id = ' . $db->quote($userId);

		$db->update('xf_conversation_recipient',
			array('recipient_state' => $recipientState),
			'conversation_id = ' . $db->quote($conversationId) . ' AND user_id = ' . $db->quote($userId)
		);
		$db->delete('xf_conversation_user',
			'conversation_id = ' . $db->quote($conversationId) . ' AND owner_user_id = ' . $db->quote($userId)
		);
		$db->delete('xf_user_alert',
			'content_type = \'conversation\' AND content_id = ' . $db->quote($conversationId)
				. ' AND alerted_user_id = ' . $db->quote($userId)
		);

		$this->rebuildUnreadConversationCountForUser($userId);

		$haveActive = false;
		foreach ($this->getConversationRecipients($conversationId) AS $recipient)
		{
			if ($recipient['recipient_state'] == 'active')
			{
				$haveActive = true;
				break;
			}
		}

		if (!$haveActive)
		{
			// no one has the conversation any more, so delete it
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
			$dw->setExistingData($conversationId);
			$dw->delete();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Marks the conversation as read to a certain point for a user.
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 * @param integer $newReadDate Timestamp to mark as read until
	 * @param integer $lastMessageDate Date of last message; only marks whole conversation read if more than this date
	 */
	public function markConversationAsRead($conversationId, $userId, $newReadDate, $lastMessageDate = 0)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$db->update('xf_conversation_recipient',
			array('last_read_date' => $newReadDate),
			'conversation_id = ' . $db->quote($conversationId) . ' AND user_id = ' . $db->quote($userId)
		);

		if ($newReadDate >= $lastMessageDate)
		{
			$rowsChanged = $db->update('xf_conversation_user',
				array('is_unread' => 0),
				'conversation_id = ' . $db->quote($conversationId) . ' AND owner_user_id = ' . $db->quote($userId)
			);
			if ($rowsChanged)
			{
				$db->query('
					UPDATE xf_user SET
						conversations_unread = IF(conversations_unread > 1, conversations_unread - 1, 0)
					WHERE user_id = ?
				', $userId);

				$visitor = XenForo_Visitor::getInstance();
				if ($userId == $visitor['user_id'] && $visitor['conversations_unread'] >= 1)
				{
					$visitor['conversations_unread'] -= 1;
				}
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Gets the count of unread conversations for the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function countUnreadConversationsForUser($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_conversation_user AS conversation_user
			INNER JOIN xf_conversation_master AS conversation_master ON
				(conversation_user.conversation_id = conversation_master.conversation_id)
			INNER JOIN xf_conversation_recipient AS conversation_recipient ON
					(conversation_user.conversation_id = conversation_recipient.conversation_id
					AND conversation_user.owner_user_id = conversation_recipient.user_id)
			WHERE conversation_user.owner_user_id = ?
				AND conversation_user.is_unread = 1
		', $userId);
	}

	/**
	 * Recalculates the unread conversation count for the specified user.
	 *
	 * @param integer $userId
	 */
	public function rebuildUnreadConversationCountForUser($userId)
	{
		$db = $this->_getDb();
		$db->update('xf_user', array(
			'conversations_unread' => $this->countUnreadConversationsForUser($userId)
		), 'user_id = ' . $db->quote($userId));
	}

	/**
	 * Determines if the viewing user can start conversations in general.
	 *
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStartConversations(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($viewingUser['user_state'] != 'valid')
		{
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'start'))
		{
			$maxRecipients = XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'maxRecipients');
			return ($maxRecipients == -1 || $maxRecipients > 0);
		}

		return false;
	}

	/**
	 * Determines if the viewing user can start a conversation with the given user.
	 * Does not check standard conversation permissions.
	 *
	 * @param array $user
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStartConversationWithUser(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return (
			$this->canStartConversations($errorPhraseKey, $viewingUser)
			&& !$user['is_banned']
			&& $this->getModelFromCache('XenForo_Model_User')->passesPrivacyCheck(
				$user['allow_send_personal_conversation'], $user, $viewingUser
			)
		);
	}

	/**
	 * Determines if the specified user can reply to the conversation.
	 * Does not check conversation viewing permissions.
	 *
	 * @param array $conversation
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canReplyToConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return ($conversation['user_id'] == $viewingUser['user_id'] || $conversation['conversation_open']);
	}

	/**
	 * Determines if the specified user can edit the conversation.
	 * Does not check conversation viewing permissions.
	 *
	 * @param array $conversation
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return ($conversation['user_id'] == $viewingUser['user_id']);
	}

	/**
	 * Determines if the specified user can invite users the conversation.
	 * Does not check conversation viewing permissions.
	 *
	 * @param array $conversation
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canInviteUsersToConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'alwaysInvite'))
		{
			return true;
		}

		if (!$conversation['conversation_open'])
		{
			return false;
		}

		if ($conversation['user_id'] == $viewingUser['user_id'] || $conversation['open_invite'])
		{
			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'start'))
			{
				$remaining = $this->allowedAdditionalConversationRecipients($conversation, $viewingUser);
				return ($remaining == -1 || $remaining >= 1);
			}
		}

		return false;
	}

	/**
	 * Determines if the specified user can edit the specified message within a conversation
	 *
	 * @param array $message
	 * @param array $conversation
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditMessage(array $message, array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		// moderator permission, so ignore conversation open/closed and time limit
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'editAnyPost'))
		{
			return true;
		}

		// no editing of messages in a closed conversation by normal users
		if (!$conversation['conversation_open'])
		{
			$errorPhraseKey = 'conversation_is_closed';
			return false;
		}

		// own message
		if ($message['user_id'] == $viewingUser['user_id'])
		{
			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'editOwnPost'))
			{
				$editLimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'editOwnPostTimeLimit');

				if ($editLimit != -1 && $message['message_date'] < XenForo_Application::$time - 60 * $editLimit)
				{
					$errorPhraseKey = array('message_edit_time_limit_expired', 'minutes' => $editLimit);
					return false;
				}

				return true;
			}
		}

		$errorPhraseKey = 'you_may_not_edit_this_message';
		return false;
	}

	/**
	 * Calculates the allowed number of additional conversation receiptions the
	 * viewing user can add to the given conversation.
	 * @param array $conversation Conversation; if empty array, assumes new conversation
	 * @param array|null $viewingUser
	 *
	 * @return integer -1 means unlimited; 0 is no more invites; other is remaining count
	 */
	public function allowedAdditionalConversationRecipients(array $conversation, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$maxRecipients = XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'maxRecipients');
		if ($maxRecipients == -1)
		{
			return -1;
		}

		if ($conversation)
		{
			$remaining = ($maxRecipients - $conversation['recipient_count'] + 1); // +1 represents self; self doesn't count
			return max(0, $remaining);
		}
		else
		{
			return $maxRecipients;
		}
	}

	/**
	 * Gets the quote text for the specified conversation message.
	 *
	 * @param array $message
	 * @param integer $maxQuoteDepth Max depth of quotes (-1 for unlimited)
	 *
	 * @return string
	 */
	public function getQuoteForConversationMessage(array $message, $maxQuoteDepth = 0)
	{
		return '[quote="' . $message['username'] . '"]'
			. trim(XenForo_Helper_String::stripQuotes($message['message'], $maxQuoteDepth))
			. "[/quote]\n";
	}

	/**
	 * Returns the last few page numbers of a conversation
	 *
	 * @param integer $replyCount
	 *
	 * @return array|boolean
	 */
	public function getLastPageNumbers($replyCount)
	{
		$perPage = XenForo_Application::get('options')->messagesPerPage;

		if (($replyCount +1) > $perPage)
		{
			return XenForo_Helper_Discussion::getLastPageNumbers($replyCount, $perPage);
		}
		else
		{
			return false;
		}
	}

	/**
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}
}