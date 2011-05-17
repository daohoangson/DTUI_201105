<?php

class XenForo_ViewAdmin_CodeEvent_Description extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		return array(
			'description' => $this->_params['event']['description']
		);
	}
}