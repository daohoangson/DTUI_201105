<?php

/**
* Data writer for report comments
*
* @package XenForo_Report
*/
class XenForo_DataWriter_ReportComment extends XenForo_DataWriter
{
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_report_comment' => array(
				'report_comment_id' => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'report_id'         => array('type' => self::TYPE_UINT,   'required' => true),
				'comment_date'      => array('type' => self::TYPE_UINT,   'default' => XenForo_Application::$time),
				'user_id'           => array('type' => self::TYPE_UINT,   'required' => true),
				'username'          => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_name'
				),
				'message'           => array('type' => self::TYPE_STRING, 'default' => ''),
				'state_change'      => array('type' => self::TYPE_STRING,  'default' => '',
						'allowedValues' => array('', 'open', 'assigned', 'resolved', 'rejected')
				),
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
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		// TODO: model function doesn't exist
		return array('xf_report_comment' => $this->_getReportModel()->getReportCommentById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'report_comment_id = ' . $this->_db->quote($this->getExisting('report_comment_id'));
	}

	protected function _preSave()
	{
		if (!$this->get('state_change') && !$this->get('message'))
		{
			$this->error(new XenForo_Phrase('please_enter_valid_message'), 'message');
		}
	}

	protected function _postSave()
	{
		$comment = $this->getMergedData();

		if ($this->isInsert())
		{
			$reportDw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
			$reportDw->setExistingData($this->get('report_id'));
			if ($comment['comment_date'] >= $reportDw->get('last_modified_date'))
			{
				$reportDw->set('last_modified_date', $comment['comment_date']);
				$reportDw->set('last_modified_user_id', $comment['user_id']);
				$reportDw->set('last_modified_username', $comment['username']);
			}
			if ($comment['message'])
			{
				$reportDw->set('comment_count', $reportDw->get('comment_count') + 1);
			}
			$reportDw->save();
		}
	}

	protected function _preDelete()
	{
		throw new XenForo_Exception('Delete not supported yet.');
	}

	/**
	 * @return XenForo_Model_Report
	 */
	protected function _getReportModel()
	{
		return $this->getModelFromCache('XenForo_Model_Report');
	}
}