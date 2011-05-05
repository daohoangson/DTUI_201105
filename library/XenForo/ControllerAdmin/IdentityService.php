<?php

class XenForo_ControllerAdmin_IdentityService extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('identityService');
	}

	public function actionIndex()
	{
		$viewParams = array(
			'identityServices' => $this->_getUserModel()->getIdentityServicesEditingData()
		);

		return $this->responseView('XenForo_ViewAdmin_IdentityService_List', 'identity_service_list', $viewParams);
	}

	public function actionAdd()
	{
		return $this->responseView(
			'XenForo_ViewAdmin_IdentityService_Add',
			'identity_service_edit'
		);
	}

	public function actionEdit()
	{
		$userModel = $this->_getUserModel();

		$identityService = $userModel->getIdentityService($this->_input->filterSingle('identity_service_id', XenForo_Input::STRING));

		if (!$identityService)
		{
			return $this->responseError(new XenForo_Phrase('requested_identity_service_not_found'), 404);
		}

		$viewParams = array(
			'identityService' => $userModel->getIdentityServiceEditingData($identityService)
		);

		return $this->responseView(
			'XenForo_ViewAdmin_IdentityService_Edit',
			'identity_service_edit',
			$viewParams
		);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		$data = $this->_input->filter(array(

			// im service fields
				'identity_service_id' => XenForo_Input::STRING,
				'model_class' => XenForo_Input::STRING,

			// phrases
				'name' => XenForo_Input::STRING,
				'hint' => XenForo_Input::STRING,

			// original service id
				'original_identity_service_id' => XenForo_Input::STRING,

		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_IdentityService');

		if ($data['original_identity_service_id'] !== '')
		{
			$dw->setExistingData($data['original_identity_service_id']);
		}

		$dw->set('identity_service_id', $data['identity_service_id']);
		$dw->set('model_class', $data['model_class']);

		$dw->setExtraData(XenForo_DataWriter_IdentityService::DATA_NAME, $data['name']);
		$dw->setExtraData(XenForo_DataWriter_IdentityService::DATA_HINT, $data['hint']);

		$dw->save();

		$ident = $dw->getMergedData();

		if ($this->_input->filterSingle('reload', XenForo_Input::STRING))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('identity-services/edit', $ident)
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('identity-services') . $this->getLastHash($data['identity_service_id'])
			);
		}
	}

	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_IdentityService', 'identity_service_id',
				XenForo_Link::buildAdminLink('identity-services')
			);
		}
		else // show confirmation dialog
		{
			$identityServiceId = $this->_input->filterSingle('identity_service_id', XenForo_Input::STRING);
			$identityService = $this->_getUserModel()->getIdentityServiceEditingData($identityServiceId);

			$viewParams = array(
				'identityService' => $identityService
			);

			return $this->responseView(
				'XenForo_ViewAdmin_IdentityService_Delete',
				'identity_service_delete',
				$viewParams
			);
		}
	}

	/**
	 * Validate a single field against the identity service DataWriter
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionValidateField()
	{
		$this->_assertPostOnly();

		return $this->_validateField('XenForo_DataWriter_IdentityService');
	}

	/**
	 * Gets the user model.
	 *
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}