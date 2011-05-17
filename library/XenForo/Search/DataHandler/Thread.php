<?php

/**
 * Handles searching of threads.
 *
 * @package XenForo_Search
 */
class XenForo_Search_DataHandler_Thread extends XenForo_Search_DataHandler_Abstract
{
	/**
	 * @var XenForo_Model_Thread
	 */
	protected $_threadModel = null;

	/**
	 * Inserts into (or replaces a record) in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_insertIntoIndex()
	 */
	protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		$metadata = array();
		$metadata['node'] = $data['node_id'];
		$metadata['thread'] = $data['thread_id'];

		$indexer->insertIntoIndex(
			'thread', $data['thread_id'],
			$data['title'], '',
			$data['post_date'], $data['user_id'], $data['thread_id'], $metadata
		);
	}

	/**
	 * Updates a record in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
	 */
	protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		$indexer->updateIndex('thread', $data['thread_id'], $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
	 */
	protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		$threadIds = array();
		foreach ($dataList AS $data)
		{
			$threadIds[] = $data['thread_id'];
		}

		$indexer->deleteFromIndex('thread', $threadIds);
	}

	/**
	 * Rebuilds the index for a batch.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
	 */
	public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
	{
		$threadIds = $this->_getThreadModel()->getThreadIdsInRange($lastId, $batchSize);
		if (!$threadIds)
		{
			return false;
		}

		$this->quickIndex($indexer, $threadIds);

		return max($threadIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		$threadModel = $this->_getThreadModel();

		$threads = $threadModel->getThreadsByIds($contentIds);

		foreach ($threads AS $thread)
		{
			if ($threadModel->isRedirect($thread) || !$threadModel->isVisible($thread))
			{
				continue;
			}

			$this->insertIntoIndex($indexer, $thread);
		}

		return true;
	}

	/**
	 * Gets the type-specific data for a collection of results of this content type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
	 */
	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		$threadModel = $this->_getThreadModel();

		$threads = $threadModel->getThreadsByIds($ids, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FIRSTPOST,
			'permissionCombinationId' => $viewingUser['permission_combination_id'],
			'readUserId' => $viewingUser['user_id'],
			'includeForumReadDate' => true,
			'postCountUserId' => $viewingUser['user_id']
		));

		return $threadModel->unserializePermissionsInList($threads, 'node_permission_cache');
	}

	/**
	 * Determines if this result is viewable.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::canViewResult()
	 */
	public function canViewResult(array $result, array $viewingUser)
	{
		return $this->_getThreadModel()->canViewThreadAndContainer(
			$result, $result, $null, $result['permissions'], $viewingUser
		);
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		return $this->_getThreadModel()->prepareThread($result, $result, $result['permissions'], $viewingUser);
	}

	/**
	 * Gets the date of the result (from the result's content).
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getResultDate()
	 */
	public function getResultDate(array $result)
	{
		return $result['post_date'];
	}

	/**
	 * Renders a result to HTML.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::renderResult()
	 */
	public function renderResult(XenForo_View $view, array $result, array $search)
	{
		return $view->createTemplateObject('search_result_thread', array(
			'thread' => $result,
			'forum' => array(
				'node_id' => $result['node_id'],
				'title' => $result['node_title']
			),
			'post' => $result,
			'search' => $search
		));
	}

	public function getSearchContentTypes()
	{
		return array('thread');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		if (!$this->_threadModel)
		{
			$this->_threadModel = XenForo_Model::create('XenForo_Model_Thread');
		}

		return $this->_threadModel;
	}
}