<?php

class XenForo_ViewPublic_Conversation_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['messages'], $bbCodeParser);

		if (!empty($this->_params['canReplyConversation']))
		{
			$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor($this, 'message');
		}
	}
}