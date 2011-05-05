<?php

/**
* Data writer for posts.
*
* @package XenForo_Discussion
*/
class XenForo_DataWriter_DiscussionMessage_Post extends XenForo_DataWriter_DiscussionMessage
{
	/**
	 * Gets the object that represents the definition of this type of message.
	 *
	 * @return XenForo_DiscussionMessage_Definition_Abstract
	 */
	public function getDiscussionMessageDefinition()
	{
		return new XenForo_DiscussionMessage_Definition_Post();
	}

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return $this->_getCommonFields();
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
		if (!$postId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array($this->getDiscussionMessageTableName() => $this->_getPostModel()->getPostById($postId));
	}

	/**
	 * Pushes an alert to any members who have been quoted in this message.
	 */
	protected function _alertQuoted()
	{
		$this->_getPostModel()->alertQuotedMembers($this->getMergedData());
	}

	protected function _messagePostSave()
	{
		if ($this->isUpdate() && $this->get('message_state') == 'deleted' && $this->getExisting('message_state') == 'visible')
		{
			$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('post', $this->get('post_id'));
		}

		if ($this->isChanged('message_state'))
		{
			if ($this->get('message_state') == 'visible')
			{
				$this->_getThreadModel()->modifyThreadUserPostCount($this->get('thread_id'), $this->get('user_id'), 1);
			}
			else if ($this->isUpdate() && $this->getExisting('message_state') == 'visible')
			{
				$this->_getThreadModel()->modifyThreadUserPostCount($this->get('thread_id'), $this->get('user_id'), -1);
			}
		}

		if ($this->isChanged('likes') && $this->get('position') == 0)
		{
			$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			$threadDw->setExistingData($this->get('thread_id'));
			if ($this->get('post_id') == $threadDw->get('first_post_id'))
			{
				$threadDw->set('first_post_likes', $this->get('likes'));
				$threadDw->save();
			}
		}
	}

	/**
	 * Post-save handling, after the transaction is committed.
	 */
	protected function _postSaveAfterTransaction()
	{
		if ($this->isInsert() && $this->get('message_state') == 'visible')
		{
			$post = $this->getMergedData();

			// alert any members who are directly quoted by this post
			$quotedMembers = $this->_getPostModel()->alertQuotedMembers($post);

			if  (!$this->isDiscussionFirstMessage())
			{
				// notify members watching this thread, unless they are already notified of being quoted
				$this->getModelFromCache('XenForo_Model_ThreadWatch')->sendNotificationToWatchUsersOnReply($post, null, $quotedMembers);
			}
		}
	}

	protected function _messagePostDelete()
	{
		if ($this->get('message_state') == 'visible')
		{
			$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('post', $this->get('post_id'));
			$this->_getThreadModel()->modifyThreadUserPostCount($this->get('thread_id'), $this->get('user_id'), -1);
		}
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}
}