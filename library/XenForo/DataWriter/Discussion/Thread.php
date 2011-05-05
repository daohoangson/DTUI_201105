<?php

/**
* Data writer for threads.
*
* @package XenForo_Discussion
*/
class XenForo_DataWriter_Discussion_Thread extends XenForo_DataWriter_Discussion
{
	/**
	 * Gets the object that represents the definition of this type of discussion.
	 *
	 * @return XenForo_Discussion_Definition_Abstract
	 */
	public function getDiscussionDefinition()
	{
		return new XenForo_Discussion_Definition_Thread();
	}

	/**
	 * Gets the object that represents the definition of the message within this discussion.
	 *
	 * @return XenForo_DiscussionMessage_Definition_Abstract
	 */
	public function getDiscussionMessageDefinition()
	{
		return new XenForo_DiscussionMessage_Definition_Post();
	}

	/**
	 * Gets information about the last message in this discussion.
	 *
	 * @return array|false
	 */
	protected function _getLastMessageInDiscussion()
	{
		return $this->_getPostModel()->getLastPostInThread($this->get('thread_id'));
	}

	/**
	 * Gets simple information about all messages in this discussion.
	 *
	 * @param boolean $includeMessage If true, includes the message contents
	 *
	 * @return array Format: [post id] => info
	 */
	protected function _getMessagesInDiscussionSimple($includeMessage = false)
	{
		return $this->_getPostModel()->getPostsInThreadSimple($this->get('thread_id'), $includeMessage);
	}

	/**
	 * Rebuilds the discussion info.
	 *
	 * @return boolean True if still valid
	 */
	public function rebuildDiscussion()
	{
		$threadId = $this->get('thread_id');

		$newCounters = $this->_getPostModel()->recalculatePostPositionsInThread($threadId);
		if (!$newCounters['firstPostId'])
		{
			return false;
		}

		$this->rebuildDiscussionCounters($newCounters['visibleCount'] - 1, $newCounters['firstPostId'], $newCounters['lastPostId']);
		$this->_getThreadModel()->replaceThreadUserPostCounters($threadId, $newCounters['userPosts']);

		return true;
	}

	/**
	 * Rebuilds the counters of the discussion.
	 *
	 * @param integer|false $replyCount Total reply count, if known
	 * @param integer|false $firstPostId First post ID, if known already
	 * @param integer|false $lastPostId Last post ID, if known already
	 */
	public function rebuildDiscussionCounters($replyCount = false, $firstPostId = false, $lastPostId = false)
	{
		$postModel = $this->_getPostModel();
		$threadId = $this->get('thread_id');

		if ($firstPostId && $lastPostId)
		{
			$posts = $postModel->getPostsByIds(
				array($firstPostId, $lastPostId),
				array('join' => XenForo_Model_Post::FETCH_USER)
			);
			$firstPost = $posts[$firstPostId];
			$lastPost = $posts[$lastPostId];
		}
		else
		{
			$postsTemp = $postModel->getPostsInThread(
				$threadId,
				array('join' => XenForo_Model_Post::FETCH_USER, 'limit' => 1)
			);
			$firstPost = reset($postsTemp);

			$lastPost = $postModel->getLastPostInThread(
				$threadId,
				array('join' => XenForo_Model_Post::FETCH_USER)
			);
		}

		if (!$firstPost || !$lastPost)
		{
			return;
		}

		if ($replyCount === false)
		{
			$replyCount = $postModel->countVisiblePostsInThread($threadId) - 1;
		}

		$this->set('first_post_id', $firstPost['post_id']);
		$this->set('post_date', $firstPost['post_date']);
		$this->set('user_id', $firstPost['user_id']);
		$this->set('username', $firstPost['username'] !== '' ? $firstPost['username'] : '-');
		$this->set('first_post_likes', $firstPost['likes']);
		$this->set('reply_count', $replyCount);

		$this->set('last_post_id', $lastPost['post_id']);
		$this->set('last_post_date', $lastPost['post_date']);
		$this->set('last_post_user_id', $lastPost['user_id']);
		$this->set('last_post_username', $lastPost['username']);
	}

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		$fields = $this->_getCommonFields();
		$fields['xf_thread']['first_post_likes'] = array('type' => self::TYPE_UINT_FORCED, 'default' => 0);

		return $fields;
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
		if (!$threadId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array($this->getDiscussionTableName() => $this->_getThreadModel()->getThreadById($threadId));
	}

	/**
	 * Specific discussion post-save behaviors.
	 */
	protected function _discussionPostSave(array $messages)
	{
		$threadId = $this->get('thread_id');

		if ($this->isUpdate() && $this->isChanged('discussion_state') && $this->get('discussion_state') != 'visible')
		{
			$this->_deleteRedirects('thread-' . $threadId . '-%', true);
		}
		else if ($this->isUpdate() && $this->isChanged('node_id'))
		{
			if ($this->get('discussion_type') == 'redirect')
			{
				$threadRedirectModel = $this->getModelFromCache('XenForo_Model_ThreadRedirect');
				$redirect = $threadRedirectModel->getThreadRedirectById($threadId);
				if ($redirect && $redirect['redirect_key'])
				{
					$redirectKey = preg_replace('/^(thread-\d+)-(\d+)$/', '$1-' . $this->get('node_id'), $redirect['redirect_key']);
					if ($redirectKey != $redirect['redirect_key'])
					{
						$threadRedirectModel->updateThreadRedirect($this->get('thread_id'), array('redirect_key' => $redirectKey));
					}
				}
			}
			else
			{
				// delete redirects if moving back to forum that already had it
				$this->_deleteRedirects('thread-' . $threadId . '-' . $this->get('node_id') . '-');
			}
		}

		if ($this->isUpdate() && $this->get('discussion_state') == 'visible' && $this->isChanged('node_id'))
		{
			$indexer = new XenForo_Search_Indexer();

			$messageHandler = $this->_messageDefinition->getSearchDataHandler();
			if ($messageHandler)
			{
				$thread = $this->getMergedData();
				$fullMessages = $this->_getMessagesInDiscussionSimple(true); // re-get with message contents
				foreach ($fullMessages AS $key => $message)
				{
					$messageHandler->insertIntoIndex($indexer, $message, $thread);
					unset($fullMessages[$key]);
				}
			}
		}
	}

	/**
	 * Specific discussion post-delete behaviors.
	 */
	protected function _discussionPostDelete(array $messages)
	{
		$threadId = $this->get('thread_id');
		$threadIdQuoted = $this->_db->quote($threadId);

		$this->_db->delete('xf_thread_watch', "thread_id = $threadIdQuoted");
		$this->_db->delete('xf_thread_user_post', "thread_id = $threadIdQuoted");

		if ($this->get('discussion_type') == 'redirect')
		{
			$this->getModelFromCache('XenForo_Model_ThreadRedirect')->deleteThreadRedirects(array($threadId));
		}
		else
		{
			$this->_deleteRedirects('thread-' . $this->get('thread_id') . '-%', true);
		}

		if ($this->get('discussion_type') == 'poll')
		{
			$poll = $this->getModelFromCache('XenForo_Model_Poll')->getPollByContent('thread', $threadId);
			if ($poll)
			{
				$pollDw = XenForo_DataWriter::create('XenForo_DataWriter_Poll', XenForo_DataWriter::ERROR_SILENT);
				$pollDw->setExistingData($poll, true);
				$pollDw->delete();
			}
		}
	}

	/**
	 * Deletes thread redirects with the specified key(s).
	 *
	 * @param string $redirectKey
	 * @param boolean $likeMatch
	 */
	protected function _deleteRedirects($redirectKey, $likeMatch = false)
	{
		$threadRedirectModel = $this->getModelFromCache('XenForo_Model_ThreadRedirect');
		$redirects = $threadRedirectModel->getThreadRedirectsByKey($redirectKey, $likeMatch);
		$threadRedirectModel->deleteThreadRedirects(array_keys($redirects));
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}
}