<?php

class XenForo_ViewAdmin_Phrase_List extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		if (!empty($this->_params['filterView']))
		{
			$this->_templateName = 'phrase_list_items';
		}

		return null;
	}
}