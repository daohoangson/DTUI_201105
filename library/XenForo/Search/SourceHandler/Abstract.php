<?php

/**
 * Abstract representation of a search source.
 *
 * @package XenForo_Search
 */
abstract class XenForo_Search_SourceHandler_Abstract
{
	/**
	 * @var XenForo_Search_Searcher|null
	 */
	protected $_searcher = null;

	/**
	 * Determines if this process is doing a bulk rebuild.
	 *
	 * @var boolean
	 */
	protected $_isRebuild = false;

	/**
	 * Determines if this source handler supports relevance searching. If false,
	 * it will not be made available as a sorting option.
	 *
	 * @return boolean
	 */
	abstract public function supportsRelevance();

	/**
	 * Inserts (or prepares to insert) a new record into the search index. This must
	 * also update (replace) an existing record, if the (type, id) pair already exists.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param string $title
	 * @param string $message
	 * @param integer $itemDate Time stamp for the content (this will be used in date limits and sorts)
	 * @param integer $userId User that created the content
	 * @param integer $discussionId ID of discussion or other grouping container
	 * @param array $metadata Arbitrary list of other metadata that should be indexed if possible
	 */
	abstract public function insertIntoIndex($contentType, $contentId, $title, $message, $itemDate, $userId, $discussionId = 0, array $metadata = array());

	/**
	 * Updates specific fields in an already existing index record. Metadata cannot
	 * be updated this way.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param array $fieldUpdates Key-value pairs to change
	 */
	abstract public function updateIndex($contentType, $contentId, array $fieldUpdates);

	/**
	 * Deletes the specified records from the index.
	 * @param string $contentType
	 * @param array $contentIds List of content IDs (of $contentType to delete)
	 */
	abstract public function deleteFromIndex($contentType, array $contentIds);

	/**
	 * Executes a search against the full text index.
	 *
	 * @param string $searchQuery Text to search for
	 * @param boolean $titleOnly If true, only searches text in titles
	 * @param array $processedConstraints Structured constraints
	 * @param array $orderParts Structured ordered by parts
	 * @param string $groupByDiscussionType If grouping, content type of grouped results
	 * @param integer $maxResults
	 * @param XenForo_Search_DataHandler_Abstract $typeHandler Type-specific handler, for joins
	 *
	 * @return array Search results ([] => array(content type, id))
	 */
	abstract public function executeSearch($searchQuery, $titleOnly, array $processedConstraints, array $orderParts,
		$groupByDiscussionType, $maxResults, XenForo_Search_DataHandler_Abstract $typeHandler = null
	);

	/**
	 * Executes a search for content by a specific user. Currently this includes no constraints,
	 * but down the line it may support non-query constraints.
	 *
	 * @param integer $userId
	 * @param integer $maxDate If >0, only messages older than this should be found
	 * @param integer $maxResults
	 *
	 * @return array Search results ([] => array(content type, id))
	 */
	abstract public function executeSearchByUserId($userId, $maxDate, $maxResults);

	/**
	 * Performs a general search. This will usually be across all types of content, but
	 * could be limited but only using standard constraints.
	 *
	 * @param string $searchQuery Text to search for
	 * @param array $constraints Constraints to apply; handled by source handlers
	 * @param string $order Ordering; handled by source handlers
	 * @param integer $maxResults Maximum number of results to return
	 *
	 * @return array Search results: [] => array(content_type => x, content_id => y)
	 */
	public function searchGeneral($searchQuery, array $constraints, $order, $maxResults)
	{
		$titleOnly = isset($constraints['title_only']);
		unset($constraints['title_only']);

		$processedConstraints = $this->processConstraints($constraints);
		$orderClause = $this->getGeneralOrderClause($order);

		return $this->executeSearch($searchQuery, $titleOnly, $processedConstraints, $orderClause, false, $maxResults);
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
	 * @return array Search results: [] => array(content_type => x, content_id => y)
	 */
	public function searchType(XenForo_Search_DataHandler_Abstract $typeHandler,
		$searchQuery, array $constraints = array(), $order, $groupByDiscussion, $maxResults
	)
	{
		$titleOnly = isset($constraints['title_only']);
		unset($constraints['title_only']);

		$constraints['content'] = $typeHandler->getSearchContentTypes();
		$constraints = $typeHandler->filterConstraints($this, $constraints);
		$processedConstraints = $this->processConstraints($constraints, $typeHandler);

		$orderClause = $typeHandler->getOrderClause($order);
		if (!$orderClause)
		{
			 $orderClause = $this->getGeneralOrderClause($order);
		}

		$groupByDiscussionType = ($groupByDiscussion ? $typeHandler->getGroupByType() : '');

		return $this->executeSearch(
			$searchQuery, $titleOnly, $processedConstraints, $orderClause,
			$groupByDiscussionType, $maxResults, $typeHandler
		);
	}

	/**
	 * Process search constraints.
	 *
	 * @param array $constraints List of constraints: [constraint name] => limit (may be scalar or array)
	 * @param XenForo_Search_DataHandler_Abstract|null $typeHandler
	 *
	 * @return array Processed constraints. Names as keys, value is array with possible keys:
	 * 		* metadata - metadata value; keys: 0 = name of metadata, 1 = scalar/array allowed value(s) for metadata
	 * 		* query - constraint to limit via query; keys: 0 = table alias, 1 = field, 2 = operator, 3 = scalar/array allowed value(s). Multiple for "=" operator only.
	 * 	Note that the metadata and query keys are assumed to be equivalent. Engines need only use one (depending on engine details).
	 */
	public function processConstraints(array $constraints, XenForo_Search_DataHandler_Abstract $typeHandler = null)
	{
		$processed = array();

		foreach ($constraints AS $constraint => $constraintInfo)
		{
			if (is_array($constraintInfo) && count($constraintInfo) == 0)
			{
				continue;
			}

			switch ($constraint)
			{
				case 'user':
					$processed[$constraint] = array(
						'metadata' => array('user', $constraintInfo),
						'query' => array('search_index', 'user_id', '=', $constraintInfo)
					);
					break;

				case 'user_content':
					if (!empty($constraints['user']))
					{
						$processed[$constraint] = array(
							'metadata' => array('content', $constraintInfo),
							'query' => array('search_index', 'content_type', '=', $constraintInfo)
						);
					}
					break;

				case 'content':
					$processed[$constraint] = array(
						'metadata' => array('content', $constraintInfo),
						'query' => array('search_index', 'content_type', '=', $constraintInfo)
					);
					break;

				case 'node':
					$processed[$constraint] = array(
						'metadata' => array('node', preg_split('/\D/', strval($constraintInfo))),
					);
					break;

				case 'date':
					$processed[$constraint] = array(
						'query' => array('search_index', 'item_date', '>', intval($constraintInfo))
					);
					break;

				default:
					if ($typeHandler)
					{
						$newConstraint = $typeHandler->processConstraint($this, $constraint, $constraintInfo, $constraints);
						if ($newConstraint)
						{
							$processed[$constraint] = $newConstraint;
						}
					}
			}
		}

		return $processed;
	}

	/**
	 * Gets the general order clauses for a search.
	 *
	 * @param string $order User-requested order
	 *
	 * @return array Structured order clause, array of arrays. Child array keys: 0 = table alias, 1 = field, 2 = dir (asc/desc)
	 */
	public function getGeneralOrderClause($order)
	{
		return array(
			array('search_index', 'item_date', 'desc')
		);
	}

	/**
	 * Triggers an error with the searcher object. An error will prevent the search
	 * from going through.
	 *
	 * @param XenForo_Phrase|string $message Error message
	 * @param string $field Field error applies to
	 */
	public function error($message, $field)
	{
		if ($this->_searcher)
		{
			$this->_searcher->error($message, $field);
		}
	}

	/**
	 * Triggers a warning with the searcher object. This will be shown to the user
	 * on the search results page.
	 *
	 * @param XenForo_Phrase|string $message Warning message
	 * @param string $field Field warning applies to
	 */
	public function warning($message, $field)
	{
		if ($this->_searcher)
		{
			$this->_searcher->warning($message, $field);
		}
	}

	/**
	 * Sets the containing searcher object. This will be used for things
	 * like error tracking.
	 *
	 * @param XenForo_Search_Searcher|null $searcher
	 */
	public function setSearcher(XenForo_Search_Searcher $searcher = null)
	{
		$this->_searcher = $searcher;
	}

	/**
	 * Sets whether this is a bulk rebuild. If true, behavior may be modified to be
	 * less asynchronous.
	 *
	 * @param boolean $rebuild
	 */
	public function setIsRebuild($rebuild)
	{
		$this->_isRebuild = $rebuild;
	}

	/**
	 * When rebuilding, it might be advantageous to bulk update records. This function
	 * must be called to ensure that all records are updated together.
	 */
	public function finalizeRebuildSet()
	{
	}

	/**
	 * Gets the default source handler.
	 *
	 * @return XenForo_Search_SourceHandler_Abstract
	 */
	public static function getDefaultSourceHandler()
	{
		$class = 'XenForo_Search_SourceHandler_MySqlFt';
		XenForo_CodeEvent::fire('search_source_create', array(&$class));

		return new $class();
	}
}