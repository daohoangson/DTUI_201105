<?php

/**
 * Helpers for displaying a list of options.
 *
 * @package XenForo_Options
 */
class XenForo_ViewAdmin_Helper_Option
{
	/**
	 * Private constructor. Use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Renders a collection of options in the standard way. See {@link renderPreparedOptionHtml()}
	 * for more specifics on the arguments.
	 *
	 * @param XenForo_View $view
	 * @param array $preparedOptions
	 * @param boolean $canEdit
	 * @param string $fieldPrefix
	 *
	 * @return array Array of XenForo_Template_Abstract objects
	 */
	public static function renderPreparedOptionsHtml(XenForo_View $view, array $preparedOptions, $canEdit, $fieldPrefix = 'options')
	{
		$renderedOptions = array();
		foreach ($preparedOptions AS $preparedOption)
		{
			$renderedOptions[] = XenForo_ViewAdmin_Helper_Option::renderPreparedOptionHtml($view, $preparedOption, $canEdit, $fieldPrefix);
		}

		return $renderedOptions;
	}

	/**
	 * Renders a {@link XenForo_Model_Option::preparedOption() prepared option}
	 * in the standard way. Note that this doesn't actually render the prepared
	 * template, but the next usage in a string context will.
	 *
	 * @param XenForo_View $view View object that this is being called from.
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if the user should see an "edit" link with the option
	 * @param string $fieldPrefix Prefix for the name of the field the options will be written into. Must be a-z0-9_ only.
	 *
	 * @return XenForo_Template_Abstract Yet-to-be-rendered template
	 */
	public static function renderPreparedOptionHtml(XenForo_View $view, array $preparedOption, $canEdit, $fieldPrefix = 'options')
	{
		switch ($preparedOption['edit_format'])
		{
			case 'textbox':  $callbackMethod = '_renderTextBoxOptionHtml'; break;
			case 'spinbox':  $callbackMethod = '_renderSpinBoxOptionHtml'; break;
			case 'onoff':    $callbackMethod = '_renderOnOffOptionHtml'; break;
			case 'radio':    $callbackMethod = '_renderRadioOptionHtml'; break;
			case 'select':   $callbackMethod = '_renderSelectOptionHtml'; break;
			case 'checkbox': $callbackMethod = '_renderCheckBoxOptionHtml'; break;

			case 'template': $callbackMethod = '_renderTemplateOptionHtml'; break;
			case 'callback': $callbackMethod = '_renderCallbackOptionHtml'; break;

			default:
				return self::_renderInvalidOptionHtml($view, $preparedOption, $canEdit);
		}

		$preparedOption['formatParams'] = self::_replacePhrasedText($preparedOption['formatParams']);

		return self::$callbackMethod($view, $fieldPrefix, $preparedOption, $canEdit);
	}

	/**
	 * Renders a text box option.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderTextBoxOptionHtml(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::renderOptionTemplateInternal('option_list_option_textbox',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * Renders a spin box option.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderSpinBoxOptionHtml(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::renderOptionTemplateInternal('option_list_option_spinbox',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * Renders a single on-off check box option.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderOnOffOptionHtml(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::renderOptionTemplateInternal('option_list_option_onoff',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * Renders a radio option.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderRadioOptionHtml(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::renderOptionTemplateInternal('option_list_option_radio',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * Renders a select option.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderSelectOptionHtml(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::renderOptionTemplateInternal('option_list_option_select',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * Prepares to display a multi-choice option (checkbox) by manipulating
	 * the formatting params into the expected format. They come out in
	 * [name] => selected format. This is manipulated to maintain a checkbox
	 * output that will keep them this way.
	 *
	 * @param string $fieldPrefix HTML form field prefix
	 * @param array $preparedOption Prepared option info
	 *
	 * @return array Updated format params
	 */
	public static function prepareMultiChoiceOptions($fieldPrefix, array $preparedOption)
	{
		$formatParams = array();
		$selected = $preparedOption['option_value'];

		foreach ($preparedOption['formatParams'] AS $name => $label)
		{
			$formatParams[] = array(
				'name' => htmlspecialchars($fieldPrefix . "[$preparedOption[option_id]][$name]"),
				'label' => $label,
				'selected' => !empty($selected[$name])
			);
		}

		return $formatParams;
	}

	/**
	 * Renders a check box option.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderCheckBoxOptionHtml(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = self::prepareMultiChoiceOptions($fieldPrefix, $preparedOption);

		return self::renderOptionTemplateInternal('option_list_option_checkbox',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * Replaces {xen:phrase x} references in a formatting params list for an option.
	 *
	 * @param array $formatParams List of format params ([name] => label string)
	 *
	 * @return array Format params with phrases replaced
	 */
	protected static function _replacePhrasedText(array $formatParams)
	{
		foreach ($formatParams AS $name => &$label)
		{
			$label = preg_replace_callback(
				'#\{xen:phrase ("|\'|)([a-z0-9-_]+)\\1\}#i',
				array('self', '_replacePhrasedTextCallback'),
				$label
			);
		}

		return $formatParams;
	}

	protected static function _replacePhrasedTextCallback(array $match)
	{
		// This will cause extra queries, but it shouldn't be a particularly big deal. It's pretty rare.
		$phrase = new XenForo_Phrase($match[2]);
		return $phrase->render();
	}

	/**
	 * Renders a template-based option.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderTemplateOptionHtml(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::renderOptionTemplateInternal($preparedOption['formatParams']['template'],
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * Internal function to prepare and render a generic option template.
	 *
	 * @param string $template Name of the template that should be rendered
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderOptionTemplateInternal($template, XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		return $view->createTemplateObject($template, array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'value' => isset($preparedOption['option_value']) ? $preparedOption['option_value'] : '',
			'formatParams' => $preparedOption['formatParams'],
			'editLink' => $editLink
		));
	}

	/**
	 * Renders a callback-based option. The callback is responsible for all processing
	 * and layout.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderCallbackOptionHtml(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$callback = array(
			(empty($preparedOption['formatParams']['class']) ? '(undefined)' : $preparedOption['formatParams']['class']),
			(empty($preparedOption['formatParams']['method']) ? '(undefined)' : $preparedOption['formatParams']['method']),
		);

		if (is_callable($callback))
		{
			return call_user_func($callback, $view, $fieldPrefix, $preparedOption, $canEdit);
		}
		else
		{
			// something went wrong
			$editLink = $view->createTemplateObject('option_list_option_editlink', array(
				'preparedOption' => $preparedOption,
				'canEditOptionDefinition' => $canEdit
			));

			return $view->createTemplateObject('option_list_option_invalid_callback', array(
				'callbackClass' => $callback[0],
				'callbackMethod' => $callback[1],
				'preparedOption' => $preparedOption,
				'value' => isset($preparedOption['option_value']) ? $preparedOption['option_value'] : '',
				'canEditOptionDefinition' => $canEdit,
				'editLink' => $editLink
			));
		}
	}

	/**
	 * Renders HTML for an invalid option type. Legitimately, this should never happen. :)
	 *
	 * @param XenForo_View $view View object
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _renderInvalidOptionHtml(XenForo_View $view, array $preparedOption, $canEdit)
	{
		return $view->createTemplateObject('option_list_option_invalid', array(
			'preparedOption' => $preparedOption,
			'value' => isset($preparedOption['option_value']) ? $preparedOption['option_value'] : '',
			'canEditOptionDefinition' => $canEdit
		));
	}
}