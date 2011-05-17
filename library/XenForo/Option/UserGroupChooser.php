<?php

/**
 * Helper for choosing a usergroup.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_UserGroupChooser
{
	/**
	 * Renders the user group chooser option as a <select>.
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
	 * Renders the user group chooser option as a group of <input type="radio" />.
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
	 * Fetches a list of user group options.
	 *
	 * @param integer $selectedGroup
	 * @param mixed Include 'unspecified' option (specify a phrase to represent the unspecified option)
	 *
	 * @return array
	 */
	public static function getUserGroupOptions($selectedGroup, $unspecifiedPhrase = false)
	{
		/* @var $userGroupModel XenForo_Model_UserGroup */
		$userGroupModel = XenForo_Model::create('XenForo_Model_UserGroup');

		$options = $userGroupModel->getUserGroupOptions($selectedGroup);

		if ($unspecifiedPhrase)
		{
			$options = array_merge(array(array
			(
				'label' => $unspecifiedPhrase,
				'value' => 0,
				'selected' => ($selectedGroup == 0)
			)), $options);
		}

		return $options;
	}

	/**
	 * Renders the user group chooser option.
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
		$preparedOption['formatParams'] = self::getUserGroupOptions(
			$preparedOption['option_value'],
			sprintf('(%s)', new XenForo_Phrase('unspecified'))
		);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			$templateName, $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}