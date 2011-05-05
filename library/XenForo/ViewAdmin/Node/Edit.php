<?php

class XenForo_ViewAdmin_Node_Edit extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$this->_params['styleOptions'] = XenForo_ViewAdmin_Helper_Style::getStylesAsSelectList($this->_params['styles'], 1);
	}
}