<?php

/**
 * Abstract handler for content type-specific attachment behaviors.
 *
 * @package XenForo_Attachment
 */
abstract class XenForo_AttachmentHandler_Abstract
{
	/**
	 * The key of the content ID value in the content data array.
	 * Must be overriden by children.
	 *
	 * @var string
	 */
	protected $_contentIdKey = '';

	/**
	 * Determines if the specified user can upload new attachments or
	 * manage existing ones. The content data may contain different type-specific
	 * values in different situations. Eg, when posting a thread, only the node_id is
	 * known; when posting a reply, the thread_id is know; when editing a post, the
	 * post_id is known.
	 *
	 * @param array $contentData Type-specific params based on context
	 * @param array $viewingUser Viewing user array
	 *
	 * @return boolean
	 */
	abstract protected function _canUploadAndManageAttachments(array $contentData, array $viewingUser);

	/**
	 * Determines if the specified user can view the given attachment.
	 *
	 * @param array $attachment Attachment to view
	 * @param array $viewingUser Viewing user array
	 *
	 * @return boolean
	 */
	abstract protected function _canViewAttachment(array $attachment, array $viewingUser);

	/**
	 * Behavior to carry out after deleting an attachment (such as reducing an
	 * attachment count on the content). This is only called when the attachment
	 * has been associated with particular content (not just uploaded unassociated).
	 *
	 * @param array $attachment Attachment that has been deleted
	 * @param Zend_Db_Adapter_Abstract $db DB object
	 */
	abstract public function attachmentPostDelete(array $attachment, Zend_Db_Adapter_Abstract $db);

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		if (!$this->_contentIdKey)
		{
			throw new XenForo_Exception('Content ID key not specified.');
		}
	}

	/**
	 * Returns the maximum allowed attachments for this content type.
	 *
	 * @return integer|true If true, there is no limit
	 */
	public function getAttachmentCountLimit()
	{
		$max = XenForo_Application::get('options')->attachmentMaxPerMessage;
		return ($max <= 0 ? true : $max);
	}

	/**
	 * Determines if the specified user can upload new attachments or
	 * manage existing ones. The content data may contain different type-specific
	 * values in different situations. Eg, when posting a thread, only the node_id is
	 * known; when posting a reply, the thread_id is know; when editing a post, the
	 * post_id is known.
	 *
	 * @param array $contentData Type-specific params based on context
	 * @param array|null $viewingUser Viewing user array; null for visitor
	 *
	 * @return boolean
	 */
	final public function canUploadAndManageAttachments(array $contentData, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		if (!empty($contentData['content_id']))
		{
			$contentData[$this->_contentIdKey] = $contentData['content_id'];
		}

		return $this->_canUploadAndManageAttachments($contentData, $viewingUser);
	}

	/**
	 * Determines if the specified user can view the given attachment.
	 *
	 * @param array $attachment Attachment to view
	 * @param array|null $viewingUser Viewing user array; null for visitor
	 *
	 * @return boolean
	 */
	final public function canViewAttachment(array $attachment, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return $this->_canViewAttachment($attachment, $viewingUser);
	}

	/**
	 * Gets the primary content ID from an array of content data, if provided.
	 *
	 * @param array $contentData Type-specific content data
	 *
	 * @return integer
	 */
	public function getContentIdFromContentData(array $contentData)
	{
		return (isset($contentData[$this->_contentIdKey]) ? $contentData[$this->_contentIdKey] : 0);
	}

	/**
	 * Builds a content data array containing only the primary content ID.
	 *
	 * @param integer $contentId
	 *
	 * @return array
	 */
	public function getContentDataFromContentId($contentId)
	{
		return array($this->_contentIdKey => $contentId);
	}

	/**
	 * Standardizes the viewing user array reference.
	 *
	 * @param array|null $viewingUser Viewing user array. Will be normalized.
	 */
	public function standardizeViewingUserReference(array &$viewingUser = null)
	{
		if (!is_array($viewingUser) || !isset($viewingUser['user_id']))
		{
			$viewingUser = XenForo_Visitor::getInstance()->toArray();
		}
	}
}