<?php

class XenForo_ViewPublic_Member_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		$this->_params['user']['aboutHtml'] = new XenForo_BbCode_TextWrapper($this->_params['user']['about'], $bbCodeParser);

		$this->_params['user']['signatureHtml'] = new XenForo_BbCode_TextWrapper($this->_params['user']['signature'], $bbCodeParser, array('lightBox' => false));
	}
}