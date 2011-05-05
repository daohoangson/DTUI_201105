<?php

class XenForo_ViewPublic_Conversation_ViewNewMessages extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['messages'], $bbCodeParser);
	}

	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);

		$output['lastDate'] = $this->_params['lastMessage']['message_date'];
		$output['count'] = count($this->_params['messages']);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}