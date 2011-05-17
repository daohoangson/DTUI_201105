<?php

/**
 * Admin controller for handling actions on templates.
 *
 * @package XenForo_Templates
 */
class XenForo_ControllerAdmin_Template extends XenForo_ControllerAdmin_StyleAbstract
{
	/**
	 * Template index. This is a list of templates, so redirect this to a
	 * style-specific list.
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionIndex()
	{
		$styleId = $this->_getStyleIdFromCookie();

		$style = $this->_getStyleModel()->getStyleById($styleId, true);
		if (!$style || !$this->_getTemplateModel()->canModifyTemplateInStyle($styleId))
		{
			$style = $this->_getStyleModel()->getStyleById(XenForo_Application::get('options')->defaultStyleId);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildAdminLink('styles/templates', $style)
		);
	}

	/**
	 * Form to add a template to the specified style. If not in debug mode,
	 * users are prevented from adding a template to the master style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$input = $this->_input->filter(array(
			'style_id' => XenForo_Input::UINT
		));

		$template = array(
			'template_id' => 0,
			'style_id' => $input['style_id']
		);

		return $this->_getTemplateAddEditResponse($template, $input['style_id']);
	}

	/**
	 * Form to edit a specified template. A style_id input must be specified. If the style ID
	 * of the requested template and the style ID of the input differ, the request is
	 * treated as adding a customized version of the requested template in the input
	 * style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$input = $this->_input->filter(array(
			'template_id' => XenForo_Input::UINT,
			'style_id' => XenForo_Input::UINT
		));

		$template = $this->_getTemplateOrError($input['template_id']);

		if (!$this->_input->inRequest('style_id'))
		{
			// default to editing in the specified style
			$input['style_id'] = $template['style_id'];
		}

		if ($input['style_id'] != $template['style_id'])
		{
			$specificTemplate = $this->_getTemplateModel()->getTemplateInStyleByTitle($template['title'], $input['style_id']);
			if ($specificTemplate)
			{
				$template = $specificTemplate;
			}
		}

		$template['template'] = $this->_getStylePropertyModel()->replacePropertiesInTemplateForEditor(
			$template['template'], $input['style_id']
		);

		return $this->_getTemplateAddEditResponse($template, $input['style_id']);
	}

	/**
	 * Saves a template. This may either be an insert or an update.
	 *
	 * @return XenForo_ControllerResponse
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			// user clicked delete
			return $this->responseReroute('XenForo_ControllerAdmin_Template', 'deleteConfirm');
		}

		$templateModel = $this->_getTemplateModel();

		$data = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'template' => array(XenForo_Input::STRING, 'noTrim' => true),
			'style_id' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));

		// only allow templates to be edited in non-master styles, unless in debug mode
		if (!$templateModel->canModifyTemplateInStyle($data['style_id']))
		{
			return $this->responseError(new XenForo_Phrase('this_template_can_not_be_modified'));
		}

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle($data['style_id'])
		);
		$propertyChanges = $propertyModel->translateEditorPropertiesToArray(
			$data['template'], $data['template'], $properties
		);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
		if ($templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT))
		{
			$writer->setExistingData($templateId);
		}

		$writer->bulkSet($data);

		if ($writer->isChanged('title') || $writer->isChanged('template') || $writer->get('style_id') > 0)
		{
			$writer->updateVersionId();
		}

		$writer->save();

		$propertyModel->saveStylePropertiesInStyleFromTemplate($data['style_id'], $propertyChanges, $properties);

		if ($this->_input->filterSingle('reload', XenForo_Input::STRING))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildAdminLink('templates/edit', $writer->getMergedData(), array('style_id' => $writer->get('style_id')))
			);
		}
		else
		{
			$style = $this->_getStyleModel()->getStyleByid($writer->get('style_id'), true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles/templates', $style) . $this->getLastHash($writer->get('title'))
			);
		}
	}

	/**
	 * Delete confirmation and action.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		$template = $this->_getTemplateOrError($templateId);

		if ($this->isConfirmedPost()) // delete the template
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$writer->setExistingData($templateId);

			if (!$this->_getTemplateModel()->canModifyTemplateInStyle($writer->get('style_id')))
			{
				return $this->responseError(new XenForo_Phrase('this_template_can_not_be_modified'));
			}

			$writer->delete();

			$style = $this->_getStyleModel()->getStyleByid($writer->get('style_id'), true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles/templates', $style)
			);
		}
		else // show a delete confirmation dialog
		{
			$viewParams = array(
				'template' => $template,
				'style' => $this->_getStyleModel()->getStyleById($template['style_id']),
			);

			return $this->responseView('XenForo_ViewAdmin_Template_Delete', 'template_delete', $viewParams);
		}
	}

	// legacy
	public function actionDeleteConfirm() { return $this->actionDelete(); }

	/**
	 * Fetches template data for each template specified by title in the incoming requirement array
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionLoadMultiple()
	{
		$data = $this->_input->filter(array(
			'style_id' => XenForo_Input::UINT,
			'title' => XenForo_Input::STRING,
			'includeTitles' => array(XenForo_Input::STRING, array('array' => true))
		));

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle($data['style_id'])
		);
		$templates = $this->_getTemplateModel()->getEffectiveTemplatesByTitles($data['includeTitles'], $data['style_id']);

		foreach ($templates AS &$template)
		{
			$template['link'] = XenForo_Link::buildAdminLink('templates/edit', $template, array('style_id' => $data['style_id']));

			$template['template'] = $propertyModel->replacePropertiesInTemplateForEditor(
				$template['template'], $data['style_id'], $properties
			);
		}

		$viewParams = array(
			'style_id' => $data['style_id'],
			'title' => $data['title'],
			'templateData' => $templates
		);

		return $this->responseView('XenForo_ViewAdmin_Template_LoadMultiple', 'template_load_multiple', $viewParams);
	}

	/**
	 * Saves multiple templates in a single action
	 *
	 * @return XenForo_ControllerResponse_Reroute|XenForo_ControllerResponse_Redirect
	 */
	public function actionSaveMultiple()
	{
		$this->_assertPostOnly();

		$templateModel = $this->_getTemplateModel();

		$data = $this->_input->filter(array(
			'includeTitles' => array(XenForo_Input::STRING, array('array' => true)),
			'titleArray'    => array(XenForo_Input::STRING, array('array' => true)),
			'templateArray' => array(XenForo_Input::STRING, array('array' => true, 'noTrim' => true)),
			'styleidArray'  => array(XenForo_Input::STRING, array('array' => true)),
			'style_id'      => XenForo_Input::UINT,
			'template_id'   => XenForo_Input::UINT,
			'addon_id'      => XenForo_Input::STRING
		));

		// only allow templates to be edited in non-master styles, unless in debug mode
		if (!$templateModel->canModifyTemplateInStyle($data['style_id']))
		{
			return $this->responseError(new XenForo_Phrase('this_template_can_not_be_modified'));
		}

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle($data['style_id'])
		);

		$writerErrors = array();
		$propertyChanges = array();

		$existingMasters = $this->_getTemplateModel()->getTemplatesInStyleByTitles($data['titleArray']);

		foreach ($data['titleArray'] AS $templateId => $title)
		{
			$isPrimaryTemplate = ($data['template_id'] == $templateId);

			if (!isset($data['templateArray'][$templateId]) && !$isPrimaryTemplate)
			{
				// template hasn't been changed
				continue;
			}

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			if ($templateId && $data['styleidArray'][$templateId] == $data['style_id'])
			{
				$writer->setExistingData($templateId);
				$exists = true;
			}
			else
			{
				// only change the style ID of a newly inserted template
				$writer->set('style_id', $data['style_id']);
				if (isset($existingMasters[$title]))
				{
					$writer->set('addon_id', $existingMasters[$title]['addon_id']);
				}
				$exists = false;
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
			else if (!$exists)
			{
				continue; // can't create
			}

			if ($isPrimaryTemplate)
			{
				$writer->set('addon_id', $data['addon_id']);
			}

			if ($writer->isChanged('title') || $writer->isChanged('template') || $writer->get('style_id') > 0)
			{
				$writer->updateVersionId();
			}

			$writer->preSave();

			if ($errors = $writer->getErrors())
			{
				$writerErrors[$title] = $errors;
			}
			else
			{
				$writer->save();

				$propertyChanges = array_merge($propertyChanges, $templatePropertyChanges);
			}
		}

		$propertyModel->saveStylePropertiesInStyleFromTemplate($data['style_id'], $propertyChanges, $properties);

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

			return $this->responseError(new XenForo_Phrase('following_templates_contained_errors_and_were_not_saved_x',
				array('errors' => $errorText), false
			));
		}

		if ($this->_input->filterSingle('_TemplateEditorAjax', XenForo_Input::UINT))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_Template', 'loadMultiple');
		}
		else
		{
			$style = $this->_getStyleModel()->getStyleByid($data['style_id'], true);

			$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
			if ($templateId && $last = $templateModel->getTemplateById($templateId))
			{
				if ($last['style_id'] != $data['style_id'])
				{
					$last = $templateModel->getEffectiveTemplateByTitle($last['title'], $data['style_id']);
				}

				$lastHash = $this->getLastHash($last['title']);
			}
			else
			{
				$lastHash = '';
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles/templates', $style) . $lastHash
			);
		}
	}

	/**
	 * Template searching.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearch()
	{
		$styleModel = $this->_getStyleModel();

		$defaultStyleId = (XenForo_Application::debugMode()
			? 0
			: XenForo_Application::get('options')->defaultStyleId
		);

		if ($this->_input->inRequest('style_id'))
		{
			$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		}
		else
		{
			$styleId = XenForo_Helper_Cookie::getCookie('edit_style_id');
			if ($styleId === false)
			{
				$styleId = $defaultStyleId;
			}
		}

		if ($this->_input->filterSingle('search', XenForo_Input::UINT))
		{
			$templateModel = $this->_getTemplateModel();

			$input = $this->_input->filter(array(
				'title' => XenForo_Input::STRING,
				'template' => XenForo_Input::STRING,
				'template_state' => array(XenForo_Input::STRING, 'array' => true)
			));

			if (!$templateModel->canModifyTemplateInStyle($styleId))
			{
				return $this->responseError(new XenForo_Phrase('templates_in_this_style_can_not_be_modified'));
			}

			$conditions = array();
			if (!empty($input['title']))
			{
				$conditions['title'] = $input['title'];
			}
			if (!empty($input['template']))
			{
				// translate @x searches to "{xen:property x" as that is what is stored
				$text = preg_replace('/@property\s*(")?([a-z0-9_]*)/i', '{xen:property \\2', $input['template']);
				$text = preg_replace('/@([a-z0-9_]+)/i', '{xen:property \\1', $text);

				$conditions['template'] = $text;
			}
			if ($styleId && !empty($input['template_state']) && count($input['template_state']) < 3)
			{
				$conditions['template_state'] = $input['template_state'];
			}

			if (empty($conditions))
			{
				return $this->responseError(new XenForo_Phrase('please_complete_required_fields'));
			}

			$templates = $templateModel->getEffectiveTemplateListForStyle($styleId, $conditions);

			$viewParams = array(
				'style' => $styleModel->getStyleById($styleId, true),
				'templates' => $templates
			);
			return $this->responseView('XenForo_ViewAdmin_Template_SearchResults', 'template_search_results', $viewParams);
		}
		else
		{
			$showMaster = $styleModel->showMasterStyle();

			$viewParams = array(
				'styles' => $styleModel->getAllStylesAsFlattenedTree($showMaster ? 1 : 0),
				'masterStyle' => $showMaster ? $styleModel->getStyleById(0, true) : false,
				'styleId' => $styleId
			);
			return $this->responseView('XenForo_ViewAdmin_Template_Search', 'template_search', $viewParams);
		}
	}

	/**
	 * Displays a list of outdated templates.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionOutdated()
	{
		$templates = $this->_getTemplateModel()->getOutdatedTemplates();
		if (!$templates)
		{
			return $this->responseMessage(new XenForo_Phrase('there_are_no_outdated_templates'));
		}

		$grouped = array();
		foreach ($templates AS $template)
		{
			$grouped[$template['style_id']][$template['template_id']] = $template;
		}

		$viewParams = array(
			'templatesGrouped' => $grouped,
			'totalTemplates' => count($templates),
			'styles' => $this->_getStyleModel()->getAllStyles()
		);
		return $this->responseView('XenForo_ViewAdmin_Template_Outdated', 'template_outdated', $viewParams);
	}
}