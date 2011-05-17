<?php

/**
* Data writer for attachment data.
*
* @package XenForo_Attachment
*/
class XenForo_DataWriter_AttachmentData extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the path to the temporary file
	 * with the data for this attachment.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_TEMP_FILE = 'tempFile';

	const DATA_TEMP_THUMB_FILE = 'tempThumbFile';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_attachment_data' => array(
				'data_id'          => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'user_id'          => array('type' => self::TYPE_UINT, 'required' => true),
				'upload_date'      => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'filename'         => array('type' => self::TYPE_STRING, 'maxLength' => 100, 'required' => true),
				'file_size'        => array('type' => self::TYPE_UINT, 'required' => true),
				'file_hash'        => array('type' => self::TYPE_STRING, 'maxLength' => 32, 'required' => true),
				'width'            => array('type' => self::TYPE_UINT, 'default' => 0),
				'height'           => array('type' => self::TYPE_UINT, 'default' => 0),
				'thumbnail_width'  => array('type' => self::TYPE_UINT, 'default' => 0),
				'thumbnail_height' => array('type' => self::TYPE_UINT, 'default' => 0),
				'attach_count'     => array('type' => self::TYPE_UINT, 'default' => 0),
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'data_id'))
		{
			return false;
		}

		return array('xf_attachment_data' => $this->_getAttachmentModel()->getAttachmentDataById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'data_id = ' . $this->_db->quote($this->getExisting('data_id'));
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$tempFile = $this->getExtraData(self::DATA_TEMP_FILE);
		if ($this->isInsert() && !$tempFile)
		{
			throw new XenForo_Exception('Tried to insert attachment data without the data.');
		}
		if ($tempFile)
		{
			if (!is_readable($tempFile))
			{
				$this->error(new XenForo_Phrase('attachment_could_not_be_read_by_server'));
				return;
			}

			clearstatcache();
			$this->set('file_size', filesize($tempFile));
			$this->set('file_hash', md5_file($tempFile));
		}

		$tempThumbFile = $this->getExtraData(self::DATA_TEMP_THUMB_FILE);
		if (!$tempThumbFile || !file_exists($tempThumbFile) || !is_readable($tempThumbFile))
		{
			$this->set('thumbnail_width', 0);
			$this->set('thumbnail_height', 0);

			$this->setExtraData(self::DATA_TEMP_THUMB_FILE, '');
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$attachmentModel = $this->_getAttachmentModel();
		$data = $this->getMergedData();

		$tempFile = $this->getExtraData(self::DATA_TEMP_FILE);
		if ($tempFile)
		{
			if (!$this->_writeAttachmentFile($tempFile, $data))
			{
				throw new XenForo_Exception('Failed to write the attachment file.');
			}
		}

		$tempThumbFile = $this->getExtraData(self::DATA_TEMP_THUMB_FILE);
		if ($tempThumbFile)
		{
			if (!$this->_writeAttachmentFile($tempThumbFile, $data, true))
			{
				throw new XenForo_Exception('Failed to write the attachment thumbnail file.');
			}
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$data = $this->getMergedData();
		$attachmentModel = $this->_getAttachmentModel();

		$file = $attachmentModel->getAttachmentDataFilePath($data);
		if (file_exists($file) && is_writable($file))
		{
			unlink($file);
		}

		$file = $attachmentModel->getAttachmentThumbnailFilePath($data);
		if (file_exists($file) && is_writable($file))
		{
			unlink($file);
		}
	}

	/**
	 * Writes out the specified attachment file. The temporary file
	 * will be moved to the new position!
	 *
	 * @param string $tempFile Temporary (source file)
	 * @param array $data Information about this attachment data (for dest path)
	 * @param boolean $thumbnail True if writing out thumbnail.
	 *
	 * @return boolean
	 */
	protected function _writeAttachmentFile($tempFile, array $data, $thumbnail = false)
	{
		if ($tempFile && is_readable($tempFile))
		{
			$attachmentModel = $this->_getAttachmentModel();

			if ($thumbnail)
			{
				$filePath = $attachmentModel->getAttachmentThumbnailFilePath($data);
			}
			else
			{
				$filePath = $attachmentModel->getAttachmentDataFilePath($data);
			}

			$directory = dirname($filePath);

			if (XenForo_Helper_File::createDirectory($directory, true))
			{
				return $this->_moveFile($tempFile, $filePath);
			}
		}

		return false;
	}

	/**
	 * Moves the specified file. If it's an uploaded file, it will be moved with
	 * move_uploaded_file().
	 *
	 * @param string $source
	 * @param string $destination
	 *
	 * @return boolean
	 */
	protected function _moveFile($source, $destination)
	{
		if (is_uploaded_file($source))
		{
			$success = move_uploaded_file($source, $destination);
		}
		else
		{
			$success = rename($source, $destination);
		}

		if ($success)
		{
			XenForo_Helper_File::makeWritableByFtpUser($destination);
		}

		return $success;
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}
}