<?php

class XenForo_ViewPublic_Conversation_ViewMessage extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		$this->_params['message']['messageHtml'] = new XenForo_BbCode_TextWrapper($this->_params['message']['message'], $bbCodeParser);

		$this->_params['message']['signatureHtml'] = new XenForo_BbCode_TextWrapper($this->_params['message']['signature'], $bbCodeParser);
	}
}