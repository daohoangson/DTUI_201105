<?php

/**
 * Helper for censoring option.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_CensorWords
{
	/**
	 * Renders the censor words option row.
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
		$value = $preparedOption['option_value'];

		$choices = array();
		if (!empty($value['exact']))
		{
			foreach ($value['exact'] AS $word => $replace)
			{
				$choices[] = array('word' => $word, 'exact' => 1, 'replace' => is_string($replace) ? $replace : '');
			}
		}
		if (!empty($value['any']))
		{
			foreach ($value['any'] AS $word => $replace)
			{
				$choices[] = array('word' => $word, 'replace' => is_string($replace) ? $replace : '');
			}
		}

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		return $view->createTemplateObject('option_template_censorWords', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $preparedOption['formatParams'],
			'editLink' => $editLink,

			'choices' => $choices,
			'nextCounter' => count($choices)
		));
	}

	/**
	 * Verifies and prepares the censor option to the correct format.
	 *
	 * @param array $words List of words to censor (from input). Keys: word, exact, replace
	 * @param XenForo_DataWriter $dw Calling DW
	 * @param string $fieldName Name of field/option
	 *
	 * @return true
	 */
	public static function verifyOption(array &$words, XenForo_DataWriter $dw, $fieldName)
	{
		$output = array(
			'exact' => array(),
			'any' => array()
		);

		foreach ($words AS $word)
		{
			if (!isset($word['word']) || strval($word['word']) === '')
			{
				continue;
			}

			$writePosition = (!empty($word['exact']) ? 'exact' : 'any');

			if (isset($word['replace']) && strval($word['replace']) !== '')
			{
				$output[$writePosition][strval($word['word'])] = strval($word['replace']);
			}
			else
			{
				$output[$writePosition][strval($word['word'])] = utf8_strlen($word['word']);
			}
		}

		if (!$output['exact'])
		{
			unset($output['exact']);
		}
		if (!$output['any'])
		{
			unset($output['any']);
		}

		$words = $output;

		return true;
	}
}