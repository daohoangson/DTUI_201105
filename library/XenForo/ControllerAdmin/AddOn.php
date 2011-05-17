<?php

/**
 * Add-ons controller.
 *
 * @package XenForo_AddOns
 */
class XenForo_ControllerAdmin_AddOn extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('addOn');
	}

	/**
	 * Lists all installed add-ons.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'addOns' => $addOnModel->getAllAddOns(),
			'canAccessDevelopment' => $addOnModel->canAccessAddOnDevelopmentAreas()
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_List', 'addon_list', $viewParams);
	}

	/**
	 * Displays a form to create a new add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$viewParams = array(
			'addOn' => array()
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_Edit', 'addon_edit', $viewParams);
	}

	/**
	 * Displays a form to edit an existing add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		$viewParams = array(
			'addOn' => $addOn
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_Edit', 'addon_edit', $viewParams);
	}

	/**
	 * Inserts a new add-on or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$originalAddOnId = $this->_input->filterSingle('original_addon_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'addon_id' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'version_string' => XenForo_Input::STRING,
			'version_id' => XenForo_Input::UINT,
			'install_callback_class'    => XenForo_Input::STRING,
			'install_callback_method'   => XenForo_Input::STRING,
			'uninstall_callback_class'  => XenForo_Input::STRING,
			'uninstall_callback_method' => XenForo_Input::STRING,
			'url' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		if ($originalAddOnId)
		{
			$dw->setExistingData($originalAddOnId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('add-ons') . $this->getLastHash($dwInput['addon_id'])
		);
	}

	/**
	 * Deletes (uninstalls) an add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		if ($this->isConfirmedPost()) // delete add-on
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
			$dw->setExistingData($addOnId);
			$dw->delete();

			return XenForo_CacheRebuilder_Abstract::getRebuilderResponse(
				$this, $dw->getExtraData(XenForo_DataWriter_AddOn::DATA_REBUILD_CACHES), XenForo_Link::buildAdminLink('add-ons')
			);
		}
		else // show delete confirmation prompt
		{
			$viewParams = array(
				'addOn' => $addOn
			);

			return $this->responseView('XenForo_ViewAdmin_AddOn_Delete', 'addon_delete', $viewParams);
		}
	}

	/**
	 * Exports an add-on's XML data.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionExport()
	{
		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		$this->_routeMatch->setResponseType('xml');

		$viewParams = array(
			'addOn' => $addOn,
			'xml' => $this->_getAddOnModel()->getAddOnXml($addOn)
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_Export', '', $viewParams);
	}

	/**
	 * Displays a form to let a user choose what add-on to install.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInstallConfirm()
	{
		return $this->responseView('XenForo_ViewAdmin_AddOn_Install', 'addon_install');
	}

	/**
	 * Installs a new add-on. This cannot be used for upgrading.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInstall()
	{
		$this->_assertPostOnly();

		$fileTransfer = new Zend_File_Transfer_Adapter_Http();
		if ($fileTransfer->isUploaded('upload_file'))
		{
			$fileInfo = $fileTransfer->getFileInfo('upload_file');
			$fileName = $fileInfo['upload_file']['tmp_name'];
		}
		else
		{
			$fileName = $this->_input->filterSingle('server_file', XenForo_Input::STRING);
		}

		$caches = $this->_getAddOnModel()->installAddOnXmlFromFile($fileName);

		return XenForo_CacheRebuilder_Abstract::getRebuilderResponse($this, $caches, XenForo_Link::buildAdminLink('add-ons'));
	}

	/**
	 * Displays a form to let the user upgrade an existing add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpgradeConfirm()
	{
		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		$viewParams = array(
			'addOn' => $addOn
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_Upgrade', 'addon_upgrade', $viewParams);
	}

	/**
	 * Upgrades the specified add-on. The given file must match the specified
	 * add-on, or an error will occur.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpgrade()
	{
		$this->_assertPostOnly();

		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		$fileTransfer = new Zend_File_Transfer_Adapter_Http();
		if ($fileTransfer->isUploaded('upload_file'))
		{
			$fileInfo = $fileTransfer->getFileInfo('upload_file');
			$fileName = $fileInfo['upload_file']['tmp_name'];
		}
		else
		{
			$fileName = $this->_input->filterSingle('server_file', XenForo_Input::STRING);
		}

		$caches = $this->_getAddOnModel()->installAddOnXmlFromFile($fileName, $addOn['addon_id']);

		return XenForo_CacheRebuilder_Abstract::getRebuilderResponse($this, $caches,
			XenForo_Link::buildAdminLink('add-ons') . $this->getLastHash($addOnId));
	}

	/**
	 * Helper to switch the active state for an add-on and get the controller response.
	 *
	 * @param string $addOnId Add-on ID
	 * @param integer $activeState O or 1
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _switchAddOnActiveStateAndGetResponse($addOnId, $activeState)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		$dw->setExistingData($addOnId);
		$dw->set('active', $activeState);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('add-ons') . $this->getLastHash($addOnId)
		);
	}

	/**
	 * Enables the specified add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEnable()
	{
		// can be requested over GET, so check for the token manually
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		return $this->_switchAddOnActiveStateAndGetResponse($addOnId, 1);
	}

	/**
	 * Disables the specified add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDisable()
	{
		// can be requested over GET, so check for the token manually
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		return $this->_switchAddOnActiveStateAndGetResponse($addOnId, 0);
	}

	/**
	 * Selectively enables of disables specified add-ons
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		$this->_assertPostOnly();

		$addOnExists = $this->_input->filterSingle('addOnExists', array(XenForo_Input::UINT, 'array' => true));
		$addOns = $this->_input->filterSingle('addOn', array(XenForo_Input::UINT, 'array' => true));

		$addOnModel = $this->_getAddOnModel();

		foreach ($addOnModel->getAllAddOns() AS $addOnId => $addOn)
		{
			if (isset($addOnExists[$addOnId]))
			{
				$addOnActive = (isset($addOns[$addOnId]) && $addOns[$addOnId] ? 1 : 0);

				if ($addOn['active'] != $addOnActive)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
					$dw->setExistingData($addOnId);
					$dw->set('active', $addOnActive);
					$dw->save();
				}
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('add-ons')
		);
	}

	/**
	 * Gets a valid add-on or throws an exception.
	 *
	 * @param string $addOnId
	 *
	 * @return array
	 */
	protected function _getAddOnOrError($addOnId)
	{
		$info = $this->_getAddOnModel()->getAddOnById($addOnId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_addon_not_found'), 404));
		}

		return $info;
	}

	/**
	 * Gets the add-on model object.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}