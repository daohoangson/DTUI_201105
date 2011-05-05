<?php

/**
 * Handles preparing to search for data and proxying to the source handler.
 *
 * @package XenForo_Search
 */
class XenForo_Search_Searcher
{
	/**
	 * @var XenForo_Search_SourceHandler_Abstract
	 */
	protected $_sourceHandler = null;

	/**
	 * @var XenForo_Model_Search
	 */
	protected $_searchModel = null;

	/**
	 * Errors are fatal conditions that will prevent the search from happening.
	 *
	 * @var array
	 */
	protected $_errors = array();

	/**
	 * Warnings are messages that should be shown to the user in the search results,
	 * but do not prevent the search from happening.
	 *
	 * @var array
	 */
	protected $_warnings = array();

	/**
	 * User viewing for permission checks.
	 *
	 * @var array|null|false Means disable checks; null means current user
	 */
	protected $_viewingUser = null;

	/**
	 * Constructor.
	 *
	 * @param XenForo_Model_Search $searchModel Search model
	 * @param XenForo_Search_SourceHandler_Abstract|null $sourceHandler Search source handler. Uses default if not specified.
	 */
	public function __construct($searchModel, XenForo_Search_SourceHandler_Abstract $sourceHandler = null)
	{
		if (!$sourceHandler)
		{
			$sourceHandler = XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler();
		}

		$this->_sourceHandler = $sourceHandler;
		$this->_sourceHandler->setSearcher($this);

		$this->_searchModel = $searchModel;
	}

	/**
	 * Performs a general search. This will usually be across all types of content, but
	 * could be limited but only using standard constraints.
	 *
	 * @param string $searchQuery Text to search for
	 * @param array $constraints Constraints to apply; handled by source handlers
	 * @param string $order Ordering; handled by source handlers
	 * @param integer $maxResults Maximum number of results to return
	 *
	 * @return array Search results: [] => array(content type , id)
	 */
	public function searchGeneral($searchQuery, array $constraints = array(), $order = 'relevance', $maxResults = 0)
	{
		if ($maxResults < 1)
		{
			$maxResults = XenForo_Application::get('options')->maximumSearchResults;
		}

		$results = $this->_sourceHandler->searchGeneral($searchQuery, $constraints, $order, $maxResults);
		if ($this->_viewingUser !== false)
		{
			$results = $this->_searchModel->getViewableSearchResults($results, $this->_viewingUser);
		}

		return $results;
	}

	/**
	 * Performs a type specific search.
	 *
	 * @param XenForo_Search_DataHandler_Abstract $typeHandler Data handler for the type of search
	 * @param string $searchQuery Text to search for
	 * @param array $constraints Constraints to apply; handled by source handlers
	 * @param string $order Ordering; handled by source handlers
	 * @param boolean $groupByDiscussion If true, fold/group the results by the discussion_id value
	 * @param integer $maxResults Maximum number of results to return
	 *
	 * @return array Search results: [] => array(content type , id)
	 */
	public function searchType(XenForo_Search_DataHandler_Abstract $typeHandler,
		$searchQuery, array $constraints = array(), $order = 'relevance', $groupByDiscussion = false, $maxResults = 0
	)
	{
		if ($maxResults < 1)
		{
			$maxResults = XenForo_Application::get('options')->maximumSearchResults;
		}

		$results = $this->_sourceHandler->searchType(
			$typeHandler, $searchQuery, $constraints, $order, $groupByDiscussion, $maxResults
		);
		if ($this->_viewingUser !== false)
		{
			$results = $this->_searchModel->getViewableSearchResults($results, $this->_viewingUser);
		}

		return $results;
	}

	/**
	 * Searches for content by a specific user.
	 *
	 * @param integer $userId
	 * @param integer $maxDate If >0, the only messages older than this will be found
	 * @param integer $maxResults
	 *
	 * @return array Search results: [] => array(content type , id)
	 */
	public function searchUser($userId, $maxDate = 0, $maxResults = 0)
	{
		if ($maxResults < 1)
		{
			$maxResults = XenForo_Application::get('options')->maximumSearchResults;
		}

		$results = $this->_sourceHandler->executeSearchByUserId($userId, $maxDate, $maxResults);
		if ($this->_viewingUser !== false)
		{
			$results = $this->_searchModel->getViewableSearchResults($results, $this->_viewingUser);
		}

		return $results;
	}

	/**
	 * Sets the viewing user.
	 *
	 * @param array|null $viewingUser
	 */
	public function setUser(array $viewingUser = null)
	{
		$this->_viewingUser = $viewingUser;
	}

	/**
	 * Triggers an error. An error will prevent the search from going through.
	 *
	 * @param XenForo_Phrase|string $message Error message
	 * @param string $field Field error applies to
	 */
	public function error($message, $field)
	{
		$this->_errors[$field] = $message;
	}

	/**
	 * Triggers a warning. This will be shown to the user on the search results
	 * page.
	 *
	 * @param XenForo_Phrase|string $message Warning message
	 * @param string $field Field warning applies to
	 */
	public function warning($message, $field)
	{
		$this->_warnings[$field] = $message;
	}

	/**
	 * Determines if the searcher has errors.
	 *
	 * @return boolean
	 */
	public function hasErrors()
	{
		return (count($this->_errors) > 0);
	}

	/**
	 * Gets all error messages.
	 *
	 * @return array Array of strings and/or XenForo_Phrase objects
	 */
	public function getErrors()
	{
		return $this->_errors;
	}

	/**
	 * Gets all warning messages.
	 *
	 * @return array Array of strings and/or XenForo_Phrase objects
	 */
	public function getWarnings()
	{
		return $this->_warnings;
	}
}