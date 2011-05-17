<?php

/**
 * Handler for reported profile posts.
 *
 * @package XenForo_Report
 */
class XenForo_ReportHandler_ProfilePost extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (eg, a post record).
	 *
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $profilePostModel XenForo_Model_ProfilePost */
		$profilePostModel = XenForo_Model::create('XenForo_Model_ProfilePost');

		$profilePost = $profilePostModel->getProfilePostById($content['profile_post_id'], array(
			'join' => XenForo_Model_ProfilePost::FETCH_USER_RECEIVER
		));
		if (!$profilePost)
		{
			return array(false, false, false);
		}

		return array(
			$content['profile_post_id'],
			$content['user_id'],
			array(
				'profile_user_id' => $profilePost['profile_user_id'],
				'profile_username' => $profilePost['profile_username'],

				'message' => $profilePost['message']
			)
		);
	}

	/**
	 * Gets the visible reports of this content type for the viewing user.
	 *
	 * @see XenForo_ReportHandler_Abstract:getVisibleReportsForUser()
	 */
	public function getVisibleReportsForUser(array $reports, array $viewingUser)
	{
		$reportsByUser = array();
		foreach ($reports AS $reportId => $report)
		{
			$info = unserialize($report['content_info']);
			$reportsByUser[$info['profile_user_id']][] = $reportId;
		}

		$users = XenForo_Model::create('XenForo_Model_User')->getUsersByIds(array_keys($reportsByUser), array(
			'join' => XenForo_Model_User::FETCH_USER_PRIVACY,
			'followingUserId' => $viewingUser['user_id']
		));

		$userProfileModel = XenForo_Model::create('XenForo_Model_UserProfile');

		foreach ($reportsByUser AS $userId => $userReports)
		{
			$remove = false;

			if (!isset($users[$userId]))
			{
				$remove = true;
			}
			else if (!$userProfileModel->canViewFullUserProfile($users[$userId], $null, $viewingUser))
			{
				$remove = true;
			}
			else if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'editAny')
				&& !XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'deleteAny')
			)
			{
				$remove = true;
			}

			if ($remove)
			{
				foreach ($userReports AS $reportId)
				{
					unset($reports[$reportId]);
				}
			}
		}

		return $reports;
	}

	/**
	 * Gets the title of the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract:getContentTitle()
	 */
	public function getContentTitle(array $report, array $contentInfo)
	{
		return new XenForo_Phrase('profile_post_for_x', array('username' => $contentInfo['profile_username']));
	}

	/**
	 * Gets the link to the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('profile-posts', array('profile_post_id' => $report['content_id']));
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @see XenForo_ReportHandler_Abstract::viewCallback()
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return $view->createTemplateObject('report_profile_post_content', array(
			'report' => $report,
			'content' => $contentInfo
		));
	}
}