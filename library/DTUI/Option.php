<?php
class DTUI_Option {
	public static function renderOptionUsergroup(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit) {
		$selected = $preparedOption['option_value'];

		$usergroups = XenForo_Model::create('XenForo_Model_UserGroup')->getAllUserGroups();
		
		$preparedOption['edit_format'] = 'select';
		$preparedOption['formatParams'] = array();
		
		foreach ($usergroups as $usergroup) {
			$preparedOption['formatParams'][] = array(
				'value' => $usergroup['user_group_id'],
				'label' => $usergroup['title'],
				'selected' => in_array($usergroup['user_group_id'], $selected),
			);
		}

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal('option_list_option_checkbox',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
	
	public static function get($key) {
		return XenForo_Application::get('options')->get('DTUI_' . $key);
	}
}