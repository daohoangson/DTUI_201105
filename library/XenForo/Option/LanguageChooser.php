<?php

class XenForo_Option_LanguageChooser
{
	/**
	 * Renders the language chooser option as a <select>.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderSelect(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		//$preparedOption['inputClass'] = 'autoSize';

		return self::_render('option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit);
	}

	/**
	 * Renders the language chooser option as a group of <input type="radio" />.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderRadio(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::_render('option_list_option_radio', $view, $fieldPrefix, $preparedOption, $canEdit);
	}

	/**
	 * Renders the language chooser option.
	 *
	 * @param string Name of template to render
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _render($templateName, XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = XenForo_Model::create('XenForo_Model_Language')->getLanguagesForOptionsTag(
			$preparedOption['option_value']
		);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			$templateName, $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}