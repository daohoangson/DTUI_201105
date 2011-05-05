<?php

class XenForo_Option_JumpMenuNodeTypes
{
	/**
	 * Renders checkboxes allowing the selection of multiple node types.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = array();

		/* @var $nodeModel XenForo_Model_Node */
		$nodeModel = XenForo_Model::create('XenForo_Model_Node');

		foreach ($nodeModel->getAllNodeTypes() AS $nodeTypeId => $nodeType)
		{
			$preparedOption['formatParams'][$nodeTypeId] = $nodeModel->getNodeTypeNameById($nodeTypeId);
		}

		$preparedOption['formatParams'] = XenForo_ViewAdmin_Helper_Option::prepareMultiChoiceOptions($fieldPrefix, $preparedOption);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_template_jumpMenuNodeTypes',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}