<?php

class XenForo_ControllerAdmin_Log extends XenForo_ControllerAdmin_Abstract
{
	public function actionServerError()
	{
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		if ($id)
		{
			$entry = $this->_getLogModel()->getServerErrorLogById($id);
			if (!$entry)
			{
				return $this->responseError(new XenForo_Phrase('requested_server_error_log_entry_not_found'), 404);
			}

			$entry['requestState'] = unserialize($entry['request_state']);

			$viewParams = array(
				'entry' => $entry
			);
			return $this->responseView('XenForo_ViewAdmin_Log_ServerErrorView', 'log_server_error_view', $viewParams);
		}
		else
		{
			$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
			$perPage = 20;

			$viewParams = array(
				'entries' => $this->_getLogModel()->getServerErrorLogs(array(
					'page' => $page,
					'perPage' => $perPage
				)),

				'page' => $page,
				'perPage' => $perPage,
				'total' => $this->_getLogModel()->countServerErrors()
			);
			return $this->responseView('XenForo_ViewAdmin_Log_ServerError', 'log_server_error', $viewParams);
		}
	}

	public function actionServerErrorDelete()
	{
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$entry = $this->_getLogModel()->getServerErrorLogById($id);
		if (!$entry)
		{
			return $this->responseError(new XenForo_Phrase('requested_server_error_log_entry_not_found'), 404);
		}

		if ($this->isConfirmedPost())
		{
			$this->_getLogModel()->deleteServerErrorLog($id);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('logs/server-error')
			);
		}
		else
		{
			$viewParams = array(
				'entry' => $entry
			);
			return $this->responseView('XenForo_ViewAdmin_Log_ServerErrorDelete', 'log_server_error_delete', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_Log
	 */
	protected function _getLogModel()
	{
		return $this->getModelFromCache('XenForo_Model_Log');
	}
}