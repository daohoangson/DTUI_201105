<?php

/**
 * Helper for choosing what happens by default to spam threads.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_SpamThreadAction
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
		$value = $preparedOption['option_value'];

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		$forumOptions = XenForo_Option_NodeChooser::getNodeOptions(
			$value['node_id'],
			sprintf('(%s)', new XenForo_Phrase('unspecified')),
			'Forum'
		);

		return $view->createTemplateObject('option_template_spamThreadAction', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $forumOptions,
			'editLink' => $editLink
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
	public static function verifyOption(array &$options, XenForo_DataWriter $dw, $fieldName)
	{
		if ($options['action'] == 'move')
		{
			if ($options['node_id'])
			{
				if ($node = self::_getNodeModel()->getNodeById($options['node_id']))
				{
					if ($node['node_type_id'] === 'Forum')
					{
						return true;
					}
				}
			}

			$dw->error(new XenForo_Phrase('please_specify_valid_spam_forum'), $fieldName);
			return false;
		}

		// not selected move, so we don't care about the node value
		return true;
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected static function _getNodeModel()
	{
		return XenForo_Model::create('XenForo_Model_Node');
	}
}