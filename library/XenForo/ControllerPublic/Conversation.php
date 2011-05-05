<?php

/**
 * Controller for conversation actions.
 *
 * @package XenForo_Conversation
 */
class XenForo_ControllerPublic_Conversation extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Pre-dispatch assurances.
	 */
	protected function _preDispatch($action)
	{
		$this->_assertRegistrationRequired();
	}

	/**
	 * Displays a list of visitor's conversations.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		if ($conversationId)
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$visitor = XenForo_Visitor::getInstance();
		$conversationModel = $this->_getConversationModel();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$conversationsPerPage = XenForo_Application::get('options')->discussionsPerPage;

		$totalConversations = $conversationModel->countConversationsForUser($visitor['user_id']);

		$conversations = $conversationModel->getConversationsForUser($visitor['user_id'], array(), array(
			'page' => $page,
			'perPage' => $conversationsPerPage
		));

		$viewParams = array(
			'conversations' => $conversationModel->prepareConversations($conversations),
			'page' => $page,
			'conversationsPerPage' => $conversationsPerPage,
			'totalConversations' => $totalConversations,

			'canStartConversation' => $conversationModel->canStartConversations()
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_List', 'conversation_list', $viewParams);
	}

	public function actionPopup()
	{
		$visitor = XenForo_Visitor::getInstance();
		$conversationModel = $this->_getConversationModel();

		$conversations = $conversationModel->getConversationsForUser($visitor['user_id'], array(
			'popupMode' => true
		), array(
			'join' => XenForo_Model_Conversation::FETCH_LAST_MESSAGE_AVATAR
		));

		$conversations = $conversationModel->prepareConversations($conversations);

		$conversationsUnread = array();
		$conversationsRead = array();

		foreach ($conversations AS $conversationId => $conversation)
		{
			if ($conversation['is_unread'])
			{
				$conversationsUnread[$conversationId] = $conversation;
			}
			else
			{
				$conversationsRead[$conversationId] = $conversation;
			}
		}

		$viewParams = array(
			'conversationsUnread' => $conversationsUnread,
			'conversationsRead' => $conversationsRead
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_ListPopup', 'conversation_list_popup', $viewParams);
	}

	/**
	 * Displays a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionView()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$messagesPerPage = XenForo_Application::get('options')->messagesPerPage;

		$conversationModel = $this->_getConversationModel();

		$recipients = $conversationModel->getConversationRecipients($conversationId);
		$messages = $conversationModel->getConversationMessages($conversationId, array(
			'perPage' => $messagesPerPage,
			'page' => $page,
		));

		$maxMessageDate = $conversationModel->getMaximumMessageDate($messages);
		if ($maxMessageDate > $conversation['last_read_date'])
		{
			$conversationModel->markConversationAsRead(
				$conversationId, XenForo_Visitor::getUserId(), $maxMessageDate, $conversation['last_message_date']
			);
		}

		$messages = $conversationModel->prepareMessages($messages, $conversation);

		$viewParams = array(
			'conversation' => $conversation,
			'recipients' => $recipients,

			'canEditConversation' => $conversationModel->canEditConversation($conversation),
			'canReplyConversation' => $conversationModel->canReplyToConversation($conversation),
			'canInviteUsers' => $conversationModel->canInviteUsersToConversation($conversation),

			'messages' => $messages,
			'lastMessage' => end($messages),
			'page' => $page,
			'messagesPerPage' => $messagesPerPage,
			'totalMessages' => $conversation['reply_count'] + 1
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_View', 'conversation_view', $viewParams);
	}

	/**
	 * Jumps to the first unread message in the conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnread()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		if (!$conversation['last_read_date'])
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('conversations', $conversation)
			);
		}

		$conversationModel = $this->_getConversationModel();

		if ($conversation['last_read_date'] >= $conversation['last_message_date'])
		{
			$firstUnread = false;
		}
		else
		{
			$firstUnread = $conversationModel->getNextMessageInConversation($conversationId, $conversation['last_read_date']);
		}

		if (!$firstUnread || $firstUnread['message_id'] == $conversation['last_message_id'])
		{
			$page = floor($conversation['reply_count'] / XenForo_Application::get('options')->messagesPerPage) + 1;
			$messageId = $conversation['last_message_id'];
		}
		else
		{
			$messagesBefore = $conversationModel->countMessagesBeforeDateInConversation($conversationId, $firstUnread['message_date']);

			$page = floor($messagesBefore / XenForo_Application::get('options')->messagesPerPage) + 1;
			$messageId = $firstUnread['message_id'];
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildPublicLink('conversations', $conversation, array('page' => $page)) . '#message-' . $messageId
		);
	}

	/**
	 * Redirects to the correct conversation, page and anchor for the given message.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMessage()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		$conversationModel = $this->_getConversationModel();

		$messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);
		$message = $conversationModel->getConversationMessageById($messageId);
		if (!$message || $message['conversation_id'] != $conversationId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('conversations', $conversation)
			);
		}
		else
		{
			$params = array();

			$messagesBefore = $conversationModel->countMessagesBeforeDateInConversation($conversationId, $message['message_date']);

			$messagesPerPage = XenForo_Application::get('options')->messagesPerPage;
			$page = floor($messagesBefore / $messagesPerPage) + 1;
			if ($page > 1)
			{
				$params['page'] = $page;
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('conversations', $conversation, $params) . '#message-' . $message['message_id']
			);
		}
	}

	/**
	 * Displays a form to create a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		if (!$this->_getConversationModel()->canStartConversations($errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$to = $this->_input->filterSingle('to', XenForo_Input::STRING);
		if ($to !== '')
		{
			$toUser = $this->getModelFromCache('XenForo_Model_User')->getUserByName($to, array(
				'join' => XenForo_Model_User::FETCH_USER_FULL
			));
			if (!$toUser)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
			}

			if (!$this->_getConversationModel()->canStartConversationWithUser($toUser, $errorPhraseKey))
			{
				return $this->responseError(new XenForo_Phrase('you_may_not_start_conversation_with_x_privacy_settings', array('name' => $toUser['username'])), 403);
			}

			$to = $toUser['username'];
		}

		$viewParams = array(
			'to' => $to,
			'remaining' => $this->_getConversationModel()->allowedAdditionalConversationRecipients(array())
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Add', 'conversation_add', $viewParams);
	}

	/**
	 * Inserts a new conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInsert()
	{
		$this->_assertPostOnly();

		if (!$this->_getConversationModel()->canStartConversations($errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$input = $this->_input->filter(array(
			'recipients' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'open_invite' => XenForo_Input::UINT,
			'conversation_locked' => XenForo_Input::UINT
		));
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$visitor = XenForo_Visitor::getInstance();

		$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
		$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $visitor->toArray());
		$conversationDw->set('user_id', $visitor['user_id']);
		$conversationDw->set('username', $visitor['username']);
		$conversationDw->set('title', $input['title']);
		$conversationDw->set('open_invite', $input['open_invite']);
		$conversationDw->set('conversation_open', $input['conversation_locked'] ? 0 : 1);
		$conversationDw->addRecipientUserNames(explode(',', $input['recipients'])); // checks permissions

		$messageDw = $conversationDw->getFirstMessageDw();
		$messageDw->set('message', $input['message']);

		$conversationDw->preSave();

		if (!$conversationDw->hasErrors())
		{
			$this->assertNotFlooding('conversation');
		}

		$conversationDw->save();
		$conversation = $conversationDw->getMergedData();

		$this->_getConversationModel()->markConversationAsRead(
			$conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('conversations', $conversation),
			new XenForo_Phrase('your_conversation_has_been_created')
		);
	}

	/**
	 * Displays a form to edit a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanEditConversation($conversation);

		$viewParams = array(
			'conversation' => $conversation
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Edit', 'conversation_edit', $viewParams);
	}

	/**
	 * Shows a preview of the conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPreview()
	{
		$this->_assertPostOnly();

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		$viewParams = array(
			'message' => $message
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Preview', 'conversation_preview', $viewParams);
	}

	/**
	 * Updates a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpdate()
	{
		$this->_assertPostOnly();

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanEditConversation($conversation);

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'open_invite' => XenForo_Input::UINT,
			'conversation_locked' => XenForo_Input::UINT
		));
		$update = array(
			'title' => $input['title'],
			'open_invite' => $input['open_invite'],
			'conversation_open' => ($input['conversation_locked'] ? 0 : 1)
		);

		$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
		$conversationDw->setExistingData($conversationId);
		$conversationDw->bulkSet($update);
		$conversationDw->save();

		$conversation = $conversationDw->getMergedData();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('conversations', $conversation)
		);
	}

	/**
	 * Leave a (user's) conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLeave()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		$deleteType = $this->_input->filterSingle('delete_type', XenForo_Input::STRING);

		if ($this->isConfirmedPost()) // delete the conversation
		{
			$this->_getConversationModel()->deleteConversationForUser(
				$conversationId, XenForo_Visitor::getUserId(), $deleteType
			);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('conversations')
			);
		}
		else
		{
			$viewParams = array(
				'conversation' => $conversation
			);

			return $this->responseView(
				'XenForo_ViewPublic_Conversation_Leave',
				'conversation_leave',
				$viewParams
			);
		}
	}

	/**
	 * Displays a form to edit a message in a conversation
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditMessage()
	{
		$viewParams = $this->_getEditViewParams();

		if ($this->_input->inRequest('more_options'))
		{
			$viewParams['conversationMessage']['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		}

		return $this->responseView(
			'XenForo_ViewPublic_Conversation_EditMessage',
			'conversation_message_edit',
			$viewParams
		);
	}

	/**
	 * Displays a simple overlay form to edit a message in a conversation
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditMessageInline()
	{
		return $this->responseView(
			'XenForo_ViewPublic_Conversation_EditMessageInline',
			'conversation_message_edit_inline',
			$this->_getEditViewParams()
		);
	}

	/**
	 * Previews the results of a message edit
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditMessagePreview()
	{
		$this->_assertPostOnly();

		$viewParams = $this->_getEditViewParams();

		$viewParams['message'] = XenForo_Helper_String::autoLinkBbCode(
			$this->getHelper('Editor')->getMessageText('editMessage', $this->_input)
		);

		return $this->responseView(
			'XenForo_ViewPublic_Conversation_EditMessagePreview',
			'conversation_message_edit_preview',
			$viewParams
		);
	}

	/**
	 * Fetch $viewParams for the two identical edit actions
	 *
	 * @return array $viewParams
	 */
	protected function _getEditViewParams()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$messageId = $this->_input->filterSingle('m', XenForo_Input::UINT);

		list($conversation, $conversationMessage) = $this->_getConversationAndMessageOrError($messageId, $conversationId);

		$this->_assertCanEditMessageInConversation($conversationMessage, $conversation);

		return array(
			'conversation' => $conversation,
			'conversationMessage' => $conversationMessage
		);
	}

	/**
	 * Saves an edited message
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSaveMessage()
	{
		if ($this->_input->inRequest('more_options'))
		{
			return $this->responseReroute(__CLASS__, 'editMessage');
		}

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$messageId = $this->_input->filterSingle('m', XenForo_Input::UINT);

		list($conversation, $conversationMessage) = $this->_getConversationAndMessageOrError($messageId, $conversationId);

		$this->_assertCanEditMessageInConversation($conversationMessage, $conversation);

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
		$dw->setExistingData($messageId);
		$dw->set('message', $message);
		$dw->save();

		if ($this->_noRedirect())
		{
			$conversationModel = $this->_getConversationModel();

			$message = array_merge($conversationMessage, $dw->getMergedData());

			$viewParams = array(
				'conversation' => $conversation,
				'message' => $conversationModel->prepareMessage($message, $conversation),
				'canReplyConversation' => $conversationModel->canReplyToConversation($conversation),
			);

			return $this->responseView(
				'XenForo_ViewPublic_Conversation_ViewMessage',
				'conversation_message',
				$viewParams
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('conversations/message', $conversation, array('message_id' => $conversationMessage['message_id']))
			);
		}
	}

	/**
	 * Displays a form to add a reply to a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionReply()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanReplyToConversation($conversation);

		if ($messageId = $this->_input->filterSingle('m', XenForo_Input::UINT)) // 'm' is a shortcut for 'message_id'
		{
			$conversationModel = $this->_getConversationModel();

			if ($message = $conversationModel->getConversationMessageById($messageId))
			{
				if ($message['conversation_id'] != $conversationId)
				{
					return $this->responseError(new XenForo_Phrase('not_possible_to_reply_to_messages_not_same_conversation'));
				}

				$defaultMessage = $conversationModel->getQuoteForConversationMessage($message);
			}
		}
		else if ($this->_input->inRequest('more_options'))
		{
			$message = array();
			$defaultMessage = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		}
		else
		{
			$message = array();
			$defaultMessage = '';
		}

		$viewParams = array(
			'conversation' => $conversation,
			'message' => $message,
			'defaultMessage' => $defaultMessage
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Reply', 'conversation_reply', $viewParams);
	}

	/**
	 * Inserts a reply into a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInsertReply()
	{
		$this->_assertPostOnly();

		if ($this->_input->inRequest('more_options'))
		{
			return $this->responseReroute(__CLASS__, 'reply');
		}

		$input = array();
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanReplyToConversation($conversation);

		$visitor = XenForo_Visitor::getInstance();

		$messageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
		$messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_MESSAGE_SENDER, $visitor->toArray());
		$messageDw->set('conversation_id', $conversation['conversation_id']);
		$messageDw->set('user_id', $visitor['user_id']);
		$messageDw->set('username', $visitor['username']);
		$messageDw->set('message', $input['message']);
		$messageDw->preSave();

		if (!$messageDw->hasErrors())
		{
			$this->assertNotFlooding('conversation');
		}

		$messageDw->save();

		$message = $messageDw->getMergedData();

		$conversationModel = $this->_getConversationModel();

		if (!$this->_noRedirect() || !$this->_input->inRequest('last_date'))
		{
			$conversationModel->markConversationAsRead(
				$conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time
			);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('conversations/message', $conversation, array('message_id' => $message['message_id'])),
				new XenForo_Phrase('your_message_has_been_posted')
			);
		}
		else
		{
			$lastDate = $this->_input->filterSingle('last_date', XenForo_Input::UINT);

			$limit = 3;
			$messageFetchOptions = array(
				'limit' => $limit + 1
			);

			$messages = $conversationModel->getNewestConversationMessagesAfterDate(
				$conversationId, $lastDate, $messageFetchOptions
			);

			// We fetched one more message than needed. If more than $limit message were returned,
			// we can show the 'there are more messages' notice
			if (count($messages) > $limit)
			{
				$firstUnshown = $conversationModel->getNextMessageInConversation($conversationId, $lastDate);

				// remove the extra post
				array_pop($messages);
			}
			else
			{
				$firstUnshown = false;
			}

			if (!$firstUnshown || $firstUnshown['message_date'] < $conversation['last_read_date'])
			{
				$conversationModel->markConversationAsRead(
					$conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time
				);
			}

			$messages = array_reverse($messages, true);
			$messages = $conversationModel->prepareMessages($messages, $conversation);

			$viewParams = array(
				'conversation' => $conversation,

				'canReplyConversation' => $conversationModel->canReplyToConversation($conversation),

				'firstUnshown' => $firstUnshown,
				'messages' => $messages,
				'lastMessage' => end($messages)
			);

			return $this->responseView(
				'XenForo_ViewPublic_Conversation_ViewNewMessages',
				'conversation_view_new_messages',
				$viewParams
			);
		}
	}

	/**
	 * Displays a form to invite users to a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInvite()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanInviteUsersToConversation($conversation);

		$viewParams = array(
			'conversation' => $conversation,
			'remaining' => $this->_getConversationModel()->allowedAdditionalConversationRecipients($conversation)
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Invite', 'conversation_invite', $viewParams);
	}

	/**
	 * Invites users to a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInviteInsert()
	{
		$this->_assertPostOnly();

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanInviteUsersToConversation($conversation);

		$recipients = $this->_input->filterSingle('recipients', XenForo_Input::STRING);

		/* @var $conversationDw XenForo_DataWriter_ConversationMaster */
		$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
		$conversationDw->setExistingData($conversationId);
		$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, XenForo_Visitor::getInstance()->toArray());
		$conversationDw->addRecipientUserNames(explode(',', $recipients));
		$conversationDw->save();

		if ($this->_noRedirect())
		{
			$viewParams = array(
				'conversation' => $conversation,
				'recipients' => $this->_getConversationModel()->getConversationRecipients($conversationId)
			);

			return $this->responseView(
				'XenForo_ViewPublic_Conversation_InviteInsert',
				'conversation_recipients',
				$viewParams
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('conversations', $conversation)
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('engaged_in_conversation');
	}

	/**
	 * Gets the specified conversation for the specified user, or throws an error.
	 *
	 * @param integer $conversationId
	 * @param integer|null $userId If null, uses visitor
	 *
	 * @return array
	 */
	protected function _getConversationOrError($conversationId, $userId = null)
	{
		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$conversationModel = $this->_getConversationModel();

		$conversation = $conversationModel->getConversationForUser($conversationId, $userId);
		if (!$conversation)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_conversation_not_found'), 404));
		}

		return $conversationModel->prepareConversation($conversation);
	}

	/**
	 * Gets the specified conversation and message, or throws an error
	 *
	 * @param integer $messageId
	 * @param integer $conversationId
	 * @param integer|null $userId
	 *
	 * @return array [$conversation, $message]
	 */
	protected function _getConversationAndMessageOrError($messageId, $conversationId, $userId = null)
	{
		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$conversationModel = $this->_getConversationModel();

		$conversation = $this->_getConversationOrError($conversationId, $userId);
		$message = $conversationModel->getConversationMessageById($messageId);

		if (!$message || $message['conversation_id'] != $conversation['conversation_id'])
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_message_not_found'), 404));
		}

		return array(
			$conversation,
			$conversationModel->prepareMessage($message, $conversation)
		);
	}

	/**
	 * Asserts that the currently browsing user can reply to this conversation.
	 *
	 * @param array $conversation
	 */
	protected function _assertCanReplyToConversation(array $conversation)
	{
		if (!$this->_getConversationModel()->canReplyToConversation($conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can edit this conversation.
	 *
	 * @param array $conversation
	 */
	protected function _assertCanEditConversation(array $conversation)
	{
		if (!$this->_getConversationModel()->canEditConversation($conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can invite users this conversation.
	 *
	 * @param array $conversation
	 */
	protected function _assertCanInviteUsersToConversation(array $conversation)
	{
		if (!$this->_getConversationModel()->canInviteUsersToConversation($conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can edit the specified message
	 *
	 * @param array $message
	 * @param array $conversation
	 */
	protected function _assertCanEditMessageInConversation(array $message, array $conversation)
	{
		if (!$this->_getConversationModel()->canEditMessage($message, $conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * @return XenForo_Model_Conversation
	 */
	protected function _getConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_Conversation');
	}
}