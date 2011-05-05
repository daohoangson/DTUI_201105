<?php

class XenForo_ViewPublic_Conversation_EditMessageInline extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'message',
			$this->_params['conversationMessage']['message'],
			array('editorId' => 'message' . $this->_params['conversationMessage']['message_id'])
		);
	}
}