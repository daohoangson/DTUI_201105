<?php

class XenForo_ViewPublic_InlineMod_Post_Merge extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'new_message', $this->_params['newMessage'], array('disable' => true, 'height' => '180px')
		);
	}
}