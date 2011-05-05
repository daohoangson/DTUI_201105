<?php

/**
 * Base (abstract) report handler for reporting specific content types.
 *
 * @package XenForo_Report
 */
abstract class XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report-related details from list of info about content being reported.
	 * Returns 3 values (as array):
	 * 	* content ID
	 *  * content user ID
	 *  * array of info about content to store with report
	 *
	 * @param array $content
	 *
	 * @return array See above.
	 */
	abstract public function getReportDetailsFromContent(array $content);

	/**
	 * Returns all the reports for this content type that are visible/manageable to the viewing user.
	 *
	 * @param array $reports Format: [report id] => report info
	 * @param array $viewingUser Viewing user array
	 *
	 * @return array List of reports that can be seen/managed, [report id] => info
	 */
	abstract public function getVisibleReportsForUser(array $reports, array $viewingUser);

	/**
	 * Gets the link to the content in the specified report.
	 *
	 * @param array $report Report info
	 * @param array $contentInfo Extra content with report
	 *
	 * @return string
	 */
	abstract public function getContentLink(array $report, array $contentInfo);

	/**
	 * Gets the title of the content in the specified report.
	 *
	 * @param array $report Report info
	 * @param array $contentInfo Extra content with report
	 *
	 * @return string|XenForo_Phrase
	 */
	abstract public function getContentTitle(array $report, array $contentInfo);

	/**
	 * Prepares the extra content for display.
	 *
	 * @param array $contentInfo
	 *
	 * @return array
	 */
	public function prepareExtraContent(array $contentInfo)
	{
		return $contentInfo;
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @param XenForo_View $view
	 * @param array $report
	 * @param array $contentInfo
	 *
	 * @return XenForo_Template_Abstract|string
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return '';
	}

	/**
	 * Prepares a report for display.
	 *
	 * @param array $report
	 *
	 * @return array Prepared report
	 */
	public function prepareReport(array $report)
	{
		$contentInfo = unserialize($report['content_info']);

		$report['extraContent'] = $this->prepareExtraContent($contentInfo);
		$report['contentLink'] = $this->getContentLink($report, $report['extraContent']);
		$report['contentTitle'] = $this->getContentTitle($report, $report['extraContent']);
		$report['viewCallback'] = array($this, 'viewCallback');

		switch ($report['report_state'])
		{
			case 'open': $report['reportState'] = new XenForo_Phrase('open_report'); break;
			case 'assigned': $report['reportState'] = new XenForo_Phrase('assigned'); break;
			case 'resolved': $report['reportState'] = new XenForo_Phrase('resolved'); break;
			case 'rejected': $report['reportState'] = new XenForo_Phrase('rejected'); break;
		}

		$report['lastModifiedInfo'] = array(
			'date' => $report['last_modified_date'],
			'user_id' => $report['last_modified_user_id'],
			'username' => $report['last_modified_username']
		);

		$report['isClosed'] = ($report['report_state'] == 'resolved' || $report['report_state'] == 'rejected');

		return $report;
	}

	/**
	 * Prepares a collection of reports.
	 *
	 * @param array $reports
	 *
	 * @return array
	 */
	public function prepareReports(array $reports)
	{
		foreach ($reports AS &$report)
		{
			$report = $this->prepareReport($report);
		}

		return $reports;
	}
}