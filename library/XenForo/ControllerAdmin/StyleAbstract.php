<?php

abstract class XenForo_ControllerAdmin_StyleAbstract extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('style');
	}

	/**
	 * Fetches $styleId from cookie if it's available, or returns the default style ID.
	 *
	 * @return integer
	 */
	protected function _getStyleIdFromCookie()
	{
		$styleId = XenForo_Helper_Cookie::getCookie('edit_style_id');
		if ($styleId === false)
		{
			$styleId = (XenForo_Application::debugMode()
				? 0
				: XenForo_Application::get('options')->defaultStyleId
			);
		}

		if (!XenForo_Application::debugMode() && !$styleId)
		{
			$styleId = XenForo_Application::get('options')->defaultStyleId;
		}

		return $styleId;
	}

	/**
	 * Helper to get the template add/edit form controller response.
	 *
	 * @param array $template
	 * @param integer $inputStyleId The style this template is being edited in
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getTemplateAddEditResponse(array $template, $inputStyleId)
	{
		$stylePropertyModel = $this->_getStylePropertyModel();
		$templateModel = $this->_getTemplateModel();
		$styleModel = $this->_getStyleModel();
		$addOnModel = $this->_getAddOnModel();

		if ($template['style_id'] != $inputStyleId)
		{
			// actually adding a "copy" of this template in this style
			$template['template_id'] = 0;
			$template['style_id'] = $inputStyleId;
		}

		if (!$templateModel->canModifyTemplateInStyle($template['style_id']))
		{
			return $this->responseError(new XenForo_Phrase('templates_in_this_style_can_not_be_modified'));
		}

		$viewParams = array(
			'template' => $template,
			'style' => $styleModel->getStyleByid($template['style_id'], true),
			'masterStyle' => $styleModel->showMasterStyle() ? $styleModel->getStyleById(0, true) : array(),
			'styles' => $styleModel->getAllStylesAsFlattenedTree($styleModel->showMasterStyle() ? 1 : 0),
			'addOnOptions' => ($template['style_id'] == 0 ? $addOnModel->getAddOnOptionsListIfAvailable() : array()),
			'addOnSelected' => (isset($template['addon_id']) ? $template['addon_id'] : $addOnModel->getDefaultAddOnId()),
		);

		return $this->responseView('XenForo_ViewAdmin_Template_Edit', 'template_edit', $viewParams);
	}

	/**
	 * Gets the named style or throws an error.
	 *
	 * @param integer $id Style ID
	 *
	 * @return array
	 */
	protected function _getStyleOrError($id)
	{
		return $this->getRecordOrError(
			$id, $this->_getStyleModel(), 'getStyleById', 'requested_style_not_found'
		);
	}

	/**
	 * Gets the named template or throws an error.
	 *
	 * @param integer $id Template ID
	 *
	 * @return array
	 */
	protected function _getTemplateOrError($id)
	{
		return $this->getRecordOrError(
			$id, $this->_getTemplateModel(), 'getTemplateById', 'requested_template_not_found'
		);
	}

	/**
	 * Lazy load the template model object.
	 *
	 * @return  XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}

	/**
	 * Lazy load the style model object.
	 *
	 * @return  XenForo_Model_Style
	 */
	protected function _getStyleModel()
	{
		return $this->getModelFromCache('XenForo_Model_Style');
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