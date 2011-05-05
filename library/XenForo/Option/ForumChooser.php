<?php

/**
 * Deprecated. Use XenForo_Option_NodeChooser::renderSelect
 */
abstract class XenForo_Option_ForumChooser
{
	/**
	 * Deprecated
	 *
	 * @see XenForo_Option_NodeChooser::renderSelect
	 */
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return XenForo_Option_NodeChooser::renderSelect($view, $fieldPrefix, $preparedOption, $canEdit);
	}
}