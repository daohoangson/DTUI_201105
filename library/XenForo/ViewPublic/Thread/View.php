<?php

class XenForo_ViewPublic_Thread_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => $this->_params['canViewAttachments']
			)
		);
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['posts'], $bbCodeParser, $bbCodeOptions);

		if (!empty($this->_params['canQuickReply']))
		{
			$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor($this, 'message');
		}
	}
}