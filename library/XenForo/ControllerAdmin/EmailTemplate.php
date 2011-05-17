<?php

/**
 * Email template actions in the admin control panel.
 *
 * @package XenForo_EmailTemplate
 */
class XenForo_ControllerAdmin_EmailTemplate extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	/**
	 * Displays a list of email templates.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$emailTemplateModel = $this->_getEmailTemplateModel();

		$standardView = $this->_input->filterSingle('standard', XenForo_Input::UINT);
		if ($standardView || !XenForo_Application::debugMode())
		{
			$templates = $emailTemplateModel->getAllEffectiveEmailTemplateTitles();
			$showSwitchLink = (XenForo_Application::debugMode() ? 'master' : false);
			$customizeTemplate = true;
		}
		else
		{
			$templates = $emailTemplateModel->getAllMasterEmailTemplateTitles();
			$showSwitchLink = 'standard';
			$customizeTemplate = false;
		}

		$viewParams = array(
			'templates' => $templates,
			'showSwitchLink' => $showSwitchLink,
			'showCreateLink' => XenForo_Application::debugMode(),
			'customizeTemplate' => $customizeTemplate
		);

		return $this->responseView('XenForo_ViewAdmin_EmailTemplate_List', 'email_template_list', $viewParams);
	}

	/**
	 * Helper to get build the controller response for the email template
	 * add or edit pages.
	 *
	 * @param array $template
	 * @param boolean $customize True if trying to customize a master email template
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getEmailTemplateAddEditResponse(array $template, $customize)
	{
		$addOnModel = $this->_getAddOnModel();

		if ($customize && empty($template['custom']))
		{
			// force template to go to custom
			$template['template_id'] = 0;
			$template['custom'] = 1;
		}

		$viewParams = array(
			'template' => $template,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnOptions' => (empty($template['custom']) ? $addOnModel->getAddOnOptionsListIfAvailable() : array()),
			'addOnSelected' => (isset($template['addon_id']) ? $template['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_EmailTemplate_Edit', 'email_template_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new email template.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		if (!XenForo_Application::debugMode())
		{
			$custom = true;
		}
		else
		{
			$custom = $this->_input->filterSingle('custom', XenForo_Input::UINT);
		}

		return $this->_getEmailTemplateAddEditResponse(array(), $custom);
	}

	/**
	 * Displays a form to edit an existing email template.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		$template = $this->_getEmailTemplateOrError($templateId);

		if (!XenForo_Application::debugMode())
		{
			$custom = true;
		}
		else
		{
			$custom = $this->_input->filterSingle('custom', XenForo_Input::UINT);
		}

		return $this->_getEmailTemplateAddEditResponse($template, $custom);
	}

	/**
	 * Inserts a new email template or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_EmailTemplate', 'deleteConfirm');
		}

		$data = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'custom' => XenForo_Input::UINT,
			'subject' => array(XenForo_Input::STRING, 'noTrim' => true),
			'body_text' => array(XenForo_Input::STRING, 'noTrim' => true),
			'body_html' => array(XenForo_Input::STRING, 'noTrim' => true),
			'addon_id' => XenForo_Input::STRING
		));

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_EmailTemplate');
		if ($templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT))
		{
			$writer->setExistingData($templateId);
		}
		$writer->bulkSet($data);
		$writer->save();

		if ($this->_input->filterSingle('reload', XenForo_Input::STRING))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildAdminLink('email-templates/edit', $writer->getMergedData())
			);
		}
		else
		{
			$extraData = array();
			if (XenForo_Application::debugMode() && $writer->get('custom'))
			{
				$extraData['standard'] = 1;
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('email-templates', '', $extraData) . $this->getLastHash($writer->get('title'))
			);
		}
	}

	/**
	 * Delets an email template.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_EmailTemplate', 'template_id',
				XenForo_Link::buildAdminLink('email-templates')
			);
		}
		else // show delete confirmation dialog
		{
			$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
			$template = $this->_getEmailTemplateOrError($templateId);

			$viewParams = array(
				'template' => $template
			);

			return $this->responseView('XenForo_ViewAdmin_EmailTemplate_Delete', 'email_template_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified email template or throws an exception.
	 *
	 * @param integer $templateId
	 *
	 * @return array
	 */
	protected function _getEmailTemplateOrError($templateId)
	{
		return $this->getRecordOrError(
			$templateId, $this->_getEmailTemplateModel(), 'getEmailTemplateById',
			'requested_email_template_not_found'
		);
	}

	/**
	 * Gets the email template model.
	 *
	 * @return XenForo_Model_EmailTemplate
	 */
	protected function _getEmailTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_EmailTemplate');
	}

	/**
	 * Get the add-on model.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}