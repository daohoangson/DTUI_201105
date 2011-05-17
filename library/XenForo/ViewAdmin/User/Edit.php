<?php

class XenForo_ViewAdmin_User_Edit extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		if (!empty($this->_params['user']['secondary_group_ids']))
		{
			$userSecondaryGroups = explode(',', $this->_params['user']['secondary_group_ids']);
		}
		else
		{
			$userSecondaryGroups = array();
		}

		$secondaryGroups = array();
		foreach ($this->_params['userGroups'] AS $userGroupId => $title)
		{
			$secondaryGroups[] = array(
				'label' => $title,
				'value' => $userGroupId,
				'selected' => in_array($userGroupId, $userSecondaryGroups)
			);
		}

		$this->_params['secondaryGroups'] = $secondaryGroups;
	}
}