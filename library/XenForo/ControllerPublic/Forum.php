<?php

/**
 * Controller for handling actions on forums.
 *
 * @package XenForo_Forum
 */
class XenForo_ControllerPublic_Forum extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Displays the contents of a forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);
		if (!$forumId && !$forumName)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('index')
			);
		}

		$visitor = XenForo_Visitor::getInstance();

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forumFetchOptions = array('readUserId' => $visitor['user_id']);
		$forum = $ftpHelper->assertForumValidAndViewable($forumId ? $forumId : $forumName, $forumFetchOptions);

		$forumId = $forum['node_id'];

		$threadModel = $this->_getThreadModel();
		$forumModel = $this->_getForumModel();

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$threadsPerPage = XenForo_Application::get('options')->discussionsPerPage;

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('forums', $forum, array('page' => $page))
		);

		$defaultOrder = 'last_post_date';
		$defaultOrderDirection = 'desc';

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

		// fetch all thread info
		$threadFetchConditions = $threadModel->getPermissionBasedThreadFetchConditions($forum) + array(
			'sticky' => 0
		);
		$threadFetchOptions = array(
			'perPage' => $threadsPerPage,
			'page' => $page,

			'join' => XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],

			'order' => $order,
			'orderDirection' => $orderDirection
		);
		if (!empty($threadFetchConditions['deleted']))
		{
			$threadFetchOptions['join'] |= XenForo_Model_Thread::FETCH_DELETION_LOG;
		}

		$totalThreads = $threadModel->countThreadsInForum($forumId, $threadFetchConditions);

		$this->canonicalizePageNumber($page, $threadsPerPage, $totalThreads, 'forums', $forum);

		$threads = $threadModel->getThreadsInForum($forumId, $threadFetchConditions, $threadFetchOptions);

		if ($page == 1)
		{
			$stickyThreads = $threadModel->getStickyThreadsInForum($forumId, $threadFetchConditions, $threadFetchOptions);
			foreach (array_keys($stickyThreads) AS $stickyThreadId)
			{
				unset($threads[$stickyThreadId]);
			}
		}
		else
		{
			$stickyThreads = array();
		}

		// prepare all threads for the thread list
		$inlineModOptions = array();
		$permissions = $visitor->getNodePermissions($forumId);

		foreach ($threads AS &$thread)
		{
			$threadModOptions = $threadModel->addInlineModOptionToThread($thread, $forum, $permissions);
			$inlineModOptions += $threadModOptions;

			$thread = $threadModel->prepareThread($thread, $forum, $permissions);
		}
		foreach ($stickyThreads AS &$thread)
		{
			$threadModOptions = $threadModel->addInlineModOptionToThread($thread, $forum, $permissions);
			$inlineModOptions += $threadModOptions;

			$thread = $threadModel->prepareThread($thread, $forum, $permissions);
		}
		unset($thread);

		// if we've read everything on the first page of a normal sort order, probably need to mark as read
		if ($visitor['user_id'] && $page == 1
			&& $order == 'last_post_date' && $orderDirection == 'desc'
			&& $forum['forum_read_date'] < $forum['last_post_date']
		)
		{
			$hasNew = false;
			foreach ($threads AS $thread)
			{
				if ($thread['isNew'])
				{
					$hasNew = true;
					break;
				}
			}

			if (!$hasNew)
			{
				// everything read, but forum not marked as read. Let's check.
				$this->_getForumModel()->markForumReadIfNeeded($forum, $visitor['user_id']);
			}
		}

		// get the ordering params set for the header links
		$orderParams = array();
		foreach (array('title', 'post_date', 'reply_count', 'view_count', 'last_post_date') AS $field)
		{
			$orderParams[$field]['order'] = ($field != $defaultOrder ? $field : false);
			if ($order == $field)
			{
				$orderParams[$field]['direction'] = ($orderDirection == 'desc' ? 'asc' : 'desc');
			}
		}

		$viewParams = array(
			'nodeList' => $this->_getNodeModel()->getNodeDataForListDisplay($forum, 0),
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum, false),

			'canPostThread' => $forumModel->canPostThreadInForum($forum),
			'canSearch' => $visitor->canSearch(),

			'inlineModOptions' => $inlineModOptions,
			'threads' => $threads,
			'stickyThreads' => $stickyThreads,

			'order' => $order,
			'orderDirection' => $orderDirection,
			'orderParams' => $orderParams,

			'pageNavParams' => array(
				'order' => ($order != $defaultOrder ? $order : false),
				'direction' => ($orderDirection != $defaultOrderDirection ? $orderDirection : false)
			),
			'page' => $page,
			'threadStartOffset' => ($page - 1) * $threadsPerPage + 1,
			'threadEndOffset' => ($page - 1) * $threadsPerPage + count($threads) ,
			'threadsPerPage' => $threadsPerPage,
			'totalThreads' => $totalThreads,

			'showPostedNotice' => $this->_input->filterSingle('posted', XenForo_Input::UINT)
		);

		return $this->responseView('XenForo_ViewPublic_Forum_View', 'forum_view', $viewParams);
	}

	/**
	 * Displays a form to create a new thread in this forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCreateThread()
	{
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable($forumId ? $forumId : $forumName);

		$forumId = $forum['node_id'];

		$this->_assertCanPostThreadInForum($forum);

		$attachmentParams = $this->getModelFromCache('XenForo_Model_Forum')->getAttachmentParams($forum, array(
			'node_id' => $forum['node_id']
		));

		$viewParams = array(
			'thread' => array('discussion_open' => 1),
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

			'attachmentParams' => $attachmentParams,

			'watchState' => $this->_getThreadWatchModel()->getThreadWatchStateForVisitor(false),

			'captcha' => XenForo_Captcha_Abstract::createDefault(),

			'canLockUnlockThread' => $this->_getForumModel()->canLockUnlockThreadInForum($forum),
			'canStickUnstickThread' => $this->_getForumModel()->canStickUnstickThreadInForum($forum),

			'attachmentConstraints' => $this->getModelFromCache('XenForo_Model_Attachment')->getAttachmentConstraints(),
		);
		return $this->responseView('XenForo_ViewPublic_Thread_Create', 'thread_create', $viewParams);
	}

	/**
	 * Inserts a new thread into this forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAddThread()
	{
		$this->_assertPostOnly();

		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable($forumId ? $forumId : $forumName);

		$forumId = $forum['node_id'];

		$this->_assertCanPostThreadInForum($forum);

		if (!XenForo_Captcha_Abstract::validateDefault($this->_input))
		{
			return $this->responseCaptchaFailed();
		}

		$visitor = XenForo_Visitor::getInstance();

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'attachment_hash' => XenForo_Input::STRING,

			'watch_thread_state' => XenForo_Input::UINT,
			'watch_thread' => XenForo_Input::UINT,
			'watch_thread_email' => XenForo_Input::UINT,

			'_set' => array(XenForo_Input::UINT, 'array' => true),
			'discussion_open' => XenForo_Input::UINT,
			'sticky' => XenForo_Input::UINT,

			'poll' => XenForo_Input::ARRAY_SIMPLE, // filtered below
		));
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$pollInputHandler = new XenForo_Input($input['poll']);
		$pollInput = $pollInputHandler->filter(array(
			'question' => XenForo_Input::STRING,
			'responses' => array(XenForo_Input::STRING, 'array' => true),
			'multiple' => XenForo_Input::UINT,
			'public_votes' => XenForo_Input::UINT,
			'close' => XenForo_Input::UINT,
			'close_length' => XenForo_Input::UNUM,
			'close_units' => XenForo_Input::STRING
		));

		// note: assumes that the message dw will pick up the username issues
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$writer->set('user_id', $visitor['user_id']);
		$writer->set('username', $visitor['username']);
		$writer->set('title', $input['title']);
		$writer->set('node_id', $forumId);

		// discussion state changes instead of first message state
		$writer->set('discussion_state', $this->getModelFromCache('XenForo_Model_Post')->getPostInsertMessageState(array(), $forum));

		// discussion open state - moderator permission required
		if (!empty($input['_set']['discussion_open']) && $this->_getForumModel()->canLockUnlockThreadInForum($forum))
		{
			$writer->set('discussion_open', $input['discussion_open']);
		}

		// discussion sticky state - moderator permission required
		if (!empty($input['_set']['sticky']) && $this->_getForumModel()->canStickUnstickThreadInForum($forum))
		{
			$writer->set('sticky', $input['sticky']);
		}

		$postWriter = $writer->getFirstMessageDw();
		$postWriter->set('message', $input['message']);
		$postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $input['attachment_hash']);

		$writer->preSave();

		if ($pollInput['question'] !== '')
		{
			$pollWriter = XenForo_DataWriter::create('XenForo_DataWriter_Poll');
			$pollWriter->bulkSet(
				XenForo_Application::arrayFilterKeys($pollInput, array('question', 'multiple', 'public_votes'))
			);
			$pollWriter->set('content_type', 'thread');
			$pollWriter->set('content_id', 0); // changed before saving
			if ($pollInput['close'])
			{
				if (!$pollInput['close_length'])
				{
					$pollWriter->error(new XenForo_Phrase('please_enter_valid_length_of_time'));
				}
				else
				{
					$pollWriter->set('close_date', strtotime('+' . $pollInput['close_length'] . ' ' . $pollInput['close_units']));
				}
			}
			$pollWriter->addResponses($pollInput['responses']);
			$pollWriter->preSave();
			$writer->mergeErrors($pollWriter->getErrors());

			$writer->set('discussion_type', 'poll', '', array('setAfterPreSave' => true));
		}
		else
		{
			$pollWriter = false;

			foreach ($pollInput['responses'] AS $response)
			{
				if ($response !== '')
				{
					$writer->error(new XenForo_Phrase('you_entered_poll_response_but_no_question'));
					break;
				}
			}
		}

		if (!$writer->hasErrors())
		{
			$this->assertNotFlooding('post');
		}

		$writer->save();

		$thread = $writer->getMergedData();

		if ($pollWriter)
		{
			$pollWriter->set('content_id', $thread['thread_id'], '', array('setAfterPreSave' => true));
			$pollWriter->save();
		}

		$this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], $input);

		$this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time, $visitor['user_id']);

		if (!$this->_getThreadModel()->canViewThread($thread, $forum))
		{
			$return = XenForo_Link::buildPublicLink('forums', $forum, array('posted' => 1));
		}
		else
		{
			$return = XenForo_Link::buildPublicLink('threads', $thread);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$return,
			new XenForo_Phrase('your_thread_has_been_posted')
		);
	}

	/**
	 * Shows a preview of the thread creation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCreateThreadPreview()
	{
		$this->_assertPostOnly();

		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable($forumId ? $forumId : $forumName);

		$forumId = $forum['node_id'];

		$this->_assertCanPostThreadInForum($forum);

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		$viewParams = array(
			'forum' => $forum,
			'message' => $message
		);

		return $this->responseView('XenForo_ViewPublic_Thread_CreatePreview', 'thread_create_preview', $viewParams);
	}

	public function actionMarkRead()
	{
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$visitor = XenForo_Visitor::getInstance();

		$markDate = $this->_input->filterSingle('date', XenForo_Input::UINT);
		if (!$markDate)
		{
			$markDate = XenForo_Application::$time;
		}

		$forumModel = $this->_getForumModel();

		if ($forumId || $forumName)
		{
			// mark individual forum read
			$ftpHelper = $this->getHelper('ForumThreadPost');
			$forum = $ftpHelper->assertForumValidAndViewable(
				$forumId ? $forumId : $forumName, array('readUserId' => $visitor['user_id'])
			);

			$forumId = $forum['node_id'];

			if ($this->isConfirmedPost())
			{
				$forumModel->markForumTreeRead($forum, $markDate);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('forums', $forum)
				);
			}
			else
			{
				$viewParams = array(
					'forum' => $forum,
					'markDate' => $markDate
				);

				return $this->responseView('XenForo_ViewPublic_Forum_MarkRead', 'forum_mark_read', $viewParams);
			}
		}
		else
		{
			// mark all forums read
			if ($this->isConfirmedPost())
			{
				$forumModel->markForumTreeRead(null, $markDate);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('index')
				);
			}
			else
			{
				$viewParams = array(
					'forum' => false,
					'markDate' => $markDate
				);

				return $this->responseView('XenForo_ViewPublic_Forum_MarkRead', 'forum_mark_read', $viewParams);
			}
		}
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		$forumIds = array();
		$nodeNames = array();
		foreach ($activities AS $activity)
		{
			if (!empty($activity['params']['node_id']))
			{
				$forumIds[$activity['params']['node_id']] = $activity['params']['node_id'];
			}
			else if (!empty($activity['params']['node_name']))
			{
				$nodeNames[$activity['params']['node_name']] = $activity['params']['node_name'];
			}
		}

		if ($nodeNames)
		{
			$nodeNames = XenForo_Model::create('XenForo_Model_Node')->getNodeIdsFromNames($nodeNames);

			foreach ($nodeNames AS $nodeName => $nodeId)
			{
				$forumIds[$nodeName] = $nodeId;
			}
		}

		$forumData = array();

		if ($forumIds)
		{
			/* @var $forumModel XenForo_Model_Forum */
			$forumModel = XenForo_Model::create('XenForo_Model_Forum');

			$visitor = XenForo_Visitor::getInstance();
			$permissionCombinationId = $visitor['permission_combination_id'];

			$forums = $forumModel->getForumsByIds($forumIds, array(
				'permissionCombinationId' => $permissionCombinationId
			));
			foreach ($forums AS $forum)
			{
				$visitor->setNodePermissions($forum['node_id'], $forum['node_permission_cache']);
				if ($forumModel->canViewForum($forum))
				{
					$forumData[$forum['node_id']] = array(
						'title' => $forum['title'],
						'url' => XenForo_Link::buildPublicLink('forums', $forum)
					);
				}
			}
		}

		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$forum = false;
			if (!empty($activity['params']['node_id']))
			{
				$nodeId = $activity['params']['node_id'];
				if (isset($forumData[$nodeId]))
				{
					$forum = $forumData[$nodeId];
				}
			}
			else if (!empty($activity['params']['node_name']))
			{
				$nodeName = $activity['params']['node_name'];
				if (isset($nodeNames[$nodeName]))
				{
					$nodeId = $nodeNames[$nodeName];
					if (isset($forumData[$nodeId]))
					{
						$forum = $forumData[$nodeId];
					}
				}
			}

			if ($forum)
			{
				$output[$key] = array(
					new XenForo_Phrase('viewing_forum'),
					$forum['title'],
					$forum['url'],
					false
				);
			}
			else
			{
				$output[$key] = new XenForo_Phrase('viewing_forum');
			}
		}

		return $output;
	}

	/**
	 * Asserts that the currently browsing user can post a thread in
	 * the specified forum.
	 *
	 * @param array $forum
	 */
	protected function _assertCanPostThreadInForum(array $forum)
	{
		if (!$this->_getForumModel()->canPostThreadInForum($forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}
}