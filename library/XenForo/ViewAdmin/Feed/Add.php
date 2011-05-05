<?php

class XenForo_ViewAdmin_Feed_Add extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$this->_params['messageEditor'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'message_template', $this->_params['feed']['message_template']
		);
	}
}