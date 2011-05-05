<?php

/**
 * Search model.
 *
 * @package XenForo_Search
 */
class XenForo_Model_Search extends XenForo_Model
{
	const CONTENT_TYPE = 0;
	const CONTENT_ID = 1;

	/**
	 * Gets the specified search.
	 *
	 * @param integer $searchId
	 *
	 * @return array|false
	 */
	public function getSearchById($searchId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_search
			WHERE search_id = ?
		', $searchId);
	}

	/**
	 * Inserts the specified search.
	 *
	 * @param array $results List of results, in format [] => array(content type, content id)
	 * @param string $searchType The type of the search (usually content type or blank, but could be general string)
	 * @param string $searchQuery Text that was queried for
	 * @param array $constraints Additional search constraints
	 * @param string $order Search sort order
	 * @param boolean $groupByDiscussion True if results should be folded up into their discussion (or other container)
	 * @param array $warnings Any search warnings that occurred
	 * @param integer|null $userId User doing search
	 * @param integer|null $searchDate Time of search or null for now
	 *
	 * @return array Search info, including search_id
	 */
	public function insertSearch(array $results, $searchType, $searchQuery, array $constraints, $order, $groupByDiscussion,
		array $warnings = array(), $userId = null, $searchDate = null
	)
	{
		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		if ($searchDate === null)
		{
			$searchDate = XenForo_Application::$time;
		}

		$search = array(
			'search_results' => json_encode(array_values($results)),
			'result_count' => count($results),
			'search_type' => $searchType,
			'search_query' => utf8_substr($searchQuery, 0, 200),
			'search_constraints' => json_encode($constraints),
			'search_order' => $order,
			'search_grouping' => $groupByDiscussion ? 1 : 0,
			'warnings' => json_encode(array_map('strval', $warnings)),
			'user_id' => $userId,
			'search_date' => $searchDate,
			'query_hash' => $this->getSearchQueryHash($searchType, $searchQuery, $constraints, $order, $groupByDiscussion)
		);

		$this->_getDb()->insert('xf_search', $search);

		$search['search_id'] = $this->_getDb()->lastInsertId();

		return $search;
	}

	/**
	 * Gets a search that matches the given criteria.
	 *
	 * @param string $searchType The type of the search (usually content type or blank, but could be general string)
	 * @param string $searchQuery Text being queried for
	 * @param array $constraints Search constraints
	 * @param string $order Search order
	 * @param boolean $groupByDiscussion True if results should be folded up into their discussion (or other container)
	 * @param integer $userId User ID doing the search
	 * @param boolean $forceUsage True to force the usage of the cache (in debug mode)
	 *
	 * @return array|false
	 */
	public function getExistingSearch($searchType, $searchQuery, array $constraints, $order, $groupByDiscussion, $userId, $forceUsage = false)
	{
		if (XenForo_Application::debugMode() && !$forceUsage)
		{
			return false;
		}

		$queryHash = $this->getSearchQueryHash($searchType, $searchQuery, $constraints, $order, $groupByDiscussion);

		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_search
			WHERE query_hash = ?
				AND search_type = ?
				AND search_query = ?
				AND user_id = ?
				AND search_date > ?
			ORDER BY search_date DESC
			LIMIT 1
		', array($queryHash, $searchType, $searchQuery, $userId, XenForo_Application::$time - 3600));
	}

	/**
	 * Generates the search query hash for the given criteria.
	 *
	 * @param string $searchType The type of the search (usually content type or blank, but could be general string)
	 * @param string $searchQuery Text being queried for
	 * @param array $constraints Search constraints
	 * @param string $order Search order
	 * @param boolean $groupByDiscussion True if results should be folded up into their discussion (or other container)
	 *
	 * @return string Query hash
	 */
	public function getSearchQueryHash($searchType, $searchQuery, array $constraints, $order, $groupByDiscussion)
	{
		$hashSource = array($searchType, $searchQuery, $constraints, $order, $groupByDiscussion ? 1 : 0);
		return md5(serialize($hashSource));
	}

	/**
	 * Prepares a search for display/use.
	 *
	 * @param array $search
	 *
	 * @return array
	 */
	public function prepareSearch(array $search)
	{
		$search['searchConstraints'] = $this->_decodeSearchTableData($search['search_constraints'], false);
		$search['searchWarnings'] = $this->_decodeSearchTableData($search['warnings'], false);

		return $search;
	}

	/**
	 * Backwards compatability for search data, which was serialized up to 1.0.0 RC3
	 * and thereafter json_encoded.
	 *
	 * @param string $data
	 *
	 * @return array
	 */
	protected function _decodeSearchTableData($data, $isSearchResults = true)
	{
		$decoded = json_decode($data, true);

		if ($decoded === null)
		{
			$decoded = unserialize($data);

			if ($isSearchResults)
			{
				foreach ($decoded AS &$result)
				{
					$result = array($result['content_type'], $result['content_id']);
				}
			}
		}

		return $decoded;
	}

	/**
	 * Gets the list of content types that have search handlers.
	 *
	 * @return array Format: [content type] => search_handler_class
	 */
	public function getSearchContentTypes()
	{
		return $this->_getDb()->fetchPairs('
			SELECT content_type, field_value
			FROM xf_content_type_field
			WHERE field_name = \'search_handler_class\'
		');
	}

	/**
	 * Creates search data handler objects for the specified content types.
	 *
	 * @param array $handlerContentTypes List of content types
	 *
	 * @return array Format: [content type] => XenForo_Search_DataHandler_Abstract object
	 */
	public function getSearchDataHandlers(array $handlerContentTypes)
	{
		$contentTypes = $this->getSearchContentTypes();
		$handlers = array();
		foreach ($handlerContentTypes AS $contentType)
		{
			if (isset($contentTypes[$contentType]))
			{
				$handlers[$contentType] = XenForo_Search_DataHandler_Abstract::create($contentTypes[$contentType]);
			}
		}

		return $handlers;
	}

	/**
	 * Gets the search data handler for a specific content type.
	 *
	 * @param string $contentType
	 *
	 * @return XenForo_Search_DataHandler_Abstract|false
	 */
	public function getSearchDataHandler($contentType)
	{
		$handlers = $this->getSearchDataHandlers(array($contentType));
		return reset($handlers);
	}

	/**
	 * Groups search results by the content type they belong to.
	 *
	 * @param array $results Format: [] => array(content type, content id)
	 *
	 * @return array Format: [content type][content id] => content id
	 */
	public function groupSearchResultsByType(array $results)
	{
		$resultsGrouped = array();
		foreach ($results AS $result)
		{
			$resultsGrouped[$result[self::CONTENT_TYPE]][$result[self::CONTENT_ID]] = $result[self::CONTENT_ID];
		}

		return $resultsGrouped;
	}

	/**
	 * Gets the data for the search results that are actually viewable. If no
	 * data is returned, the result is not viewable and should be hidden.
	 *
	 * @param array $resultsGrouped Search results, grouped by type (see {@link groupSearchResultsByType()})
	 * @param array $handlers Search data handler objects for all necessary content types
	 * @param boolean $prepareData True if the data should be prepared as well
	 * @param array|null $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions) or null for visitor
	 *
	 * @return array Result data grouped, format: [content type][content id] => data
	 */
	public function getViewableSearchResultData(array $resultsGrouped, array $handlers, $prepareData = true, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$dataGrouped = array();
		foreach ($handlers AS $contentType => $handler)
		{
			if (!isset($resultsGrouped[$contentType]))
			{
				continue;
			}

			$dataResults = $handler->getDataForResults($resultsGrouped[$contentType], $viewingUser, $resultsGrouped);
			foreach ($dataResults AS $dataId => $data)
			{
				if (!$handler->canViewResult($data, $viewingUser))
				{
					unset($dataResults[$dataId]);
					continue;
				}

				if ($prepareData)
				{
					$dataResults[$dataId] = $handler->prepareResult($data, $viewingUser);
				}
			}

			$dataGrouped[$contentType] = $dataResults;
		}

		return $dataGrouped;
	}

	/**
	 * Filters a list of search results to those that are viewable.
	 *
	 * @param array $results Search results ([] => array(content type, content id)
	 * @param array|null $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions) or null for visitor
	 *
	 * @return array Same as input results, but unviewable entries removed
	 */
	public function getViewableSearchResults(array $results, array $viewingUser = null)
	{
		$resultsGrouped = $this->groupSearchResultsByType($results);
		$handlers = $this->getSearchDataHandlers(array_keys($resultsGrouped));

		$dataGrouped = $this->getViewableSearchResultData($resultsGrouped, $handlers, false, $viewingUser);

		foreach ($results AS $resultId => $result)
		{
			if (!isset($dataGrouped[$result[self::CONTENT_TYPE]][$result[self::CONTENT_ID]]))
			{
				unset($results[$resultId]);
			}
		}

		return $results;
	}

	/**
	 * Gets the search results ready for display (using the handlers).
	 * The results (in the returned "results" key) have extra, type-specific data
	 * included with them.
	 *
	 * @param array $results Search results ([] => array(content type, content id)
	 * @param array|null $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions) or null for visitor
	 *
	 * @return array Keys: results, handlers
	 */
	public function getSearchResultsForDisplay(array $results, array $viewingUser = null)
	{
		$resultsGrouped = $this->groupSearchResultsByType($results);
		$handlers = $this->getSearchDataHandlers(array_keys($resultsGrouped));

		$dataGrouped = $this->getViewableSearchResultData($resultsGrouped, $handlers, true, $viewingUser);

		foreach ($results AS $resultId => $result)
		{
			if (isset($dataGrouped[$result[self::CONTENT_TYPE]][$result[self::CONTENT_ID]]))
			{
				$results[$resultId]['content'] = $dataGrouped[$result[self::CONTENT_TYPE]][$result[self::CONTENT_ID]];
			}
			else
			{
				unset($results[$resultId]);
			}
		}

		if (!$results)
		{
			return false;
		}

		return array(
			'results' => $results,
			'handlers' => $handlers
		);
	}

	/**
	 * Returns the slice of search results for the requested page.
	 *
	 * @param array $search Search, containing search results
	 * @param integer $page
	 * @param integer $perPage
	 *
	 * @return array Results for the specified page
	 */
	public function sliceSearchResultsToPage(array $search, $page, $perPage)
	{
		if ($page < 1)
		{
			$page = 1;
		}

		if (!isset($search['searchResults']))
		{
			$search['searchResults'] = $this->_decodeSearchTableData($search['search_results'], true);
		}

		return array_slice($search['searchResults'], ($page - 1) * $perPage, $perPage);
	}

	/**
	 * Gets the general search constraints from an array of input.
	 *
	 * @param array $input
	 * @param mixed $errors Returns a list of errors that occurred when getting constraints
	 *
	 * @return array Constraints
	 */
	public function getGeneralConstraintsFromInput(array $input, &$errors = null)
	{
		$constraints = array();
		$errors = array();

		if (!empty($input['date']))
		{
			$constraints['date'] = $input['date'];
		}
		if (!empty($input['title_only']))
		{
			$constraints['title_only'] = $input['title_only'];
		}
		if (!empty($input['nodes']) && reset($input['nodes']))
		{
			if (!empty($input['child_nodes']))
			{
				$childNodeIds = array_keys($this->getModelFromCache('XenForo_Model_Node')->getChildNodesForNodeIds($input['nodes']));
				$input['nodes'] = array_unique(array_merge($input['nodes'], $childNodeIds));
			}
			$constraints['node'] = implode(' ', $input['nodes']);
		}
		if (!empty($input['users']))
		{
			/* @var $userModel XenForo_Model_User */
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			$usernames = explode(',', $input['users']);
			$users = $userModel->getUsersByNames($usernames, array(), $notFound);

			if ($notFound)
			{
				$errors[] = new XenForo_Phrase('following_members_not_found_x', array('members' => implode(', ', $notFound)));
			}

			$constraints['user'] = array_keys($users);

			if ($constraints['user'] && !empty($input['user_content']))
			{
				$constraints['user_content'] = $input['user_content'];
			}
		}

		return $constraints;
	}
}