<?php

class XenForo_ViewAdmin_Feed_Preview extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		//die(json_encode($this->_params['feed']));

		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		$this->_params['entry']['messageHtml'] = new XenForo_BbCode_TextWrapper($this->_params['entry']['message'], $bbCodeParser);
	}
}