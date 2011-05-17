<?php

class XenForo_ViewAdmin_Style_List extends XenForo_ViewAdmin_Base
{
	public function prepareParams()
	{
		parent::prepareParams();

		if ($this->_params['masterStyle'])
		{
			foreach ($this->_params['styles'] AS &$style)
			{
				$style['depth']++;
			}
		}
	}
}