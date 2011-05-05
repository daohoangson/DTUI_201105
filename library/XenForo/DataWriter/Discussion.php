<?php

/**
* Data writer for templates.
*
* @package XenForo_Discussion
*/
abstract class XenForo_DataWriter_Discussion extends XenForo_DataWriter
{
	/**
	 * Gets the object that represents the definition of this type of discussion.
	 *
	 * @return XenForo_Discussion_Definition_Abstract
	 */
	abstract public function getDiscussionDefinition();

	/**
	 * Gets the object that represents the definition of the message within this discussion.
	 *
	 * @return XenForo_DiscussionMessage_Definition_Abstract
	 */
	abstract public function getDiscussionMessageDefinition();

	/**
	 * Get information about the last message in this discussion. It is expected to either
	 * be an an empty array (or false) or contain standard discussion message fields.
	 *
	 * @return array|false
	 */
	abstract protected function _getLastMessageInDiscussion();

	/**
	 * Gets simple information about all messages in this discussion. Fields are assumed
	 * to be the standard discussion message fields, not including the actual message unless
	 * specifically requested.
	 *
	 * @param boolean $includeMessage If true, includes the message contents
	 *
	 * @return array Format: [discussion message id] => info
	 */
	abstract protected function _getMessagesInDiscussionSimple($includeMessage = false);

	/**
	 * Rebuilds counters and position lists for this discussion.
	 *
	 * @return boolean True if the results are valid; false otherwise (if false, discussion can be removed)
	 */
	abstract public function rebuildDiscussion();

	/**
	 * Option to control whether a first message is required on insert of a new discussion.
	 * An example of this is requiring the first post when creating a thread. Generally,
	 * this will remain at the default, but certain applications will need to create a
	 * discussion before the child messages. Default is true.
	 *
	 * @var string
	 */
	const OPTION_REQUIRE_INSERT_FIRST_MESSAGE = 'requireInsertFirstMessage';

	/**
	 * Option that controls whether the data in this discussion should be indexed for
	 * search. If this value is set inconsistently for the same discussion (and messages within),
	 * data might be orphaned in the search index. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_INDEX_FOR_SEARCH = 'indexForSearch';

	/**
	 * Option that controls whether the posting user's message count will be
	 * changed by posting this message. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_CHANGE_USER_MESSAGE_COUNT = 'changeUserMessageCount';

	/**
	 * Option that controls what to do with the case of discussion titles. Defaults
	 * to option value.
	 *
	 * @var string
	 */
	const OPTION_ADJUST_TITLE_CASE = 'adjustTitleCase';

	/**
	 * Controls whether the container (eg, forum) data is updated. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_UPDATE_CONTAINER = 'updateContainer';

	/**
	 * Holds the reason for soft deletion.
	 *
	 * @var string
	 */
	const DATA_DELETE_REASON = 'deleteReason';

	/**
	 * Default value for the change user message count option.
	 *
	 * @var boolean
	 */
	protected $_defaultChangeUserMessageCount = true;

	/**
	* Identifies if a discussion has a parent container item.
	* Must overload {@see _getContainerDataWriter} if set to true.
	*
	* @var boolean
	*/
	protected $_hasParentContainer = true;

	/**
	 * Data about the discussion's definition.
	 *
	 * @var XenForo_DiscussionMessage_Definition_Abstract
	 */
	protected $_disussionDefinition = null;

	/**
	 * Data about the definition of messages within.
	 *
	 * @var XenForo_DiscussionMessage_Definition_Abstract
	 */
	protected $_messageDefinition = null;

	/**
	 * Data writer for the first message in this discussion.
	 *
	 * @var XenForo_DataWriter_DiscussionMessage|null
	 */
	protected $_firstMessageDw = null;

	/**
	* Constructor.
	*
	* @param constant   Error handler. See {@link ERROR_EXCEPTION} and related.
	* @param array|null Dependency injector. Array keys available: db, cache.
	*/
	public function __construct($errorHandler = self::ERROR_EXCEPTION, array $inject = null)
	{
		$this->_discussionDefinition = $this->getDiscussionDefinition();

		$config = $this->_discussionDefinition->getDiscussionConfiguration();
		$this->_hasParentContainer = $config['hasParentContainer'];
		$this->_defaultChangeUserMessageCount = $config['changeUserMessageCount'];

		$this->_messageDefinition = $this->getDiscussionMessageDefinition();

		parent::__construct($errorHandler, $inject);
	}

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getCommonFields()
	{
		$structure = $this->_discussionDefinition->getDiscussionStructure();

		return array(
			$structure['table'] => array(
				$structure['key']        => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				$structure['container']  => array('type' => self::TYPE_UINT, 'required' => true),
				'title'                  => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 100,
					'verification' => array('$this', '_verifyTitle'), 'requiredError' => 'please_enter_valid_title'
				),
				'reply_count'            => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'view_count'             => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'user_id'                => array('type' => self::TYPE_UINT, 'required' => true),
				'username'               => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
				'post_date'              => array('type' => self::TYPE_UINT, 'default' => 0),
				'sticky'                 => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'discussion_state'       => array('type' => self::TYPE_STRING, 'default' => 'visible',
					'allowedValues' => array('visible', 'moderated', 'deleted')
				),
				'discussion_open'        => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'discussion_type'        => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 25),
				'first_post_id'          => array('type' => self::TYPE_UINT, 'default' => 0),
				'last_post_date'         => array('type' => self::TYPE_UINT, 'default' => 0),
				'last_post_id'           => array('type' => self::TYPE_UINT, 'default' => 0),
				'last_post_user_id'      => array('type' => self::TYPE_UINT, 'default' => 0),
				'last_post_username'     => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 50),
			)
		);
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		$keyName = $this->getDiscussionKeyName();

		return $keyName . ' = ' . $this->_db->quote($this->getExisting($keyName));
	}

	/**
	* Gets the default set of options for this data writer.
	*
	* @return array
	*/
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REQUIRE_INSERT_FIRST_MESSAGE => true,
			self::OPTION_INDEX_FOR_SEARCH => true,
			self::OPTION_CHANGE_USER_MESSAGE_COUNT => $this->_defaultChangeUserMessageCount,
			self::OPTION_ADJUST_TITLE_CASE => XenForo_Application::get('options')->adjustTitleCase,
			self::OPTION_UPDATE_CONTAINER => true
		);
	}

	/**
	 * Gets a data writer that represents the first message. This is
	 * primarily used for inserts, but may also be used for updates.
	 *
	 * @return XenForo_DataWriter_DiscussionMessage
	 */
	public function getFirstMessageDw()
	{
		if (!$this->_firstMessageDw)
		{
			$this->_firstMessageDw = $this->_discussionDefinition->getFirstMessageDataWriter(
				$this->get('first_post_id'), $this->_errorHandler
			);
			$this->_firstMessageDw->setDiscussionDataWriter($this, $this->isInsert());
		}

		return $this->_firstMessageDw;
	}

	/**
	 * Verifies that the discussion title is valid
	 *
	 * @param string
	 *
	 * @return boolean
	 */
	public function _verifyTitle(&$title)
	{
		// TODO: send these to callbacks to allow hookability?

		switch ($this->getOption(self::OPTION_ADJUST_TITLE_CASE))
		{
			case 'ucfirst': // sentence case
				$title = utf8_ucfirst(utf8_strtolower($title));
				break;

			case 'ucwords': // title case
				$title = utf8_ucwords(utf8_strtolower($title));
				break;
		}

		return true;
	}

	/**
	* Generic Discussion Message Pre Save handler
	*/
	protected final function _preSave()
	{
		if ($this->isInsert() && $this->getOption(self::OPTION_REQUIRE_INSERT_FIRST_MESSAGE) && !$this->_firstMessageDw)
		{
			throw new XenForo_Exception('A discussion insert was attempted without the required first message.');
		}

		if ($this->isInsert() && !$this->isChanged('discussion_state'))
		{
			$this->set('discussion_state', 'visible');
		}

		$this->_setDynamicFieldDefaults();
		$this->_discussionPreSave();

		if ($this->_firstMessageDw)
		{
			$this->_syncFirstMessageDw();
			$this->_preSaveFirstMessageDw();
		}
	}

	/**
	 * Synchronizes the first message DW with data set in this discussions before saving.
	 * By default, this assumes that fields with matching names are the same between tables.
	 */
	protected function _syncFirstMessageDw()
	{
		if ($this->isInsert())
		{
			// this will be corrected in post-save (before the message is inserted)
			$this->_firstMessageDw->set($this->_firstMessageDw->getContainerKeyName(), 0);
		}

		foreach ($this->_newData AS $table => $newData)
		{
			foreach ($newData AS $field => $value)
			{
				$this->_firstMessageDw->set($field, $value, '', array('ignoreInvalidFields' => true));
			}
		}
	}

	/**
	 * Validate that the first message DW is saveable and merge any errors into this DW.
	 */
	protected function _preSaveFirstMessageDw()
	{
		$messageDw = $this->_firstMessageDw;

		$messageDw->preSave();
		$firstMessageErrors = $messageDw->getErrors();
		if ($firstMessageErrors)
		{
			$this->_errors = array_merge($this->_errors, $firstMessageErrors);
		}
	}

	/**
	 * Sets the pre-save defaults for fields with dynamic default values.
	 */
	protected function _setDynamicFieldDefaults()
	{
		if (!$this->get('post_date'))
		{
			$this->set('post_date', XenForo_Application::$time);
		}

		if (!$this->get('last_post_date'))
		{
			$this->set('last_post_date', $this->get('post_date'));
			$this->set('last_post_user_id', $this->get('user_id'));
			$this->set('last_post_username', $this->get('username'));
		}
	}

	/**
	* Designed to be overridden by child classes
	*/
	protected function _discussionPreSave()
	{
	}

	/**
	* Generic Discussion Message Post Save handler
	*/
	protected final function _postSave()
	{
		if ($this->_firstMessageDw)
		{
			$this->_saveFirstMessageDw();
		}

		if ($this->_hasParentContainer && $this->getOption(self::OPTION_UPDATE_CONTAINER))
		{
			$this->_updateContainerPostSave();
		}

		$this->_updateDeletionLog();
		$this->_updateModerationQueue();

		$messages = $this->_getMessagesInDiscussionSimple();

		if ($this->isChanged('discussion_state') && $this->isUpdate())
		{
			if ($this->getOption(self::OPTION_CHANGE_USER_MESSAGE_COUNT))
			{
				$this->_updateUserMessageCount($messages);
			}

			$this->_updateUserLikeCount($messages);
		}

		if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH))
		{
			$this->_indexForSearch($messages);
		}

		$this->_discussionPostSave($messages);
	}

	/**
	 * Saves the first message DW and merges and required data from it back to this
	 * (eg, first post ID).
	 */
	protected function _saveFirstMessageDw()
	{
		$messageDw = $this->_firstMessageDw;

		if ($this->isInsert())
		{
			$messageDw->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_UPDATE_PARENT_DISCUSSION, false);

			$discussionId = $this->get($this->getDiscussionKeyName());
			$messageDw->set($messageDw->getContainerKeyName(), $discussionId, '', array('setAfterPreSave' => true));
		}

		if ($messageDw->hasChanges())
		{
			// must clear out DW, as the message will try to save it and possibly cause conflicts
			$messageDw->setDiscussionDataWriter(null, $this->isInsert());

			$messageDw->save();
		}

		if ($this->isInsert())
		{
			$messageId = $messageDw->getDiscussionMessageId();

			// note: it is assumed that the other last post info will have been handled by this DW
			$toUpdate = array(
				'first_post_id' => $messageId,
				'last_post_id' => $messageId
			);

			$keyName = $this->getDiscussionKeyName();
			$condition = $keyName . ' = ' . $this->_db->quote($this->get($keyName));

			$this->_db->update($this->getDiscussionTableName(), $toUpdate, $condition);
			$this->bulkSet($toUpdate, array('setAfterPreSave' => true));
		}

		$this->_publishToNewsFeed();
	}

	/**
	 * Updates the necessary data in the container.
	 */
	protected function _updateContainerPostSave()
	{
		$containerKey = $this->getContainerKeyName();

		if ($this->isUpdate() && $this->isChanged($containerKey))
		{
			// this is a move. move is like: inserting into new container...
			$newContainerDw = $this->_discussionDefinition->getContainerDataWriter($this->get($containerKey), $this->_errorHandler);
			if ($newContainerDw)
			{
				$newContainerDw->updateCountersAfterDiscussionSave($this, true);
				if ($newContainerDw->hasChanges())
				{
					$newContainerDw->save();
				}
			}

			// ...and deleting from old container
			$oldContainerDw = $this->_discussionDefinition->getContainerDataWriter($this->getExisting($containerKey), $this->_errorHandler);
			if ($oldContainerDw)
			{
				$oldContainerDw->updateCountersAfterDiscussionDelete($this);
				if ($oldContainerDw->hasChanges())
				{
					$oldContainerDw->save();
				}
			}
		}
		else
		{
			$containerDw = $this->_discussionDefinition->getContainerDataWriter($this->get($containerKey), $this->_errorHandler);
			if ($containerDw)
			{
				$containerDw->updateCountersAfterDiscussionSave($this);
				if ($containerDw->hasChanges())
				{
					$containerDw->save();
				}
			}
		}
	}

	/**
	 * Updates the deletion log if necessary.
	 */
	protected function _updateDeletionLog()
	{
		if (!$this->isChanged('discussion_state'))
		{
			return;
		}

		if ($this->get('discussion_state') == 'deleted')
		{
			$reason = $this->getExtraData(self::DATA_DELETE_REASON);
			$this->getModelFromCache('XenForo_Model_DeletionLog')->logDeletion(
				$this->getContentType(), $this->getDiscussionId(), $reason
			);
		}
		else if ($this->getExisting('discussion_state') == 'deleted')
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
				$this->getContentType(), $this->getDiscussionId()
			);
		}
	}

	/**
	 * Updates the moderation queue if necessary.
	 */
	protected function _updateModerationQueue()
	{
		if (!$this->isChanged('discussion_state'))
		{
			return;
		}

		if ($this->get('discussion_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->insertIntoModerationQueue(
				$this->getContentType(), $this->getDiscussionId(), $this->get('post_date')
			);
		}
		else if ($this->getExisting('discussion_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue(
				$this->getContentType(), $this->getDiscussionId()
			);
		}
	}

	/**
	 * Updates the search index for this discussion.
	 *
	 * @param array $messages List of messages in this discussion. Does not include text!
	 */
	protected function _indexForSearch(array $messages)
	{
		if ($this->get('discussion_state') == 'visible')
		{
			if ($this->getExisting('discussion_state') != 'visible')
			{
				$this->_insertIntoSearchIndex($messages);
			}
			else if ($this->isChanged('title'))
			{
				$this->_updateSearchIndexTitle($messages);
			}
		}
		else if ($this->isUpdate() && $this->get('discussion_state') != 'visible' && $this->getExisting('discussion_state') == 'visible')
		{
			$this->_deleteFromSearchIndex($messages);
		}
	}

	/**
	 * Inserts a record in the search index for this discussion.
	 *
	 * @param array $messages List of messages in this discussion. Does not include text!
	 */
	protected function _insertIntoSearchIndex(array $messages)
	{
		$discussion = $this->getMergedData();
		$indexer = new XenForo_Search_Indexer();

		$discussionHandler = $this->_discussionDefinition->getSearchDataHandler();
		if ($discussionHandler)
		{
			$discussionHandler->insertIntoIndex($indexer, $discussion);
		}

		if ($messages && $this->isUpdate())
		{
			$messageHandler = $this->_messageDefinition->getSearchDataHandler();
			if ($messageHandler)
			{
				$fullMessages = $this->_getMessagesInDiscussionSimple(true); // re-get with message contents
				foreach ($fullMessages AS $key => $message)
				{
					$messageHandler->insertIntoIndex($indexer, $message, $discussion);
					unset($fullMessages[$key]);
				}
			}
		}
	}

	/**
	 * Updates the title in the search index for this discussion.
	 *
	 * @param array $messages List of messages in this discussion. Does not include text!
	 */
	protected function _updateSearchIndexTitle(array $messages)
	{
		$indexer = new XenForo_Search_Indexer();

		$discussionHandler = $this->_discussionDefinition->getSearchDataHandler();
		if ($discussionHandler)
		{
			$discussionHandler->insertIntoIndex($indexer, $this->getMergedData());
		}

		if ($messages && $this->isUpdate())
		{
			$title = $this->get('title');

			$messageHandler = $this->_messageDefinition->getSearchDataHandler();
			if ($messageHandler)
			{
				$messageHandler->updateIndex($indexer, reset($messages), array('title' => $title));
			}
		}
	}

	/**
	 * Deletes this discussion from the search index.
	 *
	 * @param array $messages List of messages in this discussion. Does not include text!
	 */
	protected function _deleteFromSearchIndex(array $messages)
	{
		$discussion = $this->getMergedData();
		$indexer = new XenForo_Search_Indexer();

		$discussionHandler = $this->_discussionDefinition->getSearchDataHandler();
		if ($discussionHandler)
		{
			$discussionHandler->deleteFromIndex($indexer, $discussion);
		}

		$messageHandler = $this->_messageDefinition->getSearchDataHandler();
		if ($messageHandler)
		{
			$messageHandler->deleteFromIndex($indexer, $messages);
		}
	}

	/**
	* Designed to be overridden by child classes
	*
	* @param array $messages Messages in discussion
	*/
	protected function _discussionPostSave(array $messages)
	{
	}

	/**
	 * Generic discussion pre-delete handler.
	 */
	protected final function _preDelete()
	{
		$this->_discussionPreDelete();
	}

	/**
	* Designed to be overridden by child classes
	*/
	protected function _discussionPreDelete()
	{
	}

	/**
	 * Generic discussion post-delete handler.
	 */
	protected final function _postDelete()
	{
		if ($this->_hasParentContainer && $this->getOption(self::OPTION_UPDATE_CONTAINER))
		{
			$this->_updateContainerPostDelete();
		}

		$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
			$this->getContentType(), $this->getDiscussionId()
		);
		$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue(
			$this->getContentType(), $this->getDiscussionId()
		);

		$messages = $this->_getMessagesInDiscussionSimple();

		$this->_deleteDiscussionMessages($messages);

		if ($this->getOption(self::OPTION_CHANGE_USER_MESSAGE_COUNT))
		{
			$this->_updateUserMessageCount($messages, true);
		}

		if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH))
		{
			$this->_deleteFromSearchIndex($messages);
		}

		$this->_discussionPostDelete($messages);

		$this->_deleteFromNewsFeed();
	}

	/**
	 * Updates the user message count for all the messages in
	 * this discussion.
	 *
	 * @param array $messages Messages in discussion
	 * @param boolean $isDelete True if discussion is being deleted
	 */
	protected function _updateUserMessageCount(array $messages, $isDelete = false)
	{
		if ($this->get('discussion_state') == 'visible'
			&& $this->getExisting('discussion_state') != 'visible'
		)
		{
			$updateType = 'add';
		}
		else if ($this->getExisting('discussion_state') == 'visible'
			&& ($this->get('discussion_state') != 'visible' || $isDelete)
		)
		{
			$updateType = 'subtract';
		}
		else
		{
			return;
		}

		$users = array();
		foreach ($messages AS $message)
		{
			if ($message['message_state'] == 'visible' && $message['user_id'])
			{
				if (isset($users[$message['user_id']]))
				{
					$users[$message['user_id']]++;
				}
				else
				{
					$users[$message['user_id']] = 1;
				}
			}
		}

		foreach ($users AS $userId => $modify)
		{
			if ($updateType == 'add')
			{
				$this->_db->query('
					UPDATE xf_user
					SET message_count = message_count + ?
					WHERE user_id = ?
				', array($modify, $userId));
			}
			else
			{
				$this->_db->query('
					UPDATE xf_user
					SET message_count = IF(message_count > ?, message_count - ?, 0)
					WHERE user_id = ?
				', array($modify, $modify, $userId));
			}
		}
	}

	/**
	 * Updates the user like count for all the messages in
	 * this discussion.
	 *
	 * @param array $messages Messages in discussion
	 * @param boolean $isDelete True if discussion is being deleted
	 */
	protected function _updateUserLikeCount(array $messages, $isDelete = false)
	{
		if ($this->get('discussion_state') == 'visible'
			&& $this->getExisting('discussion_state') != 'visible'
		)
		{
			$updateType = 'add';
		}
		else if ($this->getExisting('discussion_state') == 'visible'
			&& ($this->get('discussion_state') != 'visible' || $isDelete)
		)
		{
			$updateType = 'subtract';
		}
		else
		{
			return;
		}

		$users = array();
		foreach ($messages AS $message)
		{
			if ($message['likes'] && $message['message_state'] == 'visible' && $message['user_id'])
			{
				if (isset($users[$message['user_id']]))
				{
					$users[$message['user_id']] += $message['likes'];
				}
				else
				{
					$users[$message['user_id']] = $message['likes'];
				}
			}
		}

		foreach ($users AS $userId => $modify)
		{
			if ($updateType == 'add')
			{
				$this->_db->query('
					UPDATE xf_user
					SET like_count = like_count + ?
					WHERE user_id = ?
				', array($modify, $userId));
			}
			else
			{
				$this->_db->query('
					UPDATE xf_user
					SET like_count = IF(like_count > ?, like_count - ?, 0)
					WHERE user_id = ?
				', array($modify, $modify, $userId));
			}
		}
	}

	/**
	 * Update container information after the main record has been deleted.
	 */
	protected function _updateContainerPostDelete()
	{
		$containerDw = $this->_discussionDefinition->getContainerDataWriter($this->get($this->getContainerKeyName()), $this->_errorHandler);
		if ($containerDw)
		{
			$containerDw->updateCountersAfterDiscussionDelete($this);
			if ($containerDw->hasChanges())
			{
				$containerDw->save();
			}
		}
	}

	/**
	 * Deletes all messages in this discussion.
	 *
	 * @param array $messages
	 */
	protected function _deleteDiscussionMessages(array $messages)
	{
		if (!$messages)
		{
			return;
		}

		$messageStructure = $this->_messageDefinition->getMessageStructure();
		$messageContentType = $this->_messageDefinition->getContentType();

		$messageIds = array_keys($messages);

		$this->_db->delete($messageStructure['table'],
			"$messageStructure[key] IN (" . $this->_db->quote($messageIds) . ')'
		);

		$this->getModelFromCache('XenForo_Model_Attachment')->deleteAttachmentsFromContentIds(
			$messageContentType, $messageIds
		);

		$visibleMessageIds = array();
		$nonVisibleMessageIds = array();
		foreach ($messages AS $messageId => $message)
		{
			if (empty($message['message_state']) || $message['message_state'] == 'visible')
			{
				$visibleMessageIds[] = $messageId;
			}
			else
			{
				$nonVisibleMessageIds[] = $messageId;
			}
		}
		$this->getModelFromCache('XenForo_Model_Like')->deleteContentLikes(
			$messageContentType, $visibleMessageIds, ($this->get('discussion_state') == 'visible')
		);
		$this->getModelFromCache('XenForo_Model_Like')->deleteContentLikes(
			$messageContentType, $visibleMessageIds, false
		);

		$this->getModelFromCache('XenForo_Model_Ip')->deleteByContent(
			$messageContentType, $messageIds
		);
		$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
			$messageContentType, $messageIds
		);
		$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue(
			$messageContentType, $messageIds
		);
	}

	/**
	* Designed to be overridden by child classes
	*
	* @param array $messages List of messages in this discussion
	*/
	protected function _discussionPostDelete(array $messages)
	{
	}

	/**
	 * Updates denormalized counters, based on changes made to the provided
	 * discussion message, after the message has been saved.
	 *
	 * @param XenForo_DataWriter_DiscussionMessage $messageDw
	 */
	public function updateCountersAfterMessageSave(XenForo_DataWriter_DiscussionMessage $messageDw)
	{
		if ($messageDw->get('message_state') == 'visible' && $messageDw->get('post_date') > $this->get('last_post_date'))
		{
			$this->set('last_post_date', $messageDw->get('post_date'));
			$this->set('last_post_id', $messageDw->getDiscussionMessageId());
			$this->set('last_post_user_id', $messageDw->get('user_id'));
			$this->set('last_post_username', $messageDw->get('username'));
		}

		if ($messageDw->get('message_state') == 'visible' && $messageDw->getExisting('message_state') != 'visible')
		{
			$this->set('reply_count', $this->get('reply_count') + 1);
		}
		else if ($messageDw->getExisting('message_state') == 'visible' && $messageDw->get('message_state') != 'visible')
		{
			$this->set('reply_count', $this->get('reply_count') - 1);

			if ($messageDw->getDiscussionMessageId() == $this->get('last_post_id'))
			{
				$this->updateLastPost();
			}
		}
	}

	/**
	 * Updates denormalized counters. Used after a message has been deleted.
	 *
	 * @param XenForo_DataWriter_DiscussionMessage $messageDw
	 * @param boolean $deleteIfFirstMessage If true and message if first, delete discussion
	 *
	 * @return string State changes to discussion: delete means remove discussion; firstDelete means first message was removed but still valid
	 */
	public function updateCountersAfterMessageDelete(XenForo_DataWriter_DiscussionMessage $messageDw, $deleteIfFirstMessage = true)
	{
		$messageId = $messageDw->getDiscussionMessageId();

		if ($messageId == $this->get('first_post_id'))
		{
			if (!$deleteIfFirstMessage && $this->rebuildDiscussion())
			{
				return 'firstDelete';
			}
			else
			{
				return 'delete';
			}
		}

		if ($messageId == $this->get('last_post_id'))
		{
			$this->updateLastPost();
		}

		if ($messageDw->get('message_state') == 'visible')
		{
			$this->set('reply_count', $this->get('reply_count') - 1);
		}

		return '';
	}

	/**
	 * Updates the value of the last post for this discussion.
	 */
	public function updateLastPost()
	{
		$lastPost = $this->_getLastMessageInDiscussion();
		if ($lastPost)
		{
			$messageStructure = $this->_messageDefinition->getMessageStructure();

			$this->set('last_post_id', $lastPost[$messageStructure['key']]);
			$this->set('last_post_date', $lastPost['post_date']);
			$this->set('last_post_user_id', $lastPost['user_id']);
			$this->set('last_post_username', $lastPost['username']);
		}
		else
		{
			$this->set('last_post_id', $this->get('first_post_id'));
			$this->set('last_post_date', $this->get('post_date'));
			$this->set('last_post_user_id', $this->get('user_id'));
			$this->set('last_post_username', $this->get('username'));
		}
	}

	/**
	 * Gets the current value of the discussion ID for this discussion.
	 *
	 * @return integer
	 */
	public function getDiscussionId()
	{
		return $this->get($this->getDiscussionKeyName());
	}

	/**
	 * Publishes an insert or update event to the news feed
	 */
	protected function _publishToNewsFeed()
	{
		$this->_getNewsFeedModel()->publish(
			$this->get('user_id'),
			$this->get('username'),
			$this->getContentType(),
			$this->getDiscussionId(),
			($this->isUpdate() ? 'update' : 'insert')
		);
	}

	/**
	 * Removes an already published news feed item
	 */
	protected function _deleteFromNewsFeed()
	{
		$this->_getNewsFeedModel()->delete(
			$this->getContentType(),
			$this->getDiscussionId()
		);
	}

	/**
	 * The name of the table that holds the discussion data.
	 *
	 * @return string
	 */
	public function getDiscussionTableName()
	{
		return $this->_discussionDefinition->getDiscussionTableName();
	}

	/**
	 * The name of the discussion table's primary key. This must be an auto increment field.
	 *
	 * @return string
	 */
	public function getDiscussionKeyName()
	{
		return $this->_discussionDefinition->getDiscussionKeyName();
	}

	/**
	 * Gets the name of the field that represents the discussion's container.
	 * This must be an integer field.
	 *
	 * @return string
	 */
	public function getContainerKeyName()
	{
		return $this->_discussionDefinition->getContainerKeyName();
	}

	/**
	 * Gets the content type for tables that contain multiple data types together.
	 *
	 * @return string
	 */
	public function getContentType()
	{
		return $this->_discussionDefinition->getContentType();
	}
}