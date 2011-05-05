<?php

/**
 * Handler for reported posts.
 *
 * @package XenForo_Report
 */
class XenForo_ReportHandler_Post extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (eg, a post record).
	 *
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $postModel XenForo_Model_Post */
		$postModel = XenForo_Model::create('XenForo_Model_Post');

		$post = $postModel->getPostById($content['post_id'], array(
			'join' => XenForo_Model_Post::FETCH_THREAD | XenForo_Model_Post::FETCH_FORUM
		));
		if (!$post)
		{
			return array(false, false, false);
		}

		return array(
			$content['post_id'],
			$content['user_id'],
			array(
				'thread_id' => $post['thread_id'],
				'thread_title' => $post['title'],

				'node_id' => $post['node_id'],
				'node_title' => $post['node_title'],

				'username' => $post['username'],

				'message' => $post['message']
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
		$reportsByForum = array();
		foreach ($reports AS $reportId => $report)
		{
			$info = unserialize($report['content_info']);
			$reportsByForum[$info['node_id']][] = $reportId;
		}

		/* @var $forumModel XenForo_Model_Forum */
		$forumModel = XenForo_Model::create('XenForo_Model_Forum');
		$forums = $forumModel->getForumsByIds(array_keys($reportsByForum), array(
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		$forums = $forumModel->unserializePermissionsInList($forums, 'node_permission_cache');

		foreach ($reportsByForum AS $forumId => $forumReports)
		{
			$remove = false;
			if (!isset($forums[$forumId]))
			{
				$remove = true;
			}
			else
			{
				$forum = $forums[$forumId];
				if (!XenForo_Permission::hasContentPermission($forum['permissions'], 'editAnyPost')
					&& !XenForo_Permission::hasContentPermission($forum['permissions'], 'deleteAnyPost')
				)
				{
					$remove = true;
				}
			}

			if ($remove)
			{
				foreach ($forumReports AS $reportId)
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
		return new XenForo_Phrase('post_in_thread_x', array('title' => $contentInfo['thread_title']));
	}

	/**
	 * Gets the link to the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('posts', array('post_id' => $report['content_id']));
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @see XenForo_ReportHandler_Abstract::viewCallback()
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		$parser = new XenForo_BbCode_Parser(
			XenForo_BbCode_Formatter_Base::create('Base', array('view' => $view))
		);

		return $view->createTemplateObject('report_post_content', array(
			'report' => $report,
			'content' => $contentInfo,
			'bbCodeParser' => $parser
		));
	}

	/**
	 * Prepares the extra content for display.
	 *
	 * @see XenForo_ReportHandler_Abstract::prepareExtraContent()
	 */
	public function prepareExtraContent(array $contentInfo)
	{
		$contentInfo['thread_title'] = XenForo_Helper_String::censorString($contentInfo['thread_title']);

		return $contentInfo;
	}
}