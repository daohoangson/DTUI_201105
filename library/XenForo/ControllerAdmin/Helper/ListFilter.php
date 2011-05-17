<?php

/**
 * Helpers for filtering lists (templates, admin templates, style properties) that
 * use the filter list JS and need to pass input through pages.
 *
 * Don't instantiate this class. Use it statically!
 *
 * @package XenForo_ControllerAdmin_Helpers
 */
class XenForo_ControllerAdmin_Helper_ListFilter
{
	/**
	 * Private constructor. Can't be instantiated.
	 */
	private function __construct()
	{
	}

	/**
	 * Gets the filter parameters from the input object.
	 *
	 * @param XenForo_Input $inputHandler Input object
	 *
	 * @return array Named params
	 */
	public static function getParamsFromInput(XenForo_Input $inputHandler)
	{
		return $inputHandler->filter(array(
			'filter' => XenForo_Input::STRING,
			'prefixmatch' => XenForo_Input::BINARY
		));
	}

	/**
	 * Remove empty params (=== '') from a named param list.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public static function removeEmptyParams(array $data)
	{
		$output = array();
		foreach ($data AS $key => $value)
		{
			if ($value !== '')
			{
				$output[$key] = $value;
			}
		}

		return $output;
	}
}