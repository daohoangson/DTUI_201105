<?php

class XenForo_ViewPublic_Conversation_Add extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'message'
		);
	}
}