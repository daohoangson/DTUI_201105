<?php

/**
 * Search controller.
 *
 * @package XenForo_Search
 */
class XenForo_ControllerPublic_Search extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Displays a form to do an advanced search.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$search = array(
			'child_nodes' => true,
			'order' => 'date'
		);

		$searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
		if ($searchId)
		{
			if ($this->_input->filterSingle('searchform', XenForo_Input::UINT))
			{
				$params = $this->_input->filter(array(
					'q' => XenForo_Input::STRING,
					't' => XenForo_Input::STRING,
					'o' => XenForo_Input::STRING,
					'g' => XenForo_Input::UINT,
					'c' => XenForo_Input::ARRAY_SIMPLE
				));

				// allow this to pass through for the search type check later
				$this->_request->setParam('type', $params['t']);

				$users = '';

				if (!empty($params['c']['user']))
				{
					foreach ($this->getModelFromCache('XenForo_Model_User')->getUsersByIds($params['c']['user']) AS $user)
					{
						$users .= $user['username'] . ', ';
					}
					$users = substr($users, 0, -2);
				}

				if (!empty($params['c']['node']))
				{
					$nodes = array_fill_keys(explode(' ', $params['c']['node']), true);
				}
				else
				{
					$nodes = array();
				}

				if (!empty($params['c']['date']))
				{
					$date = XenForo_Locale::date(intval($params['c']['date']), 'picker');
				}
				else
				{
					$date = '';
				}

				if (!empty($params['c']['user_content']))
				{
					$userContent = $params['c']['user_content'];
				}
				else
				{
					$userContent = '';
				}

				$search = array_merge($search, array(
					'keywords' => $params['q'],
					'title_only' => !empty($params['c']['title_only']),
					'users' => $users,
					'user_content' => $userContent,
					'date' => $date,
					'nodes' => $nodes,
					'child_nodes' => empty($nodes),
					'order' => $params['o'],
					'group_discussion' => $params['g'],
				));
			}
			else
			{
				return $this->responseReroute(__CLASS__, 'results');
			}
		}

		if (!XenForo_Visitor::getInstance()->canSearch())
		{
			throw $this->getNoPermissionResponseException();
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		if ($nodeId)
		{
			$search['nodes'][$nodeId] = true;
		}

		$viewParams = array(
			'supportsRelevance' => XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler()->supportsRelevance(),
			'nodes' => $this->_getNodeModel()->getViewableNodeList(null, true),
			'search' => (empty($search) ? array() : $search)
		);

		$searchType = $this->_input->filterSingle('type', XenForo_Input::STRING);
		if ($searchType)
		{
			$typeHandler = $this->_getSearchModel()->getSearchDataHandler($searchType);
			if ($typeHandler)
			{
				$viewParams['searchType'] = $searchType;

				$response = $typeHandler->getSearchFormControllerResponse($this, $this->_input, $viewParams);
				if ($response)
				{
					return $response;
				}
			}
		}

		$viewParams['searchType'] = '';

		return $this->responseView('XenForo_ViewPublic_Search_Form', 'search_form', $viewParams);
	}

	/**
	 * Performs a search.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearch()
	{
		// note: intentionally not post-only

		if (!XenForo_Visitor::getInstance()->canSearch())
		{
			throw $this->getNoPermissionResponseException();
		}

		$input = $this->_input->filter(array(
			'type' => XenForo_Input::STRING,

			'keywords' => XenForo_Input::STRING,
			'title_only' => XenForo_Input::UINT,
			'date' => XenForo_Input::DATE_TIME,
			'users' => XenForo_Input::STRING,
			'nodes' => array(XenForo_Input::UINT, 'array' => true),
			'child_nodes' => XenForo_Input::UINT,
			'user_content' => XenForo_Input::STRING,

			'order' => XenForo_Input::STRING,
			'group_discussion' => XenForo_Input::UINT
		));

		if (!$input['order'])
		{
			$input['order'] = 'date';
		}

		$origKeywords = $input['keywords'];
		$input['keywords'] = XenForo_Helper_String::censorString($input['keywords'], null, ''); // don't allow searching of censored stuff

		$visitorUserId = XenForo_Visitor::getUserId();
		$searchModel = $this->_getSearchModel();

		$constraints = $searchModel->getGeneralConstraintsFromInput($input, $errors);
		if ($errors)
		{
			return $this->responseError($errors);
		}

		if (!$input['type'] && $input['keywords'] === ''
			&& count($constraints) == 1
			&& !empty($constraints['user']) && count($constraints['user']) == 1
		)
		{
			// we're searching for messages by a single user
			$this->_request->setParam('user_id', reset($constraints['user']));
			return $this->responseReroute(__CLASS__, 'member');
		}

		if ($input['keywords'] === '' && empty($constraints['user']))
		{
			// must have keyword or user constraint
			return $this->responseError(new XenForo_Phrase('please_specify_search_query_or_name_of_member'));
		}

		$typeHandler = null;
		if ($input['type'])
		{
			$typeHandler = $searchModel->getSearchDataHandler($input['type']);
			if ($typeHandler)
			{
				$constraints = array_merge($constraints,
					$typeHandler->getTypeConstraintsFromInput($this->_input)
				);
			}
		}

		$search = $searchModel->getExistingSearch(
			$input['type'], $input['keywords'], $constraints, $input['order'], $input['group_discussion'], $visitorUserId
		);

		if (!$search)
		{
			$searcher = new XenForo_Search_Searcher($searchModel);

			if ($typeHandler)
			{
				$results = $searcher->searchType(
					$typeHandler, $input['keywords'], $constraints, $input['order'], $input['group_discussion']
				);
			}
			else
			{
				$results = $searcher->searchGeneral($input['keywords'], $constraints, $input['order']);
			}

			if (!$results)
			{
				return $this->getNoSearchResultsResponse($searcher);
			}

			$search = $searchModel->insertSearch(
				$results, $input['type'], $origKeywords, $constraints, $input['order'], $input['group_discussion'],
				$searcher->getWarnings(), $visitorUserId
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('search', $search),
			''
		);
	}

	/**
	 * Searches for recent content by the specified member.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMember()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getModelFromCache('XenForo_Model_User')->getUserById($userId);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$searchModel = $this->_getSearchModel();

		$content = $this->_input->filterSingle('content', XenForo_Input::STRING);
		if ($content)
		{
			$constraints = array(
				'user' => array($userId),
				'content' => $content
			);

			$searcher = new XenForo_Search_Searcher($searchModel);
			$results = $searcher->searchGeneral('', $constraints, 'date');
		}
		else
		{
			$maxDate = $this->_input->filterSingle('before', XenForo_Input::UINT);

			$searchModel = $this->_getSearchModel();
			$searcher = new XenForo_Search_Searcher($searchModel);

			$results = $searcher->searchUser($userId, $maxDate);
		}

		if (!$results)
		{
			return $this->getNoSearchResultsResponse($searcher);
		}

		$search = $searchModel->insertSearch($results, 'user', '', array('user_id' => $userId), 'date', false);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('search', $search),
			''
		);
	}

	/**
	 * Displays the results of a search.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionResults()
	{
		$searchModel = $this->_getSearchModel();

		$searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
		$searchQuery = $this->_input->filterSingle('q', XenForo_Input::STRING);

		$search = $searchModel->getSearchById($searchId);

		if (!$search)
		{
			$rerunSearch = true;
		}
		else if ($search['user_id'] != XenForo_Visitor::getUserId())
		{
			if ($search['search_query'] === '' || $search['search_query'] !== $searchQuery)
			{
				// just browsing searches without having query
				return $this->responseError(new XenForo_Phrase('requested_search_not_found'), 404);
			}

			$rerunSearch = true;
		}
		else
		{
			$rerunSearch = false;
		}

		if ($rerunSearch)
		{
			$rerunInput = $this->_input->filter(array(
				'q' => XenForo_Input::STRING,
				't' => XenForo_Input::STRING,
				'o' => XenForo_Input::STRING,
				'g' => XenForo_Input::UINT,
				'c' => XenForo_Input::ARRAY_SIMPLE
			));
			$rerun = array(
				'search_query' => $rerunInput['q'],
				'search_type' => $rerunInput['t'],
				'search_order' => $rerunInput['o'],
				'search_grouping' => $rerunInput['g'],
			);

			$newSearch = $this->rerunSearch($rerun, $rerunInput['c']);
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('search', $newSearch)
			);
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = XenForo_Application::get('options')->searchResultsPerPage;

		$pageResultIds = $searchModel->sliceSearchResultsToPage($search, $page, $perPage);
		$results = $searchModel->getSearchResultsForDisplay($pageResultIds);
		if (!$results)
		{
			return $this->responseMessage(new XenForo_Phrase('no_results_found'));
		}

		$resultStartOffset = ($page - 1) * $perPage + 1;
		$resultEndOffset = ($page - 1) * $perPage + count($results['results']);

		if ($search['search_type'] == 'user'
			&& $search['result_count'] > $perPage
			&& $resultEndOffset >= $search['result_count']
		)
		{
			// user search on last page (with more than one page)
			$last = end($results['results']);
			$userSearchMaxDate = $results['handlers'][$last[XenForo_Model_Search::CONTENT_TYPE]]->getResultDate($last['content']);
		}
		else
		{
			$userSearchMaxDate = false;
		}

		$viewParams = array(
			'search' => $searchModel->prepareSearch($search),
			'results' => $results,

			'resultStartOffset' => $resultStartOffset,
			'resultEndOffset' => $resultEndOffset,

			'page' => $page,
			'perPage' => $perPage,
			'totalResults' => $search['result_count'],
			'nextPage' => ($resultEndOffset < $search['result_count'] ? ($page + 1) : 0),

			'userSearchMaxDate' => $userSearchMaxDate
		);

		return $this->responseView('XenForo_ViewPublic_Search_Results', 'search_results', $viewParams);
	}

	/**
	 * Reruns the given search. If errors occur, a response exception will be thrown.
	 *
	 * @param array $search Search info (does not need search_id, constraints, results, or warnings)
	 * @param array $constraints Array of search constraints
	 *
	 * @return array New search
	 */
	public function rerunSearch(array $search, array $constraints)
	{
		if (!XenForo_Visitor::getInstance()->canSearch())
		{
			throw $this->getNoPermissionResponseException();
		}

		$visitorUserId = XenForo_Visitor::getUserId();
		$searchModel = $this->_getSearchModel();

		$existingSearch = $searchModel->getExistingSearch(
			$search['search_type'], $search['search_query'], $constraints,
			$search['search_order'], $search['search_grouping'], $visitorUserId
		);
		if ($existingSearch)
		{
			return $existingSearch;
		}

		$typeHandler = null;
		if ($search['search_type'])
		{
			$typeHandler = $searchModel->getSearchDataHandler($search['search_type']);
		}

		$searcher = new XenForo_Search_Searcher($searchModel);

		if ($typeHandler)
		{
			$results = $searcher->searchType(
				$typeHandler, $search['search_query'], $constraints,
				$search['search_order'], $search['search_grouping']
			);
		}
		else
		{
			$results = $searcher->searchGeneral(
				$search['search_query'], $constraints, $search['search_order']
			);
		}

		if (!$results)
		{
			throw $this->responseException($this->getNoSearchResultsResponse($searcher));
		}

		return $searchModel->insertSearch(
			$results, $search['search_type'], $search['search_query'], $constraints,
			$search['search_order'], $existingSearch['search_grouping'],
			$searcher->getWarnings(), $visitorUserId
		);
	}

	/**
	 * Handles the response behavior when there are no search results.
	 *
	 * @param XenForo_Search_Searcher $searcher
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function getNoSearchResultsResponse(XenForo_Search_Searcher $searcher)
	{
		$errors = $searcher->getErrors();
		if ($errors)
		{
			return $this->responseError($errors);
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
		return new XenForo_Phrase('searching');
	}

	/**
	 * Gets the specified search for the specified permission combination, or throws an error.
	 * A valid search with out
	 *
	 * @param integer $searchId
	 * @param string $searchQuery Text being searched for; prevents browsing of search terms
	 *
	 * @return array
	 */
	protected function _getSearchOrError($searchId, $searchQuery)
	{
		$searchModel = $this->_getSearchModel();

		$search = $searchModel->getSearchById($searchId);
		if (!$search || $search['search_query'] !== $searchQuery)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_search_not_found'), 404));
		}

		return $searchModel->prepareSearch($search);
	}

	/**
	 * @return XenForo_Model_Search
	 */
	protected function _getSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_Search');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
}