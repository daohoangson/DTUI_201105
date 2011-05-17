<?php

class XenForo_ViewPublic_Post_Edit extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'message', $this->_params['post']['message']
		);
	}
}