<?php

/**
 * Deprecated. Use XenForo_Option_StyleChooser
 */
abstract class XenForo_Option_DefaultStyle
{
	/**
	 * Deprecated.
	 *
	 * @see XenForo_Option_StyleChooser::renderRadio
	 */
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return XenForo_Option_StyleChooser::renderRadio($view, $fieldPrefix, $preparedOption, $canEdit);
	}
}