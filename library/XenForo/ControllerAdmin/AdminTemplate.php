<?php

/**
 * Admin template actions in the admin control panel.
 *
 * @package XenForo_AdminTemplates
 */
class XenForo_ControllerAdmin_AdminTemplate extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
	}

	/**
	 * Displays a list of admin templates.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$model = $this->_getAdminTemplateModel();

		$viewParams = array(
			'templates' => $model->getAllAdminTemplateTitles(),
			'can_import' => $model->canImportAdminTemplatesFromDevelopment()
		);

		return $this->responseView('XenForo_ViewAdmin_AdminTemplate_List', 'admin_template_list', $viewParams);
	}

	/**
	 * Helper to get build the controller response for the admin template
	 * add or edit pages.
	 *
	 * @param array $template
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _templateEditResponse(array $template)
	{
		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'template' => $template,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($template['addon_id']) ? $template['addon_id'] : $addOnModel->getDefaultAddOnId()),
		);

		return $this->responseView('XenForo_ViewAdmin_AdminTemplate_Edit', 'admin_template_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new admin template.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_templateEditResponse(array());
	}

	/**
	 * Displays a form to edit an existing admin template.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		$template = $this->_getAdminTemplateOrError($templateId);

		$template['template'] = $this->_getStylePropertyModel()->replacePropertiesInTemplateForEditor(
			$template['template'], -1
		);

		return $this->_templateEditResponse($template);
	}

	/**
	 * Inserts a new admin template or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_AdminTemplate', 'deleteConfirm');
		}

		$data = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'template' => array(XenForo_Input::STRING, 'noTrim' => true),
			'addon_id' => XenForo_Input::STRING
		));

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle(-1)
		);
		$propertyChanges = $propertyModel->translateEditorPropertiesToArray(
			$data['template'], $data['template'], $properties
		);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
		if ($templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT))
		{
			$writer->setExistingData($templateId);
		}
		$writer->bulkSet($data);
		$writer->save();

		$propertyModel->saveStylePropertiesInStyleFromTemplate(-1, $propertyChanges, $properties);

		if ($this->_input->filterSingle('reload', XenForo_Input::STRING))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildAdminLink('admin-templates/edit', $writer->getMergedData())
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('admin-templates') . $this->getLastHash($writer->get('template_id'))
			);
		}
	}

	/**
	 * Delete confirmation and action
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		$template = $this->_getAdminTemplateOrError($templateId);

		if ($this->isConfirmedPost()) // delete the template
		{
			return $this->_deleteData(
				'XenForo_DataWriter_AdminTemplate', 'template_id',
				XenForo_Link::buildAdminLink('admin-templates')
			);
		}
		else
		{
			$viewParams = array(
				'template' => $template
			);

			return $this->responseView('XenForo_ViewAdmin_AdminTemplate_Delete', 'admin_template_delete', $viewParams);
		}
	}

	/**
	 * Fetches template data for each template specified by title in the incoming requirement array
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionLoadMultiple()
	{
		$data = $this->_input->filter(array(
			'includeTitles' => array(XenForo_Input::STRING, array('array' => true))
		));

		$propertyModel = $this->_getStylePropertyModel();
		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle(-1)
		);
		$templates = $this->_getAdminTemplateModel()->getAdminTemplatesByTitles($data['includeTitles']);

		foreach ($templates AS &$template)
		{
			$template['link'] = XenForo_Link::buildAdminLink('admin-templates/edit', $template);

			$template['template'] = $propertyModel->replacePropertiesInTemplateForEditor(
				$template['template'], -1, $properties
			);
		}

		$viewParams = array(
			'templateData' => $templates
		);

		return $this->responseView('XenForo_ViewAdmin_AdminTemplate_LoadMultiple', 'admin_template_load_multiple', $viewParams);
	}

	/**
	 * Saves multiple templates in a single action
	 *
	 * @return XenForo_ControllerResponse_Reroute|XenForo_ControllerResponse_Redirect
	 */
	public function actionSaveMultiple()
	{
		$this->_assertPostOnly();

		$data = $this->_input->filter(array(
			'templateArray' => array(XenForo_Input::STRING, array('array' => true, 'noTrim' => true)),
			'titleArray' => array(XenForo_Input::STRING, array('array' => true)),
			'template_id' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle(-1)
		);

		$writerErrors = array();
		$propertyChanges = array();

		foreach ($data['titleArray'] AS $templateId => $title)
		{
			$isPrimaryTemplate = ($data['template_id'] == $templateId);

			if (!isset($data['templateArray'][$templateId]) && !$isPrimaryTemplate)
			{
				// template hasn't been changed
				continue;
			}

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			if ($templateId)
			{
				$writer->setExistingData($templateId);
			}
			$writer->set('title', $title);

			$templatePropertyChanges = array();
			if (isset($data['templateArray'][$templateId]))
			{
				$templatePropertyChanges = $propertyModel->translateEditorPropertiesToArray(
					$data['templateArray'][$templateId], $templateText, $properties
				);

				$writer->set('template', $templateText);
			}

			if ($isPrimaryTemplate)
			{
				$writer->set('addon_id', $data['addon_id']);
			}

			$writer->preSave();

			if ($errors = $writer->getErrors())
			{
				$writerErrors[$title] = $errors;
			}
			else
			{
				$writer->save();
				if ($isPrimaryTemplate)
				{
					$data['template_id'] = $writer->get('template_id');
				}

				$propertyChanges = array_merge($propertyChanges, $templatePropertyChanges);
			}
		}

		$propertyModel->saveStylePropertiesInStyleFromTemplate(-1, $propertyChanges, $properties);

		if ($writerErrors)
		{
			$errorText = '';

			foreach ($writerErrors AS $templateTitle => $errors)
			{
				$errorText .= "\n\n$templateTitle:";

				foreach ($errors AS $i => $error)
				{
					$errorText .= "\n" . ($i + 1) . ")\t$error";
				}
			}

			return $this->responseError(new XenForo_Phrase('the_following_templates_contained_errors_and_were_not_saved_x',
				array('errors' => $errorText), false
			));
		}

		if ($this->_input->filterSingle('_TemplateEditorAjax', XenForo_Input::UINT))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_AdminTemplate', 'loadMultiple');
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('admin-templates') . $this->getLastHash($data['template_id'])
			);
		}
	}

	/**
	 * Gets the specified admin template or throws an exception.
	 *
	 * @param integer $templateId
	 *
	 * @return array
	 */
	protected function _getAdminTemplateOrError($templateId)
	{
		$info = $this->_getAdminTemplateModel()->getAdminTemplateById($templateId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_admin_template_not_found'), 404));
		}

		return $info;
	}

	/**
	 * Gets the admin template model.
	 *
	 * @return XenForo_Model_AdminTemplate
	 */
	protected function _getAdminTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminTemplate');
	}

	/**
	 * @return  XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
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