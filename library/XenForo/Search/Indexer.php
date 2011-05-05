<?php

/**
 * Manipulates the search index. Proxies to the search source handler.
 *
 * @package XenForo_Search
 */
class XenForo_Search_Indexer
{
	/**
	 * @var XenForo_Search_SourceHandler_Abstract
	 */
	protected $_sourceHandler = null;

	/**
	 * Constructor.
	 *
	 * @param XenForo_Search_SourceHandler_Abstract|null $sourceHandler Search source handler. Uses default if not specified.
	 */
	public function __construct(XenForo_Search_SourceHandler_Abstract $sourceHandler = null)
	{
		if (!$sourceHandler)
		{
			$sourceHandler = XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler();
		}

		$this->_sourceHandler = $sourceHandler;
	}

	/**
	 * Inserts (or replaces) the specified record in the index.
	 *
	 * @param string $contentType Content type string
	 * @param integer $contentId ID of the content being indexed
	 * @param string $title Content title
	 * @param string $message Content message
	 * @param integer $itemDate Timestamp of content
	 * @param integer $userId ID of user content belongs to
	 * @param integer $discussionId ID of discussion or other grouping container
	 * @param array $metadata Key value pairs of metadata to index.
	 */
	public function insertIntoIndex($contentType, $contentId, $title, $message, $itemDate, $userId, $discussionId = 0, array $metadata = array())
	{
		$this->_sourceHandler->insertIntoIndex($contentType, $contentId, $title, $message, $itemDate, $userId, $discussionId, $metadata);
	}

	/**
	 * Indexes the specified content.
	 *
	 * @param string $contentType
	 * @param array|integer $contentIds One or more content IDs to index
	 *
	 * @return boolean
	 */
	public function quickIndex($contentType, $contentIds)
	{
		$handler = XenForo_Model::create('XenForo_Model_Search')->getSearchDataHandler($contentType);
		if (!$handler)
		{
			return false;
		}

		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}
		if (!$contentIds)
		{
			return false;
		}

		return $handler->quickIndex($this, $contentIds);
	}

	/**
	 * Updates the existing index record for a specified item.
	 *
	 * @param string $contentType Content type string
	 * @param integer $contentId ID of the content being indexed
	 * @param array $fieldUpdates Key-value pairs of fields to update. Metadata is not updatable.
	 */
	public function updateIndex($contentType, $contentId, array $fieldUpdates)
	{
		$this->_sourceHandler->updateIndex($contentType, $contentId, $fieldUpdates);
	}

	/**
	 * Deletes the specified entry or entries from the index.
	 *
	 * @param string $contentType Type of content to remove
	 * @param array|integer $contentIds An array of content IDs or 1 as an integer
	 */
	public function deleteFromIndex($contentType, $contentIds)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}

		if (!$contentIds)
		{
			return;
		}

		$this->_sourceHandler->deleteFromIndex($contentType, $contentIds);
	}

	/**
	 * Sets whether this is a bulk rebuild. If true, behavior may be modified to be
	 * less asynchronous.
	 *
	 * @param boolean $rebuild
	 */
	public function setIsRebuild($rebuild)
	{
		$this->_sourceHandler->setIsRebuild($rebuild);
	}

	/**
	 * When rebuilding, it might be advantageous to bulk update records. This function
	 * must be called to ensure that all records are updated together.
	 */
	public function finalizeRebuildSet()
	{
		$this->_sourceHandler->finalizeRebuildSet();
	}
}