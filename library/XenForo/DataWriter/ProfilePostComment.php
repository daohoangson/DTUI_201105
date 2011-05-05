<?php

/**
* Data writer for profile post comments
*
* @package XenForo_ProfilePost
*/
class XenForo_DataWriter_ProfilePostComment extends XenForo_DataWriter
{
	const DATA_PROFILE_USER = 'profileUser';

	const DATA_PROFILE_POST = 'profilePost';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_comment_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_profile_post_comment' => array(
				'profile_post_comment_id'   => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'profile_post_id'           => array('type' => self::TYPE_UINT,   'required' => true),
				'user_id'                => array('type' => self::TYPE_UINT,   'required' => true),
				'username'               => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_name'
				),
				'comment_date'           => array('type' => self::TYPE_UINT,   'required' => true, 'default' => XenForo_Application::$time),
				'message'                => array('type' => self::TYPE_STRING, 'required' => true,
						'requiredError' => 'please_enter_valid_message'
				),
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

		return array('xf_profile_post_comment' => $this->_getProfilePostModel()->getProfilePostCommentById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'profile_post_comment_id = ' . $this->_db->quote($this->getExisting('profile_post_comment_id'));
	}

	protected function _preSave()
	{
		if ($this->isChanged('message'))
		{
			$maxLength = 420;
			if (utf8_strlen($this->get('message')) > $maxLength)
			{
				$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_characters', array('count' => $maxLength)), 'message');
			}
		}
	}

	protected function _postSave()
	{
		 $profilePostId = $this->get('profile_post_id');

		if ($this->isInsert())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
			$dw->setExistingData($profilePostId);
			$dw->insertNewComment($this->get('profile_post_comment_id'), $this->get('comment_date'));
			$dw->save();

			$profileUser = $this->getExtraData(self::DATA_PROFILE_USER);
			if ($profileUser && $profileUser['user_id'] != $this->get('user_id'))
			{
				// alert profile owner
				if (XenForo_Model_Alert::userReceivesAlert($profileUser, 'profile_post', 'comment_your_profile'))
				{
					XenForo_Model_Alert::alert(
						$profileUser['user_id'],
						$this->get('user_id'),
						$this->get('username'),
						'profile_post',
						$profilePostId,
						'comment_your_profile'
					);
				}
			}

			$profilePost = $this->getExtraData(self::DATA_PROFILE_POST);
			if ($profilePost && $profilePost['profile_user_id'] != $profilePost['user_id']
				&& $profilePost['user_id'] != $this->get('user_id')
			)
			{
				// alert post owner
				$user = $this->_getUserModel()->getUserById($profilePost['user_id'], array(
					'join' => XenForo_Model_User::FETCH_USER_OPTION
				));
				if ($user && XenForo_Model_Alert::userReceivesAlert($user, 'profile_post', 'comment_your_post'))
				{
					XenForo_Model_Alert::alert(
						$user['user_id'],
						$this->get('user_id'),
						$this->get('username'),
						'profile_post',
						$profilePostId,
						'comment_your_post'
					);
				}
			}

			$otherCommenterIds = $this->_getProfilePostModel()->getProfilePostCommentUserIds($profilePostId);

			$otherCommenters = $this->_getUserModel()->getUsersByIds($otherCommenterIds, array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION
			));

			$profileUserId = empty($profileUser) ? 0 : $profileUser['user_id'];
			$profilePostUserId = empty($profilePost) ? 0 : $profilePost['user_id'];

			foreach ($otherCommenters AS $otherCommenter)
			{
				switch ($otherCommenter['user_id'])
				{
					case $profileUserId:
					case $profilePostUserId:
					case $this->get('user_id'):
					case 0:
						break;

					default:
						if (XenForo_Model_Alert::userReceivesAlert($otherCommenter, 'profile_post', 'comment_other_commenter'))
						{
							XenForo_Model_Alert::alert(
								$otherCommenter['user_id'],
								$this->get('user_id'),
								$this->get('username'),
								'profile_post',
								$profilePostId,
								'comment_other_commenter'
							);
						}
				}
			}
		}
	}

	protected function _postDelete()
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
		$dw->setExistingData($this->get('profile_post_id'));
		$dw->rebuildProfilePostCommentCounters();
		$dw->save();
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		return $this->getModelFromCache('XenForo_Model_ProfilePost');
	}
}