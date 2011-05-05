<?php

/**
* Data writer for profile posts.
*
* @package XenForo_ProfilePost
*/
class XenForo_DataWriter_DiscussionMessage_ProfilePost extends XenForo_DataWriter_DiscussionMessage
{
	const DATA_PROFILE_USER = 'profileUser';

	/**
	 * Gets the object that represents the definition of this type of message.
	 *
	 * @return XenForo_DiscussionMessage_Definition_Abstract
	 */
	public function getDiscussionMessageDefinition()
	{
		return new XenForo_DiscussionMessage_Definition_ProfilePost();
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
		if (!$profilePostId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array($this->getDiscussionMessageTableName() => $this->_getProfilePostModel()->getProfilePostById($profilePostId));
	}

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		$fields = $this->_getCommonFields();

		$structure = $this->_messageDefinition->getMessageStructure();

		$fields[$structure['table']] += array(
			'comment_count'      => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
			'first_comment_date' => array('type' => self::TYPE_UINT, 'default' => 0),
			'last_comment_date'  => array('type' => self::TYPE_UINT, 'default' => 0),
			'latest_comment_ids' => array('type' => self::TYPE_BINARY, 'default' => '', 'maxLength' => 100)
		);

		return $fields;
	}

	/**
	* Gets the default set of options for this data writer.
	*
	* @return array
	*/
	protected function _getDefaultOptions()
	{
		$options = parent::_getDefaultOptions();
		$options[self::OPTION_MAX_MESSAGE_LENGTH] = 420;

		return $options;
	}

	protected function _publishAndNotify()
	{
		// posting on own profile is status change, which is logged separately
		if ($this->get('user_id') != $this->get('profile_user_id'))
		{
			parent::_publishAndNotify();
		}

		if ($this->isInsert() && $this->get('profile_user_id') != $this->get('user_id'))
		{
			$this->_alertUser();
		}
	}

	/**
	 * Sends an alert to the profile owner
	 *
	 * Note: ensure that you $dw->setExtraData(self::PROFILE_USER, $profileUser) to save a query
	 */
	protected function _alertUser()
	{
		if (!$profileUser = $this->getExtraData(self::DATA_PROFILE_USER))
		{
			$profileUser = array('user_id' => $this->get('profile_user_id'));
		}

		if (XenForo_Model_Alert::userReceivesAlert($profileUser, $this->getContentType(), 'insert'))
		{
			XenForo_Model_Alert::alert(
				$this->get('profile_user_id'),
				$this->get('user_id'),
				$this->get('username'),
				$this->getContentType(),
				$this->get('profile_post_id'),
				'insert'
			);
		}
	}

	protected function _messagePreSave()
	{
		if ($this->get('user_id') == $this->get('profile_user_id') && $this->isChanged('message'))
		{
			// statuses are more limited than other postss
			$message = $this->get('message');
			$maxLength = 140;

			$message = preg_replace('/\r?\n/', ' ', $message);

			if (utf8_strlen($message) > $maxLength)
			{
				$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_characters', array('count' => $maxLength)), 'message');
			}

			$this->set('message', $message);
		}
	}

	protected function _updateUserMessageCount($isDelete = false)
	{
		// disable message counting for profile posts - people are just going to get confused
		// by this, plus the messages are basically one liners
	}

	protected function _messagePostSave()
	{
		if ($this->isChanged('message_state') && $this->get('message_state') == 'visible')
		{
			$this->_updateStatus();
		}
		else if ($this->isUpdate() && $this->isChanged('message_state') && $this->getExisting('message_state') == 'visible')
		{
			$this->_hideStatus();
		}

		if ($this->isUpdate() && $this->get('message_state') == 'deleted' && $this->getExisting('message_state') == 'visible')
		{
			$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('profile_post', $this->get('profile_post_id'));
		}

		if ($this->isUpdate() && $this->isStatus() && $this->get('message_state') == 'visible' && $this->isChanged('message'))
		{
			$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			$userDw->setExistingData($this->get('user_id'));
			if ($userDw->get('status_profile_post_id') == $this->get('profile_post_id'))
			{
				$userDw->set('status', $this->get('message'));
				$userDw->save();
			}
		}
	}

	protected function _messagePostDelete()
	{
		$this->_hideStatus();

		$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('profile_post', $this->get('profile_post_id'));
		$this->_db->delete('xf_profile_post_comment', 'profile_post_id = ' . $this->_db->quote($this->get('profile_post_id')));
	}

	protected function _updateStatus()
	{
		if (!$this->isStatus())
		{
			return;
		}

		$this->_db->query('
			INSERT INTO xf_user_status
				(profile_post_id, user_id, post_date)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE
				user_id = VALUES(user_id),
				post_date = VALUES(post_date)
		', array($this->get('profile_post_id'), $this->get('user_id'), $this->get('post_date')));

		$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
		$userDw->setExistingData($this->get('user_id'));
		if ($this->get('post_date') >= $userDw->get('status_date'))
		{
			$userDw->set('status', $this->get('message'));
			$userDw->set('status_date', $this->get('post_date'));
			$userDw->set('status_profile_post_id', $this->get('profile_post_id'));
			$userDw->save();
		}
	}

	protected function _hideStatus()
	{
		if (!$this->isStatus())
		{
			return;
		}

		$this->_db->delete('xf_user_status', 'profile_post_id = ' . $this->_db->quote($this->get('profile_post_id')));

		$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$userDw->setExistingData($this->get('user_id'));
		if ($userDw->get('status_profile_post_id') == $this->get('profile_post_id'))
		{
			$userDw->set('status', '');
			$userDw->set('status_date', 0);
			$userDw->set('status_profile_post_id', 0);
			$userDw->save();
		}
	}

	/**
	 * Returns true if this message is a status update.
	 *
	 * @return boolean
	 */
	public function isStatus()
	{
		return ($this->get('user_id') == $this->get('profile_user_id'));
	}

	public function rebuildProfilePostCommentCounters()
	{
		$db = $this->_db;
		$profilePostId = $this->get('profile_post_id');

		$counts = $db->fetchRow('
			SELECT COUNT(*) AS comment_count,
				MIN(comment_date) AS first_comment_date,
				MAX(comment_date) AS last_comment_date
			FROM xf_profile_post_comment
			WHERE profile_post_id = ?
		', $profilePostId);

		if ($counts['comment_count'])
		{
			$ids = $db->fetchCol($db->limit(
				'
					SELECT profile_post_comment_id
					FROM xf_profile_post_comment
					WHERE profile_post_id = ?
					ORDER BY comment_date DESC
				', 3
			), $profilePostId);
			$ids = array_reverse($ids); // need last 3, but in oldest first order
		}
		else
		{
			$ids = array();
		}

		$this->bulkSet($counts);
		$this->set('latest_comment_ids', implode(',', $ids));
	}

	public function insertNewComment($commentId, $commentDate)
	{
		$this->set('comment_count', $this->get('comment_count') + 1);
		if (!$this->get('first_comment_date') || $commentDate < $this->get('first_comment_date'))
		{
			$this->set('first_comment_date', $commentDate);
		}
		$this->set('last_comment_date', max($this->get('last_comment_date'), $commentDate));

		$latest = $this->get('latest_comment_ids');
		$ids = ($latest ? explode(',', $latest) : array());
		$ids[] = $commentId;

		if (count($ids) > 3)
		{
			$ids = array_slice($ids, -3);
		}

		$this->set('latest_comment_ids', implode(',', $ids));
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		return $this->getModelFromCache('XenForo_Model_ProfilePost');
	}
}