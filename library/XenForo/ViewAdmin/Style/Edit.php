<?php

class XenForo_ViewAdmin_Style_Edit extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$this->_params['styleParents'] = XenForo_ViewAdmin_Helper_Style::getStylesAsSelectList($this->_params['styles'], 1);

		return null;
	}
}