<?php

class XenForo_ControllerAdmin_Feed extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('node');
	}

	public function actionIndex()
	{
		$viewParams = array(
			'feeds' => $this->_getFeedModel()->getAllFeeds()
		);

		return $this->responseView('XenForo_ViewAdmin_Feed_Index', 'feed_index', $viewParams);
	}

	public function actionAdd()
	{
		$viewParams = array(
			'feed' => $this->_getFeedModel()->getDefaultFeedArray(),
			'nodes' => $this->_getNodeModel()->getAllNodes()
		);

		return $this->responseView('XenForo_ViewAdmin_Feed_Add', 'feed_edit', $viewParams);
	}

	public function actionEdit()
	{
		$feedId = $this->_input->filterSingle('feed_id', XenForo_Input::UINT);
		$feed = $this->_getFeedOrError($feedId);

		if ($feed['user_id'])
		{
			$feed['user_id'] = -1;
		}

		$viewParams = array(
			'feed' => $feed,
			'nodes' => $this->_getNodeModel()->getAllNodes()
		);

		return $this->responseView('XenForo_ViewAdmin_Feed_Edit', 'feed_edit', $viewParams);
	}

	public function actionPreview()
	{
		$this->_assertPostOnly();

		$feed = $this->_getFeedFormData();

		$feedModel = $this->_getFeedModel();

		$feedData = $feedModel->getFeedData($feed['url'], $exception);

		if (!$feedData || empty($feedData['entries']))
		{
			return $this->responseError(new XenForo_Phrase('there_was_problem_requesting_feed', array(
				'message' => ($exception instanceof Zend_Exception ? $exception->getMessage() : new XenForo_Phrase('n_a'))
			)));
		}

		if (empty($feed['title']))
		{
			$feed['title'] = $feedData['title'];
		}

		$feed['message_template'] = XenForo_Helper_String::autoLinkBbCode($feed['message_template']);
		$feed['baseUrl'] = $feedModel->getFeedBaseUrl($feed['url']);

		// get a random entry from the feed
		$entry = $feedData['entries'][mt_rand(0, count($feedData['entries']) - 1)];
		$entry = $feedModel->prepareFeedEntry($entry, $feedData, $feed);

		if ($feed['user_id'] == -1)
		{
			$entry['author'] = $this->_input->filterSingle('username', XenForo_Input::STRING);
		}

		$viewParams = array(
			'feed' => $feed,
			'feedData' => $feedData,
			'entry' => $entry
		);

		return $this->responseView('XenForo_ViewAdmin_Feed_Preview', 'feed_preview', $viewParams);
	}

	public function actionSave()
	{
		if ($this->_input->inRequest('preview'))
		{
			return $this->responseReroute(__CLASS__, 'preview');
		}

		$this->_assertPostOnly();

		$feedId = $this->_input->filterSingle('feed_id', XenForo_Input::UINT);

		$data = $this->_getFeedFormData();

		$data['message_template'] = $this->getHelper('Editor')->getMessageText('message_template', $this->_input);
		$data['message_template'] = XenForo_Helper_String::autoLinkBbCode($data['message_template']);

		if ($data['user_id'] == -1)
		{
			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);

			if ($user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($username))
			{
				$data['user_id'] = $user['user_id'];
			}
		}

		$data['user_id'] = intval(max($data['user_id'], 0));

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Feed');

		if ($feedId)
		{
			$writer->setExistingData($feedId);
		}

		$writer->bulkSet($data);
		$writer->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('feeds')
		);
	}

	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			// delete feed
			return $this->_deleteData(
				'XenForo_DataWriter_Feed', 'feed_id',
				XenForo_Link::buildAdminLink('feeds')
			);
		}
		else
		{
			// show delete feed confirmation
			$feedId = $this->_input->filterSingle('feed_id', XenForo_Input::UINT);
			$feed = $this->_getFeedOrError($feedId);

			$viewParams = array(
				'feed' => $feed
			);
			return $this->responseView('XenForo_ViewAdmin_Feed_Delete', 'feed_delete', $viewParams);
		}
	}

	public function actionImport()
	{
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$feedId = $this->_input->filterSingle('feed_id', XenForo_Input::UINT);
		$feed = $this->_getFeedOrError($feedId);

		$this->_getFeedModel()->importFeedData($feed);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('feeds')
		);
	}

	/**
	 * Gets the specified feed or throws an error.
	 *
	 * @param integer $feedId
	 *
	 * @return array
	 */
	protected function _getFeedOrError($feedId)
	{
		$feed = $this->_getFeedModel()->getFeedById($feedId);
		if (!$feed)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_feed_not_found'), 404));
		}

		return $feed;
	}

	/**
	 * Returns a filtered array of data from the feed edit form
	 *
	 * @return array
	 */
	protected function _getFeedFormData()
	{
		return $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'url' => XenForo_Input::STRING,
			'frequency' => XenForo_Input::UINT,
			'node_id' => XenForo_Input::UINT,
			'user_id' => XenForo_Input::INT,
			'title_template' => XenForo_Input::STRING,
			'message_template' => XenForo_Input::STRING,
			'discussion_visible' => XenForo_Input::UINT,
			'discussion_open' => XenForo_Input::UINT,
			'discussion_sticky' => XenForo_Input::UINT,
			'active' => XenForo_Input::UINT,
		));
	}

	/**
	 * @return XenForo_Model_Feed
	 */
	protected function _getFeedModel()
	{
		return $this->getModelFromCache('XenForo_Model_Feed');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}