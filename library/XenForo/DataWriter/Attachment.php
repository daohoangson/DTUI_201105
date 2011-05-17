<?php

/**
* Data writer for attachments.
*
* @package XenForo_Attachment
*/
class XenForo_DataWriter_Attachment extends XenForo_DataWriter
{
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_attachment' => array(
				'attachment_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'data_id'       => array('type' => self::TYPE_UINT, 'required' => true),
				'content_type'  => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
				'content_id'    => array('type' => self::TYPE_UINT, 'default' => 0),
				'attach_date'   => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'temp_hash'     => array('type' => self::TYPE_STRING, 'maxLength' => 32, 'default' => ''),
				'unassociated'  => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'view_count'    => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'attachment_id'))
		{
			return false;
		}

		return array('xf_attachment' => $this->_getAttachmentModel()->getAttachmentById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'attachment_id = ' . $this->_db->quote($this->getExisting('attachment_id'));
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if (!$this->get('content_id'))
		{
			if (!$this->get('temp_hash'))
			{
				throw new XenForo_Exception('Temp hash must be specified if no content is specified.');
			}

			$this->set('unassociated', 1);
		}
		else
		{
			$this->set('temp_hash', '');
			$this->set('unassociated', 0);
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$this->_db->query('
			UPDATE xf_attachment_data
			SET attach_count = attach_count + 1
			WHERE data_id = ?
		', $this->get('data_id'));
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$data = $this->getMergedData();

		$this->_db->query('
			UPDATE xf_attachment_data
			SET attach_count = IF(attach_count > 0, attach_count - 1, 0)
			WHERE data_id = ?
		', $data['data_id']);

		if ($data['content_id'])
		{
			$attachmentHandler = $this->_getAttachmentModel()->getAttachmentHandler($data['content_type']);
			if ($attachmentHandler)
			{
				$attachmentHandler->attachmentPostDelete($data, $this->_db);
			}
		}
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}
}