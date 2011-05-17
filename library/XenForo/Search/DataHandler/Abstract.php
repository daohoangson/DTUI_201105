<?php

/**
 * Abstract base for handling different content types in search.
 *
 * @package XenForo_Search
 */
abstract class XenForo_Search_DataHandler_Abstract
{
	/**
	 * Inserts a new record or replaces an existing record in the index.
	 *
	 * @param XenForo_Search_Indexer $indexer Object that will will manipulate the index
	 * @param array $data Data that needs to be updated
	 * @param array|null $parentData Data about the parent info (eg, for a post, the parent thread)
	 */
	abstract protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null);

	/**
	 * Updates a record in the index.
	 *
	 * @param XenForo_Search_Indexer $indexer Object that will will manipulate the index
	 * @param array $data Data that needs to be updated
	 * @param array $fieldUpdates Key-value fields to update
	 */
	abstract protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates);

	/**
	 * Deletes one or more records from the index. Wrapper around {@link _deleteFromIndex()}.
	 *
	 * @param XenForo_Search_Indexer $indexer Object that will will manipulate the index
	 * @param array $dataList A list of data to remove. Each element is an array of the data from one record.
	 */
	abstract protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList);

	/**
	 * Rebuilds the index in bulk.
	 *
	 * @param XenForo_Search_Indexer $indexer Object that will will manipulate the index
	 * @param integer $lastId The last ID that was processed. Should continue with the IDs above this.
	 * @param integer $batchSize Number of records to process at once
	 *
	 * @return integer|false The last ID that was processed or false if none were processed
	 */
	abstract public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize);

	/**
	 * Indexes the specified content IDs.
	 *
	 * @param XenForo_Search_Indexer $indexer
	 * @param array $contentIds
	 *
	 * @return array List of content IDs indexed
	 */
	abstract public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds);

	/**
	 * Gets the additional, type-specific data for a list of results. If any of
	 * the given IDs are not returned from this, they will be removed from the results.
	 *
	 * @param array $ids List of IDs of this content type.
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 * @param array $resultsGrouped List of all results grouped by content type
	 *
	 * @return array Format: [id] => data, IDs not returned will be removed from results
	 */
	abstract public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped);

	/**
	 * Determines if the specific search result (data from getDataForResults()) can be viewed
	 * by the given user. The user and combination ID will be the same as given to getDataForResults().
	 *
	 * @param array $result Data for a result
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return boolean
	 */
	abstract public function canViewResult(array $result, array $viewingUser);

	/**
	 * Gets the date of the result (from the result's content).
	 *
	 * @param array $result
	 *
	 * @return integer
	 */
	abstract public function getResultDate(array $result);

	/**
	 * Render a result (as HTML).
	 *
	 * @param XenForo_View $view
	 * @param array $result Data from result
	 * @param array $search The search that was performed
	 *
	 * @return XenForo_Template_Abstract|string
	 */
	abstract public function renderResult(XenForo_View $view, array $result, array $search);

	/**
	 * Get the content types that will be searched, when doing a type-specific search for this type.
	 * This may be multiple types (for example, thread and post for post searches).
	 *
	 * @return array
	 */
	abstract public function getSearchContentTypes();

	/**
	 * Prepares the result for display.
	 *
	 * @param array $result
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array Prepared result
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		return $result;
	}

	/**
	 * Get the controller response for the form to search this type of content specifically.
	 *
	 * @param XenForo_ControllerPublic_Abstract $controller Invoking controller
	 * @param XenForo_Input $input Input object from controller
	 * @param array $viewParams View params prepared for general search
	 *
	 * @return XenForo_ControllerResponse_Abstract|false
	 */
	public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input, array $viewParams)
	{
		return false;
	}

	/**
	 * Get type-specific constrints from input.
	 *
	 * @param XenForo_Input $input
	 *
	 * @return array
	 */
	public function getTypeConstraintsFromInput(XenForo_Input $input)
	{
		return array();
	}

	/**
	 * Allow type-specific pre-constraint application filtering. For example,
	 * a "thread only" constraint may change the searchable content types.
	 *
	 * @param XenForo_Search_SourceHandler_Abstract $sourceHandler Source handler calling
	 * @param array $constraints Unfiltered constraints
	 *
	 * @return array Filtered constraints
	 */
	public function filterConstraints(XenForo_Search_SourceHandler_Abstract $sourceHandler, array $constraints)
	{
		return $constraints;
	}

	/**
	 * Process a constraint, if it is known to be specific to this type. If the constraint
	 * is unknown, it should simply be ignored.
	 *
	 * @param XenForo_Search_SourceHandler_Abstract $sourceHandler Source handler invoking
	 * @param string $constraint Name of the constraint
	 * @param mixed $constraintInfo Data for the constraint; may be an array or scalar
	 * @param array $constraints List of all constraints specified
	 *
	 * @return array|false If processed, return array with possible keys:
	 * 		* metadata - metadata value; keys: 0 = name of metadata, 1 = scalar/array allowed value(s) for metadata
	 * 		* query - constraint to limit via query; keys: 0 = table alias, 1 = field, 2 = operator, 3 = scalar/array allowed value(s). Multiple for "=" operator only.
	 * 	Note that the metadata and query keys are assumed to be equivalent. Engines need only use one (depending on engine details).
	 */
	public function processConstraint(XenForo_Search_SourceHandler_Abstract $sourceHandler, $constraint, $constraintInfo, array $constraints)
	{
		return false;
	}

	/**
	 * Gets the search order for a type-specific search.
	 *
	 * @param string $order Order requested by user
	 *
	 * @return false|array False or or array of arrays. Child array keys: 0 = table alias, 1 = field, 2 = dir (asc/desc)
	 */
	public function getOrderClause($order)
	{
		return false;
	}

	/**
	 * Get the data that is needed to do joins/queries against data that isn't kept
	 * in the search index itself.
	 *
	 * @param array $tables List of table aliases (in the keys) that are requested for this search
	 * @return array Keys should be table aliases (to use in query). Values are arrays with keys:
	 * 		* table - actual table name
	 * 		* key - name of the field in the table that matches up with the relationship field
	 * 		* relationship - field to join against. Array, 0 = table of field, 1 = field name.
	 */
	public function getJoinStructures(array $tables)
	{
		return array();
	}

	/**
	 * Gets the content type that will be returned when grouping results.
	 *
	 * @return string If empty, grouping will not be possible
	 */
	public function getGroupByType()
	{
		return '';
	}

	/**
	 * Inserts a new record or replaces an existing record in the index.
	 * Wrapper around {@link _insertIntoIndex()}.
	 *
	 * @param XenForo_Search_Indexer $indexer Object that will will manipulate the index
	 * @param array $data Data that needs to be updated
	 * @param array|null $parentData Data about the parent info (eg, for a post, the parent thread)
	 */
	public final function insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		$this->_insertIntoIndex($indexer, $data, $parentData);
	}

	/**
	 * Updates a record in the index. Wrapper around {@link _updateIndex()}.
	 *
	 * @param XenForo_Search_Indexer $indexer Object that will will manipulate the index
	 * @param array $data Data that needs to be updated
	 * @param array $fieldUpdates Key-value fields to update
	 */
	public final function updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		if (!$fieldUpdates)
		{
			return;
		}

		$this->_updateIndex($indexer, $data, $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index. Wrapper around {@link _deleteFromIndex()}.
	 *
	 * @param XenForo_Search_Indexer $indexer Object that will will manipulate the index
	 * @param array $dataList A list of data to remove. This may be one piece of data or multiple. Detection based on whether first element is an array.
	 */
	public final function deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		if (!$dataList)
		{
			return;
		}

		$first = reset($dataList);

		if (!is_array($first))
		{
			$dataList = array($dataList);
		}

		$this->_deleteFromIndex($indexer, $dataList);
	}

	/**
	 * Creates the specified data handler.
	 *
	 * @param string $class Object to create
	 *
	 * @return XenForo_Search_DataHandler_Abstract
	 */
	public static function create($class)
	{
		$class = XenForo_Application::resolveDynamicClass($class, 'search_data');
		return new $class;
	}
}