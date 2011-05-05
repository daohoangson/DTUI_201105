<?php

/**
 * Controller for content watching actions.
 *
 * @package XenForo_Watch
 */
class XenForo_ControllerPublic_Watched extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Pre-dispatch code for all actions.
	 */
	protected function _preDispatch($action)
	{
		$this->_assertRegistrationRequired();
	}

	/**
	 * List of all new watched content.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionThreads()
	{
		$threadWatchModel = $this->_getThreadWatchModel();
		$visitor = XenForo_Visitor::getInstance();

		$newThreads = $threadWatchModel->getThreadsWatchedByUser($visitor['user_id'], true, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],
			'permissionCombinationId' => $visitor['permission_combination_id'],
			'limit' => XenForo_Application::get('options')->discussionsPerPage
		));
		$newThreads = $threadWatchModel->unserializePermissionsInList($newThreads, 'node_permission_cache');
		$newThreads = $threadWatchModel->getViewableThreadsFromList($newThreads);

		$newThreads = $this->_prepareWatchedThreads($newThreads);

		$viewParams = array(
			'newThreads' => $newThreads
		);

		return $this->responseView('XenForo_ViewPublic_Watched_Threads', 'watch_threads', $viewParams);
	}

	/**
	 * List of all watched threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionThreadsAll()
	{
		$threadWatchModel = $this->_getThreadWatchModel();
		$visitor = XenForo_Visitor::getInstance();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$threadsPerPage = XenForo_Application::get('options')->discussionsPerPage;

		$threads = $threadWatchModel->getThreadsWatchedByUser($visitor['user_id'], false, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],
			'permissionCombinationId' => $visitor['permission_combination_id'],
			'perPage' => $threadsPerPage,
			'page' => $page,
		));
		$threads = $threadWatchModel->unserializePermissionsInList($threads, 'node_permission_cache');
		$threads = $threadWatchModel->getViewableThreadsFromList($threads);

		$threads = $this->_prepareWatchedThreads($threads);

		$totalThreads = $threadWatchModel->countThreadsWatchedByUser($visitor['user_id']);

		$this->canonicalizePageNumber($page, $threadsPerPage, $totalThreads, 'watched/threads/all');

		$viewParams = array(
			'threads' => $threads,
			'page' => $page,
			'threadsPerPage' => $threadsPerPage,
			'totalThreads' => $totalThreads
		);

		return $this->responseView('XenForo_ViewPublic_Watched_ThreadsAll', 'watch_threads_all', $viewParams);
	}

	protected function _prepareWatchedThreads(array $threads)
	{
		$visitor = XenForo_Visitor::getInstance();

		$threadModel = $this->_getThreadModel();
		foreach ($threads AS &$thread)
		{
			if (!$visitor->hasNodePermissionsCached($thread['node_id']))
			{
				$visitor->setNodePermissions($thread['node_id'], $thread['permissions']);
			}

			$thread = $threadModel->prepareThread($thread, $thread);

			// prevent these things from interfering
			$thread['canInlineMod'] = false;
			$thread['canEditThread'] = false;
		}

		return $threads;
	}

	/**
	 * Update selected watched threads (stop watching, change email notification settings).
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionThreadsUpdate()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'thread_ids' => array(XenForo_Input::UINT, 'array' => true),
			'do' => XenForo_Input::STRING
		));

		$watch = $this->_getThreadWatchModel()->getUserThreadWatchByThreadIds(XenForo_Visitor::getUserId(), $input['thread_ids']);

		foreach ($watch AS $threadWatch)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
			$dw->setExistingData($threadWatch, true);

			switch ($input['do'])
			{
				case 'stop':
					$dw->delete();
					break;

				case 'email':
					$dw->set('email_subscribe', 1);
					$dw->save();
					break;

				case 'no_email':
					$dw->set('email_subscribe', 0);
					$dw->save();
					break;
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildPublicLink('watched/threads/all'))
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('managing_account_details');
	}

	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}
}