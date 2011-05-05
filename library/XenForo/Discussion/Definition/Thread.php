<?php

/**
 * Thread discussion definition.
 *
 * @package XenForo_Discussion
 */
class XenForo_Discussion_Definition_Thread extends XenForo_Discussion_Definition_Abstract
{
	/**
	 * Gets the structure of the discussion record.
	 *
	 * @return array
	 */
	protected function _getDiscussionStructure()
	{
		return array(
			'table' => 'xf_thread',
			'key' => 'thread_id',
			'container' => 'node_id',
			'contentType' => 'thread'
		);
	}

	/**
	 * Gets the message data writer for the given message ID. If no message is given, should return
	 * a "new" DW.
	 *
	 * @param integer $firstMessageId
	 * @param constant $errorHandler DW error handler constant (usually parent DW's error handler)
	 *
	 * @return XenForo_DataWriter_DiscussionMessage
	 */
	public function getFirstMessageDataWriter($firstMessageId, $errorHandler)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', $errorHandler);
		if ($firstMessageId)
		{
			$dw->setExistingData($firstMessageId);
		}

		return $dw;
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
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Forum', $errorHandler);
		$dw->setExistingData($containerId);
		return $dw;
	}

	/**
	 * Gets the search data handler for this type of discussion.
	 *
	 * @return XenForo_Search_DataHandler_Abstract|false
	 */
	public function getSearchDataHandler()
	{
		return XenForo_Search_DataHandler_Abstract::create('XenForo_Search_DataHandler_Thread');
	}

}