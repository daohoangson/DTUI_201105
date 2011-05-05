<?php

class XenForo_ControllerAdmin_Application extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$boardTotals = $this->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
		if (!$boardTotals)
		{
			$boardTotals = $this->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
		}

		$spamCleanerModel = $this->getModelFromCache('XenForo_Model_SpamCleaner');

		$visitor = XenForo_Visitor::getInstance();

		$viewParams = array(
			'canManageNodes' => $visitor->hasAdminPermission('node'),
			'canManageSpamCleaner' => $visitor->hasAdminPermission('user'),

			'boardTotals' => $boardTotals,
			'spamCleanerExecutions' => $spamCleanerModel->countLogs(),
			'spamCleanerRecents' => $spamCleanerModel->getLogs(array(
				'page' => 1, 'perPage' => 5, 'join' => XenForo_Model_SpamCleaner::FETCH_USER
			)),
		);

		return $this->responseView('XenForo_ViewAdmin_Application_Splash', 'application_splash', $viewParams);
	}
}