<?php

class XenForo_ControllerAdmin_SpamCleaner extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('user');
	}

	public function actionIndex()
	{
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$logsPerPage = 20;

		$fetchOptions = array(
			'perPage' => $logsPerPage,
			'page' => $page,
			'join' => XenForo_Model_SpamCleaner::FETCH_USER,
		);

		$spamCleanerModel = $this->_getSpamCleanerModel();

		$viewParams = array(
			'logs' => $spamCleanerModel->getLogs($fetchOptions),
			'totalLogs' => $spamCleanerModel->countLogs(),

			'linkAction' => 'spam-cleaner',

			'page' => $page,
			'logsPerPage' => $logsPerPage
		);

		return $this->responseView('XenForo_ViewAdmin_SpamCleaner_LogList', 'spam_cleaner_log_list', $viewParams);
	}

	public function actionRestore()
	{
		if (!$logId = $this->_input->filterSingle('spam_cleaner_log_id', XenForo_Input::UINT))
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$spamCleanerModel = $this->_getSpamCleanerModel();

		if (!$log = $spamCleanerModel->getLogById($logId, array('join' => XenForo_Model_SpamCleaner::FETCH_USER)))
		{
			return $this->responseError(new XenForo_Phrase('requested_log_entry_not_found'), 404);
		}

		if ($this->isConfirmedPost())
		{
			if (!$spamCleanerModel->restore($log, $errorKey))
			{
				return $this->responseError(new XenForo_Phrase($errorKey));
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/edit', $log)
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array(
				'log' => $log,
			);

			return $this->responseView(
				'XenForo_ViewAdmin_SpamCleaner_RestoreConfirm', 'spam_cleaner_restore_confirm', $viewParams
			);
		}
	}

	/**
	 * @return XenForo_Model_SpamCleaner
	 */
	protected function _getSpamCleanerModel()
	{
		return $this->getModelFromCache('XenForo_Model_SpamCleaner');
	}
}