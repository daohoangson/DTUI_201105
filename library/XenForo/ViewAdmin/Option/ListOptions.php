<?php

/**
 * View for a list of options.
 *
 * @package XenForo_Options
 */
class XenForo_ViewAdmin_Option_ListOptions extends XenForo_ViewAdmin_Base
{
	/**
	 * Renders all options, and splits them into groups according to
	 * their 100s display order
	 */
	public function renderHtml()
	{
		$options = array();

		foreach ($this->_params['preparedOptions'] AS $i => $option)
		{
			$x = floor($option['display_order'] / 100);
			$options[$x][$i] = $option;
		}

		$renderedOptions = array();

		foreach ($options AS $x => $optionGroup)
		{
			$renderedOptions[$x] = XenForo_ViewAdmin_Helper_Option::renderPreparedOptionsHtml(
				$this, $optionGroup, $this->_params['canEditOptionDefinition']
			);
		}

		$this->_params['renderedOptions'] = $renderedOptions;
	}
}