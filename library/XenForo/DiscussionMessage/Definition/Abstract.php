<?php

/**
 * Class that represents the definition of a discussion message. This will generally
 * be used to interact with data writers (both for messages and discussions). It serves
 * mostly to decouple data that will be useful to multiple data writers.
 *
 * @package XenForo_Discussion
 */
abstract class XenForo_DiscussionMessage_Definition_Abstract
{
	/**
	 * Contains the structure returned from {@link _getMessageStructure()}.
	 *
	 * @var array
	 */
	protected $_structure = array();

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->_structure = $this->_getMessageStructure();
	}

	/**
	 * Gets the structure of the message record. This only includes parts that are variable. Keys returned:
	 * 	* table - name of the table (eg, xf_post)
	 * 	* key - name of the message's key (eg, post_id)
	 * 	* container - name of the container/discussion's key (eg, thread_id, profile_user_id)
	 * 	* contentType - name of the content type the message uses (eg, post, profile_post)
	 *
	 * @return array
	 */
	abstract protected function _getMessageStructure();

	/**
	 * Gets the parts of the message configuration options that override the defaults. Options:
	 * 	* hasParentDiscussion (false)
	 * 	* changeUserMessageCount (true)
	 *
	 * @return array
	 */
	protected function _getMessageConfiguration()
	{
		return array();
	}

	/**
	 * Gets the discussion data writer for the given discussion ID. If no discussion is given,
	 * should return false.
	 *
	 * @param integer $discussionId
	 * @param constant $errorHandler DW error handler constant (usually parent DW's error handler)
	 *
	 * @return XenForo_DataWriter_Discussion|false
	 */
	public function getDiscussionDataWriter($discussionId, $errorHandler)
	{
		return false;
	}

	/**
	 * Gets the search data handler for this type of message.
	 *
	 * @return XenForo_Search_DataHandler_Abstract|false
	 */
	public function getSearchDataHandler()
	{
		return false;
	}

	/**
	 * Gets the effective message configuration. This merges the defaults with
	 * the specific class overrides. See {@link _getMessageConfiguration()} for options.
	 *
	 * @return array
	 */
	public function getMessageConfiguration()
	{
		$configuration = array(
			'hasParentDiscussion' => false,
			'changeUserMessageCount' => true
		);

		return array_merge($configuration, $this->_getMessageConfiguration());
	}

	/**
	 * Gets the full message structure array. See {@link _getMessageStructure()} for
	 * data returned.
	 *
	 * @return array
	 */
	public function getMessageStructure()
	{
		return $this->_structure;
	}

	public function getMessageTableName()
	{
		return $this->_structure['table'];
	}

	public function getMessageKeyName()
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