<?php

/**
 * Helper for guest time zone option.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_GuestTimeZone
{
	/**
	 * Renders the guest time zone option.
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
		$preparedOption['formatParams'] = XenForo_Helper_TimeZone::getTimeZones();

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_list_option_select',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}