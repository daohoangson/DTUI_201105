<?php

class XenForo_ControllerAdmin_Import extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('import');
	}

	public function actionIndex()
	{
		$importModel = $this->_getImportModel();
		$session = new XenForo_ImportSession();

		if ($this->_request->isPost() && $this->_input->filterSingle('reset', XenForo_Input::UINT))
		{
			$session->delete();
		}

		if ($session->isRunning())
		{
			$viewParams = array(
				'name' => $importModel->getImporterName($session->getImporterKey())
			);
			return $this->responseView('XenForo_ViewAdmin_Import_Restart', 'import_restart', $viewParams);
		}
		else
		{
			$viewParams = array(
				'importers' => $importModel->getImporterList(),
				'hasImportedData' => $importModel->hasImportedData()
			);
			return $this->responseView('XenForo_ViewAdmin_Import_Choose', 'import_choose', $viewParams);
		}
	}

	public function actionConfig()
	{
		$importerKey = $this->_input->filterSingle('importer', XenForo_Input::STRING);
		if (!$importerKey)
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$importer = $this->_getImportModel()->getImporter($importerKey);

		if ($this->_request->isPost())
		{
			$config = $this->_input->filterSingle('config', XenForo_Input::ARRAY_SIMPLE);
		}
		else
		{
			$config = array();
		}

		$response = $importer->configure($this, $config);
		if ($response === true)
		{
			$session = new XenForo_ImportSession(true);
			$session->start($importerKey, $config);
			$session->save();

			$this->_getImportModel()->resetImportLog();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('import/import')
			);
		}
		else
		{
			$input = $this->_input->filter(array(
				'archive' => XenForo_Input::UINT,
				'table' => XenForo_Input::STRING
			));

			if ($input['archive'] && $input['table'])
			{
				if (!$this->_getImportModel()->archiveImportLog($input['table'], $error))
				{
					return $this->responseError($error);
				}
			}

			$response->params['name'] = $importer->getName();
			$response->params['importer'] = $importerKey;

			return $response;
		}
	}

	public function actionImport()
	{
		$importModel = $this->_getImportModel();

		$session = new XenForo_ImportSession();
		if (!$session->getImporterKey())
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$stepInfo = $session->getStepInfo();

		$importer = $importModel->getImporter($session->getImporterKey());

		$showList = $this->_input->filterSingle('list', XenForo_Input::UINT);
		if (!$stepInfo['step'] || $showList)
		{
			$runStep = false;
		}
		else
		{
			$runStep = ($stepInfo['stepStart'] || $this->_request->isPost());
		}

		if ($runStep)
		{
			$response = $this->_runStep($importer, $session, $stepInfo['step'], $stepInfo['stepStart'], $stepInfo['stepOptions']);
			return $response;
		}
		else
		{
			$steps = $importModel->addImportStateToSteps($importer->getSteps(), $session->getRunSteps());
			$viewParams = array(
				'steps' => $steps,
				'importerName' => $importer->getName()
			);

			return $this->responseView('XenForo_ViewAdmin_Import_Steps', 'import_steps', $viewParams);
		}
	}

	public function actionStartStep()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'step' => XenForo_Input::STRING,
			'options' => XenForo_Input::ARRAY_SIMPLE
		));
		if (!$input['step'])
		{
			foreach ($_POST AS $key => $value)
			{
				if (strpos($key, 'step_') === 0)
				{
					$input['step'] = substr($key, 5);
					break;
				}
			}
		}

		$session = new XenForo_ImportSession();
		if (!$session->getImporterKey())
		{
			return $this->responseReroute(__CLASS__, 'index');
		}

		$importer = $this->_getImportModel()->getImporter($session->getImporterKey());

		return $this->_startStep($importer, $session, $input['step'], $input['options']);
	}

	protected function _runStep(XenForo_Importer_Abstract $importer, XenForo_ImportSession $session, $step, $start, array $options = array())
	{
		$response = $importer->runStep($this, $session, $step, $start, $options);
		if ($response === true)
		{
			$session->completeStep();
			$session->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('import/import')
			);
		}
		else if (is_string($response) && $response !== '')
		{
			$session->completeStep();
			return $this->_startStep($importer, $session, $response);
		}
		else if (is_array($response))
		{
			list($start, $options, $message) = $response;
			$session->setStepInfo($start, $options);
			$session->save();

			$viewParams = array(
				'message' => $message,
				'importerName' => $this->_getImportModel()->getImporterName($session->getImporterKey()),
				'stepInfo' => $importer->getStep($step)
			);
			return $this->responseView('XenForo_ViewAdmin_Import_StepRun', 'import_step_run', $viewParams);
		}
		else if ($response instanceof XenForo_ControllerResponse_Abstract)
		{
			$session->save();
			return $response;
		}
		else
		{
			throw new XenForo_Exception('Invalid importer step response: ' . print_r($response, true));
		}
	}

	protected function _startStep(XenForo_Importer_Abstract $importer, XenForo_ImportSession $session, $step, array $options = array())
	{
		$configResponse = $importer->configStep($this, $session, $step, $options);
		if ($configResponse)
		{
			$configResponse->params['step'] = $step;
			$configResponse->params['importerName'] = $importer->getName();
			return $configResponse;
		}

		$session->startStep($step, $options);

		return $this->_runStep($importer, $session, $step, 0, $options);
	}

	public function actionComplete()
	{
		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'archive' => XenForo_Input::UINT,
				'table' => XenForo_Input::STRING
			));

			if ($input['archive'] && $input['table'])
			{
				if (!$this->_getImportModel()->archiveImportLog($input['table'], $error))
				{
					return $this->responseError($error);
				}
			}

			$session = new XenForo_ImportSession();
			$importerKey = $session->getImporterKey();
			$session->delete();

			$caches = array(
				'User', 'Thread', 'Poll', 'Forum'
			);

			return XenForo_CacheRebuilder_Abstract::getRebuilderResponse(
				$this, $caches, XenForo_Link::buildAdminLink('import/complete', false, array('confirm' => $importerKey))
			);
		}
		else if ($importerKey = $this->_input->filterSingle('confirm', XenForo_Input::STRING))
		{
			$messages = $this->_getImportModel()->getImporter($importerKey)->getImportCompleteMessages();

			return $this->responseView('XenForo_ViewAdmin_Import_Complete', 'import_complete', array('messages' => $messages));
		}
		else
		{
			$session = new XenForo_ImportSession();
			$config = $session->getConfig();
			$importer = $this->_getImportModel()->getImporter($session->getImporterKey());

			$viewParams = array(
				'importerName' => $importer->getName(),
				'logSuffix' => $config['db']['dbname']
			);

			return $this->responseView('XenForo_ViewAdmin_Import_CompleteConfirm', 'import_complete_confirm', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_Import
	 */
	protected function _getImportModel()
	{
		return $this->getModelFromCache('XenForo_Model_Import');
	}
}