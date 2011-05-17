<?php

/**
 * View for the moderator type/user choice page.
 *
 * @packge XenForo_Moderator
 */
class XenForo_ViewAdmin_Moderator_AddChoice extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$this->_params['typeChoices'] = array();

		if (!empty($this->_params['typeHandlers']))
		{
			foreach ($this->_params['typeHandlers'] AS $contentType => $handler)
			{
				$selectedContentId = (isset($this->_params['typeId'][$contentType]) ? $this->_params['typeId'][$contentType] : 0);
				$this->_params['typeChoices'][] = $handler->getAddModeratorOption($this, $selectedContentId, $contentType);
			}
		}
	}
}