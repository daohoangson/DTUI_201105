<?php

class XenForo_ControllerPublic_FindNew extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Finds new/unread threads (or posts within).
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionThreads()
	{
		$searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
		if (!$searchId)
		{
			return $this->findNewThreads();
		}

		$searchModel = $this->_getSearchModel();

		$search = $searchModel->getSearchById($searchId);
		if (!$search
			|| $search['user_id'] != XenForo_Visitor::getUserId()
			|| !in_array($search['search_type'], array('new-threads', 'recent-threads'))
		)
		{
			return $this->findNewThreads();
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = XenForo_Application::get('options')->discussionsPerPage;

		$pageResultIds = $searchModel->sliceSearchResultsToPage($search, $page, $perPage);
		$results = $searchModel->getSearchResultsForDisplay($pageResultIds);
		if (!$results)
		{
			return $this->getNoResultsResponse();
		}

		$resultStartOffset = ($page - 1) * $perPage + 1;
		$resultEndOffset = ($page - 1) * $perPage + count($results['results']);

		$threadModel = $this->_getThreadModel();

		$threads = array();
		$inlineModOptions = array();
		foreach ($results['results'] AS $result)
		{
			$thread = $result['content'];

			$thread['forum'] = array(
				'node_id' => $thread['node_id'],
				'title' => $thread['node_title']
			);

			$threadModOptions = $threadModel->addInlineModOptionToThread($thread, $thread, $thread['permissions']);
			$inlineModOptions += $threadModOptions;

			$threads[$result[XenForo_Model_Search::CONTENT_ID]] = $thread;
		}

		$viewParams = array(
			'search' => $search,
			'threads' => $threads,
			'inlineModOptions' => $inlineModOptions,

			'threadStartOffset' => $resultStartOffset,
			'threadEndOffset' => $resultEndOffset,

			'page' => $page,
			'perPage' => $perPage,
			'totalThreads' => $search['result_count'],
			'nextPage' => ($resultEndOffset < $search['result_count'] ? ($page + 1) : 0),

			'showingNewThreads' => ($search['search_type'] == 'new-threads') // vs recent threads
		);

		return $this->responseView('XenForo_ViewPublic_FindNew_Threads', 'find_new_threads', $viewParams);
	}

	public function findNewThreads()
	{
		$threadModel = $this->_getThreadModel();
		$searchModel = $this->_getSearchModel();

		$userId = XenForo_Visitor::getUserId();

		$limitOptions = array(
			'limit' => XenForo_Application::get('options')->maximumSearchResults
		);

		$days = $this->_input->filterSingle('days', XenForo_Input::UINT);
		$recent = $this->_input->filterSingle('recent', XenForo_Input::UINT);

		if ($userId && !$days && !$recent)
		{
			$threadIds = $threadModel->getUnreadThreadIds($userId, $limitOptions);

			$searchType = 'new-threads';
		}
		else
		{
			if ($days < 1)
			{
				$days = 3;
			}

			$fetchOptions = $limitOptions + array(
				'order' => 'last_post_date',
				'orderDirection' => 'desc',
			);

			$threadIds = array_keys($threadModel->getThreads(array(
				'last_post_date' => array('>', XenForo_Application::$time - 86400 * $days),
				'deleted' => false,
				'moderated' => false
			), $fetchOptions));

			$searchType = 'recent-threads';
		}

		$results = array();
		foreach ($threadIds AS $threadId)
		{
			$results[] = array(
				XenForo_Model_Search::CONTENT_TYPE => 'thread',
				XenForo_Model_Search::CONTENT_ID => $threadId);
		}

		$results = $searchModel->getViewableSearchResults($results);
		if (!$results)
		{
			return $this->getNoResultsResponse();
		}

		$search = $searchModel->insertSearch($results, $searchType, '', array(), 'date', false);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('find-new/threads', $search)
		);
	}

	public function getNoResultsResponse()
	{
		$days = $this->_input->filterSingle('days', XenForo_Input::UINT);
		$recent = $this->_input->filterSingle('recent', XenForo_Input::UINT);

		if (XenForo_Visitor::getUserId() && !$days && !$recent)
		{
			return $this->responseMessage(new XenForo_Phrase(
				'no_unread_threads_view_recent',
				array('link' => XenForo_Link::buildPublicLink('find-new/threads', false, array('recent' => 1)))
			));
		}
		else
		{
			return $this->responseMessage(new XenForo_Phrase('no_results_found'));
		}
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_latest_content');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_Search
	 */
	protected function _getSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_Search');
	}
}