<?php

/**
* Data writer for discussion.
*
* @package XenForo_Discussion
*/
abstract class XenForo_DataWriter_DiscussionMessage extends XenForo_DataWriter
{
	/**
	 * Gets the object that represents the definition of this type of message.
	 *
	 * @return XenForo_DiscussionMessage_Definition_Abstract
	 */
	abstract public function getDiscussionMessageDefinition();

	/**
	 * Meta-option for discussion message submissions that are via automated means.
	 * Some dynamic checks are skipped (eg, message length and flood check), and
	 * other data is ignored (eg, IP address).
	 */
	const OPTION_IS_AUTOMATED = 'isAutomated';

	/**
	 * Option that controls whether an IP address should be recorded for this message.
	 * Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_SET_IP_ADDRESS = 'setIpAddress';

	/**
	 * Option that controls whether data in the parent discussion should be updated,
	 * including reply counts and last post info. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_UPDATE_PARENT_DISCUSSION = 'updateParentDiscussion';

	/**
	 * Option that controls whether the data in this message should be indexed for
	 * search. If this value is set inconsistently for the same message, data might
	 * be orphaned in the search index.
	 *
	 * @var string
	 */
	const OPTION_INDEX_FOR_SEARCH = 'indexForSearch';

	/**
	 * Option that controls whether we should check this message for spam. This applies
	 * to new messages only.
	 *
	 * @var string
	 */
	const OPTION_CHECK_SPAM = 'checkSpam';

	/**
	 * Option that controls the maximum number of characters that are allowed in
	 * a message.
	 *
	 * @var string
	 */
	const OPTION_MAX_MESSAGE_LENGTH = 'maxMessageLength';

	/**
	 * Maximum number of images allowed in a message.
	 *
	 * @var string
	 */
	const OPTION_MAX_IMAGES = 'maxImages';

	/**
	 * Maximum pieces of media allowed in a message.
	 *
	 * @var string
	 */
	const OPTION_MAX_MEDIA = 'maxMedia';

	/**
	 * Option that controls whether the posting user's message count will be
	 * changed by posting this message.
	 *
	 * @var string
	 */
	const OPTION_CHANGE_USER_MESSAGE_COUNT = 'changeUserMessageCount';

	/**
	 * Option that controls whether a guest user name is checked to confirm that it
	 * doesn't conflict with a valid user. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_VERIFY_GUEST_USERNAME = 'verifyGuestUsername';

	/**
	 * Option that controls whether the discussion is removed if deleting the first message.
	 * Note that if the discussion only has the first message and it's deleted, it will always be removed.
	 *
	 * @var string
	 */
	const OPTION_DELETE_DISCUSSION_FIRST_MESSAGE = 'deleteDiscussionFirstMessage';

	/**
	 * Holds the temporary hash used to pull attachments and associate them with this message.
	 *
	 * @var string
	 */
	const DATA_ATTACHMENT_HASH = 'attachmentHash';

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
	* Identifies if a message has a parent discussion item.
	* Must overload {@see getDiscussionDataWriter()} if set to true.
	*
	* @var boolean
	*/
	protected $_hasParentDiscussion = true;

	/**
	 * Data about the message's definition.
	 *
	 * @var XenForo_DiscussionMessage_Definition_Abstract
	 */
	protected $_messageDefinition = null;

	/**
	 * Reflects important changes to the parent discussion. Current values:
	 * 	* [empty] - no change of value
	 *  * delete - discussion has been deleted
	 *  * firstDelete - first message has been deleted, restructure has been carried out
	 *
	 * @var string
	 */
	protected $_discussionChange = '';

	/**
	 * The discussion data writer.
	 *
	 * @var XenForo_DataWriter_Discussion
	 */
	protected $_discussionDw = null;

	/**
	 * The insert/update mode of the discussion data writer
	 *
	 * @var string insert|update
	 */
	protected $_discussionMode = null;

	/**
	* Constructor.
	*
	* @param constant   Error handler. See {@link ERROR_EXCEPTION} and related.
	* @param array|null Dependency injector. Array keys available: db, cache.
	*/
	public function __construct($errorHandler = self::ERROR_EXCEPTION, array $inject = null)
	{
		$this->_messageDefinition = $this->getDiscussionMessageDefinition();

		$config = $this->_messageDefinition->getMessageConfiguration();
		$this->_hasParentDiscussion = $config['hasParentDiscussion'];
		$this->_defaultChangeUserMessageCount = $config['changeUserMessageCount'];

		parent::__construct($errorHandler, $inject);
	}

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getCommonFields()
	{
		$structure = $this->_messageDefinition->getMessageStructure();

		$fields = array(
			$structure['table'] => array(
				$structure['key']        => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				$structure['container']  => array('type' => self::TYPE_UINT, 'required' => true),
				'user_id'                => array('type' => self::TYPE_UINT,   'required' => true),
				'username'               => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_name'
				),
				'post_date'              => array('type' => self::TYPE_UINT,   'required' => true, 'default' => XenForo_Application::$time),
				'message'                => array('type' => self::TYPE_STRING, 'required' => true,
						'requiredError' => 'please_enter_valid_message'
				),
				'ip_id'                  => array('type' => self::TYPE_UINT,   'default' => 0),
				'message_state'          => array('type' => self::TYPE_STRING, 'default' => 'visible',
						'allowedValues' => array('visible', 'moderated', 'deleted')
				),
				'attach_count'           => array('type' => self::TYPE_UINT_FORCED, 'default' => 0, 'max' => 65535),
				'likes'                  => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'like_users'             => array('type' => self::TYPE_SERIALIZED, 'default' => 'a:0:{}')
			)
		);

		if ($this->_hasParentDiscussion)
		{
			$fields[$structure['table']]['position'] = array('type' => self::TYPE_UINT_FORCED);
		}

		return $fields;
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		$keyName = $this->getDiscussionMessageKeyName();

		return $keyName . ' = ' . $this->_db->quote($this->getExisting($keyName));
	}

	/**
	* Gets the default set of options for this data writer.
	*
	* @return array
	*/
	protected function _getDefaultOptions()
	{
		$options = XenForo_Application::get('options');

		return array(
			self::OPTION_SET_IP_ADDRESS => true,
			self::OPTION_UPDATE_PARENT_DISCUSSION => true,
			self::OPTION_INDEX_FOR_SEARCH => true,
			self::OPTION_CHECK_SPAM => true,
			self::OPTION_MAX_MESSAGE_LENGTH => $options->messageMaxLength,
			self::OPTION_MAX_IMAGES => $options->messageMaxImages,
			self::OPTION_MAX_MEDIA => $options->messageMaxMedia,
			self::OPTION_CHANGE_USER_MESSAGE_COUNT => $this->_defaultChangeUserMessageCount,
			self::OPTION_VERIFY_GUEST_USERNAME => true,
			self::OPTION_DELETE_DISCUSSION_FIRST_MESSAGE => true
		);

		// note: OPTION_IS_AUTOMATED is not here, see setOption()
	}

	/**
	 * Sets an option. If the IS_AUTOMATED option is specified, other options are
	 * set instead.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function setOption($name, $value)
	{
		if ($name === self::OPTION_IS_AUTOMATED)
		{
			if ($value)
			{
				parent::setOption(self::OPTION_SET_IP_ADDRESS, false);
				parent::setOption(self::OPTION_CHECK_SPAM, false);
				parent::setOption(self::OPTION_MAX_MESSAGE_LENGTH, 0);
				parent::setOption(self::OPTION_MAX_IMAGES, 0);
			}
		}
		else
		{
			parent::setOption($name, $value);
		}
	}

	/**
	* Generic discussion message pre-save handler.
	*/
	protected final function _preSave()
	{
		if ($this->isInsert()
			&& !$this->get('user_id') && $this->isChanged('username')
			&& $this->getOption(self::OPTION_VERIFY_GUEST_USERNAME)
		)
		{
			$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_ARRAY);
			$userDw->set('username', $this->get('username'));
			$userErrors = $userDw->getErrors();
			if ($userErrors)
			{
				$this->error(reset($userErrors), 'username');
			}
		}

		if ($this->isInsert() && !$this->isChanged('message_state'))
		{
			$this->set('message_state', 'visible');
		}
		if ($this->isInsert() && !$this->isChanged('post_date'))
		{
			$this->set('post_date', XenForo_Application::$time);
		}

		if ($this->isChanged('message'))
		{
			$this->_checkMessageValidity();
		}

		if ($this->isInsert() && $this->getOption(self::OPTION_CHECK_SPAM) && $this->get('message_state') == 'visible')
		{
			$this->_checkMessageForSpam();
		}

		if ($this->_hasParentDiscussion)
		{
			$this->_checkFirstMessageState();

			if (!$this->isChanged('position'))
			{
				$this->_setPosition();
			}
		}

		$this->_messagePreSave();
	}

	/**
	 * Check that the contents of the message are valid, based on length, images, etc.
	 */
	protected function _checkMessageValidity()
	{
		$message = $this->get('message');

		$maxLength = $this->getOption(self::OPTION_MAX_MESSAGE_LENGTH);
		if ($maxLength && utf8_strlen($message) > $maxLength)
		{
			$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_characters', array('count' => $maxLength)), 'message');
		}

		$maxImages = $this->getOption(self::OPTION_MAX_IMAGES);
		$maxMedia = $this->getOption(self::OPTION_MAX_MEDIA);
		if ($maxImages || $maxMedia)
		{
			$formatter = XenForo_BbCode_Formatter_Base::create('ImageCount', false);
			$parser = new XenForo_BbCode_Parser($formatter);
			$parser->render($message);

			if ($maxImages && $formatter->getImageCount() > $maxImages)
			{
				$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_images', array('count' => $maxImages)), 'message');
			}
			if ($maxMedia && $formatter->getMediaCount() > $maxMedia)
			{
				$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_images', array('count' => $maxMedia)), 'message');
			}
		}
	}

	/**
	 * Checks whether the message is spam.
	 */
	protected function _checkMessageForSpam()
	{
		// TODO: check if the message is spam
	}

	/**
	 * Sets the message's position within the discussion.
	 */
	protected function _setPosition()
	{
		if ($this->isInsert() || $this->isChanged('message_state'))
		{
			$discussionDw = $this->getDiscussionDataWriter();
			if ($this->isInsert())
			{
				if (!$discussionDw || $discussionDw->isInsert())
				{
					// likely the first message
					$this->set('position', 0);
				}
				else
				{
					if ($this->get('post_date') < $discussionDw->get('last_post_date'))
					{
						// TODO: this doesn't deal with inserting a message in the middle of a discussion
						throw new XenForo_Exception('Cannot insert a message in the middle of a discussion.');
					}

					if ($this->get('message_state') == 'visible')
					{
						$this->set('position', $discussionDw->get('reply_count') + 1);
					}
					else
					{
						$this->set('position', $discussionDw->get('reply_count'));
					}
				}
			}
			else
			{
				// updated the state on an existing message -- need to slot in
				if ($this->get('message_state') == 'visible' && $this->getExisting('message_state') != 'visible')
				{
					$this->set('position', $this->get('position') + 1);
				}
				else if ($this->get('message_state') != 'visible' && $this->getExisting('message_state') == 'visible')
				{
					$this->set('position', $this->get('position') - 1);
				}
			}
		}
	}

	/**
	 * Checks that the first message has a valid state. Normally,
	 * the first message must always be visible. If a non-visible
	 * first message is required, change the discussion.
	 */
	protected function _checkFirstMessageState()
	{
		if ($this->get('message_state') != 'visible')
		{
			if ($this->isUpdate() && $this->get('position') > 0)
			{
				// position > 0 means this isn't the first post
				return;
			}

			$discussionDw = $this->getDiscussionDataWriter();
			if (!$discussionDw)
			{
				// debugging message, no need for phrasing
				$this->error('The first message of a discussion must be visible.');
			}
			else if ($discussionDw->isInsert() || $discussionDw->get('first_post_id') == $this->getDiscussionMessageId())
			{
				// treat first message state as the discussion state
				$discussionDw->set('discussion_state', $this->get('message_state'));
				$this->set('message_state', 'visible');

				// in case we're deleting the discussion, push the reason up
				$discussionDw->setExtraData(XenForo_DataWriter_Discussion::DATA_DELETE_REASON,
					$this->getExtraData(self::DATA_DELETE_REASON)
				);
			}
		}
	}

	/**
	* Designed to be overridden by child classes
	*/
	protected function _messagePreSave()
	{
	}

	/**
	* Generic discussion message post-save handler.
	*/
	protected final function _postSave()
	{
		if ($this->isInsert() && $this->getOption(self::OPTION_SET_IP_ADDRESS) && !$this->get('ip_id'))
		{
			$this->_updateIpData();
		}

		$attachmentHash = $this->getExtraData(self::DATA_ATTACHMENT_HASH);
		if ($attachmentHash)
		{
			$this->_associateAttachments($attachmentHash);
		}

		if ($this->_hasParentDiscussion && $this->getOption(self::OPTION_UPDATE_PARENT_DISCUSSION))
		{
			$this->_updateDiscussionPostSave();
		}

		if ($this->_hasParentDiscussion && $this->isUpdate() && $this->isChanged('message_state'))
		{
			$this->_updateMessagePositionList();
		}

		$this->_updateDeletionLog();
		$this->_updateModerationQueue();

		if ($this->get('user_id') && $this->isChanged('message_state')
		)
		{
			if ($this->getOption(self::OPTION_CHANGE_USER_MESSAGE_COUNT))
			{
				$this->_updateUserMessageCount();
			}

			if ($this->get('likes'))
			{
				$this->_updateUserLikeCount();
			}
		}

		if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH))
		{
			$this->_indexForSearch();
		}

		$this->_publishAndNotify();

		$this->_messagePostSave();

		$this->_saveDiscussionDataWriter();
	}

	/**
	 * Updates the discussion container info.
	 */
	protected function _updateDiscussionPostSave()
	{
		$discussionDw = $this->getDiscussionDataWriter();
		if ($discussionDw && $discussionDw->isUpdate())
		{
			$discussionDw->updateCountersAfterMessageSave($this);
		}
	}

	/**
	 * Updates the position list based on state changes.
	 */
	protected function _updateMessagePositionList()
	{
		if ($this->get('message_state') == 'visible' && $this->getExisting('message_state') != 'visible')
		{
			$this->_adjustPositionListForInsert();
		}
		else if ($this->get('message_state') != 'visible' && $this->getExisting('message_state') == 'visible')
		{
			$this->_adjustPositionListForRemoval();
		}
	}

	/**
	* Upates the IP data.
	*/
	protected function _updateIpData()
	{
		if (!empty($this->_extraData['ipAddress']))
		{
			$ipAddress = $this->_extraData['ipAddress'];
		}
		else
		{
			$ipAddress = null;
		}

		$ipId = XenForo_Model_Ip::log(
			$this->get('user_id'), $this->getContentType(), $this->getDiscussionMessageId(), 'insert', $ipAddress
		);
		$this->set('ip_id', $ipId, '', array('setAfterPreSave' => true));

		// TODO: ideally, this can be consolidated with other post-save message updates (see associateAttachments)
		$this->_db->update($this->getDiscussionMessageTableName(), array(
			'ip_id' => $ipId
		), $this->getDiscussionMessageKeyName() . ' = ' .  $this->_db->quote($this->getDiscussionMessageId()));
	}

	/**
	 * Associates attachments with this message.
	 *
	 * @param string $attachmentHash
	 */
	protected function _associateAttachments($attachmentHash)
	{
		$rows = $this->_db->update('xf_attachment', array(
			'content_type' => $this->getContentType(),
			'content_id' => $this->getDiscussionMessageId(),
			'temp_hash' => '',
			'unassociated' => 0
		), 'temp_hash = ' . $this->_db->quote($attachmentHash));
		if ($rows)
		{
			// TODO: ideally, this can be consolidated with other post-save message updates (see updateIpData)
			$this->set('attach_count', $this->get('attach_count') + $rows, '', array('setAfterPreSave' => true));

			$this->_db->update($this->getDiscussionMessageTableName(), array(
				'attach_count' => $this->get('attach_count')
			), $this->getDiscussionMessageKeyName() . ' = ' .  $this->_db->quote($this->getDiscussionMessageId()));
		}
	}

	/**
	 * Updates the deletion log if necessary.
	 */
	protected function _updateDeletionLog()
	{
		if (!$this->isChanged('message_state'))
		{
			return;
		}

		if ($this->get('message_state') == 'deleted')
		{
			$reason = $this->getExtraData(self::DATA_DELETE_REASON);
			$this->getModelFromCache('XenForo_Model_DeletionLog')->logDeletion(
				$this->getContentType(), $this->getDiscussionMessageId(), $reason
			);
		}
		else if ($this->getExisting('message_state') == 'deleted')
		{
			$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
				$this->getContentType(), $this->getDiscussionMessageId()
			);
		}
	}

	/**
	 * Updates the moderation queue if necessary.
	 */
	protected function _updateModerationQueue()
	{
		if (!$this->isChanged('message_state'))
		{
			return;
		}

		if ($this->get('message_state') == 'moderated' )
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->insertIntoModerationQueue(
				$this->getContentType(), $this->getDiscussionMessageId(), $this->get('post_date')
			);
		}
		else if ($this->getExisting('message_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue(
				$this->getContentType(), $this->getDiscussionMessageId()
			);
		}
	}

	/**
	 * Updates the search index for this message.
	 */
	protected function _indexForSearch()
	{
		if ($this->get('message_state') == 'visible')
		{
			if ($this->getExisting('message_state') != 'visible' || $this->isChanged('message'))
			{
				$this->_insertOrUpdateSearchIndex();
			}
		}
		else if ($this->isUpdate() && $this->get('message_state') != 'visible' && $this->getExisting('message_state') == 'visible')
		{
			$this->_deleteFromSearchIndex();
		}
	}

	/**
	 * Inserts or updates a record in the search index for this message.
	 */
	protected function _insertOrUpdateSearchIndex()
	{
		$dataHandler = $this->_messageDefinition->getSearchDataHandler();
		if (!$dataHandler)
		{
			return;
		}

		$indexer = new XenForo_Search_Indexer();
		$dataHandler->insertIntoIndex($indexer, $this->getMergedData(), $this->getDiscussionData());
	}

	/**
	* Designed to be overridden by child classes
	*/
	protected function _messagePostSave()
	{
	}

	/**
	 * Generic discussion message pre-delete handler.
	 */
	protected final function _preDelete()
	{
		$this->_messagePreDelete();
	}

	/**
	* Designed to be overridden by child classes
	*/
	protected function _messagePreDelete()
	{
	}

	/**
	 * Generic discussion message post-delete handler.
	 */
	protected final function _postDelete()
	{
		if ($this->_hasParentDiscussion && $this->getOption(self::OPTION_UPDATE_PARENT_DISCUSSION))
		{
			$this->_updateDiscussionPostDelete();
		}

		// firstDelete would trigger this
		if (!$this->discussionDeleted())
		{
			if ($this->_hasParentDiscussion)
			{
				$this->_adjustPositionListForRemoval();
			}
		}

		$this->getModelFromCache('XenForo_Model_DeletionLog')->removeDeletionLog(
			$this->getContentType(), $this->getDiscussionMessageId()
		);
		$this->getModelFromCache('XenForo_Model_ModerationQueue')->deleteFromModerationQueue(
			$this->getContentType(), $this->getDiscussionMessageId()
		);

		if ($this->get('attach_count'))
		{
			$this->_deleteAttachments();
		}

		if ($this->get('user_id'))
		{
			if ($this->getOption(self::OPTION_CHANGE_USER_MESSAGE_COUNT))
			{
				$this->_updateUserMessageCount(true);
			}
		}

		if ($this->getOption(self::OPTION_INDEX_FOR_SEARCH))
		{
			$this->_deleteFromSearchIndex();
		}

		if ($this->get('likes'))
		{
			$this->_deleteLikes();
		}

		$this->_messagePostDelete();

		$this->_deleteFromNewsFeed();
		$this->_deleteIp();

		if (!$this->discussionDeleted())
		{
			$this->_saveDiscussionDataWriter();
		}
	}

	/**
	 * Updates data in the discussion after the message is deleted.
	 * This may cause the entire discussion to be deleted.
	 */
	protected function _updateDiscussionPostDelete()
	{
		$discussionDw = $this->getDiscussionDataWriter();
		if ($discussionDw)
		{
			$deleteIfFirst = $this->getOption(self::OPTION_DELETE_DISCUSSION_FIRST_MESSAGE);
			$discussionChange = $discussionDw->updateCountersAfterMessageDelete($this, $deleteIfFirst);
			$this->_discussionChange = $discussionChange;

			if ($discussionChange == 'delete')
			{
				$discussionDw->delete();
			}
		}
	}

	/**
	 * Deletes the attachments associated with this message.
	 */
	protected function _deleteAttachments()
	{
		$this->getModelFromCache('XenForo_Model_Attachment')->deleteAttachmentsFromContentIds(
			$this->getContentType(),
			array($this->getDiscussionMessageId())
		);
	}

	/**
	 * Updates the user message count.
	 *
	 * @param boolean $isDelete True if hard deleting the message
	 */
	protected function _updateUserMessageCount($isDelete = false)
	{
		if ($this->_hasParentDiscussion)
		{
			$discussionDw = $this->getDiscussionDataWriter();
			if ($discussionDw && $discussionDw->get('discussion_state') != 'visible')
			{
				return;
			}
		}

		if ($this->getExisting('message_state') == 'visible'
			&& ($this->get('message_state') != 'visible' || $isDelete)
		)
		{
			$this->_db->query('
				UPDATE xf_user
				SET message_count = IF(message_count > 0, message_count - 1, 0)
				WHERE user_id = ?
			', $this->get('user_id'));
		}
		else if ($this->get('message_state') == 'visible' && $this->getExisting('message_state') != 'visible')
		{
			$this->_db->query('
				UPDATE xf_user
				SET message_count = message_count + 1
				WHERE user_id = ?
			', $this->get('user_id'));
		}
	}

	/**
	 * Updates the user like count.
	 *
	 * @param boolean $isDelete True if hard deleting the message
	 */
	protected function _updateUserLikeCount($isDelete = false)
	{
		$likes = $this->get('likes');
		if (!$likes)
		{
			return;
		}

		if ($this->_hasParentDiscussion)
		{
			$discussionDw = $this->getDiscussionDataWriter();
			if ($discussionDw && $discussionDw->get('discussion_state') != 'visible')
			{
				return;
			}
		}

		if ($this->getExisting('message_state') == 'visible'
			&& ($this->get('message_state') != 'visible' || $isDelete)
		)
		{
			$this->_db->query('
				UPDATE xf_user
				SET like_count = IF(like_count > ?, like_count - ?, 0)
				WHERE user_id = ?
			', array($likes, $likes, $this->get('user_id')));
		}
		else if ($this->get('message_state') == 'visible' && $this->getExisting('message_state') != 'visible')
		{
			$this->_db->query('
				UPDATE xf_user
				SET like_count = like_count + ?
				WHERE user_id = ?
			', array($likes, $this->get('user_id')));
		}
	}

	/**
	 * Deletes this message from the search index.
	 */
	protected function _deleteFromSearchIndex()
	{
		$dataHandler = $this->_messageDefinition->getSearchDataHandler();
		if (!$dataHandler)
		{
			return;
		}

		$indexer = new XenForo_Search_Indexer();
		$dataHandler->deleteFromIndex($indexer, $this->getMergedData());
	}

	/**
	 * Delete all like entries for content.
	 */
	protected function _deleteLikes()
	{
		$updateUserLikeCounter = ($this->get('message_state') == 'visible');

		if ($updateUserLikeCounter && $this->_hasParentDiscussion)
		{
			$discussionDw = $this->getDiscussionDataWriter();
			if ($discussionDw && $discussionDw->get('discussion_state') != 'visible')
			{
				$updateUserLikeCounter = false;
			}
		}

		$this->getModelFromCache('XenForo_Model_Like')->deleteContentLikes(
			$this->getContentType(), $this->getDiscussionMessageId(), $updateUserLikeCounter
		);
	}

	/**
	 * Deletes the IP entry for this content.
	 */
	protected function _deleteIp()
	{
		$this->getModelFromCache('XenForo_Model_Ip')->deleteByContent(
			$this->getContentType(), $this->getDiscussionMessageId()
		);
	}

	/**
	* Designed to be overridden by child classes
	*/
	protected function _messagePostDelete()
	{
	}

	/**
	 * Adjust the position list surrounding this message, when this message
	 * has been put from a position that "counts" (removed or hidden).
	 */
	protected function _adjustPositionListForInsert()
	{
		if ($this->get('message_state') != 'visible')
		{
			// only renumber if becoming visible
			return;
		}

		$containerKey = $this->getContainerKeyName();
		$containerCondition = "$containerKey = " . $this->get($containerKey);

		$positionQuoted = $this->_db->quote($this->getExisting('position'));
		$postDateQuoted = $this->_db->quote($this->get('post_date'));
		$messageKeyCondition = $this->getDiscussionMessageKeyName() . ' <> ' . $this->_db->quote($this->getDiscussionMessageId());

		$this->_db->query("
			UPDATE " . $this->getDiscussionMessageTableName() . "
			SET position = position + 1
			WHERE $containerCondition
				AND (position > $positionQuoted
					OR (position = $positionQuoted AND post_date > $postDateQuoted)
				)
				AND $messageKeyCondition
		");
	}

	/**
	 * Adjust the position list surrounding this message, when this message
	 * has been removed from a position that "counts" (removed or hidden).
	 */
	protected function _adjustPositionListForRemoval()
	{
		if ($this->getExisting('message_state') != 'visible')
		{
			// no need to renumber after removal something that didn't count
			return;
		}

		$containerKey = $this->getContainerKeyName();
		$containerCondition = "$containerKey = " . $this->get($containerKey);

		$messageKeyCondition = $this->getDiscussionMessageKeyName() . ' <> ' . $this->_db->quote($this->getDiscussionMessageId());

		$this->_db->query('
			UPDATE ' . $this->getDiscussionMessageTableName() . '
			SET position = position - 1
			WHERE ' . $containerCondition . '
				AND position >= ?
				AND ' . $messageKeyCondition . '
		', $this->getExisting('position'));
	}

	/**
	 * Gets the discussion data writer. Note that if the container value changes,
	 * this cache will not be removed.
	 *
	 * @return XenForo_DataWriter_Discussion|false
	 */
	public function getDiscussionDataWriter()
	{
		if (!$this->_hasParentDiscussion)
		{
			return false;
		}

		if ($this->_discussionDw === null)
		{
			$containerId = $this->get($this->getContainerKeyName());
			if (!$containerId)
			{
				$this->_discussionDw = false;
			}
			else
			{
				$this->_discussionDw = $this->_messageDefinition->getDiscussionDataWriter($containerId, $this->_errorHandler);
				if ($this->_discussionDw && $this->_discussionMode === null)
				{
					$this->_discussionMode = 'update';
				}
			}
		}

		return $this->_discussionDw;
	}

	/**
	 * Sets the data writer for the discussion this message is in--or will be in.
	 *
	 * @param XenForo_DataWriter_Discussion|null $discussionDw
	 * @param boolean True if $discussionDataWriter->isInsert()
	 */
	public function setDiscussionDataWriter(XenForo_DataWriter_Discussion $discussionDw = null, $isInsert = null)
	{
		$this->_discussionDw = $discussionDw;

		if ($isInsert !== null)
		{
			$this->_discussionMode = ($isInsert ? 'insert' : 'update');
		}
	}

	/**
	 * Saves the discussion data writer if it exists and has changed.
	 */
	protected function _saveDiscussionDataWriter()
	{
		if ($this->_discussionDw && $this->_discussionDw->hasChanges())
		{
			$this->_discussionDw->save();
		}
	}

	/**
	 * Gets the data about the discussion this message is in. This may use the
	 * discussion data writer, or some other source if needed.
	 *
	 * @return array|null
	 */
	public function getDiscussionData()
	{
		if ($this->_hasParentDiscussion)
		{
			$discussionDw = $this->getDiscussionDataWriter();
			if ($discussionDw)
			{
				return $discussionDw->getMergedData();
			}
		}

		return null;
	}

	/**
	 * Returns true if the parent discussion has been deleted (instead of this).
	 *
	 * @return boolean
	 */
	public function discussionDeleted()
	{
		return ($this->_discussionChange == 'delete');
	}

	/**
	 * Gets the current value of the discussion message ID for this message.
	 *
	 * @return integer
	 */
	public function getDiscussionMessageId()
	{
		return $this->get($this->getDiscussionMessageKeyName());
	}

	/**
	 * Returns true if this is the first message in a newly inserted discussion
	 *
	 * @return boolean
	 */
	public function isDiscussionFirstMessage()
	{
		return ($this->_discussionMode == 'insert');
	}

	/**
	 * Called during post-save, handles publishing to news feed
	 * and sending notifications
	 */
	protected function _publishAndNotify()
	{
		if ($this->isInsert() && !$this->isDiscussionFirstMessage())
		{
			$this->_publishToNewsFeed();
		}
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
			$this->getDiscussionMessageId(),
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
			$this->getDiscussionMessageId()
		);
	}

	/**
	 * The name of the table that holds this type of discussion message.
	 *
	 * @return string
	 */
	public function getDiscussionMessageTableName()
	{
		return $this->_messageDefinition->getMessageTableName();
	}

	/**
	 * The name of the discussion message primary key. Must be an auto increment column.
	 *
	 * @return string
	 */
	public function getDiscussionMessageKeyName()
	{
		return $this->_messageDefinition->getMessageKeyName();
	}

	/**
	 * Gets the field name of the container this message belongs to. This may
	 * be a discussion (eg, thread) or something more general (a user for profile posts).
	 *
	 * @return string
	 */
	public function getContainerKeyName()
	{
		return $this->_messageDefinition->getContainerKeyName();
	}

	/**
	 * Gets the content type for tables that contain multiple data types together.
	 *
	 * @return string
	 */
	public function getContentType()
	{
		return $this->_messageDefinition->getContentType();
	}
}