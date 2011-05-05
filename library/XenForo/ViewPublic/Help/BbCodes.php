<?php

class XenForo_ViewPublic_Help_BbCodes extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['bbCodeParser'] = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
	}
}