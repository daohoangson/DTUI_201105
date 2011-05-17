<?php

/**
 * Model for reporting content.
 *
 * @package XenForo_Report
 */
class XenForo_Model_Report extends XenForo_Model
{
	/**
	 * Gets the specified report.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getReportById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT report.*,
				user.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			WHERE report.report_id = ?
		', $id);
	}

	/**
	 * Gets the report for a specified content if it exists.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return array|false
	 */
	public function getReportByContent($contentType, $contentId)
	{
		return $this->_getDb()->fetchRow('
			SELECT report.*,
				user.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			WHERE report.content_type = ?
				AND report.content_id = ?
		', array($contentType, $contentId));
	}

	/**
	 * Gets all the active (open, assigned) reports.
	 *
	 * @return array [report id] => info
	 */
	public function getActiveReports()
	{
		return $this->fetchAllKeyed('
			SELECT report.*,
				user.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			WHERE report.report_state IN (\'open\', \'assigned\')
			ORDER BY report.last_modified_date DESC
		', 'report_id');
	}

	/**
	 * Gets closed (resolved, rejected) in the specified time frame.
	 *
	 * @param integer $minimumTimestamp Minimum timestamp to display reports from
	 * @param integer|null $maximumTimestamp Maximum timestamp to display reports to; null means until now
	 *
	 * @return array [report id] => info
	 */
	public function getClosedReportsInTimeFrame($minimumTimestamp, $maximumTimestamp = null)
	{
		if ($maximumTimestamp === null)
		{
			$maximumTimestamp = XenForo_Application::$time;
		}

		return $this->fetchAllKeyed('
			SELECT report.*,
				user.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			WHERE report.report_state IN (\'resolved\', \'rejected\')
				AND report.last_modified_date > ?
				AND report.last_modified_date <= ?
			ORDER BY report.last_modified_date DESC
		', 'report_id', array($minimumTimestamp, $maximumTimestamp));
	}

	/**
	 * Filters out the reports a user cannot see from a list. Automatically prepares reports for display.
	 *
	 * @param array $reports List of reports; keyed by report ID
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return array Visible reports; [report id] => info (prepared)
	 */
	public function getVisibleReportsForUser(array $reports, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		if (!$viewingUser['user_id'])
		{
			return array();
		}

		$reportsGrouped = array();
		foreach ($reports AS $reportId => $report)
		{
			$reportsGrouped[$report['content_type']][$reportId] = $report;
		}

		if (!$reportsGrouped)
		{
			return array();
		}

		$reportHandlers = $this->getReportHandlers();

		$userReports = array();
		foreach ($reportsGrouped AS $contentType => $typeReports)
		{
			if (!empty($reportHandlers[$contentType]))
			{
				$handler = $reportHandlers[$contentType];

				$typeReports = $handler->getVisibleReportsForUser($typeReports, $viewingUser);
				$userReports += $handler->prepareReports($typeReports);
			}
		}

		$outputReports = array();
		foreach ($reports AS $reportId => $null)
		{
			if (isset($userReports[$reportId]))
			{
				$outputReports[$reportId] = $userReports[$reportId];
			}
		}

		return $outputReports;
	}

	/**
	 * Gets counters for all active reports for a specified user.
	 *
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return array Keys: total, assigned
	 */
	public function getActiveReportsCountsForUser(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$visibleReports = $this->getVisibleReportsForUser($this->getActiveReports(), $viewingUser);

		return $this->getSessionCountsForReports($visibleReports, $viewingUser['user_id']);
	}

	/**
	 * Gets the counts for the session data, using the given reports. Reports are assumed
	 * to be visible.
	 *
	 * @param array $reports
	 *
	 * @param integer $userId
	 *
	 * @return array Keys: total, assigned
	 */
	public function getSessionCountsForReports(array $reports, $userId)
	{
		$counts = array(
			'total' => count($reports),
			'assigned' => 0
		);
		foreach ($reports AS $report)
		{
			if ($report['assigned_user_id'] == $userId)
			{
				$counts['assigned']++;
			}
		}

		return $counts;
	}

	/**
	 * Gets the specified report if it is visable to the viewing user.
	 *
	 * @param integer $reportId
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return array|false
	 */
	public function getVisibleReportById($reportId, array $viewingUser = null)
	{
		$report = $this->getReportById($reportId);
		$reports = $this->getVisibleReportsForUser(array($report['report_id'] => $report), $viewingUser);
		return reset($reports);
	}

	/**
	 * Prepares the specified report using the necessary handler.
	 *
	 * @param array $report
	 *
	 * @return array
	 */
	public function prepareReport(array $report)
	{
		$handler = $this->getReportHandler($report['content_type']);
		if ($handler)
		{
			$report = $handler->prepareReport($report);
		}

		return $report;
	}

	/**
	 * Reports a piece of content.
	 *
	 * @param string $contentType
	 * @param array $content Information about content
	 * @param string $message
	 * @param array|null $viewingUser User reporting; null means visitor
	 *
	 * @return false|integer Report ID or false if no report was made
	 */
	public function reportContent($contentType, array $content, $message, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		$handler = $this->getReportHandler($contentType);
		if (!$handler)
		{
			return false;
		}

		list($contentId, $contentUserId, $contentInfo) = $handler->getReportDetailsFromContent($content);
		if (!$contentId)
		{
			return false;
		}

		XenForo_Db::beginTransaction($this->_getDb());

		$newReportState = '';

		$report = $this->getReportByContent($contentType, $contentId);
		if ($report)
		{
			$reportId = $report['report_id'];

			if ($report['report_state'] == 'resolved' || $report['report_state'] == 'rejected')
			{
				// re-open an existing report
				$reportDw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
				$reportDw->setExistingData($report, true);
				$reportDw->set('report_state', 'open');
				$reportDw->save();

				$newReportState = 'open';
			}
		}
		else
		{
			$reportDw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
			$reportDw->bulkSet(array(
				'content_type' => $contentType,
				'content_id' => $contentId,
				'content_user_id' => $contentUserId,
				'content_info' => $contentInfo
			));
			$reportDw->save();

			$reportId = $reportDw->get('report_id');
		}

		$reasonDw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment');
		$reasonDw->bulkSet(array(
			'report_id' => $reportId,
			'user_id' => $viewingUser['user_id'],
			'username' => $viewingUser['username'],
			'message' => $message,
			'state_change' => $newReportState
		));
		$reasonDw->save();

		XenForo_Db::commit($this->_getDb());

		return $reportId;
	}

	/**
	 * Determines if the specified user can update the given report.
	 *
	 * @param array $report
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUpdateReport(array $report, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($report['report_state'] == 'resolved' || $report['report_state'] == 'rejected')
		{
			return false;
		}

		return ($report['assigned_user_id'] == $viewingUser['user_id']);
	}

	/**
	 * Determines if the specified user can be assigned to the given report.
	 * Note that this does allow a user to steal an assignement.
	 *
	 * @param array $report
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canAssignReport(array $report, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($report['report_state'] == 'resolved' || $report['report_state'] == 'rejected')
		{
			return false;
		}

		return ($report['assigned_user_id'] != $viewingUser['user_id']);
	}

	/**
	 * Gets all the comments for a report.
	 *
	 * @param integer $reportId
	 *
	 * @return array Format: [reason id] => info
	 */
	public function getReportComments($reportId)
	{
		return $this->fetchAllKeyed('
			SELECT report_comment.*,
				user.*
			FROM xf_report_comment AS report_comment
			INNER JOIN xf_user AS user ON (user.user_id = report_comment.user_id)
			WHERE report_comment.report_id = ?
			ORDER BY report_comment.comment_date DESC
		', 'report_comment_id', $reportId);
	}

	public function prepareReportComments(array $comments)
	{
		return array_map(array($this, 'prepareReportComment'), $comments);
	}

	public function prepareReportComment(array $comment)
	{
		switch ($comment['state_change'])
		{
			case 'open': $comment['stateChange'] = new XenForo_Phrase('open_report'); break;
			case 'assigned': $comment['stateChange'] = new XenForo_Phrase('assigned'); break;
			case 'resolved': $comment['stateChange'] = new XenForo_Phrase('resolved'); break;
			case 'rejected': $comment['stateChange'] = new XenForo_Phrase('rejected'); break;
			default: $comment['stateChange'] = '';
		}

		return $comment;
	}

	/**
	 * Gets the report handler object for the specified content.
	 *
	 * @param string $contentType
	 *
	 * @return XenForo_ReportHandler_Abstract|false
	 */
	public function getReportHandler($contentType)
	{
		$handlerClass = $this->getContentTypeField($contentType, 'report_handler_class');
		if (!$handlerClass)
		{
			return false;
		}

		return new $handlerClass();
	}

	/**
	 * Gets the timestamp of the latest report modification.
	 *
	 * @return integer
	 */
	public function getLatestReportModificationDate()
	{
		$date = $this->_getDb()->fetchOne('
			SELECT MAX(last_modified_date)
			FROM xf_report
		');
		return ($date ? $date : 0);
	}

	/**
	 * Rebuilds the report count cache.
	 *
	 * @param integer|null $activeCount Number of active reports; null to calculate automatically
	 *
	 * @return array
	 */
	public function rebuildReportCountCache($activeCount = null)
	{
		if ($activeCount === null)
		{
			$activeCount = count($this->getActiveReports());
		}

		$cache = array(
			'activeCount' => $activeCount,
			'lastModifiedDate' => $this->getLatestReportModificationDate()
		);

		$this->_getDataRegistryModel()->set('reportCounts', $cache);

		return $cache;
	}

	/**
	 * Gets all report handler classes.
	 *
	 * @return array [content type] => XenForo_ReportHandler_Abstract
	 */
	public function getReportHandlers()
	{
		$classes = $this->getContentTypesWithField('report_handler_class');
		$handlers = array();
		foreach ($classes AS $contentType => $class)
		{
			$handlers[$contentType] = new $class();
		}

		return $handlers;
	}
}