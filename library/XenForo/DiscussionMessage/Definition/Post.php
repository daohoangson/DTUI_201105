<?php

/**
 * Post discussion message definition.
 *
 * @package XenForo_Discussion
 */
class XenForo_DiscussionMessage_Definition_Post extends XenForo_DiscussionMessage_Definition_Abstract
{
	/**
	 * Gets the structure of the message record.
	 *
	 * @return array
	 */
	protected function _getMessageStructure()
	{
		return array(
			'table' => 'xf_post',
			'key' => 'post_id',
			'container' => 'thread_id',
			'contentType' => 'post'
		);
	}

	/**
	 * Gets the parts of the message configuration options that are to override the defaults.
	 *
	 * @return array
	 */
	protected function _getMessageConfiguration()
	{
		return array(
			'hasParentDiscussion' => true
		);
	}

	/**
	 * Gets the discussion data writer for the given discussion ID.
	 *
	 * @param integer $discussionId
	 * @param constant $errorHandler DW error handler constant (usually parent DW's error handler)
	 *
	 * @return XenForo_DataWriter_Discussion|false
	 */
	public function getDiscussionDataWriter($discussionId, $errorHandler)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', $errorHandler);
		$dw->setExistingData($discussionId);
		return $dw;
	}

	/**
	 * Gets the search data handler for this type of message.
	 *
	 * @return XenForo_Search_DataHandler_Abstract|false
	 */
	public function getSearchDataHandler()
	{
		return XenForo_Search_DataHandler_Abstract::create('XenForo_Search_DataHandler_Post');
	}
}