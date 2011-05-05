<?php

abstract class XenForo_Discussion_Definition_Abstract
{
	/*
	 * first message dw name
	 * last message in discussion
	 * messages in discussion
	 * has parent container
	 * -search data handler
	 * message search data handler
	 */

	/**
	 * Contains the structure returned from {@link _getDiscussionStructure()}.
	 *
	 * @var array
	 */
	protected $_structure = array();

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->_structure = $this->_getDiscussionStructure();
	}

	/**
	 * Gets the structure of the discussion record. This only includes parts that are variable. Keys returned:
	 * 	* table - name of the table (eg, xf_thread)
	 * 	* key - name of the discussion's key (eg, thread_id)
	 * 	* container - name of the container's key (eg, forum_id); leave blank (but define) if none!
	 * 	* contentType - name of the content type the discussion uses (eg, thread)
	 *
	 * @return array
	 */
	abstract protected function _getDiscussionStructure();

	/**
	 * Gets the message data writer for the given message ID. If no message is given, should return
	 * a "new" DW.
	 *
	 * @param integer $firstMessageId
	 * @param constant $errorHandler DW error handler constant (usually parent DW's error handler)
	 *
	 * @return XenForo_DataWriter_DiscussionMessage
	 */
	abstract public function getFirstMessageDataWriter($firstMessageId, $errorHandler);

	/**
	 * Gets the parts of the discussion configuration options that override the defaults. Options:
	 * 	* changeUserMessageCount (true)
	 *
	 * @return array
	 */
	protected function _getDiscussionConfiguration()
	{
		return array();
	}

	/**
	 * Gets the datawriter for the discussion's container. This DW must implement
	 * XenForo_DataWriter_DiscussionContainerInterface. May be false.
	 *
	 * @param integer $containerId
	 * @param constant $errorHandler DW error handler constant (usually parent DW's error handler)
	 *
	 * @return XenForo_DataWriter|false
	 */
	public function getContainerDataWriter($containerId, $errorHandler)
	{
		return false;
	}

	/**
	 * Gets the search data handler for this type of discussion.
	 *
	 * @return XenForo_Search_DataHandler_Abstract|false
	 */
	public function getSearchDataHandler()
	{
		return false;
	}

	/**
	 * Gets the effective discussion configuration. This merges the defaults with
	 * the specific class overrides. See {@link _getDiscussionConfiguration()} for options.
	 *
	 * @return array
	 */
	public function getDiscussionConfiguration()
	{
		$configuration = array(
			'hasParentContainer' => (!empty($this->_structure['container'])),
			'changeUserMessageCount' => true
		);

		return array_merge($configuration, $this->_getDiscussionConfiguration());
	}

	/**
	 * Gets the full discussion structure array. See {@link _getDiscussionStructure()} for
	 * data returned.
	 *
	 * @return array
	 */
	public function getDiscussionStructure()
	{
		return $this->_structure;
	}

	public function getDiscussionTableName()
	{
		return $this->_structure['table'];
	}

	public function getDiscussionKeyName()
	{
		return $this->_structure['key'];
	}

	public function getContainerKeyName()
	{
		return $this->_structure['container'];
	}

	public function getContentType()
	{
		return $this->_structure['contentType'];
	}
}