<?php

class XenForo_ViewPublic_Thread_Create extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (!empty($this->_params['captcha']))
		{
			$this->_params['captcha'] = $this->_params['captcha']->render($this);
		}

		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate($this, 'message');
	}
}