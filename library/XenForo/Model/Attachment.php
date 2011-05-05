<?php

/**
 * Model for attachments.
 *
 * @package XenForo_Attachment
 */
class XenForo_Model_Attachment extends XenForo_Model
{
	public static $dataColumns =
		'data.filename, data.file_size, data.file_hash, data.width, data.height, data.thumbnail_width, data.thumbnail_height';

	/**
	 * Get attachments (and limited data info) by the given content IDs.
	 *
	 * @param string $contentType
	 * @param array $contentIds
	 *
	 * @return array Format: [attachment id] => info
	 */
	public function getAttachmentsByContentIds($contentType, array $contentIds)
	{
		return $this->fetchAllKeyed('
			SELECT attachment.*,
				' . self::$dataColumns . '
			FROM xf_attachment AS attachment
			INNER JOIN xf_attachment_data AS data ON
				(data.data_id = attachment.data_id)
			WHERE attachment.content_type = ?
				AND attachment.content_id IN (' . $this->_getDb()->quote($contentIds) . ')
			ORDER BY attachment.content_id, attachment.attach_date
		', 'attachment_id', $contentType);
	}

	/**
	 * Gets the attachments (along with limited data info) that belong to the given content ID.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return array Format: [attachment id] => info
	 */
	public function getAttachmentsByContentId($contentType, $contentId)
	{
		return $this->getAttachmentsByContentIds($contentType, array($contentId));
	}

	/**
	 * Gets all attachments (with limited data info) that have the specified temp hash.
	 *
	 * @param string $tempHash
	 *
	 * @return array Format: [attachment id] => info
	 */
	public function getAttachmentsByTempHash($tempHash)
	{
		if (strval($tempHash) === '')
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT attachment.*,
				' . self::$dataColumns . '
			FROM xf_attachment AS attachment
			INNER JOIN xf_attachment_data AS data ON
				(data.data_id = attachment.data_id)
			WHERE attachment.temp_hash = ?
			ORDER BY attachment.attach_date
		', 'attachment_id', $tempHash);
	}

	/**
	 * Gets the specified attachment by it's ID. Includes some data info.
	 *
	 * @param integer $attachmentId
	 *
	 * @return array|false
	 */
	public function getAttachmentById($attachmentId)
	{
		return $this->_getDb()->fetchRow('
			SELECT attachment.*,
				' . self::$dataColumns . '
			FROM xf_attachment AS attachment
			INNER JOIN xf_attachment_data AS data ON
				(data.data_id = attachment.data_id)
			WHERE attachment.attachment_id = ?
		', $attachmentId);
	}

	/**
	 * Gets the specified attachment data by ID.
	 *
	 * @param integer $dataId
	 *
	 * @return array|false
	 */
	public function getAttachmentDataById($dataId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_attachment_data
			WHERE data_id = ?
		', $dataId);
	}

	/**
	 * Gets the attachment handler object for a specified content type.
	 *
	 * @param string $contentType
	 *
	 * @return XenForo_AttachmentHandler_Abstract|null
	 */
	public function getAttachmentHandler($contentType)
	{
		if (!$contentType)
		{
			return null;
		}

		$cacheKey = "attachmentHandler_$contentType";
		$object = $this->_getLocalCacheData($cacheKey);
		if ($object === false)
		{
			$class = $this->_getDb()->fetchOne('
				SELECT field_value
				FROM xf_content_type_field
				WHERE content_type = ?
					AND field_name = \'attachment_handler_class\'
			', $contentType);

			$object = ($class ? new $class() : null);
			$this->setLocalCacheData($cacheKey, $object);
		}

		return $object;
	}

	/**
	 * Gets the full path to this attachment's data.
	 *
	 * @param array $data Attachment data info
	 *
	 * @return string
	 */
	public function getAttachmentDataFilePath(array $data)
	{
		return XenForo_Helper_File::getInternalDataPath()
			. '/attachments/' . floor($data['data_id'] / 1000)
			. "/$data[data_id]-$data[file_hash].data";
	}

	/**
	 * Gets the full path to this attachment's thumbnail.
	 *
	 * @param array $data Attachment data info
	 *
	 * @return string
	 */
	public function getAttachmentThumbnailFilePath(array $data)
	{
		return XenForo_Helper_File::getExternalDataPath()
			. '/attachments/' . floor($data['data_id'] / 1000)
			. "/$data[data_id]-$data[file_hash].jpg";
	}

	/**
	 * Gets the URL to this attachment's thumbnail. May be absolute or
	 * relative to the application root directory.
	 *
	 * @param array $data Attachment data info
	 *
	 * @return string
	 */
	public function getAttachmentThumbnailUrl(array $data)
	{
		return XenForo_Application::$externalDataPath . '/attachments/' . floor($data['data_id'] / 1000)
			. "/$data[data_id]-$data[file_hash].jpg";
	}

	/**
	 * Prepares an attachment for viewing (mainly as a "thumbnail" or similar view).
	 *
	 * @param array $attachment
	 *
	 * @return array
	 */
	public function prepareAttachment(array $attachment)
	{
		if ($attachment['thumbnail_width'])
		{
			$attachment['thumbnailUrl'] = $this->getAttachmentThumbnailUrl($attachment);
		}
		else
		{
			$attachment['thumbnailUrl'] = '';
		}

		$attachment['extension'] = strtolower(substr(strrchr($attachment['filename'], '.'), 1));

		return $attachment;
	}

	/**
	 * Prepares a list of attachments.
	 *
	 * @param array $attachments
	 *
	 * @return array
	 */
	public function prepareAttachments(array $attachments)
	{
		foreach ($attachments AS &$attachment)
		{
			$attachment = $this->prepareAttachment($attachment);
		}

		return $attachments;
	}

	/**
	 * Inserts uploaded attachment data.
	 *
	 * @param XenForo_Upload $file Uploaded attachment info. Assumed to be valid
	 * @param integer $userId User ID uploading
	 * @param array $extra Extra params to set
	 *
	 * @return integer Attachment data ID
	 */
	public function insertUploadedAttachmentData(XenForo_Upload $file, $userId, array $extra = array())
	{
		if ($file->isImage())
		{
			$dimensions = array(
				'width' => $file->getImageInfoField('width'),
				'height' => $file->getImageInfoField('height'),
			);

			$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			if ($tempThumbFile)
			{
				$image = XenForo_Image_Abstract::createFromFile($file->getTempFile(), $file->getImageInfoField('type'));
				if ($image)
				{
					if ($image->thumbnail(XenForo_Application::get('options')->attachmentThumbnailDimensions))
					{
						$image->output($file->getImageInfoField('type'), $tempThumbFile);
					}
					else
					{
						copy($file->getTempFile(), $tempThumbFile); // no resize necessary, use the original
					}

					$dimensions['thumbnail_width'] = $image->getWidth();
					$dimensions['thumbnail_height'] = $image->getHeight();

					unset($image);
				}
			}
		}
		else
		{
			$tempThumbFile = '';
			$dimensions = array();
		}

		try
		{
			$dataDw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dataDw->bulkSet($extra);
			$dataDw->set('user_id', $userId);
			$dataDw->set('filename', $file->getFileName());
			$dataDw->bulkSet($dimensions);
			$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $file->getTempFile());
			if ($tempThumbFile)
			{
				$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_THUMB_FILE, $tempThumbFile);
			}
			$dataDw->save();
		}
		catch (Exception $e)
		{
			if ($tempThumbFile)
			{
				@unlink($tempThumbFile);
			}

			throw $e;
		}

		if ($tempThumbFile)
		{
			@unlink($tempThumbFile);
		}

		// TODO: add support for "on rollback" behavior

		return $dataDw->get('data_id');
	}

	public function deleteAttachmentData($dataId)
	{
		$dataDw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData', XenForo_DataWriter::ERROR_SILENT);
		$dataDw->setExistingData($dataId);
		$dataDw->delete();
	}

	/**
	 * Inserts a temporary attachment for the specified attachment data.
	 *
	 * @param integer $dataId
	 * @param string $tempHash
	 *
	 * @return integer $attachmentId
	 */
	public function insertTemporaryAttachment($dataId, $tempHash)
	{
		$attachmentDw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
		$attachmentDw->set('data_id', $dataId);
		$attachmentDw->set('temp_hash', $tempHash);
		$attachmentDw->save();

		return $attachmentDw->get('attachment_id');
	}

	/**
	 * Deletes attachments from the specified content IDs.
	 *
	 * @param string $contentType
	 * @param array $contentIds
	 */
	public function deleteAttachmentsFromContentIds($contentType, array $contentIds)
	{
		if (!$contentIds)
		{
			return;
		}

		$db = $this->_getDb();
		$attachments = $db->fetchPairs('
			SELECT attachment_id, data_id
			FROM xf_attachment
			WHERE content_type = ?
				AND content_id IN (' . $db->quote($contentIds) . ')
		', $contentType);

		$this->_deleteAttachmentsFromPairs($attachments);
	}

	/**
	 * Deletes unassociated attachments up to a certain date.
	 *
	 * @param integer $maxDate Maximum timestamp to delete up to
	 */
	public function deleteUnassociatedAttachments($maxDate)
	{
		$attachments = $this->_getDb()->fetchPairs('
			SELECT attachment_id, data_id
			FROM xf_attachment
			WHERE unassociated = 1
				AND attach_date <= ?
		', $maxDate);

		$this->_deleteAttachmentsFromPairs($attachments);
	}

	/**
	 * Helper to delete attachments from a set of pairs [attachment id] => data id.
	 *
	 * @param array $attachments [attachment id] => data id
	 */
	protected function _deleteAttachmentsFromPairs(array $attachments)
	{
		if (!$attachments)
		{
			return;
		}

		$dataCount = array();
		foreach ($attachments AS $dataId)
		{
			if (isset($dataCount[$dataId]))
			{
				$dataCount[$dataId]++;
			}
			else
			{
				$dataCount[$dataId] = 1;
			}
		}

		$db = $this->_getDb();
		$db->delete('xf_attachment',
			'attachment_id IN (' . $db->quote(array_keys($attachments)) . ')'
		);
		foreach ($dataCount AS $dataId => $delta)
		{
			$db->query('
				UPDATE xf_attachment_data
				SET attach_count = IF(attach_count > ?, attach_count - ?, 0)
				WHERE data_id = ?
			', array($delta, $delta, $dataId));
		}
	}

	public function deleteUnusedAttachmentData()
	{
		$attachments = $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_attachment_data
			WHERE attach_count = 0
		');
		foreach ($attachments AS $attachment)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dw->setExistingData($attachment, true);
			$dw->delete();
		}
	}

	/**
	 * Determines if the specified attachment can be viewed. Unassociated attachments
	 * can be viewed if the temp hash is known.
	 *
	 * @param array $attachment
	 * @param string $tempHash
	 * @param array|null $viewingUser Viewing user ref; if null, uses visitor
	 *
	 * @return boolean
	 */
	public function canViewAttachment(array $attachment, $tempHash = '', array $viewingUser = null)
	{
		if (!empty($attachment['temp_hash']) && empty($attachment['content_id']))
		{
			// can view temporary attachments as long as the hash is known
			return ($tempHash === $attachment['temp_hash']);
		}
		else
		{
			$attachmentHandler = $this->getAttachmentHandler($attachment['content_type']);
			return ($attachmentHandler && $attachmentHandler->canViewAttachment($attachment, $viewingUser));
		}
	}

	/**
	 * Determines if the specified attachment can be deleted. Unassociated attachments
	 * can be deleted if the temp hash is known.
	 *
	 * @param array $attachment
	 * @param string $tempHash
	 * @param array|null $viewingUser Viewing user ref; if null, uses visitor
	 *
	 * @return boolean
	 */
	public function canDeleteAttachment(array $attachment, $tempHash = '', array $viewingUser = null)
	{
		if (!empty($attachment['temp_hash']) && empty($attachment['content_id']))
		{
			// can view temporary attachments as long as the hash is known
			return ($tempHash === $attachment['temp_hash']);
		}
		else
		{
			$attachmentHandler = $this->getAttachmentHandler($attachment['content_type']);
			return ($attachmentHandler && $attachmentHandler->canUploadAndManageAttachments($attachment, $viewingUser));
		}
	}

	/**
	 * Logs the viewing of an attachment.
	 *
	 * @param integer $attachmentId
	 */
	public function logAttachmentView($attachmentId)
	{
		$this->_getDb()->query('
			INSERT DELAYED INTO xf_attachment_view
				(attachment_id)
			VALUES
				(?)
		', $attachmentId);
	}

	/**
	 * Updates attachment views in bulk.
	 */
	public function updateAttachmentViews()
	{
		$db = $this->_getDb();

		$updates = $db->fetchPairs('
			SELECT attachment_id, COUNT(*)
			FROM xf_attachment_view
			GROUP BY attachment_id
		');

		XenForo_Db::beginTransaction($db);

		$db->query('TRUNCATE TABLE xf_attachment_view');

		foreach ($updates AS $threadId => $views)
		{
			$db->query('
				UPDATE xf_attachment SET
					view_count = view_count + ?
				WHERE attachment_id = ?
			', array($views, $threadId));
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Fetches attachment constraints
	 *
	 * @return array
	 */
	public function getAttachmentConstraints()
	{
		$options = XenForo_Application::get('options');

		return array(
			'extensions' => preg_split('/\s+/', trim($options->attachmentExtensions)),
			'size' => $options->attachmentMaxFileSize * 1024,
			'width' => $options->attachmentMaxDimensions['width'],
			'height' => $options->attachmentMaxDimensions['height'],
			'count' => $options->attachmentMaxPerMessage
		);
	}
}