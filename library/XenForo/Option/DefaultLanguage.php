<?php

/**
 * Deprecated. Use XenForo_Option_LanguageChooser
 */
abstract class XenForo_Option_DefaultLanguage
{
	/**
	 * Deprecated.
	 *
	 * @see XenForo_Option_LanguageChooser::renderRadio
	 */
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return XenForo_Option_LanguageChooser::renderRadio($view, $fieldPrefix, $preparedOption, $canEdit);
	}
}